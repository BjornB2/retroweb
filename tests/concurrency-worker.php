<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
use RetroWEB\Infrastructure\Persistence\{AggregateStatistics,TrafficLimiter};

$directory=$argv[1]??'';$worker=(int)($argv[2]??0);
if($directory===''||$worker<1)exit(2);
$now=1700000000;
$traffic=new TrafficLimiter($directory.'/traffic.sqlite');
if(!$traffic->allow('client-'.$worker,$now,100,60))exit(3);
if(!$traffic->record('client-'.$worker,100,$now))exit(4);
(new AggregateStatistics($directory.'/statistics.sqlite'))->increment('images','success',$now);
