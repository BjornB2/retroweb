<?php
declare(strict_types=1);
namespace RetroWEB\Rendering\Extraction;
use RetroWEB\Domain\Document\Fragment;
use RetroWEB\Domain\Document\LegacyDocument;

final class ArticleExtractionStrategy implements ExtractionStrategy
{
    public function extract(string $html, string $sourceUrl): LegacyDocument
    {
        $dom = new \DOMDocument('1.0', 'UTF-8'); libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET); libxml_clear_errors();
        if (!$loaded) return new LegacyDocument($sourceUrl, [new Fragment(htmlspecialchars(strip_tags($html), ENT_QUOTES, 'UTF-8'))]);
        $xpath = new \DOMXPath($dom); $title = trim((string)($xpath->query('//title')->item(0)->textContent ?? '')) ?: $sourceUrl;
        $candidates = $xpath->query('//article | //main | //*[@role="main"]');
        $root = $candidates->length ? $candidates->item(0) : ($dom->getElementsByTagName('body')->item(0) ?: $dom->documentElement);
        $classification = $xpath->query('//article')->length ? 'article' : ($xpath->query('//main//section')->length >= 2 ? 'overview' : 'generic');
        $fragments = [];
        foreach (iterator_to_array($root->childNodes) as $node) {
            $fragment = trim($dom->saveHTML($node)); if ($fragment !== '') $fragments[] = new Fragment($fragment);
        }
        if (!$fragments) $fragments[] = new Fragment('<p>' . htmlspecialchars(trim($root->textContent), ENT_QUOTES, 'UTF-8') . '</p>');
        return new LegacyDocument($title, $fragments, $classification);
    }
}
