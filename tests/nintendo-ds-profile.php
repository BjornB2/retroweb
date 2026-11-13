<?php
require dirname(__DIR__).'/vendor/autoload.php';
$root=dirname(__DIR__);
$detectorClass='RetroWEB\\Domain\\Profile\\ProfileDetector';$repositoryClass='RetroWEB\\Domain\\Profile\\ProfileRepository';
$detector=new $detectorClass(require $root.'/config/profiles/signatures.php');$profiles=new $repositoryClass(require $root.'/config/profiles/builtin.php');
$ua='Mozilla/4.0 (compatible; MSIE 6.0; Nitro) Opera 8.50 [en]';$ds=$detector->detect($ua);$dsi=$detector->detect('Opera/9.50 (Nintendo DSi; Opera/507; U; en-US)');$p=$profiles->get('nintendo_ds');
if($ds->profileId!=='nintendo_ds'||!$ds->fixed||$dsi->profileId!=='nintendo_dsi'||$p->pageBudgetKb!==512||$p->viewportWidth!==256||$p->viewportHeight!==192||$p->imageWidth!==240||$p->imageHeight!==160||$p->imageMode!=='inline'||$p->html!=='html401-basic')throw new RuntimeException('DS profile or separation from DSi failed');
if($detector->detect('Opera/8.50 (Windows NT 5.1)')->profileId==='nintendo_ds')throw new RuntimeException('Desktop Opera matched');
echo "Nintendo DS profile checks passed.\n";
