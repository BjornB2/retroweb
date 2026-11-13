<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
header('HTTP/1.0 200 OK');

use RetroWEB\Application\{RenderPage,RenderVersion};
use RetroWEB\Domain\Profile\{ProfileDetector, ProfileRepository};
use RetroWEB\Domain\Settings\{SettingsInput, SettingsOverrides, SettingsResolver};
use RetroWEB\Infrastructure\Http\{CurlHttpTransport, SafeSourceFetcher, UrlPolicy};
use RetroWEB\Infrastructure\Persistence\{AggregateStatistics, ClientIdentity, CookieSettingsCodec, FileSettingsRepository, FileSnapshotRepository, UpstreamCookieStore,TrafficLimiter};
use RetroWEB\Rendering\Extraction\CurrentArticleExtractionStrategy;
use RetroWEB\Rendering\Pagination\MemoryBudgetPaginator;
use RetroWEB\Rendering\ProfileAwareRenderer;
use RetroWEB\Rendering\Transform\LegacyTransformer;
use RetroWEB\Rendering\Transform\LegacyOutputWorkarounds;
use RetroWEB\Rendering\Transform\LegacyUiResponse;
use RetroWEB\Rendering\Transform\OutputEncoder;
use RetroWEB\Transport\Direct\{DirectRequestAdapter, SmartInput};
use RetroWEB\Domain\Navigation\{NavigationBuilder,NavigationContext};

const RW_V2_COOKIE = 'retroweb_v2_settings';

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');

$h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$configuredBasePath=getenv('RETROWEB_PUBLIC_BASE_PATH');$basePath=$configuredBasePath!==false?rtrim($configuredBasePath,'/'):'';if($basePath!==''&&!preg_match('#^/[A-Za-z0-9._-]+$#',$basePath))throw new RuntimeException('Invalid public base path');
$publicOrigin=getenv('RETROWEB_PUBLIC_ORIGIN')?:'http://retroweb.bjrno.com';$navigationBuilder=new NavigationBuilder($publicOrigin,$basePath);$publicHost=(string)parse_url($publicOrigin,PHP_URL_HOST);
$formAction = $publicOrigin.$basePath.'/';
$proxyPort=(int)(getenv('RETROWEB_PUBLIC_PROXY_PORT')?:8081);
$root = dirname(__DIR__);
$data = $root . '/var/data';
if (!is_dir($data)) mkdir($data, 0700, true);
$startedAt=microtime(true);
$statistics=new AggregateStatistics($data . '/statistics-v2.sqlite');
$secretFile = $data . '/settings-v2.key';
if (!is_file($secretFile)) file_put_contents($secretFile, bin2hex(random_bytes(32)), LOCK_EX);
$secret = trim((string) file_get_contents($secretFile));

$profiles = new ProfileRepository(require $root . '/config/profiles/builtin.php');
$detector = new ProfileDetector(require $root . '/config/profiles/signatures.php');
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$detection = $detector->detect($userAgent);
$identityService=new ClientIdentity($secret);$address=(string)($_SERVER['REMOTE_ADDR']??'unknown');$identity=$identityService->from($address,$userAgent);$abuseIdentity=$identityService->fromAddress($address);
$serverStore = new FileSettingsRepository($data . '/settings');
$codec = new CookieSettingsCodec($secret);
[$cookieSettings, $cookieCustom,$cookieValid,$cookieProfile] = $codec->decodeVerified($_COOKIE[RW_V2_COOKIE] ?? null);
$serverState = $serverStore->loadState($identity, time());
$selectedProfile=$cookieValid?$cookieSettings->selectedProfile:($serverState[0]->selectedProfile??null);
if($selectedProfile!==null&&!$profiles->has($selectedProfile))$selectedProfile=null;
$profileId=$selectedProfile??$detection->profileId;
$profile = $profiles->get($profileId);
$sendUi=static function(string$html)use($profile):void{$body=LegacyUiResponse::encode($html,$profile);header('Content-Type: text/html; charset='.$profile->encoding);header('Content-Length: '.strlen($body));echo$body;};
$directTraffic=new TrafficLimiter($data.'/direct-traffic-v2.sqlite');$pageTrafficIdentity=$abuseIdentity.':page';
if(!$directTraffic->allow($pageTrafficIdentity,time(),20,60,0)){header('HTTP/1.0 429 Too Many Requests');$sendUi('<html><body><h1>Too many requests</h1><p>Please wait before trying again.</p></body></html>');exit;}
$statistics->recordVisit($abuseIdentity,time());$statistics->increment('pageviews','new');
$cookieApplies=$cookieValid&&($cookieProfile===$profile->id||($cookieProfile===null&&$profile->id==='generic'));
$stored = $cookieApplies ? $cookieSettings : ($serverState[0] ?? new SettingsOverrides());
if(!$cookieApplies&&$cookieValid)$stored=new SettingsOverrides($stored->values,$cookieSettings->imageMode??$stored->imageMode,$selectedProfile);
elseif($stored->selectedProfile!==$selectedProfile)$stored=new SettingsOverrides($stored->values,$stored->imageMode,$selectedProfile);
$custom = $profile->id==='generic'||($cookieApplies ? $cookieCustom : ($serverState[1] ?? false));
if($cookieValid)$serverStore->save($identity,$stored,time(),$custom);

