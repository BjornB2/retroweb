<?php
declare(strict_types=1);
namespace RetroWEB\Application;
final class RenderResult { public function __construct(public string $body, public string $sourceUrl, public ?string $snapshotToken, public int $part, public int $partCount, public bool $fromSnapshot, public string $encoding='UTF-8') {} }
