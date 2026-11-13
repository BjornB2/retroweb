<?php
declare(strict_types=1);
namespace RetroWEB\Transport\ForwardProxy;
use RetroWEB\Application\RenderRequest;
use RetroWEB\Infrastructure\Http\UrlPolicy;
use RetroWEB\Domain\Form\SensitiveFieldPolicy;

final class ForwardRequestAdapter
{
    public function __construct(private UrlPolicy $policy) {}
    public function parse(string $raw): RenderRequest
    {
        if(strlen($raw)>81920)throw new \LengthException('Proxy request too large'); $sections=preg_split('/\r?\n\r?\n/',$raw,2); $lines=preg_split('/\r?\n/',$sections[0]); $line=array_shift($lines);
        if(!is_string($line)||!preg_match('/^(GET|HEAD|POST|CONNECT)\s+(\S+)\s+HTTP\/1\.[01]$/i',$line,$m))throw new \InvalidArgumentException('Invalid proxy request');
        $method=strtoupper($m[1]); if($method==='CONNECT')throw new \DomainException('CONNECT is disabled'); $url=$this->absolute($m[2]); $this->policy->validate($url);
        $headers=[]; foreach($lines as $line){ if(!preg_match('/^([A-Za-z0-9-]+):\s*(.*)$/',$line,$h))continue; $headers[strtolower($h[1])]=$h[2]; }
        if(isset($headers['authorization'])||isset($headers['proxy-authorization']))throw new \DomainException('Authentication is unsupported');
        $body=$sections[1]??'';$fields=[]; if($method==='POST'){ if(strtolower(trim(explode(';',$headers['content-type']??'')[0]))!=='application/x-www-form-urlencoded')throw new \DomainException('Unsupported form encoding'); if(strlen($body)>65536)throw new \LengthException('Form too large');if(isset($headers['content-length'])&&(!preg_match('/^[0-9]+$/',$headers['content-length'])||(int)$headers['content-length']!==strlen($body)))throw new \InvalidArgumentException('Invalid Content-Length'); parse_str($body,$fields);(new SensitiveFieldPolicy())->assertSafe($fields); }elseif($body!=='')throw new \InvalidArgumentException('Unexpected request body');
        return new RenderRequest($url,'forward',$method,$fields,null,1,false,false,(string)($headers['user-agent']??''));
    }
    private function absolute(string $url): string { if(!preg_match('#^https?://#i',$url))throw new \InvalidArgumentException('Absolute URL required');return$url; }
}
