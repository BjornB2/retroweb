<?php
declare(strict_types=1);
namespace RetroWEB\Domain\Navigation;

final class NavigationBuilder
{
    public function __construct(private string$directOrigin='http://retroweb.bjrno.com',private string$directBasePath='')
    {
        $parts=parse_url($this->directOrigin);
        if(!$parts||strtolower((string)($parts['scheme']??''))!=='http'||!isset($parts['host'])||isset($parts['user'])||isset($parts['pass'])||isset($parts['query'])||isset($parts['fragment'])||(($parts['path']??'')!==''&&($parts['path']??'')!=='/'))throw new \InvalidArgumentException('Public origin must be an HTTP origin without a path or credentials');
        $this->directOrigin=rtrim($this->directOrigin,'/');
        if(!preg_match('#^http://[A-Za-z0-9.-]+(?::[0-9]+)?$#',$directOrigin))throw new \InvalidArgumentException('Invalid direct origin');
        if($directBasePath!==''&&!preg_match('#^/[A-Za-z0-9._-]+$#',$directBasePath))throw new \InvalidArgumentException('Invalid direct base path');
    }
    public function content(string $url,string $transport):string
    {
        $this->transport($transport);$this->origin($url);
        return$transport==='direct'?$this->directBasePath.'/?url='.rawurlencode($url):preg_replace('#^https://#i','http://',$url);
    }
    public function home(string$transport):string
    {
        $this->transport($transport);return$transport==='direct'?$this->directBasePath.'/':$this->directOrigin.$this->directBasePath.'/';
    }
    public function form(string$url,string$transport):string
    {
        return$transport==='direct'?$this->directOrigin.$this->directBasePath.'/':$this->content($url,$transport);
    }
    public function reload(string$url,string$transport):string
    {
        $target=$this->content($url,$transport);
        return$transport==='direct'?$target.'&action=reload':$target;
    }
    public function image(string$target,string$source,string$transport,int$width,int$height):string
    {
        $this->transport($transport);$this->origin($target);
        if($transport==='direct')return$this->directBasePath.'/image.php?url='.rawurlencode($target).'&mw='.$width.'&mh='.$height;
        return$this->legacyOrigin($source).'/.retroweb/image?url='.rawurlencode($target).'&mw='.$width.'&mh='.$height;
    }
    public function settings(string$source,string$transport,bool$custom=false):string
    {
        $this->transport($transport);if($transport==='direct')return$this->directBasePath.'/?settings=1'.($source!==''?'&url='.rawurlencode($source):'').($custom?'&custom=1':'');$suffix='source='.rawurlencode($source).($custom?'&custom=1':'');return$this->legacyOrigin($source).'/.retroweb/settings?'.$suffix;
    }
    public function imageMode(string$source,string$transport,string$mode):string
    {
        $this->transport($transport);if(!in_array($mode,['inline','links'],true))throw new \InvalidArgumentException('Invalid image mode');return$transport==='direct'?$this->directBasePath.'/?action=image-mode&mode='.$mode.($source!==''?'&url='.rawurlencode($source):''):$this->legacyOrigin($source).'/.retroweb/image-mode?source='.rawurlencode($source).'&mode='.$mode;
    }
    public function clientInfo(string$source,string$transport):string
    {
        $this->transport($transport);return$transport==='direct'?$this->directBasePath.'/client-info.php':$this->legacyOrigin($source).'/.retroweb/client-info?source='.rawurlencode($source);
    }
    public function statistics(string$source,string$transport):string
    {
        $this->transport($transport);return$transport==='direct'?$this->directBasePath.'/stats.php':$this->legacyOrigin($source).'/.retroweb/statistics?source='.rawurlencode($source);
    }
    public function source(NavigationContext $c): string
    {
        return $this->content($c->sourceUrl,$c->transport);
    }
    public function snapshot(NavigationContext $c, int $part): string
    {
        if ($c->snapshotToken === null || !preg_match('/^[a-f0-9]{64}$/', $c->snapshotToken)) throw new \DomainException('Valid snapshot binding required');
        $path = '/.retroweb/page/' . $c->snapshotToken . '/' . $part;
        return $c->transport === 'direct' ? $this->directBasePath.'/?url='.rawurlencode($c->sourceUrl).'&snapshot='.$c->snapshotToken.'&part='.$part : $this->legacyOrigin($c->sourceUrl) . $path.'?source='.rawurlencode($c->sourceUrl);
    }
    public function entire(NavigationContext$c):string
    {
        if($c->snapshotToken===null||!preg_match('/^[a-f0-9]{64}$/',$c->snapshotToken))throw new \DomainException('Valid snapshot binding required');return$c->transport==='direct'?$this->directBasePath.'/?url='.rawurlencode($c->sourceUrl).'&snapshot='.$c->snapshotToken.'&action=entire':$this->legacyOrigin($c->sourceUrl).'/.retroweb/page/'.$c->snapshotToken.'/entire?source='.rawurlencode($c->sourceUrl);
    }
    private function origin(string $url): string
    {
        $p = parse_url($url); if (!$p || !isset($p['scheme'],$p['host'])) throw new \DomainException('Invalid source');
        return strtolower($p['scheme']) . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
    }
    private function legacyOrigin(string$url):string{return preg_replace('#^https://#i','http://',$this->origin($url));}
    private function transport(string$transport):void{if(!in_array($transport,['direct','forward'],true))throw new \InvalidArgumentException('Invalid transport');}
}
