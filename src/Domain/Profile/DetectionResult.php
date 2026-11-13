<?php
declare(strict_types=1);
namespace RetroWEB\Domain\Profile;

final class DetectionResult
{
    public function __construct(
        public string $profileId,
        public bool $fixed,
        public string $status,
        public string $reason
    ) {
        if ($fixed && $status !== 'verified') {
            throw new \InvalidArgumentException('Only verified signatures may select a fixed profile');
        }
    }
}
