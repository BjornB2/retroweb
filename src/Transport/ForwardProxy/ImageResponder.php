<?php
declare(strict_types=1);
namespace RetroWEB\Transport\ForwardProxy;
interface ImageResponder{public function image(string$url,int$maxWidth,int$maxHeight):string;}
