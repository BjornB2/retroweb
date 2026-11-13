<?php
declare(strict_types=1);
namespace RetroWEB\Rendering\Transform;
use RetroWEB\Domain\Navigation\NavigationBuilder;
use RetroWEB\Domain\Form\SensitiveFieldPolicy;
use RetroWEB\Infrastructure\Http\UrlPolicy;

final class LegacyTransformer
{
    private const DROP = ['script','style','noscript','template','iframe','object','embed','svg','canvas'];
    private const BASE = ['a','p','br','hr','h1','h2','h3','h4','h5','h6','b','strong','i','em','u','tt','pre','code','blockquote','ol','ul','li','dl','dt','dd','small','font','img','form','input','select','option','textarea','label','table','caption','thead','tbody','tfoot','tr','th','td'];
    private NavigationBuilder$navigation;
    private UrlPolicy$policy;
    public function __construct(?NavigationBuilder$navigation=null,?UrlPolicy$policy=null){$this->navigation=$navigation??new NavigationBuilder();$this->policy=$policy??new UrlPolicy();}
    public function transform(string $html, string $compatibility, bool $forms = true, string $sourceUrl = '', string $transport = 'direct', string $imageMode = 'inline', int $imageWidth = 320, int $imageHeight = 240, int $viewportWidth = 320,array $capabilities=[],string$documentBackground='#ffffff'): string
    {
        $dom=new \DOMDocument('1.0','UTF-8'); libxml_use_internal_errors(true); $ok=$dom->loadHTML('<?xml encoding="UTF-8"><div id="rw-root">'.$html.'</div>', LIBXML_NONET); libxml_clear_errors(); if(!$ok)return '';
        $xpath=new \DOMXPath($dom); $root=$xpath->query('//*[@id="rw-root"]')->item(0); if(!$root)return '';$rules=$this->cssRules($xpath,$root);$this->removeDroppedElements($xpath,$root);
        $tables=($capabilities['tables']??true)!==false;$layoutTables=($capabilities['layout_tables']??$tables)!==false;$profileForms=($capabilities['forms']??true)!==false;
        $this->applyVisualOrder($xpath,$root,$rules);$this->convertLayouts($dom,$xpath,$root,$compatibility,$viewportWidth,$rules,$layoutTables);
        if($compatibility==='html20'||!$tables)$this->lineariseTables($dom,$xpath,$root);
        $this->neutralisePresentationLists($dom,$xpath,$root,$rules);
        foreach(iterator_to_array($xpath->query('.//figure[.//img and count(.//img)=1]',$root))as$figure){
            if(!$figure->parentNode)continue;$block=$dom->createElement('p');$block->setAttribute('data-rw-image-block','1');
            foreach(iterator_to_array($figure->childNodes)as$child){if($child instanceof \DOMElement&&strtolower($child->tagName)==='figcaption'){$block->appendChild($dom->createElement('br'));foreach(iterator_to_array($child->childNodes)as$captionChild){if($captionChild instanceof \DOMElement&&strtolower($captionChild->tagName)==='p'){while($captionChild->firstChild)$block->appendChild($captionChild->firstChild);}else$block->appendChild($captionChild);}}else$block->appendChild($child);}
            $figure->parentNode->replaceChild($block,$figure);
        }
        foreach(iterator_to_array($xpath->query('.//*',$root))as$n)if($n instanceof \DOMElement&&($n->hasAttribute('hidden')||strtolower($n->getAttribute('aria-hidden'))==='true'||preg_match('/(?:display\s*:\s*none|visibility\s*:\s*hidden)/i',$this->effectiveStyle($n,$rules))))if($n->parentNode)$n->parentNode->removeChild($n);
        foreach (array_reverse(iterator_to_array($root->getElementsByTagName('*'))) as $el) {
            if(!$el instanceof \DOMElement || !$el->parentNode)continue; $tag=strtolower($el->tagName);
            if(in_array($tag,self::DROP,true)){ $el->parentNode->removeChild($el); continue; }
            if(!in_array($tag,self::BASE,true) || ($compatibility==='html20' && in_array($tag,['table','caption','thead','tbody','tfoot','tr','th','td'],true))){ while($el->firstChild)$el->parentNode->insertBefore($el->firstChild,$el); $el->parentNode->removeChild($el); continue; }
            if($tag==='form' && (!$forms || !$profileForms || ($el->hasAttribute('enctype')&&strtolower(trim($el->getAttribute('enctype')))!=='application/x-www-form-urlencoded') || $xpath->query('.//input[translate(@type,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="password" or translate(@type,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="file"]',$el)->length||$this->hasSensitiveFieldName($xpath,$el))){ $p=$dom->createElement('p','Form not supported: sensitive fields, uploads and unsupported encodings are disabled.'); $el->parentNode->replaceChild($p,$el); continue; }
            $style=$this->effectiveStyle($el,$rules);$this->applyClassicStyle($dom,$el,$tag,$style);
            foreach(iterator_to_array($el->attributes) as $a){ $name=strtolower($a->name); if(!$this->allowedAttribute($tag,$name)||in_array($name,['href','src','action'],true)&&preg_match('/^\s*(?:javascript|data|vbscript):/i',$a->value))$el->removeAttributeNode($a); }
            if($tag==='a'&&$el->hasAttribute('href')){$href=$el->getAttribute('href');if(str_starts_with($href,'#'))$el->setAttribute('href',$href);else{$target=$this->absolute($href,$sourceUrl);if($target!==null){$target=$this->unwrapSearchRedirect($target);if($transport==='forward')$target=$this->unwrapRetroWebProxy($target);$el->setAttribute('href',$this->navigation->content($target,$transport));}else$el->removeAttribute('href');}}
            if($tag==='img'&&$el->hasAttribute('src')){$target=$this->absolute($el->getAttribute('src'),$sourceUrl);if($target===null){$el->parentNode->removeChild($el);continue;}$alt=trim($el->getAttribute('alt'))?:'Image';[$renderedWidth,$renderedHeight,$knownDimensions]=$this->renderedImageDimensions($el,$imageWidth,$imageHeight);$imageUrl=$this->navigation->image($target,$sourceUrl,$transport,$renderedWidth,$renderedHeight);$large=$this->isLargeImage($renderedWidth,$renderedHeight,$viewportWidth);if($imageMode==='links'){$link=$this->replaceImageWithLink($dom,$el,$imageUrl,$alt);if($large)$this->surroundWithBreaks($dom,$link);continue;}$el->setAttribute('src',$imageUrl);if($knownDimensions){$el->setAttribute('width',(string)$renderedWidth);$el->setAttribute('height',(string)$renderedHeight);}else{$el->removeAttribute('width');$el->removeAttribute('height');}$el->setAttribute('data-rw-decoded-bytes',(string)($renderedWidth*$renderedHeight+1024));if($large)$this->surroundWithBreaks($dom,$el);}
            if($tag==='form'){$target=$this->absolute($el->getAttribute('action')?:$sourceUrl,$sourceUrl);try{if($target===null)throw new \DomainException('Invalid form target');$this->policy->validate($target);}catch(\DomainException){$p=$dom->createElement('p','Form not supported: unsafe target.');$el->parentNode->replaceChild($p,$el);continue;}$method=strtolower($el->getAttribute('method'))==='post'?'post':'get';while($el->attributes->length)$el->removeAttributeNode($el->attributes->item(0));$el->setAttribute('method',$method);$el->setAttribute('action',$this->navigation->form($target,$transport));if($transport==='direct')foreach(['rw_source'=>$target,'rw_form'=>'1']as$name=>$value){$hidden=$dom->createElement('input');$hidden->setAttribute('type','hidden');$hidden->setAttribute('name',$name);$hidden->setAttribute('value',$value);$el->insertBefore($hidden,$el->firstChild);}}
        }
        $this->ensureContrast($xpath,$root,$documentBackground);$out=''; foreach($root->childNodes as $child)$out.=$dom->saveHTML($child); return $out;
    }
    private function absolute(string $url,string $base):?string
    {
        $url=html_entity_decode(trim($url),ENT_QUOTES|ENT_HTML5,'UTF-8');
        if(preg_match('#^https?://#i',$url))return$url;
        if($url===''||str_starts_with($url,'#')||preg_match('/^[a-z][a-z0-9+.-]*:/i',$url))return null;
        $p=parse_url($base);if(!$p||!isset($p['scheme'],$p['host']))return null;
        $origin=strtolower($p['scheme']).'://'.$p['host'].(isset($p['port'])?':'.$p['port']:'');
        if(str_starts_with($url,'//'))return strtolower($p['scheme']).':'.$url;
        if(str_starts_with($url,'?'))return$origin.($p['path']??'/').$url;
        $suffix='';if(($position=strpos($url,'?'))!==false){$suffix=substr($url,$position);$url=substr($url,0,$position);}
        $path=str_starts_with($url,'/')?$url:rtrim(dirname($p['path']??'/'),'/').'/'.$url;
        $normal=[];foreach(explode('/',$path)as$segment){if($segment===''||$segment==='.')continue;if($segment==='..')array_pop($normal);else$normal[]=$segment;}
        return$origin.'/'.implode('/',$normal).$suffix;
    }
    private function unwrapSearchRedirect(string$url):string
    {
        $parts=parse_url($url);$host=strtolower((string)($parts['host']??''));if(($host==='duckduckgo.com'||str_ends_with($host,'.duckduckgo.com'))&&($parts['path']??'')==='/l/'){parse_str((string)($parts['query']??''),$query);$target=$query['uddg']??null;if(is_string($target)&&preg_match('#^https?://#i',$target))return$target;}return$url;
    }
    private function unwrapRetroWebProxy(string$url):string
    {
        $parts=parse_url($url);if(!$parts||strtolower((string)($parts['host']??''))!=='retroweb.bjrno.com'||isset($parts['user'])||isset($parts['pass']))return$url;
        $path=rtrim((string)($parts['path']??'/'),'/');if(!in_array($path,['','/index.php','/proxy.php','/beta','/beta/index.php'],true))return$url;
        parse_str((string)($parts['query']??''),$query);$target=$query['url']??null;if(!is_string($target)||!preg_match('#^https?://#i',$target))return$url;
        $targetParts=parse_url($target);if(!$targetParts||!isset($targetParts['scheme'],$targetParts['host'])||isset($targetParts['user'])||isset($targetParts['pass']))return$url;
        return$target;
    }
    private function allowedAttribute(string$tag,string$name):bool{$common=['id','title'];$map=['a'=>['href','name'],'img'=>['src','alt','width','height'],'font'=>['color','face','size'],'p'=>['align'],'h1'=>['align'],'h2'=>['align'],'h3'=>['align'],'h4'=>['align'],'h5'=>['align'],'h6'=>['align'],'table'=>['border','cellpadding','cellspacing','width','align','bgcolor'],'tr'=>['align','valign','bgcolor'],'td'=>['colspan','rowspan','width','height','align','valign','bgcolor','nowrap'],'th'=>['colspan','rowspan','width','height','align','valign','bgcolor','nowrap'],'form'=>['action','method'],'input'=>['type','name','value','checked','size','maxlength'],'select'=>['name','size','multiple'],'option'=>['value','selected'],'textarea'=>['name','rows','cols'],'label'=>['for']];return in_array($name,array_merge($common,$map[$tag]??[]),true);}
    private function hasSensitiveFieldName(\DOMXPath$xpath,\DOMElement$form):bool{$policy=new SensitiveFieldPolicy();foreach($xpath->query('.//*[@name]',$form)as$field)if($field instanceof \DOMElement&&$policy->isSensitiveName($field->getAttribute('name')))return true;return false;}
    private function removeDroppedElements(\DOMXPath$xpath,\DOMNode$root):void
    {
        $query='.//*[self::'.implode(' or self::',self::DROP).']';$nodes=[];foreach($xpath->query($query,$root)?:[]as$node)$nodes[]=$node;foreach(array_reverse($nodes)as$node)if($node->parentNode)$node->parentNode->removeChild($node);
    }
    private function lineariseTables(\DOMDocument$dom,\DOMXPath$xpath,\DOMNode$root):void
    {
        foreach(array_reverse(iterator_to_array($xpath->query('.//table',$root)))as$table){if(!$table->parentNode)continue;$container=$dom->createElement('div');$headers=[];foreach($xpath->query('.//tr',$table)as$row){$cells=$xpath->query('./th|./td',$row);if(!$cells->length)continue;foreach($cells as$i=>$cell){if(strtolower($cell->nodeName)==='th'){$headers[$i]=trim(preg_replace('/\s+/',' ',$cell->textContent));continue;}$line=$dom->createElement('div');if(isset($headers[$i])&&$headers[$i]!==''){$label=$dom->createElement('b');$label->appendChild($dom->createTextNode($headers[$i].': '));$line->appendChild($label);}while($cell->firstChild)$line->appendChild($cell->firstChild);$line->appendChild($dom->createElement('br'));$container->appendChild($line);}}$table->parentNode->replaceChild($container,$table);}
    }
    private function neutralisePresentationLists(\DOMDocument$dom,\DOMXPath$xpath,\DOMNode$root,array$rules):void
    {
        foreach(array_reverse(iterator_to_array($xpath->query('.//ul|.//ol',$root)))as$list){if(!$list instanceof \DOMElement||!$list->parentNode)continue;$items=iterator_to_array($xpath->query('./li',$list));if(!$items)continue;$style=$this->effectiveStyle($list,$rules);$hiddenMarkers=preg_match('/(?:^|;)\s*list-style(?:-type)?\s*:\s*none\b/i',$style)===1;$imageList=false;foreach($items as$item)if($item instanceof \DOMElement&&$xpath->query('.//img',$item)->length>0){$imageList=true;break;}if(!$hiddenMarkers&&!$imageList)continue;$container=$dom->createElement('div');foreach($items as$item){$card=$dom->createElement('div');while($item->firstChild)$card->appendChild($item->firstChild);$container->appendChild($card);}$list->parentNode->replaceChild($container,$list);}
    }
    private function convertLayouts(\DOMDocument$dom,\DOMXPath$xpath,\DOMNode$root,string$compatibility,int$viewportWidth,array$rules,bool$layoutTables=true):void{if(!$layoutTables)return;foreach(array_reverse(iterator_to_array($xpath->query('.//*',$root)))as$container){if(!$container instanceof \DOMElement||!$container->parentNode)continue;$style=$this->effectiveStyle($container,$rules);if(!preg_match('/display\s*:\s*(?:inline-)?(flex|grid)\b/i',$style,$display))continue;if(strtolower($display[1])==='flex'&&preg_match('/flex-direction\s*:\s*column\b/i',$style))continue;$children=[];$hasText=false;foreach(iterator_to_array($container->childNodes)as$child){if($child instanceof \DOMElement)$children[]=$child;elseif($child instanceof \DOMText&&trim($child->textContent)!=='')$hasText=true;}if($hasText||count($children)<2)continue;$columns=strtolower($display[1])==='grid'?$this->gridColumns($style):count($children);$columns=max(1,min(count($children),$columns));$safe=$compatibility!=='html20'&&$columns<=max(1,(int)floor($viewportWidth/120))&&count($children)<=8;if(!$safe)continue;$table=$dom->createElement('table');$table->setAttribute('border','0');$table->setAttribute('cellpadding','2');foreach($children as$i=>$child){if($i%$columns===0)$row=$table->appendChild($dom->createElement('tr'));$cell=$row->appendChild($dom->createElement('td'));$cell->appendChild($child);}$container->parentNode->replaceChild($table,$container);}}
    private function ensureContrast(\DOMXPath$xpath,\DOMNode$root,string$documentBackground):void{foreach($xpath->query('.//font[@color]',$root)as$font){$background=$documentBackground;for($parent=$font->parentNode;$parent instanceof \DOMElement;$parent=$parent->parentNode)if($parent->hasAttribute('bgcolor')){$background=$parent->getAttribute('bgcolor');break;}$font->setAttribute('color',ColorContrast::readableForeground($font->getAttribute('color'),$background));}}
    private function renderedImageDimensions(\DOMElement$image,int$maxWidth,int$maxHeight):array
    {
        $sourceWidth=filter_var($image->getAttribute('width'),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        $sourceHeight=filter_var($image->getAttribute('height'),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if(!$sourceWidth||!$sourceHeight)return[$maxWidth,$maxHeight,false];
        $scale=min(1.0,$maxWidth/$sourceWidth,$maxHeight/$sourceHeight);
        return[max(1,(int)floor($sourceWidth*$scale)),max(1,(int)floor($sourceHeight*$scale)),true];
    }
    private function isLargeImage(int$width,int$height,int$viewportWidth):bool{return$width>=max(80,(int)floor($viewportWidth*.4))||$height>=80;}
    private function replaceImageWithLink(\DOMDocument$dom,\DOMElement$image,string$url,string$label):\DOMElement
    {
        $parent=$image->parentNode;if($parent instanceof \DOMElement&&strtolower($parent->tagName)==='a'){$meaningful=[];foreach(iterator_to_array($parent->childNodes)as$child)if(!($child instanceof \DOMText&&trim($child->textContent)===''))$meaningful[]=$child;if(count($meaningful)===1&&$meaningful[0]===$image){$parent->setAttribute('href',$url);$parent->replaceChild($dom->createTextNode($label),$image);return$parent;}$link=$dom->createElement('a',$label);$link->setAttribute('href',$url);$parent->parentNode?->insertBefore($link,$parent);$parent->removeChild($image);return$link;}$link=$dom->createElement('a',$label);$link->setAttribute('href',$url);$parent?->replaceChild($link,$image);return$link;
    }
    private function surroundWithBreaks(\DOMDocument$dom,\DOMElement$image):void
    {
        $block=$image->parentNode;while($block instanceof \DOMElement&&in_array(strtolower($block->tagName),['a','picture','span'],true))$block=$block->parentNode;
        if($block instanceof \DOMElement&&strtolower($block->tagName)==='p'&&($block->hasAttribute('data-rw-image-block')||trim($block->textContent)===''))return;
        $parent=$image->parentNode;if(!$parent)return;$previous=$image->previousSibling;while($previous instanceof \DOMText&&trim($previous->textContent)==='')$previous=$previous->previousSibling;if(!$previous instanceof \DOMElement||strtolower($previous->tagName)!=='br')$parent->insertBefore($dom->createElement('br'),$image);$next=$image->nextSibling;while($next instanceof \DOMText&&trim($next->textContent)==='')$next=$next->nextSibling;if(!$next instanceof \DOMElement||strtolower($next->tagName)!=='br')$parent->insertBefore($dom->createElement('br'),$image->nextSibling);
    }
    private function cssRules(\DOMXPath$xpath,\DOMNode$root):array
    {
        $rules=[];foreach($xpath->query('.//style',$root)as$style){$css=preg_replace('/\/\*.*?\*\//s','',$style->textContent);$previous=null;while($previous!==$css){$previous=$css;$css=preg_replace('/@(media|supports|container|keyframes)[^{]*\{(?:[^{}]*|\{[^{}]*\})*\}/is','',(string)$css);}if(!preg_match_all('/([^{}]+)\{([^{}]*)\}/s',(string)$css,$matches,PREG_SET_ORDER))continue;foreach($matches as$match)foreach(explode(',',$match[1])as$selector){$selector=trim($selector);if(preg_match('/^(?:[a-z][a-z0-9-]*|[.#][a-z0-9_-]+)$/i',$selector))$rules[$selector]=($rules[$selector]??'').';'.$match[2];}}return$rules;
    }
    private function effectiveStyle(\DOMElement$element,array$rules):string
    {
        $styles=[];$tag=strtolower($element->tagName);if(isset($rules[$tag]))$styles[]=$rules[$tag];$id=$element->getAttribute('id');if($id!==''&&isset($rules['#'.$id]))$styles[]=$rules['#'.$id];foreach(preg_split('/\s+/',trim($element->getAttribute('class')))?:[]as$class)if($class!==''&&isset($rules['.'.$class]))$styles[]=$rules['.'.$class];if($element->getAttribute('style')!=='')$styles[]=$element->getAttribute('style');return implode(';',$styles);
    }
    private function applyVisualOrder(\DOMXPath$xpath,\DOMNode$root,array$rules):void
    {
        foreach($xpath->query('.//*',$root)as$container){if(!$container instanceof \DOMElement)continue;$style=$this->effectiveStyle($container,$rules);if(!preg_match('/display\s*:\s*(?:inline-)?(?:flex|grid)\b/i',$style))continue;$items=[];$changed=preg_match('/flex-direction\s*:\s*(?:row|column)-reverse\b/i',$style)===1;foreach(iterator_to_array($container->childNodes)as$index=>$child)if($child instanceof \DOMElement){$childStyle=$this->effectiveStyle($child,$rules);$order=0;if(preg_match('/(?:^|;)\s*order\s*:\s*(-?[0-9]{1,3})/i',$childStyle,$match)){$order=(int)$match[1];$changed=true;}$items[]=['node'=>$child,'index'=>$index,'order'=>$order];}if(!$changed)continue;usort($items,static fn(array$a,array$b):int=>($a['order']<=>$b['order'])?:($a['index']<=>$b['index']));if(preg_match('/flex-direction\s*:\s*(?:row|column)-reverse\b/i',$style))$items=array_reverse($items);foreach($items as$item)$container->appendChild($item['node']);}
    }
    private function applyClassicStyle(\DOMDocument$dom,\DOMElement$element,string$tag,string$style):void
    {
        if(preg_match('/(?:^|;)\s*text-align\s*:\s*(left|center|right|justify)/i',$style,$match)&&in_array($tag,['p','h1','h2','h3','h4','h5','h6','table','tr','td','th','caption'],true))$element->setAttribute('align',strtolower($match[1]));
        if(preg_match('/(?:^|;)\s*vertical-align\s*:\s*(top|middle|bottom|baseline)/i',$style,$match)&&in_array($tag,['tr','td','th'],true))$element->setAttribute('valign',strtolower($match[1]));
        foreach(['width'=>'width','height'=>'height']as$property=>$attribute)if(preg_match('/(?:^|;)\s*'.$property.'\s*:\s*([0-9]{1,5})(px|%)/i',$style,$match)&&in_array($tag,['table','td','th'],true))$element->setAttribute($attribute,$match[1].($match[2]==='%'?'%':''));
        if(preg_match('/(?:^|;)\s*background-color\s*:\s*(#[0-9a-f]{3,6}|[a-z]{1,20})/i',$style,$match)&&in_array($tag,['table','tr','th','td'],true))$element->setAttribute('bgcolor',$match[1]);
        if(preg_match('/(?:^|;)\s*border(?:-[a-z]+)?\s*:[^;]*(?:solid|double|[1-9][0-9]*px)/i',$style)&&$tag==='table')$element->setAttribute('border','1');
        if(preg_match('/(?:^|;)\s*padding\s*:\s*([0-9]{1,2})px/i',$style,$match)&&$tag==='table')$element->setAttribute('cellpadding',$match[1]);
        $wrappers=[];if(preg_match('/(?:^|;)\s*font-weight\s*:\s*(?:bold|[6-9]00)/i',$style))$wrappers[]='b';if(preg_match('/(?:^|;)\s*font-style\s*:\s*italic/i',$style))$wrappers[]='i';if(preg_match('/(?:^|;)\s*text-decoration(?:-line)?\s*:[^;]*underline/i',$style))$wrappers[]='u';if(preg_match('/(?:^|;)\s*color\s*:\s*(#[0-9a-f]{3,6}|[a-z]{1,20})/i',$style,$color)){if($tag==='font')$element->setAttribute('color',$color[1]);else{$font=$dom->createElement('font');$font->setAttribute('color',ColorContrast::readableForeground($color[1],'#ffffff'));$wrappers[]=$font;}}
        foreach($wrappers as$wrapper){$node=is_string($wrapper)?$dom->createElement($wrapper):$wrapper;while($element->firstChild)$node->appendChild($element->firstChild);$element->appendChild($node);}
    }
    private function gridColumns(string$style):int{if(!preg_match('/grid-template-columns\s*:\s*([^;]+)/i',$style,$match))return 2;if(preg_match('/repeat\(\s*([1-4])\s*,/i',$match[1],$repeat))return(int)$repeat[1];return max(1,min(4,count(preg_split('/\s+/',trim($match[1]))?:[])));}
}
