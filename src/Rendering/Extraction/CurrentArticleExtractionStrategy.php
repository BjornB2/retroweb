<?php
declare(strict_types=1);
namespace RetroWEB\Rendering\Extraction;
use RetroWEB\Domain\Document\{LegacyDocument,Fragment};

final class CurrentArticleExtractionStrategy implements ExtractionStrategy
{
    public function extract(string$html,string$sourceUrl):LegacyDocument
    {
        $html=(new ArticleCardOrder())->apply($html);
        $search=new DuckDuckGoSearchResults();if($search->appliesTo($sourceUrl))return$search->extract($html,$sourceUrl);
        if(!class_exists('\RetroWEB\CustomDOMExtractor',false))require_once dirname(__DIR__,2).'/ContentExtractor.php';$result=(new \RetroWEB\CustomDOMExtractor())->extract($html,$sourceUrl);if(!is_array($result)||!is_string($result['content']??null))return(new ArticleExtractionStrategy())->extract($html,$sourceUrl);$classification=\RetroWEB\DocumentAnalyzer::analyse($html)['classification']??'generic';$dom=new \DOMDocument('1.0','UTF-8');libxml_use_internal_errors(true);$ok=$dom->loadHTML('<?xml encoding="UTF-8"><div id="rw-extracted">'.$result['content'].'</div>',LIBXML_NONET);libxml_clear_errors();if(!$ok)return new LegacyDocument((string)($result['title']??$sourceUrl),[new Fragment($result['content'])],$classification);$xpath=new \DOMXPath($dom);$root=$xpath->query('//*[@id="rw-extracted"]')->item(0);$wikipedia=new WikipediaArticleRefinement();if($root&&$wikipedia->appliesTo($sourceUrl))$wikipedia->removeInterfaceNoise($xpath,$root);$fragments=[];foreach(iterator_to_array($root->childNodes)as$node){$fragment=trim($dom->saveHTML($node));if($fragment!=='')$fragments[]=new Fragment($fragment);}return new LegacyDocument(trim((string)($result['title']??''))?:$sourceUrl,$fragments?:[new Fragment($result['content'])],$classification);
    }
}
