<?php
declare(strict_types=1);
namespace RetroWEB\Infrastructure\Http;

final class CurlHttpTransport implements HttpTransportWithHeaders
{
    public function request(string$url,string$method,?string$body,array$addresses,int$maxBytes):HttpResponse{return$this->requestWithHeaders($url,$method,$body,$addresses,$maxBytes,[]);}
    public function requestWithHeaders(string$url,string$method,?string$body,array$addresses,int$maxBytes,array$extraHeaders):HttpResponse
    {
        if(!function_exists('curl_init'))throw new \RuntimeException('cURL unavailable');$p=parse_url($url);$host=$p['host'];$port=$p['port']??(strtolower($p['scheme'])==='https'?443:80);$last='';
        foreach($addresses as$address){$data='';$headers=[];$tooLarge=false;$ch=curl_init($url);$pin=$host.':'.$port.':'.(str_contains($address,':')?'['.$address.']':$address);$requestHeaders=['User-Agent: RetroWEB/2.0','Accept-Encoding: identity'];foreach($extraHeaders as$name=>$value)$requestHeaders[]=$name.': '.preg_replace('/[\r\n]/','',(string)$value);if($body!==null)$requestHeaders[]='Content-Type: application/x-www-form-urlencoded';
            curl_setopt_array($ch,[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>20,CURLOPT_PROTOCOLS=>CURLPROTO_HTTP|CURLPROTO_HTTPS,CURLOPT_RESOLVE=>[$pin],CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_HTTPHEADER=>$requestHeaders,CURLOPT_HEADERFUNCTION=>function($c,$line)use(&$headers){$length=strlen($line);$trimmed=trim($line);if($trimmed!==''&&strpos($trimmed,':')!==false){[$key,$value]=explode(':',$trimmed,2);$key=trim($key);$value=trim($value);if(isset($headers[$key]))$headers[$key]=array_merge((array)$headers[$key],[$value]);else$headers[$key]=$value;}return$length;},CURLOPT_WRITEFUNCTION=>function($c,$chunk)use(&$data,$maxBytes,&$tooLarge){if(strlen($data)+strlen($chunk)>$maxBytes){$tooLarge=true;return 0;}$data.=$chunk;return strlen($chunk);}]);
            if($method==='HEAD')curl_setopt($ch,CURLOPT_NOBODY,true);if($body!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,$body);$ok=curl_exec($ch);$last=curl_error($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);if(PHP_VERSION_ID<80500)curl_close($ch);else unset($ch);if($tooLarge)throw new \LengthException('Source response too large');if($ok!==false)return new HttpResponse($status,$data,$headers);
        }throw new \RuntimeException('Upstream request failed: '.$last);
    }
}
