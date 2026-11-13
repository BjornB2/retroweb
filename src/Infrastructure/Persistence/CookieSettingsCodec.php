<?php
declare(strict_types=1);
namespace RetroWEB\Infrastructure\Persistence;
use RetroWEB\Domain\Settings\SettingsOverrides;

final class CookieSettingsCodec
{
    public function __construct(private string $secret) { if(strlen($secret)<32)throw new \InvalidArgumentException('Cookie secret too short'); }
    public function encode(SettingsOverrides $settings,bool $custom,?string$profileId=null):string
    {
        if($profileId!==null&&!preg_match('/^[a-z0-9_]{1,40}$/',$profileId))throw new \InvalidArgumentException('Invalid cookie profile');
        $json=json_encode(['schema'=>'settings-v2','profile_id'=>$profileId,'selected_profile'=>$settings->selectedProfile,'custom'=>$custom,'values'=>$settings->values,'image_mode'=>$settings->imageMode],JSON_UNESCAPED_SLASHES);
        $payload=rtrim(strtr(base64_encode((string)$json),'+/','-_'),'='); return $payload.'.'.hash_hmac('sha256',$payload,$this->secret);
    }
    public function decode(?string $cookie):array
    {
        [$settings,$custom]=$this->decodeVerified($cookie);return[$settings,$custom];
    }
    public function decodeVerified(?string$cookie):array
    {
        if(!is_string($cookie)||strlen($cookie)>12000||!preg_match('/^([A-Za-z0-9_-]+)\.([a-f0-9]{64})$/',$cookie,$m)||!hash_equals(hash_hmac('sha256',$m[1],$this->secret),$m[2]))return [new SettingsOverrides(),false,false,null];
        $raw=strtr($m[1],'-_','+/');$raw.=str_repeat('=',(4-strlen($raw)%4)%4);$data=json_decode((string)base64_decode($raw,true),true);
        if(!is_array($data)||($data['schema']??'')!=='settings-v2')return [new SettingsOverrides(),false,false,null];$profileId=is_string($data['profile_id']??null)&&preg_match('/^[a-z0-9_]{1,40}$/',$data['profile_id'])?$data['profile_id']:null;
        try{return [new SettingsOverrides(is_array($data['values']??null)?$data['values']:[],is_string($data['image_mode']??null)?$data['image_mode']:null,is_string($data['selected_profile']??null)?$data['selected_profile']:null),!empty($data['custom']),true,$profileId];}catch(\InvalidArgumentException $e){return [new SettingsOverrides(),false,false,null];}
    }
}
