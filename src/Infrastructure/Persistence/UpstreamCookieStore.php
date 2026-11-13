<?php
declare(strict_types=1);
namespace RetroWEB\Infrastructure\Persistence;

final class UpstreamCookieStore
{
    public function __construct(private string$directory,private int$ttl=3600){}
    public function headerFor(string$identity,string$url,int$now):?string
    {
        $data=$this->read($identity);$host=strtolower((string)parse_url($url,PHP_URL_HOST));$path=(string)(parse_url($url,PHP_URL_PATH)?:'/');$secure=strtolower((string)parse_url($url,PHP_URL_SCHEME))==='https';$cookies=[];foreach($data[$host]??[]as$name=>$item){$cookiePath=is_string($item['path']??null)?$item['path']:'/';if(($item['expires']??0)<$now||!$this->pathMatches($path,$cookiePath)||!$secure&&!empty($item['secure']))continue;$cookies[]=['header'=>$name.'='.$item['value'],'path'=>$cookiePath];}usort($cookies,static fn(array$a,array$b):int=>strlen($b['path'])<=>strlen($a['path']));return$cookies?implode('; ',array_column($cookies,'header')):null;
    }
    public function capture(string$identity,string$url,array$headers,int$now):void
    {
        $host=strtolower((string)parse_url($url,PHP_URL_HOST));if($host==='')return;$requestPath=(string)(parse_url($url,PHP_URL_PATH)?:'/');$defaultPath=str_contains($requestPath,'/')?rtrim(dirname($requestPath),'/').'/':'/';if(!str_starts_with($defaultPath,'/'))$defaultPath='/';$values=[];foreach($headers as$name=>$value)if(strcasecmp((string)$name,'Set-Cookie')===0)foreach((array)$value as$line)$values[]=(string)$line;if(!$values)return;$data=$this->read($identity);foreach($values as$line){$segments=array_map('trim',explode(';',$line));$pair=array_shift($segments);if(!is_string($pair)||!str_contains($pair,'='))continue;[$name,$value]=explode('=',$pair,2);if(!preg_match('/^[A-Za-z0-9!#$%&*+.^_`|~-]{1,80}$/',$name)||strlen($value)>2048)continue;$path=$defaultPath;$secure=false;$expires=$now+$this->ttl;$valid=true;foreach($segments as$attribute){[$key,$attributeValue]=array_pad(explode('=',$attribute,2),2,'');$key=strtolower(trim($key));$attributeValue=trim($attributeValue);if($key==='domain'&&ltrim(strtolower($attributeValue),'.')!==$host){$valid=false;break;}if($key==='path'&&str_starts_with($attributeValue,'/'))$path=$attributeValue;if($key==='secure')$secure=true;if($key==='max-age'&&preg_match('/^-?[0-9]+$/',$attributeValue))$expires=min($expires,$now+(int)$attributeValue);}if(!$valid)continue;if($value===''||$expires<=$now)unset($data[$host][$name]);else$data[$host][$name]=['value'=>$value,'expires'=>$expires,'path'=>$path,'secure'=>$secure];}$this->write($identity,$data);
    }
    private function read(string$id):array{if(!preg_match('/^[a-f0-9]{64}$/',$id))return[];$path=$this->path($id);if(!is_file($path)||filesize($path)>32768)return[];$data=json_decode((string)file_get_contents($path),true);return is_array($data)?$data:[];}
    private function write(string$id,array$data):void{if(!preg_match('/^[a-f0-9]{64}$/',$id))return;if(!is_dir($this->directory))mkdir($this->directory,0700,true);$json=json_encode($data,JSON_UNESCAPED_SLASHES);if(!is_string($json)||strlen($json)>32768)return;$tmp=tempnam($this->directory,'cookie-');if($tmp!==false&&file_put_contents($tmp,$json,LOCK_EX)===strlen($json))rename($tmp,$this->path($id));elseif(is_string($tmp))@unlink($tmp);}
    private function path(string$id):string{return rtrim($this->directory,'/').'/'.$id.'.json';}
    private function pathMatches(string$request,string$cookie):bool{return$request===$cookie||str_starts_with($request,rtrim($cookie,'/').'/');}
}
