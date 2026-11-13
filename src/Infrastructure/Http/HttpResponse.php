<?php
declare(strict_types=1);
namespace RetroWEB\Infrastructure\Http;
final class HttpResponse { public function __construct(public int $status, public string $body, public array $headers = []) {} public function header(string $name): ?string { foreach ($this->headers as $key=>$value) if (strcasecmp((string)$key, $name) === 0){$values=(array)$value;return$values?(string)end($values):null;} return null; } }
