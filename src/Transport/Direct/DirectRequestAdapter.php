<?php
declare(strict_types=1);
namespace RetroWEB\Transport\Direct;
use RetroWEB\Application\RenderRequest;
use RetroWEB\Infrastructure\Http\UrlPolicy;
use RetroWEB\Domain\Form\SensitiveFieldPolicy;

final class DirectRequestAdapter
{
    public function __construct(private UrlPolicy $policy) {}
    public function parse(array $query, array $post = [], string $userAgent = '', ?string $clientIdentity = null): RenderRequest
    {
        $sourceForm=(string)($query['rw_form']??$post['rw_form']??'')==='1';if($post&&!$sourceForm)throw new \DomainException('Direct POST requires a validated RetroWEB form');$url=$sourceForm&&is_string($query['rw_source']??null)?$query['rw_source']:(is_string($query['url']??null)?$query['url']:'');
        $method=$post ? 'POST':'GET'; $controls=$sourceForm?['rw_source'=>true,'rw_form'=>true]:['url'=>true,'snapshot'=>true,'part'=>true,'action'=>true,'mode'=>true,'settings'=>true,'custom'=>true,'q'=>true,'search'=>true];
        $sensitive=new SensitiveFieldPolicy();if(!$post){$fields=array_diff_key($query,$controls);$sensitive->assertSafe($fields);if($fields)$url.=(str_contains($url,'?')?'&':'?').http_build_query($fields,'','&',PHP_QUERY_RFC3986);} $this->policy->validate($url);
        $fields=$post?array_diff_key($post,['settings_save'=>true,'settings_reset'=>true,'url'=>true,'rw_source'=>true,'rw_form'=>true]):[];$sensitive->assertSafe($fields);if(strlen(http_build_query($fields,'','&',PHP_QUERY_RFC3986))>65536)throw new \LengthException('Form too large');$token=is_string($query['snapshot']??null)&&preg_match('/^[a-f0-9]{64}$/',$query['snapshot'])?$query['snapshot']:null;
        $part=filter_var($query['part']??1,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]) ?: 1;
        $action=is_string($query['action']??null)?$query['action']:''; $force=in_array($action,['reload','image-mode','settings-return'],true);
        return new RenderRequest($url,'direct',$method,$fields,$token,$part,$force,$action==='entire',$userAgent,$clientIdentity);
    }
}
