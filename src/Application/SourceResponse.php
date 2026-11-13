<?php
declare(strict_types=1);
namespace RetroWEB\Application;
final class SourceResponse { public function __construct(public string $body, public string $finalUrl, public int $status = 200, public array $headers = []) {} }