$sourceValue = $_POST['rw_source'] ?? $_GET['rw_source'] ?? $_POST['url'] ?? $_GET['url'] ?? '';
$source = is_string($sourceValue) ? trim($sourceValue) : '';
$smartInput = is_string($_GET['q'] ?? null) ? $_GET['q'] : '';
if ($source === '' && trim($smartInput) !== '') $source = (new SmartInput())->resolve($smartInput);
if ($source === '' && is_string($_GET['search'] ?? null) && trim($_GET['search']) !== '') $source = (new SmartInput())->resolve($_GET['search']);

$setCookie = static function (SettingsOverrides $settings, bool $isCustom,string $profileId) use ($codec,$basePath): void {
    setcookie(RW_V2_COOKIE, $codec->encode($settings, $isCustom,$profileId), ['expires'=>time()+31536000,'path'=>$basePath.'/','httponly'=>true,'samesite'=>'Lax']);
};
$cleanLocation = static fn(string $currentSource): string => $basePath.'/' . ($currentSource !== '' ? '?url=' . rawurlencode($currentSource) : '');

if (($_GET['action'] ?? '') === 'image-mode' && in_array($_GET['mode'] ?? '', ['inline', 'links'], true)) {
    $stored = new SettingsOverrides($stored->values, $_GET['mode'],$stored->selectedProfile);
    $serverStore->save($identity, $stored, time(), $custom);
    $setCookie($stored, $custom,$profile->id);
    header('Location: ' . $cleanLocation($source), true, 303);
    exit;
}

$settingsError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['profile_save'] ?? '') === '1') {
    $choice=is_string($_POST['profile_select']??null)?$_POST['profile_select']:'auto';
    if($choice!=='auto'&&!$profiles->has($choice)){$settingsError='Unknown browser profile.';}else{
        $selectedProfile=$choice==='auto'?null:$choice;$profileId=$selectedProfile??$detection->profileId;$profile=$profiles->get($profileId);
        $stored=new SettingsOverrides([],$stored->imageMode,$selectedProfile);$custom=$profile->id==='generic';
        $serverStore->save($identity,$stored,time(),$custom);$setCookie($stored,$custom,$profile->id);
        header('Location: '.$basePath.'/?settings=1&url='.rawurlencode($source),true,302);exit;
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['settings_reset'] ?? '') === '1') {
    $stored = new SettingsOverrides([], $stored->imageMode,$stored->selectedProfile);
    $custom = $profile->id==='generic';
    $serverStore->save($identity, $stored, time(), $custom);
    $setCookie($stored, $custom,$profile->id);
    header('Location: '.$basePath.'/?settings=1&url=' . rawurlencode($source), true, 302);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['settings_save'] ?? '') === '1') {
    try {
        $candidate = (new SettingsInput())->parse($_POST, $profile, $stored->imageMode,$stored->selectedProfile);
        $stored = $candidate;
        $custom = true;
        $serverStore->save($identity, $stored, time(), true);
        $setCookie($stored, true,$profile->id);
        header('Location: ' . $cleanLocation($source), true, 302);
        exit;
    } catch (InvalidArgumentException $error) {
        $settingsError = 'Settings were not saved. Use dimensions from 1–4096 pixels and a page budget from 10–2048 kB.';
    }
}

