<?php
declare(strict_types=1);
namespace RetroWEB\Infrastructure\Persistence;

final class FileSnapshotRepository implements SnapshotRepository
{
    public function __construct(private string $directory, private int $maxBytes = 4194304) {}
    public function put(string $sourceUrl, array $parts, int $expiresAt, string $renderVersion, string $encoding='UTF-8'): Snapshot
    {
        $payload = ['source_url'=>$sourceUrl,'parts_b64'=>array_map('base64_encode',array_values($parts)),'expires_at'=>$expiresAt,'render_version'=>$renderVersion,'encoding'=>$encoding];
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || strlen($json) > $this->maxBytes) throw new \LengthException('Snapshot too large');
        $token = hash('sha256', random_bytes(32)); $this->ensureDirectory();
        $temporary = tempnam($this->directory, 'snapshot-');
        if ($temporary === false || file_put_contents($temporary, $json, LOCK_EX) !== strlen($json) || !rename($temporary, $this->path($token))) { if (is_string($temporary)) @unlink($temporary); throw new \RuntimeException('Unable to store snapshot'); }
        return new Snapshot($token, $sourceUrl, $parts, $expiresAt, $renderVersion,$encoding);
    }
    public function get(string $token, int $now, string $renderVersion): ?Snapshot
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
        $path = $this->path($token); if (!is_file($path) || filesize($path) > $this->maxBytes) return null;
        $data = json_decode((string)file_get_contents($path), true);
        if (!is_array($data) || ($data['expires_at'] ?? 0) < $now || !is_string($data['render_version'] ?? null) || !hash_equals($data['render_version'], $renderVersion)) return null;
        if(!is_string($data['source_url']??null)||!is_array($data['parts_b64']??null))return null;$parts=[];foreach($data['parts_b64']as$part){$decoded=base64_decode((string)$part,true);if($decoded===false)return null;$parts[]=$decoded;}
        return new Snapshot($token,$data['source_url'],$parts,(int)$data['expires_at'],$data['render_version'],is_string($data['encoding']??null)?$data['encoding']:'UTF-8');
    }
    private function ensureDirectory(): void { if (!is_dir($this->directory) && !mkdir($this->directory, 0700, true) && !is_dir($this->directory)) throw new \RuntimeException('Unable to create snapshot directory'); }
    private function path(string $token): string { return rtrim($this->directory, '/') . '/' . $token . '.json'; }
}
