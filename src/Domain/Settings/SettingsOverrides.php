<?php
declare(strict_types=1);
namespace RetroWEB\Domain\Settings;

final class SettingsOverrides
{
    private const ALLOWED = ['html','viewport_width','viewport_height','image_width','image_height','delivery','page_budget_kb'];
    public function __construct(public array $values = [], public ?string $imageMode = null, public ?string $selectedProfile = null)
    {
        if (array_diff(array_keys($values), self::ALLOWED)) throw new \InvalidArgumentException('Unknown settings override');
        if ($imageMode !== null && !in_array($imageMode, ['inline','links'], true)) throw new \InvalidArgumentException('Invalid image mode');
        if ($selectedProfile !== null && !preg_match('/^[a-z0-9_]{1,40}$/', $selectedProfile)) throw new \InvalidArgumentException('Invalid selected profile');
    }
}
