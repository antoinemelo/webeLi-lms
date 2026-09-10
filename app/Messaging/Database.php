<?php
declare(strict_types=1);
namespace Liike\Messaging;
require_once dirname(__DIR__).'/DatabaseMigrations.php';
final class Database
{
    public const VERSION=1;
    public static function connect(string $root): \PDO
    {
        $path=rtrim($root,'/').'/storage/messaging.sqlite';
        self::migrate($path,dirname(__DIR__,2).'/database/messaging/migrations');
        return self::open($path);
    }
    public static function open(string $path): \PDO
    {
        $p=new \PDO('sqlite:'.$path,null,null,[\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION,\PDO::ATTR_DEFAULT_FETCH_MODE=>\PDO::FETCH_ASSOC]);
        $p->exec('PRAGMA foreign_keys=ON;PRAGMA busy_timeout=5000;PRAGMA secure_delete=ON');
        return $p;
    }
    public static function migrate(string $path,string $directory,int $target=self::VERSION): array
    {
        if(!is_dir(dirname($path)))mkdir(dirname($path),0700,true);
        $lock=fopen($path.'.migration.lock','c+');if(!$lock||!flock($lock,LOCK_EX))throw new \RuntimeException('Messaging migration lock unavailable');
        try{
            $p=self::open($path);$current=\database_recorded_migration_version($p);
            $plan=\database_plan_packaged_migrations($p,$directory,$target);
            if($plan['pending']){
                $result=\database_apply_packaged_migrations($p,$directory,$target);
                $p->exec('PRAGMA journal_mode=WAL');
            }else $result=['from'=>$current,'to'=>$target,'applied'=>[]];
            return $result;
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
}
