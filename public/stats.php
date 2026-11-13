<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
header('HTTP/1.0 200 OK');

use RetroWEB\Infrastructure\Persistence\AggregateStatistics;
use RetroWEB\Domain\Profile\{ProfileDetector,ProfileRepository};
use RetroWEB\Rendering\Transform\LegacyUiResponse;
use RetroWEB\Rendering\StatisticsPage;

$root=dirname(__DIR__);$configuredBasePath=getenv('RETROWEB_PUBLIC_BASE_PATH');$basePath=$configuredBasePath!==false?rtrim($configuredBasePath,'/'):'';if($basePath!==''&&!preg_match('#^/[A-Za-z0-9._-]+$#',$basePath))throw new RuntimeException('Invalid public base path');$stats=new AggregateStatistics($root.'/var/data/statistics-v2.sqlite');
$h=static fn(string$value):string=>htmlspecialchars($value,ENT_QUOTES,'UTF-8');
$detection=(new ProfileDetector(require $root.'/config/profiles/signatures.php'))->detect($_SERVER['HTTP_USER_AGENT']??'');
$profile=(new ProfileRepository(require $root.'/config/profiles/builtin.php'))->get($detection->profileId);
$page=(new StatisticsPage())->render($stats->totals(),'','','RetroWEB usage statistics');
$bar=\RetroWEB\Rendering\NavigationBar::render($profile->label,$basePath.'/',$basePath.'/client-info.php',$basePath.'/?settings=1',$basePath.'/stats.php');
$page=preg_replace('/<body>/i','<body>'.$bar,$page,1)??$page;$body=LegacyUiResponse::encode($page,$profile);header('Content-Type: text/html; charset='.$profile->encoding);header('Content-Length: '.strlen($body));echo$body;
