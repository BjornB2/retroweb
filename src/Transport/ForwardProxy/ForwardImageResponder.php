<?php
declare(strict_types=1);
namespace RetroWEB\Transport\ForwardProxy;
use RetroWEB\Application\SourceFetcher;use RetroWEB\Rendering\Image\ImageConverter;use RetroWEB\Infrastructure\Persistence\FileImageCache;
final class ForwardImageResponder implements ImageResponder{public function __construct(private SourceFetcher$fetcher,private ImageConverter$converter,private ?FileImageCache$cache=null){}public function image(string$url,int$maxWidth,int$maxHeight):string{$key=$this->cache?->key($url,$maxWidth,$maxHeight);if($key!==null&&($cached=$this->cache->get($key))!==null)return$cached;$source=$this->fetcher->fetch($url,'GET',[]);$data=$this->converter->toGif($source->body,$maxWidth,$maxHeight)['data'];if($key!==null)$this->cache->put($key,$data);return$data;}}
