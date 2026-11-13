<?php
declare(strict_types=1);
namespace RetroWEB\Infrastructure\Http;
use RetroWEB\Application\SourceFetcher;
use RetroWEB\Application\SourceResponse;
use RetroWEB\Application\ContextualSourceFetcher;
use RetroWEB\Application\RenderRequest;
use RetroWEB\Infrastructure\Persistence\UpstreamCookieStore;

final class SafeSourceFetcher implements SourceFetcher,ContextualSourceFetcher
{
    private $resolver;
    public function __construct(private UrlPolicy $policy, private HttpTransport $transport, callable $resolver, private int $maxBytes = 2097152, private int $maxRedirects = 5, private array $allowedTypes = ['text/html','application/xhtml+xml'],private ?UpstreamCookieStore$cookies=null) { $this->resolver = $resolver; }
    public function fetch(string $url, string $method, array $fields): SourceResponse
    {return$this->fetchInternal($url,$method,$fields,null);}
    public function fetchFor(RenderRequest$request):SourceResponse
    {return$this->fetchInternal($request->sourceUrl,$request->method,$request->fields,$request->clientIdentity);}
    private function fetchInternal(string $url,string $method,array $fields,?string$identity):SourceResponse
    {
        $original=$url;$url=$this->preferHttps($url);$httpFallback=$url!==$original?$original:null;
        $method = strtoupper($method); if (!in_array($method, ['GET','HEAD','POST'], true)) throw new \DomainException('Unsupported source method');
        $body = $method === 'POST' ? http_build_query($fields, '', '&', PHP_QUERY_RFC3986) : null;
        if ($body !== null && strlen($body) > 65536) throw new \LengthException('Form body too large');
        $visited = [];
        for ($redirects=0; $redirects <= $this->maxRedirects; $redirects++) {
            $this->policy->validate($url); if (isset($visited[$url])) throw new \RuntimeException('Redirect loop'); $visited[$url] = true;
            $addresses = ($this->resolver)(parse_url($url, PHP_URL_HOST)); $this->policy->assertResolvedAddresses($addresses);
            $headers=[];if($identity!==null&&$this->cookies!==null&&($cookie=$this->cookies->headerFor($identity,$url,time()))!==null)$headers['Cookie']=$cookie;try{$response=$headers&&$this->transport instanceof HttpTransportWithHeaders?$this->transport->requestWithHeaders($url,$method,$body,$addresses,$this->maxBytes,$headers):$this->transport->request($url,$method,$body,$addresses,$this->maxBytes);}catch(\RuntimeException$failure){if($httpFallback===null)throw$failure;$url=$httpFallback;$httpFallback=null;$this->policy->validate($url);$addresses=($this->resolver)(parse_url($url,PHP_URL_HOST));$this->policy->assertResolvedAddresses($addresses);$headers=[];if($identity!==null&&$this->cookies!==null&&($cookie=$this->cookies->headerFor($identity,$url,time()))!==null)$headers['Cookie']=$cookie;$response=$headers&&$this->transport instanceof HttpTransportWithHeaders?$this->transport->requestWithHeaders($url,$method,$body,$addresses,$this->maxBytes,$headers):$this->transport->request($url,$method,$body,$addresses,$this->maxBytes);}if($identity!==null&&$this->cookies!==null)$this->cookies->capture($identity,$url,$response->headers,time());
            $location = $response->header('Location');
            if ($response->status >= 300 && $response->status < 400 && $location !== null) {
                $redirect=$this->absolute($location,$url);$preferred=$this->preferHttps($redirect);$fallback=$preferred!==$redirect?$redirect:null;if(isset($visited[$preferred])&&$fallback!==null&&!isset($visited[$fallback])){$url=$fallback;$httpFallback=null;}else{$url=$preferred;$httpFallback=$fallback;}
                if ($response->status === 303 || (($response->status === 301 || $response->status === 302) && $method === 'POST')) { $method = 'GET'; $body = null; }
                continue;
            }
            if (strlen($response->body) > $this->maxBytes) throw new \LengthException('Source response too large');
            $contentType=strtolower(trim(explode(';',$response->header('Content-Type')??'text/html')[0]));if($this->allowedTypes&&!in_array($contentType,$this->allowedTypes,true))throw new \DomainException('Unsupported upstream content type');
            return new SourceResponse($response->body, $url, $response->status, $response->headers);
        }
        throw new \RuntimeException('Too many redirects');
    }
    private function absolute(string $location, string $base): string
    {
        if (preg_match('#^https?://#i', $location)) return $location;
        $p=parse_url($base);if(!$p||!isset($p['scheme'],$p['host']))throw new \DomainException('Unsupported redirect');$origin=$p['scheme'].'://'.$p['host'].(isset($p['port'])?':'.$p['port']:'');if(str_starts_with($location,'/'))return$origin.$location;$directory=isset($p['path'])?rtrim(dirname($p['path']),'/'):'';$segments=explode('/',$directory.'/'.$location);$normal=[];foreach($segments as$segment){if($segment===''||$segment==='.')continue;if($segment==='..')array_pop($normal);else$normal[]=$segment;}return$origin.'/'.implode('/',$normal);
    }
    private function preferHttps(string$url):string{return preg_replace('#^http://#i','https://',$url);}
}
