<?php
declare(strict_types=1);
namespace RetroWEB\Infrastructure\Http;
interface HttpTransport { public function request(string $url, string $method, ?string $body, array $addresses, int $maxBytes): HttpResponse; }
