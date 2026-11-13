<?php
declare(strict_types=1);
namespace RetroWEB\Domain\Profile;

final class ProfileRepository
{
    private array $profiles=[];
    public function __construct(array $definitions)
    {
        foreach($definitions as $id=>$d){if(!is_array($d['capabilities']??null)||!is_array($d['workarounds']??null))throw new \InvalidArgumentException('Profile capability and workaround bundles are required');$this->profiles[$id]=new BrowserProfile($id,(int)$d['version'],(string)$d['label'],(string)$d['html'],(string)$d['encoding'],(int)$d['viewport'][0],(int)$d['viewport'][1],(int)$d['image'][0],(int)$d['image'][1],(int)$d['budget_kb'],(string)$d['delivery'],(string)$d['image_mode'],$d['capabilities'],$d['workarounds']);}
        if(!isset($this->profiles['generic']))throw new \InvalidArgumentException('Generic profile is required');
    }
    public function get(string $id): BrowserProfile { return $this->profiles[$id] ?? $this->profiles['generic']; }
    public function has(string $id): bool { return isset($this->profiles[$id]); }
    public function all(): array { return $this->profiles; }
    public function alphabetically(): array
    {
        $profiles=$this->profiles;
        uasort($profiles,static function(BrowserProfile$a,BrowserProfile$b):int{
            $labelOrder=strcasecmp($a->label,$b->label);
            return$labelOrder!==0?$labelOrder:strcmp($a->id,$b->id);
        });
        return$profiles;
    }
}
