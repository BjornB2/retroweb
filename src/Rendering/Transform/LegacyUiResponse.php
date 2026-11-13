<?php
declare(strict_types=1);
namespace RetroWEB\Rendering\Transform;
use RetroWEB\Domain\Profile\BrowserProfile;

final class LegacyUiResponse
{
    public static function encode(string$html,BrowserProfile$profile):string
    {
        $html=LegacyDoctype::apply($html,$profile->html);
        $html=LegacyOutputWorkarounds::apply($html,$profile->workarounds);
        return strcasecmp($profile->encoding,'UTF-8')===0?$html:(new OutputEncoder())->encode($html,$profile->encoding);
    }
}
