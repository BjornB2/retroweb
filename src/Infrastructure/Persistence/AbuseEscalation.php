<?php
declare(strict_types=1);
namespace RetroWEB\Infrastructure\Persistence;

final class AbuseEscalation
{
    public static function check(\SQLite3 $db,string $client,int $now,bool $exceeded,string $directory):bool
    {
        $db->exec('CREATE TABLE IF NOT EXISTS escalation(client TEXT PRIMARY KEY,level INTEGER NOT NULL,until_time INTEGER NOT NULL,last_trigger INTEGER NOT NULL)');
        $db->exec('CREATE TABLE IF NOT EXISTS block_events(client TEXT NOT NULL,occurred INTEGER NOT NULL,duration INTEGER NOT NULL)');
        $q=$db->prepare('SELECT * FROM escalation WHERE client=:client');$q->bindValue(':client',$client);$row=$q->execute()->fetchArray(SQLITE3_ASSOC);
        if($row&&(int)$row['until_time']>$now)return false;
        if(!$exceeded)return true;
        $level=$row&&$now-(int)$row['last_trigger']<2592000?min(5,(int)$row['level']+1):0;
        if($level===5){
            $db->exec('CREATE TABLE IF NOT EXISTS permanent_ip_clients(client TEXT PRIMARY KEY)');
            foreach(['INSERT OR IGNORE INTO permanent_ip_clients VALUES(:client)','INSERT OR IGNORE INTO blocked_clients(client,blocked_at) VALUES(:client,:now)','INSERT INTO block_events VALUES(:client,:now,0)']as$sql){$q=$db->prepare($sql);$q->bindValue(':client',$client);if(str_contains($sql,':now'))$q->bindValue(':now',$now,SQLITE3_INTEGER);$q->execute();}
            return false;
        }
        $duration=[60,600,3600,86400,604800][$level];
        $q=$db->prepare('INSERT INTO escalation VALUES(:client,:level,:until,:now) ON CONFLICT(client) DO UPDATE SET level=excluded.level,until_time=excluded.until_time,last_trigger=excluded.last_trigger');
        $q->bindValue(':client',$client);$q->bindValue(':level',$level,SQLITE3_INTEGER);$q->bindValue(':until',$now+$duration,SQLITE3_INTEGER);$q->bindValue(':now',$now,SQLITE3_INTEGER);$q->execute();
        $q=$db->prepare('INSERT INTO block_events VALUES(:client,:now,:duration)');$q->bindValue(':client',$client);$q->bindValue(':now',$now,SQLITE3_INTEGER);$q->bindValue(':duration',$duration,SQLITE3_INTEGER);$q->execute();
        $db->exec('DELETE FROM block_events WHERE occurred < '.($now-2592000));
        $stats=new AggregateStatistics($directory.'/statistics-v2.sqlite');$stats->increment('bot_timeouts',(string)$duration,$now);
        return false;
    }
}