try {
    $effective = (new SettingsResolver())->resolve($profile, $stored, $custom)->values;
} catch (InvalidArgumentException $error) {
    $stored = new SettingsOverrides([], in_array($stored->imageMode, ['inline', 'links'], true) ? $stored->imageMode : null,$stored->selectedProfile);
    $custom = $profile->id==='generic';
    $serverStore->save($identity, $stored, time(), $custom);
    $setCookie($stored, $custom,$profile->id);
    $effective = (new SettingsResolver())->resolve($profile, $stored, $custom)->values;
    $settingsError = 'Invalid saved settings were reset to safe profile defaults.';
}

$toolbar = static function (string $currentSource, string $navigation = '') use ($h, $profile, $detection, $effective,$navigationBuilder,$selectedProfile,$basePath): string {
    $bar=\RetroWEB\Rendering\NavigationBar::render($profile->label,$basePath.'/',$basePath.'/client-info.php',$navigationBuilder->settings($currentSource,'direct'),$basePath.'/stats.php',$currentSource!==''?$navigationBuilder->reload($currentSource,'direct'):'',$currentSource!==''?$effective['image_mode']:null,$navigationBuilder->imageMode($currentSource,'direct','inline'),$navigationBuilder->imageMode($currentSource,'direct','links'));
    return str_replace('</small>',str_replace(' | ',' ',$navigation).'</small>',$bar);
};

if (isset($_GET['settings']) || ($settingsError !== '' && $_SERVER['REQUEST_METHOD'] === 'POST')) {
    $profileForm='<form method="post" action="'.$h($formAction).'"><input type="hidden" name="profile_save" value="1"><input type="hidden" name="url" value="'.$h($source).'"><p><label>Browser profile: <select name="profile_select"><option value="auto"'.($selectedProfile===null?' selected':'').'>Automatic ('.$h($profiles->get($detection->profileId)->label).')</option>';
    foreach($profiles->alphabetically()as$id=>$available)$profileForm.='<option value="'.$h($id).'"'.($selectedProfile===$id?' selected':'').'>'.$h($available->label).'</option>';
    $profileForm.='</select></label> <input type="submit" value="Apply profile"></p></form>';
    $profileForm.='<p>New device/browser combination or incorrect detection? <a href="'.$h($basePath.'/client-info.php').'">Report device/browser</a>.</p>';
    if (!$custom && $profile->id!=='generic' && !isset($_GET['custom'])) {
        $sendUi('<html><head><title>RetroWEB Settings</title></head><body>' . $toolbar($source)
            . '<h1>Settings</h1>'.$profileForm.'<p>Active profile: <b>' . $h($profile->label) . '</b>.</p>'
            . '<p><a href="?settings=1&amp;custom=1&amp;url=' . rawurlencode($source) . '">Use Custom</a></p></body></html>');
        exit;
    }
    ob_start();
    echo '<html><head><title>RetroWEB Settings</title></head><body>' . $toolbar($source) . '<h1>Custom settings</h1>'.$profileForm;
    if ($settingsError !== '') echo '<p><b>' . $h($settingsError) . '</b></p>';
    echo '<form method="post" action="'.$h($formAction).'"><input type="hidden" name="settings_save" value="1"><input type="hidden" name="url" value="' . $h($source) . '">';
    foreach (['viewport_width'=>'Viewport width','viewport_height'=>'Viewport height','image_width'=>'Maximum image width','image_height'=>'Maximum image height'] as $key => $label) echo '<p><label>' . $label . ': <input name="' . $key . '" value="' . $h((string)$effective[$key]) . '" size="6"></label></p>';
    echo '<p><label>HTML: <select name="html">';
    foreach (['html20'=>'HTML 2.0','html32'=>'HTML 3.2','html401-basic'=>'Basic HTML 4.01'] as $value => $label) echo '<option value="' . $value . '"' . ($effective['html'] === $value ? ' selected' : '') . '>' . $label . '</option>';
    echo '</select></label></p><p><label>Delivery: <select name="delivery"><option value="paginated"' . ($effective['delivery'] === 'paginated' ? ' selected' : '') . '>Paginated</option><option value="unsplit"' . ($effective['delivery'] === 'unsplit' ? ' selected' : '') . '>Unsplit</option></select></label></p>'
        . '<p><label>Page memory budget (10–2048 kB): <input name="page_budget_kb" value="'.$h((string)$effective['page_budget_kb']).'" size="6"></label></p>'
        . '<p>Memory guidance:</p><ul><li>100–125 kB for about 1 MB free memory</li><li>200–250 kB for about 2 MB</li><li>400–500 kB for about 4–5 MB</li><li>1024 kB for about 8 MB</li><li>1536–2048 kB for about 16 MB</li></ul>'
        . '<p><input type="submit" value="Save"> <a href="' . $h($cleanLocation($source)) . '">Cancel</a></p></form>'
        . '<form method="post" action="'.$h($formAction).'"><input type="hidden" name="settings_reset" value="1"><input type="hidden" name="url" value="' . $h($source) . '"><p><input type="submit" value="Reset profile overrides"></p></form></body></html>';$sendUi((string)ob_get_clean());
    exit;
}

