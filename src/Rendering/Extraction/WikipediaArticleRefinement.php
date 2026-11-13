<?php
declare(strict_types=1);
namespace RetroWEB\Rendering\Extraction;

final class WikipediaArticleRefinement
{
    public function appliesTo(string $sourceUrl): bool
    {
        $host=strtolower((string)parse_url($sourceUrl,PHP_URL_HOST));
        return $host==='wikipedia.org'||str_ends_with($host,'.wikipedia.org');
    }

    public function removeInterfaceNoise(\DOMXPath $xpath,\DOMNode $root): void
    {
        $query='.//*[@id="p-lang-btn" or @id="p-lang" or @id="p-lang-btn-sticky-header"'
            .' or contains(concat(" ", normalize-space(@class), " "), " mw-portlet-lang ")'
            .' or contains(concat(" ", normalize-space(@class), " "), " interlanguage-link ")]';
        $nodes=[];foreach($xpath->query($query,$root)?:[]as$node)$nodes[]=$node;
        foreach($nodes as$node)if($node->parentNode)$node->parentNode->removeChild($node);
    }
}
