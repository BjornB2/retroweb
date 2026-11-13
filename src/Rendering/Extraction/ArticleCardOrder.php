<?php
declare(strict_types=1);
namespace RetroWEB\Rendering\Extraction;

final class ArticleCardOrder
{
    public function apply(string$html):string
    {
        $dom=new \DOMDocument('1.0','UTF-8');libxml_use_internal_errors(true);$ok=$dom->loadHTML('<?xml encoding="UTF-8">'.$html,LIBXML_NONET);libxml_clear_errors();if(!$ok)return$html;
        $xp=new \DOMXPath($dom);$cards=$xp->query('//article[not(ancestor::article)] | //div[contains(concat(" ",normalize-space(@class)," ")," card ") and not(ancestor::article)]');
        $eligible=[];
        foreach($cards as$card){
            if($xp->query('.//article|.//h1|.//figcaption',$card)->length)continue;
            $heads=$xp->query('.//h2|.//h3',$card);$images=$xp->query('.//img',$card);
            if($heads->length!==1||$images->length!==1||mb_strlen(trim($card->textContent))>1800)continue;
            $heading=$heads->item(0);$link=$this->anchor($heading,$card);if(!$link)$link=$xp->query('.//a[@href]',$heading)->item(0);if(!$link)continue;
            $eligible[]=[$card,$heading,$images->item(0),$link];
        }
        // A lone article is not evidence of an overview or repeated card layout.
        if(count($eligible)<2)return$html;
        foreach($eligible as[$card,$heading,$image,$link]){
            $title=$heading->cloneNode(true);
            if(!$xp->query('.//a',$title)->length){$a=$dom->createElement('a');$a->setAttribute('href',$link->getAttribute('href'));while($title->firstChild)$a->appendChild($title->firstChild);$title->appendChild($a);}
            $heading->parentNode->removeChild($heading);
            $category=null;
            foreach($xp->query('.//*[@rel="tag" or @class]',$card)as$node){if(!preg_match('/(?:^|[\s_-])(?:category|categories|tag|tags)(?:$|[\s_-])/i',$node->getAttribute('class'))&&$node->getAttribute('rel')!=='tag')continue;if($xp->query('.//img|.//h2|.//h3|.//p',$node)->length||mb_strlen(trim($node->textContent))>80||trim($node->textContent)==='')continue;$category=$node;break;}
            $categoryCopy=$category?$category->cloneNode(true):null;if($category)$category->parentNode->removeChild($category);
            $asset=$image;for($p=$image->parentNode;$p&&$p!==$card;$p=$p->parentNode)if($p instanceof \DOMElement&&in_array(strtolower($p->tagName),['picture','figure'],true))$asset=$p;
            $imageLink=$this->anchor($asset,$card);$assetCopy=$asset->cloneNode(true);
            if($imageLink){$a=$dom->createElement('a');$a->setAttribute('href',$imageLink->getAttribute('href'));$a->appendChild($assetCopy);$assetCopy=$a;}
            $asset->parentNode->removeChild($asset);
            if($categoryCopy){$title->appendChild($dom->createElement('br'));$small=$dom->createElement('small');$categoryLink=strtolower($categoryCopy->nodeName)==='a'?$categoryCopy:$xp->query('.//a[@href]',$categoryCopy)->item(0);if($categoryLink){$a=$dom->createElement('a');$a->setAttribute('href',$categoryLink->getAttribute('href'));$a->appendChild($dom->createTextNode(trim($categoryCopy->textContent)));$small->appendChild($a);}else$small->appendChild($dom->createTextNode(trim($categoryCopy->textContent)));$title->appendChild($small);}
            $imageBlock=$dom->createElement('p');$imageBlock->appendChild($assetCopy);
            $before=$card->firstChild;$card->insertBefore($title,$before);$card->insertBefore($imageBlock,$before);
        }
        $result=$dom->saveHTML();return is_string($result)?preg_replace('/<\?xml[^>]*>\s*/','',$result):$html;
    }
    private function anchor(\DOMNode$node,\DOMNode$boundary):?\DOMElement
    {
        for($p=$node->parentNode;$p&&$p!==$boundary;$p=$p->parentNode)if($p instanceof \DOMElement&&strtolower($p->tagName)==='a'&&$p->hasAttribute('href'))return$p;
        return null;
    }
}
