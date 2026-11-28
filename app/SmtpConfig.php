<?php
declare(strict_types=1);
require_once __DIR__.'/AppSecrets.php';

final class SmtpConfig
{
    public static function read(string $root): array
    {
        $path=rtrim($root,'/').'/storage/mail-settings.json';
        $defaults=['enabled'=>false,'host'=>'','port'=>587,'encryption'=>'starttls','username'=>'','password'=>'','from_address'=>'','from_name'=>'liike','revision'=>''];
        if(!is_file($path))return $defaults;
        $source=file_get_contents($path);
        if($source===false)throw new RuntimeException('Configuration SMTP illisible.');
        $config=json_decode($source,true,512,JSON_THROW_ON_ERROR);
        if(!is_array($config))throw new RuntimeException('Configuration SMTP invalide.');
        return array_replace($defaults,$config,['revision'=>hash('sha256',$source)]);
    }

    public static function save(string $root,array $input,array $admin): void
    {
        if(($admin['role']??'')!=='teacher'||(int)($admin['is_superadmin']??0)!==1||($admin['account_status']??'')!=='active')throw new InvalidArgumentException('Accès superadmin refusé.');
        $lock=fopen(rtrim($root,'/').'/storage/mail-settings.lock','c');
        if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Enregistrement SMTP indisponible.');
        try{
            $old=self::read($root);
            if(!hash_equals($old['revision'],(string)($input['smtp_revision']??'')))throw new InvalidArgumentException('La configuration a changé. Rechargez la page.');
            $enabled=($input['smtp_enabled']??'')==='1';
            $host=trim((string)($input['smtp_host']??''));$port=filter_var($input['smtp_port']??587,FILTER_VALIDATE_INT);
            $encryption=(string)($input['smtp_encryption']??'starttls');$username=trim((string)($input['smtp_username']??''));
            $password=(string)($input['smtp_password']??'');$address=trim((string)($input['smtp_from_address']??''));$name=trim((string)($input['smtp_from_name']??'liike'));
            if(($host!==''&&!filter_var($host,FILTER_VALIDATE_DOMAIN,FILTER_FLAG_HOSTNAME)&&!filter_var($host,FILTER_VALIDATE_IP))||strlen($host)>253||!$port||$port<1||$port>65535||!in_array($encryption,['starttls','smtps'],true))throw new InvalidArgumentException('Hôte, port ou sécurité SMTP invalide.');
            if(strlen($username)>254||strlen($password)>1024||preg_match('/[\x00-\x1f\x7f]/',$username.$password.$name)||mb_strlen($name)>120)throw new InvalidArgumentException('Identifiants ou nom d’expéditeur invalides.');
            if($address!==''&&(!filter_var($address,FILTER_VALIDATE_EMAIL)||strlen($address)>254||preg_match('/[\s\x00-\x1f\x7f,;]/',$address)))throw new InvalidArgumentException('Adresse d’expéditeur invalide.');
            $sealed=$password!==''?AppSecrets::encrypt($password,$root,'smtp-password'):$old['password'];
            if($username==='')$sealed='';
            if($enabled&&($host===''||$address===''||$name===''||($username!==''&&$sealed==='')))throw new InvalidArgumentException('Complétez la configuration avant d’activer SMTP.');
            $config=['enabled'=>$enabled,'host'=>$host,'port'=>$port,'encryption'=>$encryption,'username'=>$username,'password'=>$sealed,'from_address'=>$address,'from_name'=>$name,'updated_by'=>(int)$admin['id']];
            $path=rtrim($root,'/').'/storage/mail-settings.json';$temporary=$path.'.'.bin2hex(random_bytes(6)).'.tmp';
            try{
                if(file_put_contents($temporary,json_encode($config,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT))===false)throw new RuntimeException('Écriture SMTP impossible.');
                chmod($temporary,0600);if(!rename($temporary,$path))throw new RuntimeException('Enregistrement SMTP impossible.');
            }finally{if(is_file($temporary))unlink($temporary);}
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }

    public static function mailer(array $config,string $root): \PHPMailer\PHPMailer\PHPMailer
    {
        foreach(['Exception','SMTP','PHPMailer'] as $class)require_once __DIR__.'/Mail/PHPMailer/src/'.$class.'.php';
        $mail=new \PHPMailer\PHPMailer\PHPMailer(true);$mail->isSMTP();
        $mail->Host=$config['host'];$mail->Port=(int)$config['port'];
        $mail->SMTPSecure=$config['encryption']==='smtps'?\PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS:\PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->SMTPAuth=$config['username']!=='';$mail->Username=$config['username'];
        if($mail->SMTPAuth)$mail->Password=AppSecrets::decrypt($config['password'],$root,'smtp-password');
        $mail->SMTPOptions=['ssl'=>['verify_peer'=>true,'verify_peer_name'=>true,'allow_self_signed'=>false]];
        $mail->Timeout=8;$mail->Timelimit=10;$mail->SMTPDebug=0;$mail->CharSet='UTF-8';
        $mail->setFrom($config['from_address'],$config['from_name']);$mail->addReplyTo($config['from_address'],$config['from_name']);
        $mail->isHTML(false);return $mail;
    }
}
