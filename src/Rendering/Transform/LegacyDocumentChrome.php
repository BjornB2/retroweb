<?php
declare(strict_types=1);
namespace RetroWEB\Rendering\Transform;

final class LegacyDocumentChrome
{
    public static function inject(string$html,string$top,string$bottom=''):string
    {
        $withTop=preg_replace_callback('/<body\b[^>]*>/i',static fn(array$match):string=>$match[0].$top,$html,1,$count);
        if($count===0)$withTop=$top.$html;
        if($bottom==='')return(string)$withTop;
        $withBottom=preg_replace('/<\/body>/i',$bottom.'</body>',(string)$withTop,1,$bottomCount);
        return$bottomCount>0?(string)$withBottom:(string)$withTop.$bottom;
    }
}
