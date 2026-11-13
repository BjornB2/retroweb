<?php
declare(strict_types=1);
namespace RetroWEB\Domain\Form;

final class SensitiveFieldPolicy
{
    public function isSensitiveName(string$name):bool
    {
        return preg_match('/(?:^|[_\-\s])(?:pass(?:word|wd)?|secret|credit[_\-]?card|card[_\-]?(?:number|no)|cc[_\-]?(?:number|num|csc|cvv)|cvv|cvc|otp|one[_\-]?time[_\-]?(?:code|password)|pin)(?:$|[_\-\s])/i',$name)===1;
    }
    public function assertSafe(array$fields):void
    {
        foreach($fields as$name=>$value){if($this->isSensitiveName((string)$name))throw new \DomainException('Sensitive form field');if(is_array($value))$this->assertSafe($value);}
    }
}
