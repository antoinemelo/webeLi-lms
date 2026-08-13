<?php

declare(strict_types=1);

require_once __DIR__.'/DatabaseMigrations.php';

const LIIKE_RELEASE_MANIFEST_URL='https://raw.githubusercontent.com/antoinemelo/webeLi-lms/main/RELEASE.json';
const LIIKE_RELEASE_ARCHIVE_URL='https://codeload.github.com/antoinemelo/webeLi-lms/tar.gz/refs/heads/main';
const LIIKE_RELEASE_REPOSITORY='git@github.com:antoinemelo/webeLi-lms.git';
const LIIKE_RELEASE_CACHE_SECONDS=900;

final class UpdateException extends RuntimeException {}

function maintenance_safe_release_path(string $path): bool
{
    if($path===''||str_starts_with($path,'/')||str_contains($path,"\0")||str_contains($path,'\\'))return false;
    $parts=explode('/',$path);
    foreach($parts as $part)if($part===''||$part==='.'||$part==='..')return false;
    if(str_starts_with($path,'storage/'))return $path==='storage/.htaccess';
    if(str_starts_with($path,'uploads/'))return in_array($path,['uploads/.htaccess','uploads/fiche-observation.txt'],true);
    return true;
}

function maintenance_preserved_external_path(string $path,bool $preserveVendor=true): bool
{
    return $preserveVendor&&($path==='vendor'||str_starts_with($path,'vendor/'));
}

