<?php
declare(strict_types=1);
namespace RetroWEB\Domain\Settings;
use RetroWEB\Domain\Profile\BrowserProfile;

final class SettingsInput
{
    public function parse(array $input,BrowserProfile $base,?string $imageMode=null,?string $selectedProfile=null):SettingsOverrides
    {
        $allowed=['html','viewport_width','viewport_height','image_width','image_height','delivery','page_budget_kb'];$values=[];
        foreach($allowed as $key){if(!array_key_exists($key,$input))continue;$value=$input[$key];if(in_array($key,['viewport_width','viewport_height','image_width','image_height','page_budget_kb'],true)){if(!is_scalar($value)||!preg_match('/^[0-9]+$/',(string)$value))throw new \InvalidArgumentException('Invalid numeric setting');$value=(int)$value;}else{$value=(string)$value;}$values[$key]=$value;}
        // Validate the complete candidate before persisting it. Previously a
        // value such as 10000 kB was saved and only failed on the next request,
        // leaving that browser with an unrecoverable blank HTTP 500 page.
        (new SettingsResolver())->resolve($base, new SettingsOverrides($values, $imageMode), true);
        $defaults=['html'=>$base->html,'viewport_width'=>$base->viewportWidth,'viewport_height'=>$base->viewportHeight,'image_width'=>$base->imageWidth,'image_height'=>$base->imageHeight,'delivery'=>$base->delivery,'page_budget_kb'=>$base->pageBudgetKb];
        foreach($values as $key=>$value)if($defaults[$key]===$value)unset($values[$key]);
        return new SettingsOverrides($values,$imageMode,$selectedProfile);
    }
}
