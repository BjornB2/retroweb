<?php
declare(strict_types=1);
namespace RetroWEB\Rendering\Extraction;
use RetroWEB\Domain\Document\LegacyDocument;
interface ExtractionStrategy { public function extract(string $html, string $sourceUrl): LegacyDocument; }
