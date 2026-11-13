<?php
declare(strict_types=1);
namespace RetroWEB\Transport\ForwardProxy;

use RetroWEB\Domain\Profile\{BrowserProfile,ProfileDetector};
use RetroWEB\Infrastructure\Persistence\{AggregateStatistics,DeviceSubmissionRepository,TrafficLimiter};
use RetroWEB\Rendering\StatisticsPage;
use RetroWEB\Domain\Navigation\NavigationBuilder;

final class ForwardInformationController
{
    public function __construct(private DeviceSubmissionRepository $devices,private TrafficLimiter $submissions,private AggregateStatistics $statistics,private ProfileDetector $detector,private ?NavigationBuilder$navigation=null){}

    public function submit(string $identity,string $userAgent,array $fields,int $now):string
    {
        if(!$this->submissions->allow($identity,$now,3,3600,0))return'Submission was not accepted.';
        try{$d=$this->detector->detect($userAgent);$result=$this->devices->submitWithStatus((string)($fields['device']??''),(string)($fields['browser']??''),$userAgent,$now,$d->profileId,$d->status);return$result['new']?'Submission received. It is awaiting review; you do not need to submit it again.':'This browser has already been received'.($result['status']==='pending'?' and is awaiting review':' and has been reviewed').'; you do not need to submit it again.';}catch(\Throwable){return'Submission was not accepted.';}
    }

    public function clientPage(string $userAgent,BrowserProfile $profile,string $status,string $action,string $returnUrl,string $statisticsUrl,string $message=''):string
    {
        $h=static fn(string$v):string=>htmlspecialchars($v,ENT_QUOTES,'UTF-8');$accepted=$message!==''&&$message!=='Submission was not accepted.';$home=($this->navigation??new NavigationBuilder())->home('forward');$html='<html><head><title>RetroWEB client information</title></head><body>'.\RetroWEB\Rendering\NavigationBar::render($profile->label,$home,$action,preg_replace('#/client-info\\?#','/settings?',$action),$statisticsUrl).'<h1>'.($accepted?'Submission received':'Client information').'</h1>';if($message!=='')$html.='<p><b>'.$h($message).'</b></p>';if($accepted)$html.='<h2>Client information</h2>';$html.='<p>Observed browser: '.$h($userAgent?:'(none)').'</p><p>Profile: '.$h($profile->label).'</p><h2>Submit correction</h2><p>Do not include personal information. Accepted submissions remain private until reviewed.</p><form method="post" action="'.$h($action).'"><p><label>Device: <input type="text" name="device" maxlength="80"></label></p><p><label>Browser/version: <input type="text" name="browser" maxlength="80"></label></p><p><input type="submit" value="Submit"></p></form><h2>Community-reported devices &amp; browsers</h2><ul>';foreach($this->devices->publicCatalog()as$row)$html.='<li>'.$h($row['device']).' - '.$h($row['browser']).' - profile '.$h($row['profile']).' ('.(int)$row['confirmations'].' confirmations)</li>';return$html.'</ul>'.\RetroWEB\Rendering\NavigationBar::spaceTest().'</body></html>';
    }

    public function statisticsPage(string $returnUrl,string $clientInfoUrl):string
    {
        return(new StatisticsPage())->render($this->statistics->totals(),$returnUrl,$clientInfoUrl,'RetroWEB usage statistics');
    }
}
