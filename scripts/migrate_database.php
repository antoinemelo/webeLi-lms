<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/app/Database.php';

$options=getopt('', ['database:','status','no-backup']);
$path=(string)($options['database']??dirname(__DIR__).'/storage/apr.sqlite');
$path=(string)(realpath($path)?:$path);

if(!is_file($path)){
    fwrite(STDERR,"Base SQLite introuvable : $path\n");
    exit(1);
}

try{
    if(isset($options['status'])){
        $pdo=new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $status=Database::migrationStatus($pdo);
        echo 'Base : '.$path."\n";
        echo 'Version de la base : v'.$status['current'].' / v'.$status['latest']."\n";
        echo $status['up_to_date']?"État : à jour\n":'Migrations en attente : '.implode(', ',$status['pending'])."\n";
        exit($status['up_to_date']?0:2);
    }
    $result=Database::migrateFile($path,!isset($options['no-backup']));
    echo 'Base : '.$path."\n";
    echo $result['applied']?'Migrations appliquées : '.implode(', ',$result['applied'])."\n":"Aucune migration nécessaire.\n";
    if($result['backup'])echo 'Sauvegarde : '.$result['backup']."\n";
    echo 'Version de la base : v'.$result['status']['current'].' / v'.$result['status']['latest']."\n";
}catch(Throwable $exception){
    fwrite(STDERR,'Migration impossible : '.$exception->getMessage()."\n");
    exit(1);
}
