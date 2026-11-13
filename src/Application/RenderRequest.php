<?php
declare(strict_types=1);
namespace RetroWEB\Application;

final class RenderRequest
{
    public function __construct(public string $sourceUrl, public string $transport, public string $method = 'GET', public array $fields = [], public ?string $snapshotToken = null, public int $part = 1, public bool $forceRefetch = false, public bool $entire = false, public string $userAgent = '', public ?string $clientIdentity = null) {}
}
