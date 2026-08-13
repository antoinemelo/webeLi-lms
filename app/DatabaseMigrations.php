<?php

declare(strict_types=1);

function database_packaged_migrations(string $directory): array
{
    if(!is_dir($directory))return [];
    $migrations=[];
    foreach(scandir($directory)?:[] as $filename){
        if(!preg_match('/^(\d{3,})_[a-z0-9_]+\.php$/',$filename,$matches))continue;
        $path=$directory.'/'.$filename;
        $descriptor=(static fn(string $migrationFile):mixed=>require $migrationFile)($path);
        $version=(int)$matches[1];
        if(!is_array($descriptor)||(int)($descriptor['version']??0)!==$version||trim((string)($descriptor['name']??''))===''||!is_callable($descriptor['up']??null))throw new RuntimeException('Migration de base invalide : '.$filename);
        if(isset($migrations[$version]))throw new RuntimeException('Version de migration dupliquée : '.$version);
        $migrations[$version]=['version'=>$version,'name'=>trim((string)$descriptor['name']),'up'=>$descriptor['up'],'checksum'=>hash_file('sha256',$path),'path'=>$path];
    }
    ksort($migrations);return $migrations;
}

function database_recorded_migration_version(PDO $pdo): int
{
    $hasTable=(bool)$pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='schema_migrations'")->fetchColumn();
    if(!$hasTable)return (int)$pdo->query('PRAGMA user_version')->fetchColumn();
    $versions=array_map('intval',$pdo->query('SELECT version FROM schema_migrations ORDER BY version')->fetchAll(PDO::FETCH_COLUMN));
    if(!$versions)return (int)$pdo->query('PRAGMA user_version')->fetchColumn();
    $current=0;foreach($versions as $version){if($version!==$current+1)break;$current=$version;}return $current;
}

function database_compatibility_contract(string $path): callable
{
    if(!is_file($path))throw new RuntimeException('Le contrat de compatibilité de la base manque dans la publication.');
    $contract=(static fn(string $contractFile):mixed=>require $contractFile)($path);
    if(!is_callable($contract))throw new RuntimeException('Le contrat de compatibilité de la base est invalide.');
    return $contract;
}

function database_plan_packaged_migrations(PDO $pdo,string $directory,int $targetVersion): array
{
    $current=database_recorded_migration_version($pdo);
    if($targetVersion<$current)throw new RuntimeException('La publication cible utilise une base plus ancienne (v'.$targetVersion.') que l’instance (v'.$current.').');
    $catalog=database_packaged_migrations($directory);
    $hasTable=(bool)$pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='schema_migrations'")->fetchColumn();
    if($hasTable){
        $checksums=$pdo->query('SELECT version,checksum FROM schema_migrations')->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach($catalog as $version=>$migration)if(isset($checksums[$version])&&!hash_equals((string)$checksums[$version],$migration['checksum']))throw new RuntimeException('La migration v'.$version.' publiée a été modifiée après son application.');
    }
    $pending=[];for($version=$current+1;$version<=$targetVersion;$version++){
        if(!isset($catalog[$version]))throw new RuntimeException('La migration v'.$version.' manque dans la publication Git.');
        $pending[$version]=$catalog[$version];
    }
    return ['from'=>$current,'to'=>$targetVersion,'pending'=>$pending];
}

function database_apply_packaged_migrations(PDO $pdo,string $directory,int $targetVersion,?callable $compatibility=null): array
{
    $plan=database_plan_packaged_migrations($pdo,$directory,$targetVersion);$current=$plan['from'];$pending=$plan['pending'];
    if($pdo->inTransaction())throw new RuntimeException('Une transaction empêche le démarrage des migrations de mise à jour.');
    try{
        $pdo->beginTransaction();
        $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version INTEGER PRIMARY KEY,name TEXT NOT NULL,checksum TEXT NOT NULL,applied_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $insert=$pdo->prepare('INSERT INTO schema_migrations(version,name,checksum,applied_at) VALUES(?,?,?,CURRENT_TIMESTAMP)');
        foreach($pending as $version=>$migration){
            ($migration['up'])($pdo);
            if(!$pdo->inTransaction())throw new RuntimeException('La migration v'.$version.' a interrompu la transaction atomique.');
            $insert->execute([$version,$migration['name'],$migration['checksum']]);
        }
        $pdo->exec('PRAGMA user_version = '.$targetVersion);
        if($compatibility!==null)$compatibility($pdo);
        if(!$pdo->inTransaction())throw new RuntimeException('Le contrat de compatibilité a interrompu la transaction atomique.');
        if($pdo->query('PRAGMA integrity_check')->fetchColumn()!=='ok')throw new RuntimeException('Le contrôle d’intégrité SQLite a échoué après migration.');
        if($pdo->query('PRAGMA foreign_key_check')->fetchAll())throw new RuntimeException('Des relations SQLite sont invalides après migration.');
        $pdo->commit();
        return ['from'=>$current,'to'=>$targetVersion,'applied'=>array_keys($pending)];
    }catch(Throwable $exception){
        if($pdo->inTransaction())$pdo->rollBack();
        throw $exception;
    }
}
