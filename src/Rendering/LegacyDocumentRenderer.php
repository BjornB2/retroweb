<?php
declare(strict_types=1);
namespace RetroWEB\Rendering;
use RetroWEB\Application\DocumentRenderer;
use RetroWEB\Rendering\Extraction\ExtractionStrategy;
use RetroWEB\Rendering\Transform\LegacyTransformer;
use RetroWEB\Rendering\Pagination\MemoryBudgetPaginator;
use RetroWEB\Domain\Document\Fragment;
use RetroWEB\Application\RenderRequest;
use RetroWEB\Rendering\Transform\LegacyOutputWorkarounds;

final class LegacyDocumentRenderer implements DocumentRenderer
{
    public function __construct(private ExtractionStrategy $extractor, private LegacyTransformer $transformer, private MemoryBudgetPaginator $paginator, private string $compatibility='html32', private int $budgetBytes=128000, private int $overheadBytes=2048, private bool $unsplit=false, private string $imageMode='inline', private int $imageWidth=320, private int $imageHeight=240,private int$viewportWidth=320,private array$capabilities=[],private array$workarounds=[]) {}
    public function render(string $html, string $sourceUrl, RenderRequest $request): array
    {
        [$bodyAttributes,$documentBackground]=$this->bodyPresentation($html);$document=$this->extractor->extract($html,$sourceUrl);$sourceFragments=$document->fragments;$hasHeading=false;foreach($sourceFragments as$fragment)if(preg_match('/<h1\b/i',$fragment->html)){$hasHeading=true;break;}if(!$hasHeading)array_unshift($sourceFragments,new Fragment('<h1>'.htmlspecialchars($document->title,ENT_QUOTES,'UTF-8').'</h1>'));$fragments=[];
        foreach($sourceFragments as $fragment){$converted=$this->transformer->transform($fragment->html,$this->compatibility,true,$sourceUrl,$request->transport,$this->imageMode,$this->imageWidth,$this->imageHeight,$this->viewportWidth,$this->capabilities,$documentBackground);foreach($this->budgetFragments($converted)as$budgetFragment)$fragments[]=$budgetFragment;}
        $parts=$this->paginator->paginate($fragments,$this->budgetBytes,$this->overheadBytes,$this->unsplit); $output=[];
        foreach($parts as $part){ $body=''; foreach($part as $fragment)$body.=$fragment->html; $output[]=LegacyOutputWorkarounds::apply('<html><head><title>'.htmlspecialchars($document->title,ENT_QUOTES,'UTF-8').'</title></head><body'.$bodyAttributes.'>'.$body.'</body></html>',$this->workarounds); }
        return array_map(fn(string$page):string=>\RetroWEB\Rendering\Transform\LegacyDoctype::apply($page,$this->compatibility),$output);
    }
    public function encoding(RenderRequest $request):string{return'UTF-8';}
    private function bodyPresentation(string$html):array
    {
        if($this->compatibility==='html20')return['','#ffffff'];$dom=new \DOMDocument('1.0','UTF-8');libxml_use_internal_errors(true);$ok=$dom->loadHTML('<?xml encoding="UTF-8">'.$html,LIBXML_NONET);libxml_clear_errors();$body=$ok?$dom->getElementsByTagName('body')->item(0):null;if(!$body instanceof \DOMElement)return['','#ffffff'];$attributes='';$background='#ffffff';foreach(['bgcolor','text','link','vlink','alink']as$name){$value=trim($body->getAttribute($name));if($value===''||!preg_match('/^(?:#[0-9a-f]{3}|#[0-9a-f]{6}|[a-z]{1,20})$/i',$value))continue;if($name==='bgcolor')$background=$value;$attributes.=' '.$name.'="'.htmlspecialchars($value,ENT_QUOTES,'UTF-8').'"';}return[$attributes,$background];
    }

