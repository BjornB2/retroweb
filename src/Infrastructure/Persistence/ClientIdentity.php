<?php
declare(strict_types=1);
namespace RetroWEB\Infrastructure\Persistence;

final class ClientIdentity
{
    public function __construct(private string $secret) { if (strlen($secret) < 32) throw new \InvalidArgumentException('Identity secret too short'); }
    public function from(string $address, string $userAgent): string { return hash_hmac('sha256', $address . "\0" . $userAgent, $this->secret); }
    public function fromAddress(string$address):string{return hash_hmac('sha256',$address."\0address",$this->secret);}
}
