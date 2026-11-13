<?php
declare(strict_types=1);
namespace RetroWEB\Rendering;
final class NavigationBar
{
    public static function render(string$profile,string$home,string$report,string$settings,string$statistics,string$reload='',?string$mode=null,string$inline='',string$links=''):string
    {
        $h=static fn(string$s):string=>htmlspecialchars($s,ENT_QUOTES,'UTF-8');
        $items=['<a href="'.$h($home).'">RetroWEB search</a>',$h($profile).' <a href="'.$h($settings).'">[Change profile]</a>'];
        if($reload!=='')$items[]='<a href="'.$h($reload).'">Reload page</a>';
        if($mode!==null)$items[]='Images: <a href="'.$h($inline).'">'.($mode==='inline'?'<b>Inline</b>':'Inline').'</a> / <a href="'.$h($links).'">'.($mode==='links'?'<b>As links</b>':'As links').'</a>';
        return'<p><small>'.implode(' | ',$items).'</small></p><hr>';
    }
    public static function spaceTest():string{return'<h2>Space test</h2><p>Each bracketed pair should stay together except the normal-space pair.</p><p>Normal: [ONE TWO] [ONE TWO]<br>Named: [ONE&nbsp;TWO] [ONE&nbsp;TWO]<br>Numeric: [ONE&#160;TWO] [ONE&#160;TWO]</p>';}
}