    /** Split transformed top-level content so one large NOS section cannot
     * turn every image into a link as one indivisible memory item. */
    private function budgetFragments(string $html):array
    {
        if(trim($html)==='')return[];$dom=new \DOMDocument('1.0','UTF-8');libxml_use_internal_errors(true);$ok=$dom->loadHTML('<?xml encoding="UTF-8"><div id="rw-budget-root">'.$html.'</div>',LIBXML_NONET);libxml_clear_errors();if(!$ok)return[$this->budgetFragment($html)];$root=(new \DOMXPath($dom))->query('//*[@id="rw-budget-root"]')->item(0);if(!$root)return[$this->budgetFragment($html)];$result=[];foreach(iterator_to_array($root->childNodes)as$node)foreach($this->splitNodeByImages($node)as$split){$part=$dom->saveHTML($split);if(trim($part)!=='')$result[]=$this->budgetFragment($part);}return$result?:[$this->budgetFragment($html)];
    }
    private function budgetFragment(string$html):Fragment
    {
        $bytes=0;preg_match_all('/<img\b[^>]*>/i',$html,$matches);foreach($matches[0]as$image){$declared=$this->attributeInt($image,'data-rw-decoded-bytes');$bytes+=$declared?:$this->imageWidth*$this->imageHeight+1024;}$html=preg_replace('/\sdata-rw-decoded-bytes="[0-9]+"/i','',$html)??$html;$fallback=$bytes>0?$this->memoryLinkedImages($html):null;return new Fragment($html,$bytes,null,$fallback);
    }
    private function attributeInt(string$html,string$name):?int{return preg_match('/\b'.preg_quote($name,'/').'="([0-9]+)"/i',$html,$match)?max(1,(int)$match[1]):null;}
    private function memoryLinkedImages(string$html):string
    {
        $replacement=static function(string$image):string{$label='Image';if(preg_match('/\balt="([^"]*)"/i',$image,$alt)&&trim(html_entity_decode($alt[1],ENT_QUOTES|ENT_HTML5,'UTF-8'))!=='')$label=trim(html_entity_decode($alt[1],ENT_QUOTES|ENT_HTML5,'UTF-8'));$text='[Image: '.htmlspecialchars($label,ENT_QUOTES,'UTF-8').' — linked because of memory budget]';return preg_match('/\bsrc="([^"]+)"/i',$image,$src)?'<a href="'.$src[1].'">'.$text.'</a>':$text;};
        $html=preg_replace_callback('#<a\b[^>]*>\s*(<img\b[^>]*>)\s*</a>#i',static fn(array$m):string=>$replacement($m[1]),$html)??$html;
        return preg_replace_callback('/<img\b[^>]*>/i',static fn(array$m):string=>$replacement($m[0]),$html)??$html;
    }
    private function splitNodeByImages(\DOMNode$node):array
    {
        $count=$node instanceof \DOMElement&&strtolower($node->tagName)==='img'?1:0;if($node instanceof \DOMElement)$count+=$node->getElementsByTagName('img')->length;if($count<=1)return[$node->cloneNode(true)];if($this->nodeFitsBudget($node))return[$node->cloneNode(true)];if(!$node instanceof \DOMElement)return[$node->cloneNode(true)];$result=[];foreach(iterator_to_array($node->childNodes)as$child)foreach($this->splitNodeByImages($child)as$piece){$wrapper=$node->cloneNode(false);$wrapper->appendChild($piece);$result[]=$wrapper;}return$result?:[$node->cloneNode(true)];
    }
    private function nodeFitsBudget(\DOMNode$node):bool
    {
        $html=$node->ownerDocument?->saveHTML($node)??'';$bytes=0;if($node instanceof \DOMElement){$images=strtolower($node->tagName)==='img'?[$node]:iterator_to_array($node->getElementsByTagName('img'));foreach($images as$image)if($image instanceof \DOMElement){$declared=filter_var($image->getAttribute('data-rw-decoded-bytes'),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);$bytes+=$declared?:$this->imageWidth*$this->imageHeight+1024;}}return strlen($html)+$bytes<=max(1,$this->budgetBytes-$this->overheadBytes);
    }
}
