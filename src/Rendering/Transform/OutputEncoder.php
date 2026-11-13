<?php
declare(strict_types=1);
namespace RetroWEB\Rendering\Transform;

final class OutputEncoder
{
    public function encode(string $utf8,string $encoding):string
    {
        $target=$this->target($encoding);if(strcasecmp($target,'UTF-8')===0)return$utf8;$converted=@iconv('UTF-8',$target.'//TRANSLIT',$utf8);if($converted===false)$converted=@iconv('UTF-8',$target.'//IGNORE',$utf8);return is_string($converted)?$converted:$utf8;
    }
    public function contentType(string $encoding):string{return'text/html; charset='.$this->target($encoding);}
    private function target(string $encoding):string{$allowed=['UTF-8','ISO-8859-1','Windows-1252','Macintosh'];foreach($allowed as$value)if(strcasecmp($value,$encoding)===0)return$value;throw new \InvalidArgumentException('Unsupported output encoding');}
}
