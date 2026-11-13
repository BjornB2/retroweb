<?php
declare(strict_types=1);
namespace RetroWEB\Domain\Navigation;

final class NavigationContext
{
    public function __construct(public string $sourceUrl, public string $transport, public ?string $snapshotToken = null, public int $part = 1, public ?string $anchor = null)
    {
        if (!in_array($transport, ['direct','forward'], true) || $part < 1) throw new \InvalidArgumentException('Invalid navigation context');
    }
}
