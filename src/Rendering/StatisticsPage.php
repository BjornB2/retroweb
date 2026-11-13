<?php
declare(strict_types=1);
namespace RetroWEB\Rendering;

final class StatisticsPage
{
    public function render(array $rows,string $returnUrl,string $clientInfoUrl,string $heading='RetroWEB statistics'):string
    {
        $values=[];foreach($rows as$row)$values[(string)$row['metric']][(string)$row['bucket']]=(int)$row['count'];
        $h=static fn(string$value):string=>htmlspecialchars($value,ENT_QUOTES,'UTF-8');
        $sum=static fn(array$value):int=>array_sum($value);
        $navigation=$returnUrl!==''&&$clientInfoUrl!==''?'<p><small><a href="'.$h($returnUrl).'">Return</a> | <a href="'.$h($clientInfoUrl).'">Client info</a> | Statistics</small></p><hr>':'';
        $html='<html><head><title>RetroWEB statistics</title></head><body>'.$navigation.'<h1>'.$h($heading).'</h1>';
        $visits=$values['visits']??[];$pageviews=$values['pageviews']??[];$pageviewTotal=$sum($pageviews)+$sum($values['proxy_requests']??[]);if($visits||$pageviewTotal>0){$html.='<h2>Visits</h2>';if($pageviewTotal>0)$html.='<p>Total pageviews: <strong>'.number_format($pageviewTotal,0,'.',',').'</strong></p>';if($visits)$html.='<p>Approximate visits: <strong>'.number_format($sum($visits),0,'.',',').'</strong></p>';}
        $pages=$values['pages']??[];$results=$values['page_results']??[];if(isset($results['legacy_success'])){$results['success']=($results['success']??0)+$results['legacy_success'];unset($results['legacy_success']);}
        $html.='<h2>Pages</h2>';
        if($results)$html.=$this->list($results,['success'=>'Successfully rendered','rejected'=>'Rejected for safety or invalid input','error'=>'Could not be rendered'],['success','rejected','error']);
        if($pages){arsort($pages);$html.='<h3>Page renders by browser profile</h3>'.$this->list($pages,['pocket_ie401'=>'Pocket IE 4.01 (240x320)','nintendo_ds'=>'Nintendo DS / DS Lite Browser','nintendo_dsi'=>'Nintendo DSi Browser','generic'=>'Generic legacy browser','macweb'=>'MacWeb 2.0 on Macintosh','netscape2_mac'=>'Netscape Navigator 2.02 on Macintosh','nokia9210'=>'Nokia 9210/9210i','nokia9500'=>'Nokia 9500','psion5mx'=>'Psion Series 5mx','psion_revo'=>'Psion Revo','palm'=>'Palm Web browser','palm_rbrowser'=>'RBrowser on Palm','palm_iiic'=>'RBrowser on Palm IIIc','palm_tungsten_t'=>'RBrowser on Palm Tungsten T']);}
        $images=$values['images']??[];if($images){$html.='<h2>Images</h2>'.$this->list($images,['success'=>'Images delivered through the web proxy','error'=>'Images unavailable','200'=>'Images delivered through the forwarding proxy'],['success','200','error']);}
        $timings=$values['processing_ms']??[];if($timings){$html.='<h2>Page processing time</h2>'.$this->list($timings,['under_250'=>'Under 0.25 seconds','250_999'=>'0.25 to 1 second','1000_2999'=>'1 to 3 seconds','3000_plus'=>'3 seconds or longer'],['under_250','250_999','1000_2999','3000_plus']);}
        $directBytes=$sum($values['direct_bytes']??[]);$imageBytes=$sum($values['image_bytes']??[]);$proxyBytes=$sum($values['proxy_bytes']??[]);$totalBytes=$directBytes+$imageBytes+$proxyBytes;if($totalBytes>0)$html.='<h2>Data transferred</h2><p>Total recorded output: <strong>'.$h($this->bytes($totalBytes)).'</strong></p><ul><li>Direct pages: <strong>'.$h($this->bytes($directBytes)).'</strong></li><li>Direct images: <strong>'.$h($this->bytes($imageBytes)).'</strong></li><li>Forwarding proxy: <strong>'.$h($this->bytes($proxyBytes)).'</strong></li></ul>';
        $requests=$values['proxy_requests']??[];if($requests||$proxyBytes){$html.='<h2>Forwarding proxy</h2><p><strong>'.number_format($sum($requests),0,'.',',').'</strong> requests; <strong>'.$h($this->bytes($proxyBytes)).'</strong> transferred.</p>';if($requests)$html.=$this->list($requests,['200'=>'Completed','400'=>'Rejected or invalid request','502'=>'Remote server unavailable'],['200','400','502']);}
        $html.='<h2>Community submissions</h2><p>Device and browser combinations reported by visitors to improve profile detection. Counts show distinct submissions by their current review status; repeated confirmations are not counted separately.</p>'.$this->list(array_replace(['pending'=>0,'approved'=>0,'rejected'=>0],$values['device_submissions']??[]),['pending'=>'Awaiting review','approved'=>'Approved','rejected'=>'Rejected'],['pending','approved','rejected']);
        $html.='<h2>The robot waiting room</h2><p>Overenthusiastic visitors sent for a tea break. These are rate-limit events, not proven bots or unique visitors.</p>'.$this->list(array_replace(['60'=>0,'600'=>0,'3600'=>0,'86400'=>0,'604800'=>0],$values['bot_timeouts']??[]),['60'=>'One-minute tea breaks','600'=>'Ten-minute tea breaks','3600'=>'One-hour cooling off','86400'=>'Day passes to the naughty step','604800'=>'One-week holidays'],['60','600','3600','86400','604800']);
        $html.='<p>Permanent residents of the robot retirement home: <strong>'.number_format($sum($values['permanent_ip_blocks']??[]),0,'.',',').'</strong> IP addresses permanently banned.</p>';
        return$html.'<hr><p>Aggregate statistics shown as anonymous totals. RetroWEB does not store visited URLs, search terms or IP addresses in these statistics.</p><p>Detailed statistics have been recorded since November 8, 2026. Total pageviews, approximate visits and successfully rendered pages include totals carried forward from the previous RetroWEB version. Transferred data has been recorded since the new version went live.</p></body></html>';
    }

    private function list(array $values,array $labels,array$order=[]):string
    {
        if($order){$rank=array_flip($order);uksort($values,static fn(string$a,string$b):int=>($rank[$a]??PHP_INT_MAX)<=>($rank[$b]??PHP_INT_MAX)?:strcmp($a,$b));}$html='<ul>';foreach($values as$key=>$count)$html.='<li>'.htmlspecialchars($labels[$key]??$this->words((string)$key),ENT_QUOTES,'UTF-8').': <strong>'.number_format((int)$count,0,'.',',').'</strong></li>';return$html.'</ul>';
    }

    private function words(string $value):string{return ucfirst(str_replace(['_','-'], ' ', $value));}
    private function bytes(int $bytes):string{if($bytes<1024)return$bytes.' bytes';if($bytes<1048576)return number_format($bytes/1024,1,'.',',').' KB';if($bytes<1073741824)return number_format($bytes/1048576,1,'.',',').' MB';if($bytes<1099511627776)return number_format($bytes/1073741824,2,'.',',').' GB';return number_format($bytes/1099511627776,2,'.',',').' TB';}
}
