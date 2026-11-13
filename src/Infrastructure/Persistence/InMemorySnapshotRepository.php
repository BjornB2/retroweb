<?php
declare(strict_types=1);
namespace RetroWEB\Infrastructure\Persistence;

final class InMemorySnapshotRepository implements SnapshotRepository
{
    private array $items = [];
    public function put(string $sourceUrl, array $parts, int $expiresAt, string $renderVersion, string $encoding='UTF-8'): Snapshot
    {
        $token = hash('sha256', random_bytes(32));
        return $this->items[$token] = new Snapshot($token, $sourceUrl, $parts, $expiresAt, $renderVersion,$encoding);
    }
    public function get(string $token, int $now, string $renderVersion): ?Snapshot
    {
        $item = $this->items[$token] ?? null;
        if (!$item || $item->expiresAt < $now || !hash_equals($item->renderVersion, $renderVersion)) return null;
        return $item;
    }
}
