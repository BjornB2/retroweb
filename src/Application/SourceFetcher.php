<?php
declare(strict_types=1);
namespace RetroWEB\Application;
interface SourceFetcher { public function fetch(string $url, string $method, array $fields): SourceResponse; }
