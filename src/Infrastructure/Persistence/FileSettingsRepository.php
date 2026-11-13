<?php
declare(strict_types=1);
namespace RetroWEB\Infrastructure\Persistence;
use RetroWEB\Domain\Settings\SettingsOverrides;

final class FileSettingsRepository
{
    public function __construct(private string $directory, private int $ttl = 604800) {}
    public function save(string $identity, SettingsOverrides $overrides, int $now, bool $custom = false): void
    {
        $this->validIdentity($identity); if (!is_dir($this->directory) && !mkdir($this->directory, 0700, true) && !is_dir($this->directory)) throw new \RuntimeException('Unable to create settings directory');
        $json = json_encode(['schema'=>'settings-v2','custom'=>$custom,'values'=>$overrides->values,'image_mode'=>$overrides->imageMode,'selected_profile'=>$overrides->selectedProfile,'last_used'=>$now], JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || strlen($json) > 8192) throw new \LengthException('Settings too large');
        $tmp = tempnam($this->directory, 'settings-');
        if ($tmp === false || file_put_contents($tmp, $json, LOCK_EX) !== strlen($json) || !rename($tmp, $this->path($identity))) { if (is_string($tmp)) @unlink($tmp); throw new \RuntimeException('Unable to save settings'); }
    }
    public function load(string $identity, int $now): ?SettingsOverrides
    {
        $state=$this->loadState($identity,$now);return$state!==null?$state[0]:null;
    }
    public function loadState(string $identity,int $now):?array
    {
        $this->validIdentity($identity); $path = $this->path($identity);
        if (!is_file($path) || filesize($path) > 8192) return null;
        $data = json_decode((string)file_get_contents($path), true);
        if (!is_array($data) || ($data['schema'] ?? '') !== 'settings-v2' || ($data['last_used'] ?? 0) + $this->ttl < $now) return null;
        $result = new SettingsOverrides(is_array($data['values'] ?? null) ? $data['values'] : [], is_string($data['image_mode'] ?? null) ? $data['image_mode'] : null, is_string($data['selected_profile'] ?? null) ? $data['selected_profile'] : null);
        $custom=!empty($data['custom']);$this->save($identity,$result,$now,$custom);return[$result,$custom];
    }
    private function validIdentity(string $id): void { if (!preg_match('/^[a-f0-9]{64}$/', $id)) throw new \InvalidArgumentException('Invalid client identity'); }
    private function path(string $id): string { return rtrim($this->directory, '/') . '/' . $id . '.json'; }
}
