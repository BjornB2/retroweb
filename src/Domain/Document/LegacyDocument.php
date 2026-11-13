<?php
declare(strict_types=1);
namespace RetroWEB\Domain\Document;

final class LegacyDocument
{
    public function __construct(public string $title, public array $fragments, public string $classification = 'generic')
    {
        foreach ($fragments as $fragment) if (!$fragment instanceof Fragment) throw new \InvalidArgumentException('LegacyDocument fragments must be typed');
    }
}
