<?php
declare(strict_types=1);
namespace RetroWEB\Domain\Settings;

final class EffectiveSettings
{
    public function __construct(public array $values) {}
}
