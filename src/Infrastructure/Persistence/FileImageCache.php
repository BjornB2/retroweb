<?php
declare(strict_types=1);
namespace RetroWEB\Infrastructure\Persistence;

final class FileImageCache
{
    public function __construct(private string$directory,private int$ttl=3600,private int$maxFiles=2000,private int$maxBytes=268435456){}
    public function key(string$url,int$width,int$height):string{return hash('sha256','image-v2|'.$width.'x'.$height.'|'.$url);}
    public function get(string$key,?int$now=null):?string
    {
        $path=$this->path($key);if(!is_file($path))return null;$now??=time();$mtime=filemtime($path);if($this->ttl>0&&($mtime===false||$mtime<$now-$this->ttl)){@unlink($path);return null;}$data=@file_get_contents($path);return is_string($data)&&str_starts_with($data,'GIF')?$data:null;
    }
    public function put(string$key,string$data):void
    {
        if(!str_starts_with($data,'GIF'))throw new \InvalidArgumentException('Only converted GIF data may be cached');$path=$this->path($key);$directory=dirname($path);if(!is_dir($directory)&&!mkdir($directory,0700,true)&&!is_dir($directory))throw new \RuntimeException('Unable to create image cache');$temporary=tempnam($directory,'image-');if($temporary===false||file_put_contents($temporary,$data,LOCK_EX)!==strlen($data)||!rename($temporary,$path)){if(is_string($temporary))@unlink($temporary);throw new \RuntimeException('Unable to store image cache entry');}@chmod($path,0600);
    }
    public function prune(?int$now=null):array
    {
        $now??=time();$files=[];$bytes=0;foreach(glob(rtrim($this->directory,'/').'/*/*.gif')?:[]as$path){if(!is_file($path))continue;$mtime=filemtime($path)?:0;$size=filesize($path)?:0;if($this->ttl>0&&$mtime<$now-$this->ttl){@unlink($path);continue;}$files[]=['path'=>$path,'mtime'=>$mtime,'size'=>$size];$bytes+=$size;}usort($files,static fn(array$a,array$b):int=>$a['mtime']<=>$b['mtime']);$removed=0;while(count($files)>$this->maxFiles||$bytes>$this->maxBytes){$file=array_shift($files);if(@unlink($file['path'])){$bytes-=$file['size'];$removed++;}}return['files'=>count($files),'bytes'=>$bytes,'removed'=>$removed];
    }
    private function path(string$key):string{if(!preg_match('/^[a-f0-9]{64}$/',$key))throw new \InvalidArgumentException('Invalid image cache key');return rtrim($this->directory,'/').'/'.substr($key,0,2).'/'.$key.'.gif';}
}
