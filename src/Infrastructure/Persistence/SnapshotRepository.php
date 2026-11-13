<?php
declare(strict_types=1);
namespace RetroWEB\Infrastructure\Persistence;

interface SnapshotRepository
{
    public function put(string $sourceUrl, array $parts, int $expiresAt, string $renderVersion, string $encoding='UTF-8'): Snapshot;
    public function get(string $token, int $now, string $renderVersion): ?Snapshot;
}
