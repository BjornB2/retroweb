<?php
declare(strict_types=1);
namespace RetroWEB\Application;
use RetroWEB\Infrastructure\Persistence\SnapshotRepository;
use RetroWEB\Rendering\Transform\OutputEncoder;
use RetroWEB\Domain\Navigation\{NavigationBuilder,NavigationContext};

final class RenderPage
{
    private NavigationBuilder$navigation;
    public function __construct(private SourceFetcher $fetcher, private SnapshotRepository $snapshots, private string $renderVersion, private int $snapshotTtl = 900, private ?DocumentRenderer $renderer = null,?NavigationBuilder$navigation=null){$this->navigation=$navigation??new NavigationBuilder();}
    public function render(RenderRequest $request, int $now): RenderResult
    {
        if ($request->snapshotToken !== null && !$request->forceRefetch) {
            $s = $this->snapshots->get($request->snapshotToken, $now, $this->renderVersion);
            if($s!==null&&hash_equals($s->sourceUrl,$request->sourceUrl)){if($request->entire)return new RenderResult(implode("\n",$s->parts),$s->sourceUrl,$s->token,1,1,true,$s->encoding);return $this->part($s->parts,$s->sourceUrl,$s->token,$request->part,true,$s->encoding,$request->transport);}
        }
        $source=$this->fetcher instanceof ContextualSourceFetcher?$this->fetcher->fetchFor($request):$this->fetcher->fetch($request->sourceUrl,$request->method,$request->fields);
        $parts=$this->renderer?$this->renderer->render($source->body,$source->finalUrl,$request):[$source->body];$encoding=$this->renderer?$this->renderer->encoding($request):'UTF-8';if(strcasecmp($encoding,'UTF-8')!==0){$encoder=new OutputEncoder();$parts=array_map(static fn(string$part):string=>$encoder->encode($part,$encoding),$parts);}
        $snapshot = $this->snapshots->put($source->finalUrl, $parts, $now + $this->snapshotTtl, $this->renderVersion,$encoding);
        return $this->part($parts, $source->finalUrl, $snapshot->token, 1, false,$encoding,$request->transport);
    }
    public function renderSnapshot(string$token,int$part,string$transport,int$now,bool$entire=false):RenderResult
    {
        $snapshot=$this->snapshots->get($token,$now,$this->renderVersion);if($snapshot===null)throw new \DomainException('Snapshot expired or invalid');if($entire)return new RenderResult(implode("\n",$snapshot->parts),$snapshot->sourceUrl,$snapshot->token,1,1,true,$snapshot->encoding);return$this->part($snapshot->parts,$snapshot->sourceUrl,$snapshot->token,$part,true,$snapshot->encoding,$transport);
    }
    private function part(array $parts, string $source, string $token, int $part, bool $snapshot,string$encoding='UTF-8',string$transport='direct'): RenderResult
    {
        if ($part < 1 || $part > count($parts)) throw new \OutOfRangeException('Snapshot part unavailable');
        $body=$this->rewriteAnchors((string)$parts[$part-1],$parts,$source,$token,$transport);return new RenderResult($body,$source,$token,$part,count($parts),$snapshot,$encoding);
    }
    private function rewriteAnchors(string$body,array$parts,string$source,string$token,string$transport):string{return preg_replace_callback('/href=("|\')#([A-Za-z][A-Za-z0-9_.:-]*)\1/i',function(array$m)use($parts,$source,$token,$transport):string{$target=1;foreach($parts as$i=>$candidate)if(preg_match('/(?:id|name)=("|\')'.preg_quote($m[2],'/').'\1/i',(string)$candidate)){$target=$i+1;break;}$base=$this->navigation->snapshot(new NavigationContext($source,$transport,$token),$target);return'href='.$m[1].$base.'#'.$m[2].$m[1];},$body)??$body;}
}
