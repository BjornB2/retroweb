<?php
declare(strict_types=1);
namespace RetroWEB\Domain\Settings;
use RetroWEB\Domain\Profile\BrowserProfile;

final class SettingsResolver
{
    public function resolve(BrowserProfile $profile, SettingsOverrides $saved, bool $custom, array $temporary = []): EffectiveSettings
    {
        $base = ['html'=>$profile->html,'viewport_width'=>$profile->viewportWidth,'viewport_height'=>$profile->viewportHeight,
            'image_width'=>$profile->imageWidth,'image_height'=>$profile->imageHeight,'delivery'=>$profile->delivery,
            'page_budget_kb'=>$profile->pageBudgetKb,'image_mode'=>$profile->imageMode];
        $values = $custom ? array_replace($base, $saved->values) : $base;
        if ($saved->imageMode !== null) $values['image_mode'] = $saved->imageMode;
        $values = array_replace($values, array_intersect_key($temporary, ['image_mode'=>true]));
        $this->validate($values);
        return new EffectiveSettings($values);
    }
    private function validate(array $v): void
    {
        if (!in_array($v['html'], ['html20','html32','html401-basic'], true) || !in_array($v['delivery'], ['paginated','unsplit'], true) || !in_array($v['image_mode'], ['inline','links'], true)) throw new \InvalidArgumentException('Invalid settings');
        foreach (['viewport_width','viewport_height','image_width','image_height'] as $key) if (!is_int($v[$key]) || $v[$key] < 1 || $v[$key] > 4096) throw new \InvalidArgumentException('Invalid dimension');
        if (!is_int($v['page_budget_kb']) || $v['page_budget_kb'] < 10 || $v['page_budget_kb'] > 2048) throw new \InvalidArgumentException('Invalid page budget');
    }
}
