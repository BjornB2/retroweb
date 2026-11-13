<?php
declare(strict_types=1);
namespace RetroWEB\Infrastructure\Http;

final class UrlPolicy
{
    public function validate(string $url): string
    {
        $p = parse_url($url);
        if (!$p || !isset($p['scheme'],$p['host']) || !in_array(strtolower($p['scheme']), ['http','https'], true)) throw new \DomainException('Only HTTP and HTTPS sources are supported');
        if (isset($p['user']) || isset($p['pass'])) throw new \DomainException('URL credentials are forbidden');
        $scheme = strtolower($p['scheme']); $port = $p['port'] ?? ($scheme === 'https' ? 443 : 80);
        if (($scheme === 'http' && $port !== 80) || ($scheme === 'https' && $port !== 443)) throw new \DomainException('Source port is forbidden');
        $host = trim(strtolower($p['host']), '[]');
        if ($host === 'localhost' || str_ends_with($host, '.localhost')) throw new \DomainException('Private target is forbidden');
        if (filter_var($host, FILTER_VALIDATE_IP) && !$this->publicIp($host)) throw new \DomainException('Private target is forbidden');
        return $url;
    }
    public function assertResolvedAddresses(array $addresses): void
    {
        if (!$addresses) throw new \DomainException('Target did not resolve');
        foreach ($addresses as $ip) if (!$this->publicIp((string)$ip)) throw new \DomainException('Unsafe resolved address');
    }
    private function publicIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
}
