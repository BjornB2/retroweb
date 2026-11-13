<?php
declare(strict_types=1);
namespace RetroWEB\Domain\Profile;

final class ProfileDetector
{
    public function __construct(private array $signatures) {}

    public function detect(string $userAgent): DetectionResult
    {
        $matches = [];
        foreach ($this->signatures as $signature) {
            if (($signature['status'] ?? '') !== 'verified') continue;
            if (@preg_match($signature['pattern'] ?? '', $userAgent) === 1) $matches[] = $signature;
        }
        $ids = array_values(array_unique(array_column($matches, 'profile')));
        if (count($ids) !== 1) {
            return new DetectionResult('generic', false, $ids ? 'ambiguous' : 'unmatched', $ids ? 'Multiple profiles matched' : 'No verified signature matched');
        }
        return new DetectionResult($ids[0], true, 'verified', (string)($matches[0]['provenance'] ?? 'verified signature'));
    }
}
