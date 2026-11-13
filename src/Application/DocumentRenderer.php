<?php
declare(strict_types=1);
namespace RetroWEB\Application;
interface DocumentRenderer { public function render(string $html, string $sourceUrl, RenderRequest $request): array; public function encoding(RenderRequest $request): string; }
