<?php
require dirname(__DIR__).'/vendor/autoload.php';
use RetroWEB\Infrastructure\Persistence\TrafficLimiter;
$dir=sys_get_temp_dir().'/rw-escalation-'.bin2hex(random_bytes(8));
$limiter=new TrafficLimiter($dir.'/traffic.sqlite');$now=100;
foreach([60,600,3600,86400,604800]as$duration){
    if(!$limiter->allow('test',$now,1,60)||$limiter->allow('test',$now+1,1,60)||$limiter->allow('test',$now+$duration,1,60))throw new RuntimeException('Escalation failed');
    $now+=$duration+62;
}
$db=new SQLite3($dir.'/traffic.sqlite');
if($db->querySingle('SELECT COUNT(*) FROM block_events')!=5)throw new RuntimeException('Repeated attempts must not escalate');
if(!$limiter->allow('test',$now,1,60)||$limiter->allow('test',$now+1,1,60)||$limiter->allow('test',$now+604900,1,60))throw new RuntimeException('Second week-long trigger must permanently ban');
if($db->querySingle('SELECT COUNT(*) FROM permanent_ip_clients')!=1)throw new RuntimeException('Permanent ban missing');
if(!$limiter->allow('other',$now,1,60))throw new RuntimeException('Independent visitor blocked');
echo "Escalation ladder passed.\n";
$db->close();unset($limiter);foreach(glob($dir.'/*')as$file)unlink($file);rmdir($dir);
