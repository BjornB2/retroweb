<?php
declare(strict_types=1);
namespace RetroWEB\Rendering\Transform;

final class ColorContrast
{
    private const NAMES=['black'=>'#000000','white'=>'#ffffff','red'=>'#ff0000','green'=>'#008000','blue'=>'#0000ff','yellow'=>'#ffff00','gray'=>'#808080','grey'=>'#808080','navy'=>'#000080','silver'=>'#c0c0c0'];
    public static function readableForeground(string$foreground,string$background):string{$fg=self::rgb($foreground);$bg=self::rgb($background);if($fg===null||$bg===null)return$foreground;if(self::ratio($fg,$bg)>=3.0)return$foreground;$black=[0,0,0];$white=[255,255,255];return self::ratio($black,$bg)>=self::ratio($white,$bg)?'#000000':'#ffffff';}
    private static function rgb(string$value):?array{$value=strtolower(trim($value));$value=self::NAMES[$value]??$value;if(preg_match('/^#([0-9a-f]{3})$/',$value,$m))$value='#'.$m[1][0].$m[1][0].$m[1][1].$m[1][1].$m[1][2].$m[1][2];if(!preg_match('/^#([0-9a-f]{6})$/',$value,$m))return null;return[hexdec(substr($m[1],0,2)),hexdec(substr($m[1],2,2)),hexdec(substr($m[1],4,2))];}
    private static function luminance(array$rgb):float{$values=array_map(static function(int$value):float{$v=$value/255;return$v<=.03928?$v/12.92:(($v+.055)/1.055)**2.4;},$rgb);return.2126*$values[0]+.7152*$values[1]+.0722*$values[2];}
    private static function ratio(array$a,array$b):float{$l1=self::luminance($a);$l2=self::luminance($b);return(max($l1,$l2)+.05)/(min($l1,$l2)+.05);}
}
