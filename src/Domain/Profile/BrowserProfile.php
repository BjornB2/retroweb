<?php
declare(strict_types=1);
namespace RetroWEB\Domain\Profile;

final class BrowserProfile
{
    public function __construct(
        public string $id, public int $version,
        public string $label, public string $html,
        public string $encoding, public int $viewportWidth,
        public int $viewportHeight, public int $imageWidth,
        public int $imageHeight, public int $pageBudgetKb,
        public string $delivery = 'paginated',
        public string $imageMode = 'inline', public array $capabilities = [],public array$workarounds=[]
    ) {
        if (!in_array($html, ['html20','html32','html401-basic'], true)) throw new \InvalidArgumentException('Unsupported HTML compatibility');
        if (!in_array($delivery, ['paginated','unsplit'], true)) throw new \InvalidArgumentException('Unsupported delivery mode');
        if (!in_array($imageMode, ['inline','links'], true)) throw new \InvalidArgumentException('Unsupported image mode');
        foreach ([$viewportWidth,$viewportHeight,$imageWidth,$imageHeight,$pageBudgetKb] as $number) if ($number < 1) throw new \InvalidArgumentException('Profile dimensions and budget must be positive');
    }
}
