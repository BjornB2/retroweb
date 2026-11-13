<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
header('HTTP/1.0 200 OK');

use RetroWEB\Infrastructure\Http\{UrlPolicy,CurlHttpTransport,SafeSourceFetcher};
use RetroWEB\Rendering\Image\{ImageConverter,ImagePlanner};
use RetroWEB\Infrastructure\Persistence\{AggregateStatistics,FileImageCache,TrafficLimiter,ClientIdentity};

$root=dirname(__DIR__);$runtime=$root.'/var/data';$statistics=null;
$keyFile=$runtime.'/settings-v2.key';

try{
    $statistics=new AggregateStatistics($runtime.'/statistics-v2.sqlite');
    if(!is_file($keyFile))file_put_contents($keyFile,bin2hex(random_bytes(32)),LOCK_EX);
    $identity=(new ClientIdentity(trim((string)file_get_contents($keyFile))))->fromAddress((string)($_SERVER['REMOTE_ADDR']??'unknown')).':image';
    $traffic=new TrafficLimiter($runtime.'/direct-traffic-v2.sqlite');
    if(!$traffic->allow($identity,time(),120,60,0)){header('HTTP/1.0 429 Too Many Requests');header('Content-Type: text/plain; charset=UTF-8');echo'Too many image requests';exit;}
    $url=is_string($_GET['url']??null)?$_GET['url']:'';
    $mw=filter_var($_GET['mw']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>1280]])?:320;
    $mh=filter_var($_GET['mh']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>1280]])?:240;
    $policy=new UrlPolicy();$policy->validate($url);
    $cache=new FileImageCache($root.'/var/cache/images');$key=$cache->key($url,$mw,$mh);$data=$cache->get($key);
    if($data===null){
        $resolver=static function(string$host):array{$result=gethostbynamel($host)?:[];if(function_exists('dns_get_record'))foreach(dns_get_record($host,DNS_AAAA)?:[]as$row)if(isset($row['ipv6']))$result[]=$row['ipv6'];return array_values(array_unique($result));};
        $source=(new SafeSourceFetcher($policy,new CurlHttpTransport(),$resolver,8388608,5,['image/gif','image/jpeg','image/png','image/webp']))->fetch($url,'GET',[]);
        $data=(new ImageConverter(new ImagePlanner()))->toGif($source->body,$mw,$mh)['data'];$cache->put($key,$data);if(random_int(1,100)===1)$cache->prune();
    }
    if(!$traffic->record($identity,strlen($data),time())){header('HTTP/1.0 429 Too Many Requests');header('Content-Type: text/plain; charset=UTF-8');echo'Traffic limit reached';exit;}
    $statistics->increment('images','success');$statistics->add('image_bytes','all',strlen($data));header('Content-Type: image/gif');header('Content-Length: '.strlen($data));header('Cache-Control: public, max-age=3600');echo$data;
}catch(Throwable$e){if($statistics instanceof AggregateStatistics)try{$statistics->increment('images','error');}catch(Throwable){}header('HTTP/1.0 400 Bad Request');header('Content-Type: text/plain; charset=UTF-8');echo'Image unavailable';}
