<?php
declare(strict_types=1);
return ['version'=>24,'name'=>'Identités durables et messagerie indépendante','up'=>static function(PDO $p): void {
 foreach(['users','courses'] as $table){
  $columns=array_column($p->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC),'name');
  if(!in_array('messaging_uuid',$columns,true))$p->exec("ALTER TABLE $table ADD COLUMN messaging_uuid TEXT NOT NULL DEFAULT ''");
  $p->exec("UPDATE $table SET messaging_uuid=lower(hex(randomblob(16))) WHERE messaging_uuid='';CREATE UNIQUE INDEX IF NOT EXISTS {$table}_messaging_uuid ON $table(messaging_uuid) WHERE messaging_uuid<>'';CREATE TRIGGER IF NOT EXISTS {$table}_messaging_identity AFTER INSERT ON $table WHEN NEW.messaging_uuid='' BEGIN UPDATE $table SET messaging_uuid=lower(hex(randomblob(16))) WHERE id=NEW.id; END;");
 }
 $columns=array_column($p->query('PRAGMA table_info(courses)')->fetchAll(PDO::FETCH_ASSOC),'name');
 if(!in_array('messaging_enabled',$columns,true))$p->exec('ALTER TABLE courses ADD COLUMN messaging_enabled INTEGER NOT NULL DEFAULT 0 CHECK(messaging_enabled IN (0,1))');
 $p->exec("CREATE TABLE IF NOT EXISTS messaging_instance (id INTEGER PRIMARY KEY CHECK(id=1),uuid TEXT NOT NULL);INSERT OR IGNORE INTO messaging_instance VALUES(1,lower(hex(randomblob(16))));");
 // Older maintenance tools execute core migrations: this also installs the separate module database.
 $files=$p->query('PRAGMA database_list')->fetchAll(PDO::FETCH_ASSOC);$main=array_values(array_filter($files,fn($r)=>$r['name']==='main'))[0]['file']??'';
 if($main!==''){
  $path=dirname($main).'/messaging.sqlite';$lock=fopen($path.'.migration.lock','c+');
  if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Messaging migration lock unavailable');
  try{
   $chat=new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$chat->exec('PRAGMA foreign_keys=ON;PRAGMA busy_timeout=5000');
   if(database_recorded_migration_version($chat)<1)database_apply_packaged_migrations($chat,dirname(__DIR__).'/messaging/migrations',1);
   $chat->exec('PRAGMA journal_mode=WAL');
  }finally{flock($lock,LOCK_UN);fclose($lock);}

 }
}];
