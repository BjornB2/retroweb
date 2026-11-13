<?php
declare(strict_types=1);
namespace RetroWEB\Domain\Document;

final class Fragment
{
    public function __construct(public string $html, public int $decodedImageBytes = 0, public ?string $imageLabel = null, public ?string $memoryFallbackHtml = null)
    {
        if ($decodedImageBytes < 0) throw new \InvalidArgumentException('Negative image cost');
    }
    public function cost(): int { return strlen($this->html) + $this->decodedImageBytes; }
}
