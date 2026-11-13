<?php
require dirname(__DIR__).'/vendor/autoload.php';
use RetroWEB\Infrastructure\Persistence\{DeviceSubmissionRepository,AggregateStatistics};
use RetroWEB\Rendering\StatisticsPage;
$dir=sys_get_temp_dir().'/rw-submission-stats-'.bin2hex(random_bytes(6));$repo=new DeviceSubmissionRepository($dir.'/device-submissions-v2.sqlite');
$pending=$repo->submit('Pending device','Browser','UA pending',100);$repo->submit('Pending device','Browser','UA pending',101);
$approved=$repo->submit('Approved device','Browser','UA approved',100);$repo->moderate($approved,'approved');
$rejected=$repo->submit('Rejected device','Browser','UA rejected',100);$repo->moderate($rejected,'rejected');
$stats=new AggregateStatistics($dir.'/statistics-v2.sqlite');$html=(new StatisticsPage())->render($stats->totals(),'','');
foreach(['Awaiting review','Approved','Rejected']as$label)if(!str_contains($html,$label.': <strong>1</strong>'))throw new RuntimeException('Incorrect submission status count');
$repo->moderate($pending,'approved');$html=(new StatisticsPage())->render($stats->totals(),'','');if(!str_contains($html,'Awaiting review: <strong>0</strong>')||!str_contains($html,'Approved: <strong>2</strong>'))throw new RuntimeException('Moderation not reflected immediately');
unset($stats,$repo);foreach(glob($dir.'/*')as$file)unlink($file);rmdir($dir);echo "Submission statistics checks passed.\n";