function maintenance_validate_manifest(array $manifest): array
{
    if(($manifest['format']??null)!==1||($manifest['application']??'')!=='liike')throw new UpdateException('Le manifeste de mise à jour est incompatible.');
    $version=trim((string)($manifest['version']??''));
    if(!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/',$version))throw new UpdateException('La version publiée est invalide.');
    $databaseVersion=$manifest['database_version']??0;
    if(!is_int($databaseVersion)||$databaseVersion<0||$databaseVersion>100000)throw new UpdateException('La version de base publiée est invalide.');
    $databaseReleaseVersion=trim((string)($manifest['database_release_version']??''));
    if($databaseReleaseVersion!==''&&!preg_match('/^\d{4}S[12]\.\d+$/',$databaseReleaseVersion))throw new UpdateException('La version semestrielle de la base publiée est invalide.');
    if(($manifest['repository']??'')!==LIIKE_RELEASE_REPOSITORY||($manifest['branch']??'')!=='main')throw new UpdateException('La source de mise à jour n’est pas autorisée.');
    $files=$manifest['files']??null;
    if(!is_array($files)||count($files)<10)throw new UpdateException('La liste des fichiers publiés est incomplète.');
    foreach($files as $path=>$hash){
        if(!is_string($path)||!maintenance_safe_release_path($path)||!is_string($hash)||!preg_match('/^[a-f0-9]{64}$/',$hash))throw new UpdateException('Le manifeste contient un chemin ou une empreinte invalide.');
    }
    foreach(['index.php','app/bootstrap.php','app/Database.php','database/schema.sql','VERSION'] as $required)if(!isset($files[$required]))throw new UpdateException('Le manifeste ne contient pas tous les fichiers indispensables.');
    $preserve=$manifest['preserve_on_update']??['storage/apr.sqlite','uploads/','vendor/'];
    if(!is_array($preserve)||array_values($preserve)!==$preserve||array_diff($preserve,['storage/apr.sqlite','uploads/','vendor/'])||!in_array('storage/apr.sqlite',$preserve,true)||!in_array('uploads/',$preserve,true))throw new UpdateException('La politique de préservation de la publication est invalide.');
    $manifest['version']=$version;$manifest['database_version']=$databaseVersion;$manifest['database_release_version']=$databaseReleaseVersion?:null;$manifest['files']=$files;$manifest['preserve_on_update']=$preserve;
    return $manifest;
}

function maintenance_http_get(string $url,int $maxBytes=10_000_000,int $timeout=12): string
{
    $parts=parse_url($url);
    $allowedHosts=['raw.githubusercontent.com','codeload.github.com'];
    if(($parts['scheme']??'')!=='https'||!in_array(strtolower((string)($parts['host']??'')),$allowedHosts,true))throw new UpdateException('Adresse de mise à jour non autorisée.');
    if(!function_exists('curl_init'))throw new UpdateException('L’extension PHP cURL est requise pour vérifier les mises à jour.');
    $contents='';$handle=curl_init($url);
    curl_setopt_array($handle,[CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>3,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>$timeout,CURLOPT_USERAGENT=>'liike-update-client/1',CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_FAILONERROR=>true,CURLOPT_WRITEFUNCTION=>static function($curl,string $chunk)use(&$contents,$maxBytes):int{$length=strlen($chunk);if(strlen($contents)+$length>$maxBytes)return 0;$contents.=$chunk;return $length;}]);
    $ok=curl_exec($handle);$status=(int)curl_getinfo($handle,CURLINFO_RESPONSE_CODE);$error=curl_error($handle);curl_close($handle);
    if($ok!==true||$status!==200)throw new UpdateException('GitHub est momentanément inaccessible'.($error!==''?' : '.$error:'.'));
    return $contents;
}

function maintenance_installed_manifest(string $root): ?array
{
    $path=rtrim($root,'/').'/RELEASE.json';if(!is_file($path))return null;
    try{$document=json_decode((string)file_get_contents($path),true,512,JSON_THROW_ON_ERROR);return is_array($document)?maintenance_validate_manifest($document):null;}catch(Throwable){return null;}
}

function maintenance_installed_version(string $root): string
{
    $path=rtrim($root,'/').'/VERSION';
    if(is_file($path)){$version=trim((string)file_get_contents($path));if($version!=='')return $version;}
    return 'non versionnée';
}

function maintenance_cache_path(string $root): string
{
    return rtrim($root,'/').'/storage/update-status.json';
}

function maintenance_format_checked_at(int $timestamp): string
{
    if($timestamp<1)return '—';
    return (new DateTimeImmutable('@'.$timestamp))
        ->setTimezone(new DateTimeZone('Europe/Zurich'))
        ->format('d.m.Y H:i');
}

function maintenance_compare_versions(string $left,string $right): int
{
    $quarterPattern='/^(\d{4})Q([1-4])\.(\d+)$/';
    $leftQuarter=preg_match($quarterPattern,$left,$leftParts)===1;$rightQuarter=preg_match($quarterPattern,$right,$rightParts)===1;
    if($leftQuarter&&$rightQuarter){
        foreach([1,2,3] as $index){$comparison=(int)$leftParts[$index]<=>(int)$rightParts[$index];if($comparison!==0)return $comparison;}return 0;
    }
    if($leftQuarter!==$rightQuarter){
        $quarterParts=$leftQuarter?$leftParts:$rightParts;$legacy=$leftQuarter?$right:$left;
        if(preg_match('/^(\d{4})(?:\.|$)/',$legacy,$legacyYear)&&((int)$quarterParts[1])===(int)$legacyYear[1])return $leftQuarter?1:-1;
    }
    return version_compare($left,$right);
}

function maintenance_release_status(string $root,bool $refresh=false): array
{
    $installed=maintenance_installed_version($root);$installedManifest=maintenance_installed_manifest($root);$cachePath=maintenance_cache_path($root);$cached=null;
    if(!$refresh&&is_file($cachePath)){
        try{$candidate=json_decode((string)file_get_contents($cachePath),true,32,JSON_THROW_ON_ERROR);if(is_array($candidate)&&(int)($candidate['checked_at']??0)>=time()-LIIKE_RELEASE_CACHE_SECONDS)$cached=$candidate;}catch(Throwable){}
    }
    if($cached===null){
        try{
            $raw=maintenance_http_get(LIIKE_RELEASE_MANIFEST_URL,2_000_000);$decoded=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
            if(!is_array($decoded))throw new UpdateException('Le manifeste distant est invalide.');
            $manifest=maintenance_validate_manifest($decoded);$cached=['checked_at'=>time(),'manifest'=>$manifest,'error'=>null];
        }catch(Throwable $exception){$cached=['checked_at'=>time(),'manifest'=>null,'error'=>$exception->getMessage()];}
        $directory=dirname($cachePath);if((is_dir($directory)||@mkdir($directory,0775,true))&&is_writable($directory))@file_put_contents($cachePath,json_encode($cached,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    }
    $manifest=is_array($cached['manifest']??null)?$cached['manifest']:null;$latest=(string)($manifest['version']??'');
    $versioned=$installed!=='non versionnée';
    $available=$latest!==''&&(!$versioned||maintenance_compare_versions($latest,$installed)>0);
    $current=$latest!==''&&$versioned&&maintenance_compare_versions($latest,$installed)<=0;
    return ['installed'=>$installed,'latest'=>$latest?:null,'installed_database_version'=>(int)($installedManifest['database_version']??0),'installed_database_release_version'=>$installedManifest['database_release_version']??null,'database_version'=>(int)($manifest['database_version']??0),'database_release_version'=>$manifest['database_release_version']??null,'checked_at'=>(int)($cached['checked_at']??0),'manifest'=>$manifest,'error'=>$cached['error']??null,'available'=>$available,'current'=>$current,'writable'=>is_writable($root)&&is_writable(rtrim($root,'/').'/storage')];
}

function maintenance_remove_tree(string $path): void
{
    if(!file_exists($path)&&!is_link($path))return;
    if(is_link($path)||is_file($path)){@unlink($path);return;}
    $iterator=new FilesystemIterator($path,FilesystemIterator::SKIP_DOTS);
    foreach($iterator as $item)maintenance_remove_tree($item->getPathname());
    @rmdir($path);
}

function maintenance_copy_file_atomic(string $source,string $destination): void
{
    $directory=dirname($destination);if(!is_dir($directory)&&!mkdir($directory,0775,true)&&!is_dir($directory))throw new UpdateException('Impossible de créer un dossier de la mise à jour.');
    $temporary=$destination.'.updating-'.bin2hex(random_bytes(5));
    if(!copy($source,$temporary)){@unlink($temporary);throw new UpdateException('Impossible de préparer un fichier de la mise à jour.');}
    @chmod($temporary,fileperms($source)&0777);
    if(!rename($temporary,$destination)){@unlink($temporary);throw new UpdateException('Impossible de remplacer un fichier de l’application.');}
}

function maintenance_extract_verified_release(string $archive,string $destination,array $manifest,string $manifestRaw): string
{
    if(!class_exists('PharData'))throw new UpdateException('L’extension PHP Phar est requise pour installer une mise à jour.');
    $tar=preg_replace('/\.gz$/','',$archive)?:($archive.'.tar');
    try{$compressed=new PharData($archive);$compressed->decompress();$phar=new PharData($tar);$phar->extractTo($destination,null,true);}catch(Throwable $exception){throw new UpdateException('L’archive de mise à jour ne peut pas être extraite : '.$exception->getMessage());}
    $entries=array_values(array_filter(scandir($destination)?:[],static fn(string $entry):bool=>$entry!=='.'&&$entry!=='..'));
    if(count($entries)!==1||!is_dir($destination.'/'.$entries[0]))throw new UpdateException('L’archive de mise à jour possède une structure inattendue.');
    $releaseRoot=$destination.'/'.$entries[0];$actual=[];
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($releaseRoot,FilesystemIterator::SKIP_DOTS));
    foreach($iterator as $item){
        if($item->isLink())throw new UpdateException('Les liens symboliques sont interdits dans une mise à jour.');
        if(!$item->isFile())continue;$relative=str_replace('\\','/',substr($item->getPathname(),strlen($releaseRoot)+1));$actual[]=$relative;
    }
    sort($actual);$expected=array_keys($manifest['files']);$expected[]='RELEASE.json';sort($expected);
    if($actual!==$expected)throw new UpdateException('Le contenu de l’archive ne correspond pas au manifeste publié.');
    foreach($manifest['files'] as $relative=>$hash)if(!hash_equals($hash,hash_file('sha256',$releaseRoot.'/'.$relative)))throw new UpdateException('L’empreinte du fichier '.$relative.' est invalide.');
    $archiveManifest=(string)file_get_contents($releaseRoot.'/RELEASE.json');
    try{$decoded=json_decode($archiveManifest,true,512,JSON_THROW_ON_ERROR);$validated=maintenance_validate_manifest($decoded);}catch(Throwable){throw new UpdateException('Le manifeste inclus dans l’archive est invalide.');}
    if($validated['version']!==$manifest['version']||$validated['database_version']!==$manifest['database_version']||$validated['database_release_version']!==$manifest['database_release_version']||$validated['preserve_on_update']!==$manifest['preserve_on_update']||$validated['files']!==$manifest['files'])throw new UpdateException('Le manifeste et l’archive ne désignent pas la même publication.');
    return $releaseRoot;
}

function maintenance_backup_database(PDO $pdo,string $destination): void
{
    $quoted=$pdo->quote($destination);$pdo->exec('VACUUM INTO '.$quoted);
    if(!is_file($destination)||filesize($destination)===0)throw new UpdateException('La sauvegarde de la base de données a échoué.');
}

function maintenance_cleanup_entry(string $path,array &$result): void
{
    if(is_link($path)||is_file($path)){
        $size=is_file($path)?(int)(filesize($path)?:0):0;
        if(!unlink($path))throw new UpdateException('Impossible de supprimer un ancien fichier de maintenance.');
        $result['files']++;$result['bytes']+=$size;return;
    }
    if(!is_dir($path))return;
    foreach(new FilesystemIterator($path,FilesystemIterator::SKIP_DOTS) as $item)maintenance_cleanup_entry($item->getPathname(),$result);
    if(!rmdir($path))throw new UpdateException('Impossible de supprimer un ancien dossier de maintenance.');
    $result['directories']++;
}

function maintenance_cleanup_storage(string $root): array
{
    $storage=rtrim($root,'/').'/storage';
    if(!is_dir($storage)||!is_writable($storage))throw new UpdateException('Le dossier de stockage doit être accessible en écriture.');
    $updates=$storage.'/updates';
    if(!is_dir($updates)&&!mkdir($updates,0775,true)&&!is_dir($updates))throw new UpdateException('Le dossier des mises à jour ne peut pas être créé.');
    if(is_link($updates))throw new UpdateException('Le dossier des mises à jour ne peut pas être un lien symbolique.');
    $updateLock=fopen($updates.'/update.lock','c+');
    if(!$updateLock||!flock($updateLock,LOCK_EX|LOCK_NB))throw new UpdateException('Une mise à jour est en cours : le nettoyage est momentanément indisponible.');
    $migrationLock=fopen($storage.'/apr.sqlite.migration.lock','c+');
    if(!$migrationLock||!flock($migrationLock,LOCK_EX|LOCK_NB)){
        flock($updateLock,LOCK_UN);fclose($updateLock);
        if(is_resource($migrationLock))fclose($migrationLock);
        throw new UpdateException('Une migration de la base est en cours : le nettoyage est momentanément indisponible.');
    }
    $result=['files'=>0,'directories'=>0,'bytes'=>0];
    try{
        $backups=$storage.'/backups';
        if(is_link($backups))throw new UpdateException('Le dossier des sauvegardes ne peut pas être un lien symbolique.');
        if(is_dir($backups))foreach(new FilesystemIterator($backups,FilesystemIterator::SKIP_DOTS) as $item)maintenance_cleanup_entry($item->getPathname(),$result);
        foreach(new FilesystemIterator($updates,FilesystemIterator::SKIP_DOTS) as $item){
            if($item->getFilename()==='update.lock')continue;
            maintenance_cleanup_entry($item->getPathname(),$result);
        }
        return $result;
    }finally{
        flock($migrationLock,LOCK_UN);fclose($migrationLock);
        flock($updateLock,LOCK_UN);fclose($updateLock);
    }
}

function maintenance_format_bytes(int $bytes): string
{
    if($bytes<1024)return $bytes.' o';
    if($bytes<1024*1024)return number_format($bytes/1024,1,',','').' Ko';
    if($bytes<1024*1024*1024)return number_format($bytes/(1024*1024),1,',','').' Mo';
    return number_format($bytes/(1024*1024*1024),1,',','').' Go';
}

function maintenance_apply_release(PDO $pdo,string $root,array $manifest): array
{
    $manifest=maintenance_validate_manifest($manifest);$root=rtrim($root,'/');$storage=$root.'/storage';
    if(!is_writable($root)||!is_writable($storage))throw new UpdateException('Les dossiers de l’application et de stockage doivent être inscriptibles.');
    $updates=$storage.'/updates';if(!is_dir($updates)&&!mkdir($updates,0775,true)&&!is_dir($updates))throw new UpdateException('Le dossier des mises à jour ne peut pas être créé.');
    $lockHandle=fopen($updates.'/update.lock','c+');if(!$lockHandle||!flock($lockHandle,LOCK_EX|LOCK_NB))throw new UpdateException('Une autre mise à jour est déjà en cours.');
    $work=$updates.'/work-'.bin2hex(random_bytes(6));$backup=$updates.'/backup-'.date('Ymd-His').'-'.$manifest['version'];$archive=$work.'/release.tar.gz';$extract=$work.'/extract';$touched=[];
    try{
        mkdir($work,0775,true);mkdir($extract,0775,true);mkdir($backup,0775,true);
        $manifestRaw=maintenance_http_get(LIIKE_RELEASE_MANIFEST_URL,2_000_000);$fresh=json_decode($manifestRaw,true,512,JSON_THROW_ON_ERROR);$fresh=maintenance_validate_manifest($fresh);
        if($fresh['version']!==$manifest['version']||$fresh['database_version']!==$manifest['database_version']||$fresh['database_release_version']!==$manifest['database_release_version']||$fresh['preserve_on_update']!==$manifest['preserve_on_update']||$fresh['files']!==$manifest['files'])throw new UpdateException('Une nouvelle publication est apparue : vérifiez à nouveau la version disponible.');
        file_put_contents($archive,maintenance_http_get(LIIKE_RELEASE_ARCHIVE_URL,80_000_000,45));
        $releaseRoot=maintenance_extract_verified_release($archive,$extract,$manifest,$manifestRaw);
        $schemaSql=(string)file_get_contents($releaseRoot.'/database/schema.sql');
        if(!preg_match('/PRAGMA\s+user_version\s*=\s*(\d+)\s*;/i',$schemaSql,$schemaVersion)||((int)$schemaVersion[1])!==(int)$manifest['database_version'])throw new UpdateException('La version de base du schéma ne correspond pas au manifeste Git.');
        try{$databaseCompatibility=database_compatibility_contract($releaseRoot.'/database/compatibility.php');database_plan_packaged_migrations($pdo,$releaseRoot.'/database/migrations',(int)$manifest['database_version']);}catch(Throwable $exception){throw new UpdateException('La chaîne de migrations Git est incomplète : '.$exception->getMessage(),0,$exception);}
        maintenance_backup_database($pdo,$backup.'/apr.sqlite');
        $oldManifest=maintenance_installed_manifest($root);$oldFiles=array_keys($oldManifest['files']??[]);$newFiles=array_keys($manifest['files']);$managed=array_values(array_unique(array_merge($oldFiles,$newFiles,['RELEASE.json'])));$preserveVendor=in_array('vendor/',$manifest['preserve_on_update'],true);
        foreach($managed as $relative){if(maintenance_preserved_external_path($relative,$preserveVendor)||(!maintenance_safe_release_path($relative)&&$relative!=='RELEASE.json'))continue;$target=$root.'/'.$relative;if(is_file($target)){$copy=$backup.'/code/'.$relative;$directory=dirname($copy);if(!is_dir($directory))mkdir($directory,0775,true);if(!copy($target,$copy))throw new UpdateException('La sauvegarde du code a échoué.');}}
        file_put_contents($backup.'/previous-files.json',json_encode($oldFiles,JSON_UNESCAPED_SLASHES));
        file_put_contents($storage.'/maintenance.flag',(string)time());
        foreach($newFiles as $relative){if(maintenance_preserved_external_path($relative,$preserveVendor))continue;$target=$root.'/'.$relative;$touched[]=$relative;maintenance_copy_file_atomic($releaseRoot.'/'.$relative,$target);}
        $touched[]='RELEASE.json';maintenance_copy_file_atomic($releaseRoot.'/RELEASE.json',$root.'/RELEASE.json');
        foreach(array_diff($oldFiles,$newFiles) as $relative){if(!maintenance_preserved_external_path($relative,$preserveVendor)&&maintenance_safe_release_path($relative)&&is_file($root.'/'.$relative)){$touched[]=$relative;unlink($root.'/'.$relative);}}
        try{$databaseUpdate=database_apply_packaged_migrations($pdo,$releaseRoot.'/database/migrations',(int)$manifest['database_version'],$databaseCompatibility);}catch(Throwable $exception){throw new UpdateException('La migration automatique de la base a échoué : '.$exception->getMessage(),0,$exception);}
        @unlink(maintenance_cache_path($root));@unlink($storage.'/maintenance.flag');maintenance_remove_tree($work);flock($lockHandle,LOCK_UN);fclose($lockHandle);
        return ['version'=>$manifest['version'],'database'=>$databaseUpdate,'backup'=>$backup];
    }catch(Throwable $exception){
        foreach(array_reverse($touched) as $relative){$saved=$backup.'/code/'.$relative;$target=$root.'/'.$relative;if(is_file($saved))maintenance_copy_file_atomic($saved,$target);elseif(is_file($target))@unlink($target);}
        @unlink($storage.'/maintenance.flag');maintenance_remove_tree($work);if(is_resource($lockHandle)){flock($lockHandle,LOCK_UN);fclose($lockHandle);}throw $exception instanceof UpdateException?$exception:new UpdateException('La mise à jour a échoué : '.$exception->getMessage());
    }
}
