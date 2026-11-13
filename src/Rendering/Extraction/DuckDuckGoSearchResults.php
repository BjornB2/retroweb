<?php
declare(strict_types=1);
namespace RetroWEB\Rendering\Extraction;

use RetroWEB\Domain\Document\{Fragment,LegacyDocument};

final class DuckDuckGoSearchResults
{
    public function appliesTo(string $sourceUrl): bool
    {
        $parts=parse_url($sourceUrl);$host=strtolower((string)($parts['host']??''));
        return ($host==='duckduckgo.com'||str_ends_with($host,'.duckduckgo.com'))&&str_starts_with((string)($parts['path']??''),'/html');
    }

    public function extract(string $html,string $sourceUrl): LegacyDocument
    {
        $parts=parse_url($sourceUrl);parse_str((string)($parts['query']??''),$queryValues);$query=trim((string)($queryValues['q']??''));
        $heading='Search results'.($query!==''?' for: '.$query:'');$escape=static fn(string$value):string=>htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $dom=new \DOMDocument('1.0','UTF-8');libxml_use_internal_errors(true);$loaded=$dom->loadHTML('<?xml encoding="UTF-8">'.$html,LIBXML_NONET);libxml_clear_errors();
        $items='';$seen=[];$count=0;
        if($loaded){$xpath=new \DOMXPath($dom);$links=$xpath->query('//a[contains(concat(" ",normalize-space(@class)," ")," result__a ")]');foreach($links?:[]as$link){if(!$link instanceof \DOMElement)continue;$href=html_entity_decode(trim($link->getAttribute('href')),ENT_QUOTES|ENT_HTML5,'UTF-8');$title=trim(preg_replace('/\s+/u',' ',$link->textContent));if($href===''||$title===''||isset($seen[$href]))continue;$seen[$href]=true;$container=$link;while($container->parentNode instanceof \DOMElement&&!str_contains(' '.$container->getAttribute('class').' ',' result '))$container=$container->parentNode;$snippet='';if($container instanceof \DOMElement){$snippetNode=$xpath->query('.//*[contains(concat(" ",normalize-space(@class)," ")," result__snippet ")]',$container)->item(0);if($snippetNode)$snippet=trim(preg_replace('/\s+/u',' ',$snippetNode->textContent));}$items.='<li><p><b><a href="'.$escape($href).'">'.$escape($title).'</a></b></p>'.($snippet!==''?'<p>'.$escape($snippet).'</p>':'').'</li>';if(++$count>=30)break;}}
        $body='<h1>'.$escape($heading).'</h1>'.($items!==''?'<ol>'.$items.'</ol>':'<p>No results found. Try a different search.</p>');
        return new LegacyDocument($heading,[new Fragment($body)],'search-results');
    }
}
