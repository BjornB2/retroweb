<?php
declare(strict_types=1);
namespace RetroWEB\Infrastructure\Persistence;

final class Snapshot
{
    public function __construct(public string $token, public string $sourceUrl, public array $parts, public int $expiresAt, public string $renderVersion, public string $encoding='UTF-8') {}
}
