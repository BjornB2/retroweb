<?php
declare(strict_types=1);
namespace RetroWEB\Rendering\Transform;
final class LegacyDoctype
{
    public static function apply(string$html,string$compatibility):string
    {
        $declaration=match($compatibility){
            'html20'=>'<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">',
            'html32'=>'<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 3.2 Final//EN">',
            'html401-basic'=>'<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">',
            default=>throw new \InvalidArgumentException('Unknown HTML compatibility'),
        };
        $html=preg_replace('/^\s*<!DOCTYPE[^>]*>\s*/i','',$html)??$html;
        return$declaration."\n".$html;
    }
}