if ($source === '') {
    $legacyNotice=($_GET['legacy']??'')==='1'?'<p><b>RetroWEB has been renewed. The URL structure has changed; please start again from this page.</b></p>':'';
    $sendUi('<!DOCTYPE html><html><head><title>RetroWEB</title></head><body>' . $toolbar('')
        . '<h1>RetroWEB</h1>'.$legacyNotice.'<p>Enter a web address or search terms.</p>'
        . '<form method="get" action="'.$h($formAction).'"><label>Address or search: <input type="text" name="q" size="50"></label> <input type="submit" name="go" value="Go"></form>'
        . '<h2>Features</h2><ul><li>Search the web via DuckDuckGo</li><li>View modern web pages in simplified legacy HTML</li><li>Use inline images or image links</li><li>Automatically convert images to small, compatible GIF files</li><li>Split large pages to fit the browser memory budget</li><li>Browse through the RetroWEB HTTP forwarding proxy</li></ul>'
        . '<h2>Forwarding proxy</h2><p>HTTP proxy server: <b>'.$h($publicHost).'</b><br>Port: <b>'.$proxyPort.'</b></p><p>Configure this as the HTTP proxy only. HTTPS proxying and CONNECT are not supported.</p>'
        . '<h2>About</h2><p>RetroWEB 2.0 makes modern websites usable in classic web browsers. Its forwarding proxy lets a browser request ordinary HTTP links while RetroWEB safely fetches and converts the modern source.</p><p>Created by <a href="mailto:bjorn@heirman.nl">Björn Heirman</a>. Visit <a href="'.$h($navigationBuilder->content('https://bjornsblog.nl/','direct')).'">BjornsBlog.nl</a> | <a href="'.$h($navigationBuilder->content('https://github.com/BjornB2/retroweb','direct')).'">GitHub</a>.</p><p><a href="'.$h($basePath.'/stats.php').'">Statistics</a></p></body></html>');
    exit;
}

