<?php
declare(strict_types=1);

/** Installation-local key, separate from SQLite and never included in content exports. */
final class AppSecrets
{
    public static function key(string $root,bool $create=true): string
    {
        $directory=rtrim($root,'/').'/storage';
        if(!is_dir($directory)&&!mkdir($directory,0700,true))throw new RuntimeException('Stockage sécurisé indisponible.');
        $lock=fopen($directory.'/security.lock','c');
        if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Stockage sécurisé indisponible.');
        try{
            $path=$directory.'/security.key';
            if(!is_file($path)){
                if(!$create)throw new RuntimeException('Clé de sécurité indisponible.');
                $file=fopen($path,'x');if(!$file)throw new RuntimeException('Création de la clé impossible.');
                chmod($path,0600);$key=random_bytes(32);
                try{if(fwrite($file,$key)!==32||!fflush($file))throw new RuntimeException('Écriture de la clé impossible.');}finally{fclose($file);}
            }
            $key=file_get_contents($path);
            if(!is_string($key)||strlen($key)!==32)throw new RuntimeException('Clé de sécurité indisponible.');
            return $key;
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }

    public static function encrypt(string $plain,string $root,string $purpose): string
    {
        $nonce=random_bytes(12);$tag='';
        $cipher=openssl_encrypt($plain,'aes-256-gcm',self::key($root),OPENSSL_RAW_DATA,$nonce,$tag,$purpose,16);
        if($cipher===false)throw new RuntimeException('Chiffrement indisponible.');
        return 'v1:'.base64_encode($nonce.$tag.$cipher);
    }

    public static function decrypt(string $sealed,string $root,string $purpose): string
    {
        $raw=str_starts_with($sealed,'v1:')?base64_decode(substr($sealed,3),true):false;
        if($raw===false||strlen($raw)<28)throw new RuntimeException('Secret illisible.');
        $plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',self::key($root,false),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16),$purpose);
        if($plain===false)throw new RuntimeException('Secret illisible.');
        return $plain;
    }
}
