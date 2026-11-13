<?php
declare(strict_types=1);
namespace RetroWEB\Rendering\Transform;

final class LegacyOutputWorkarounds
{
    public static function apply(string$html,array$workarounds):string
    {
        if(($workarounds['raw_url_ampersands']??false)!==true)return$html;
        return preg_replace_callback('/\b(href|src|action)=("|\')(.*?)\2/is',static fn(array$m):string=>$m[1].'='.$m[2].str_replace('&amp;','&',$m[3]).$m[2],$html)??$html;
    }
}
