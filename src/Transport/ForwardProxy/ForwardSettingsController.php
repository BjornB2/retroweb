<?php
declare(strict_types=1);
namespace RetroWEB\Transport\ForwardProxy;
use RetroWEB\Domain\Profile\{ProfileDetector,ProfileRepository};
use RetroWEB\Domain\Settings\{SettingsInput,SettingsOverrides,SettingsResolver};
use RetroWEB\Infrastructure\Persistence\FileSettingsRepository;
use RetroWEB\Domain\Navigation\NavigationBuilder;

final class ForwardSettingsController
{
    public function __construct(private ProfileDetector$detector,private ProfileRepository$profiles,private FileSettingsRepository$settings,private ?NavigationBuilder$navigation=null){}
    public function profileState(string$identity,string$userAgent,int$now):array{$d=$this->detector->detect($userAgent);$state=$this->settings->loadState($identity,$now);$saved=$state[0]??new SettingsOverrides();$manual=$saved->selectedProfile!==null&&$this->profiles->has($saved->selectedProfile);return[$this->profiles->get($manual?$saved->selectedProfile:$d->profileId),$manual?'manual':($d->fixed?'verified':'Custom')];}
    public function imageMode(string$identity,string$userAgent,int$now):string
    {
        [$profile]=$this->profileState($identity,$userAgent,$now);$state=$this->settings->loadState($identity,$now);$saved=$state[0]??new SettingsOverrides();return(new SettingsResolver())->resolve($profile,$saved,$profile->id==='generic'||($state[1]??false))->values['image_mode'];
    }
    public function page(string$identity,string$userAgent,string$source,bool$chooseCustom=false):string
    {
        $d=$this->detector->detect($userAgent);$now=time();$state=$this->settings->loadState($identity,$now);$saved=$state[0]??new SettingsOverrides();$selected=$saved->selectedProfile!==null&&$this->profiles->has($saved->selectedProfile)?$saved->selectedProfile:null;$profile=$this->profiles->get($selected??$d->profileId);$custom=$profile->id==='generic'||($state[1]??false)||$chooseCustom;
        $h=static fn(string$v):string=>htmlspecialchars($v,ENT_QUOTES,'UTF-8');$origin=$this->origin($source);$reload=$h((string)preg_replace('#^https://#i','http://',$source));$action=$origin.'/.retroweb/settings';$nav=$this->navigation??new NavigationBuilder();$bar=\RetroWEB\Rendering\NavigationBar::render($profile->label,$nav->home('forward'),$nav->clientInfo($source,'forward'),$nav->settings($source,'forward'),$nav->statistics($source,'forward'));
        $profileForm='<form method="post" action="'.$action.'"><input type="hidden" name="source" value="'.$h($source).'"><input type="hidden" name="profile_save" value="1"><p><label>Browser profile: <select name="profile_select"><option value="auto"'.($selected===null?' selected':'').'>Automatic ('.$h($this->profiles->get($d->profileId)->label).')</option>';foreach($this->profiles->alphabetically()as$id=>$p)$profileForm.='<option value="'.$h($id).'"'.($selected===$id?' selected':'').'>'.$h($p->label).'</option>';$profileForm.='</select></label> <input type="submit" value="Apply profile"></p></form>';
        $profileForm.='<p>New device/browser combination or incorrect detection? <a href="'.$h($nav->clientInfo($source,'forward')).'">Report device/browser</a>.</p>';
        if(!$custom)return'<html><body>'.$bar.'<h1>Settings</h1>'.$profileForm.'<p>Active profile: <b>'.$h($profile->label).'</b>. <a href="'.$origin.'/.retroweb/settings?source='.rawurlencode($source).'&custom=1">Use Custom</a></p></body></html>';
        try{$effective=(new SettingsResolver())->resolve($profile,$saved,true)->values;}catch(\InvalidArgumentException){$saved=new SettingsOverrides([],in_array($saved->imageMode,['inline','links'],true)?$saved->imageMode:null,$selected);$this->settings->save($identity,$saved,$now,true);$effective=(new SettingsResolver())->resolve($profile,$saved,true)->values;}
        $html='<html><body>'.$bar.'<h1>Custom settings</h1>'.$profileForm.'<form method="post" action="'.$action.'"><input type="hidden" name="source" value="'.$h($source).'">';foreach(['viewport_width'=>'Viewport width','viewport_height'=>'Viewport height','image_width'=>'Maximum image width','image_height'=>'Maximum image height']as$key=>$label)$html.='<p><label>'.$label.': <input name="'.$key.'" value="'.$h((string)$effective[$key]).'"></label></p>';$html.='<p><label>HTML: <select name="html">';foreach(['html20'=>'HTML 2.0','html32'=>'HTML 3.2','html401-basic'=>'Basic HTML 4.01']as$value=>$label)$html.='<option value="'.$value.'"'.($effective['html']===$value?' selected':'').'>'.$label.'</option>';
        return$html.'</select></label></p><p><label>Delivery: <select name="delivery"><option value="paginated"'.($effective['delivery']==='paginated'?' selected':'').'>Paginated</option><option value="unsplit"'.($effective['delivery']==='unsplit'?' selected':'').'>Unsplit</option></select></label></p><p><label>Page memory budget (10–2048 kB): <input name="page_budget_kb" value="'.$h((string)$effective['page_budget_kb']).'"></label></p><p>Memory guidance:</p><ul><li>100–125 kB for about 1 MB free memory</li><li>200–250 kB for about 2 MB</li><li>400–500 kB for about 4–5 MB</li><li>1024 kB for about 8 MB</li><li>1536–2048 kB for about 16 MB</li></ul><p><input type="submit" value="Save"></p></form><form method="post" action="'.$action.'"><input type="hidden" name="source" value="'.$h($source).'"><input type="hidden" name="settings_reset" value="1"><p><input type="submit" value="Reset profile overrides"></p></form></body></html>';
    }
    public function save(string$identity,string$userAgent,string$source,array$fields,int$now):void
    {
        $d=$this->detector->detect($userAgent);$state=$this->settings->loadState($identity,$now);$saved=$state[0]??new SettingsOverrides();if(($fields['profile_save']??'')==='1'){$choice=is_string($fields['profile_select']??null)?$fields['profile_select']:'auto';if($choice!=='auto'&&!$this->profiles->has($choice))throw new \InvalidArgumentException('Unknown browser profile');$selected=$choice==='auto'?null:$choice;$profile=$this->profiles->get($selected??$d->profileId);$this->settings->save($identity,new SettingsOverrides([],$saved->imageMode,$selected),$now,$profile->id==='generic');return;}
        $selected=$saved->selectedProfile!==null&&$this->profiles->has($saved->selectedProfile)?$saved->selectedProfile:null;$profile=$this->profiles->get($selected??$d->profileId);$mode=$saved->imageMode;if(($fields['settings_reset']??'')==='1'){$this->settings->save($identity,new SettingsOverrides([],$mode,$selected),$now,$profile->id==='generic');return;}$this->settings->save($identity,(new SettingsInput())->parse($fields,$profile,$mode,$selected),$now,true);
    }
    private function origin(string$source):string{$parts=parse_url($source);$host=is_string($parts['host']??null)?$parts['host']:'retroweb.invalid';$port=isset($parts['port'])?':'.(int)$parts['port']:'';return'http://'.$host.$port;}
}