try {
    if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['rw_form']??'')!=='1')throw new DomainException('Direct POST requires a validated RetroWEB form');
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['rw_form'] ?? '') === '1') {
        $contentLength = filter_var($_SERVER['CONTENT_LENGTH'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
        $contentType = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
        if ($contentLength > 65536) throw new LengthException('Form body too large');
        if ($contentType !== 'application/x-www-form-urlencoded') throw new DomainException('Unsupported form encoding');
        if (!empty($_FILES)) throw new DomainException('File uploads are unsupported');
    }
    $policy = new UrlPolicy();
    $requestQuery = $_GET;
    $requestQuery['url'] = $source;
    if (($_POST['rw_form'] ?? '') === '1') {
        $requestQuery['rw_form'] = '1';
        $requestQuery['rw_source'] = $source;
    }
    unset($requestQuery['q'], $requestQuery['search'], $requestQuery['go']);
    $request = (new DirectRequestAdapter($policy))->parse($requestQuery, $_POST, $userAgent, $identity);
    $resolver = static function (string $host): array {
        $addresses = gethostbynamel($host) ?: [];
        if (function_exists('dns_get_record')) foreach (dns_get_record($host, DNS_AAAA) ?: [] as $row) if (isset($row['ipv6'])) $addresses[] = $row['ipv6'];
        return array_values(array_unique($addresses));
    };
    $fetcher = new SafeSourceFetcher($policy, new CurlHttpTransport(), $resolver, 4194304, 5, ['text/html','application/xhtml+xml'], new UpstreamCookieStore($data . '/upstream-cookies'));
    $renderer = new ProfileAwareRenderer($detector, $profiles, $serverStore, new CurrentArticleExtractionStrategy(), new LegacyTransformer($navigationBuilder), new MemoryBudgetPaginator());
    $result = (new RenderPage($fetcher, new FileSnapshotRepository($root . '/var/cache/snapshots'), RenderVersion::CURRENT, 900, $renderer,$navigationBuilder))->render($request, time());
    $navigation = '';$bottomNavigation='';
    if ($result->partCount > 1) {
        $pagination=['Page '.$result->part.'/'.$result->partCount];
        $context=new NavigationContext($result->sourceUrl,'direct',$result->snapshotToken,$result->part);
        if ($result->part > 1) $pagination[]='<a href="'.$h($navigationBuilder->snapshot($context,$result->part-1)).'">Previous</a>';
        if ($result->part < $result->partCount) $pagination[]='<a href="'.$h($navigationBuilder->snapshot($context,$result->part+1)).'">Next</a>';
        $pagination[]='<a href="'.$h($navigationBuilder->entire($context)).'">Entire page</a>';
        $navigation=' | '.implode(' | ',$pagination);$bottomNavigation='<hr><p><small>'.implode(' | ',$pagination).'</small></p>';
    }
    $pageToolbar = $toolbar($result->sourceUrl, $navigation);
    $statistics->increment('pages', $profile->id);
    $statistics->increment('page_results','success');
    $elapsed=(int)round((microtime(true)-$startedAt)*1000);$statistics->increment('processing_ms',$elapsed<250?'under_250':($elapsed<1000?'250_999':($elapsed<3000?'1000_2999':'3000_plus')));
    if(strcasecmp($result->encoding,'UTF-8')!==0){$fragmentEncoder=new OutputEncoder();$pageToolbar=$fragmentEncoder->encode($pageToolbar,$result->encoding);$bottomNavigation=$fragmentEncoder->encode($bottomNavigation,$result->encoding);}
    $body = \RetroWEB\Rendering\Transform\LegacyDocumentChrome::inject($result->body,$pageToolbar,$bottomNavigation);$body=LegacyOutputWorkarounds::apply((string)$body,$profile->workarounds);
    if(!$directTraffic->record($pageTrafficIdentity,strlen((string)$body),time())){header('HTTP/1.0 429 Too Many Requests');$sendUi('<html><body>'.$toolbar($source).'<h1>Traffic limit reached</h1><p>Thanks to a bot army, RetroWEB has reached its temporary data limit. Please try again later.</p></body></html>');exit;}$statistics->add('direct_bytes','all',strlen((string)$body));
    header('Content-Type: text/html; charset=' . $result->encoding);
    header('Content-Length: '.strlen((string)$body));
    echo $body;
} catch (Throwable $error) {
    $rejected=$error instanceof DomainException||$error instanceof InvalidArgumentException||$error instanceof LengthException;$statistics->increment('page_results',$rejected?'rejected':'error');
    header($rejected ? 'HTTP/1.0 400 Bad Request' : 'HTTP/1.0 502 Bad Gateway');
    $publicMessage=$rejected?$error->getMessage():'The source could not be fetched or converted.';
    $sendUi('<html><body>' . $toolbar($source) . '<h1>Unable to render page</h1><p>' . $h($publicMessage) . '</p><p><a href="'.$basePath.'/">Return</a></p></body></html>');
}
