<?php

declare(strict_types=1);

require_once __DIR__.'/MailAbuseProtection.php';
require_once __DIR__.'/SmtpConfig.php';

const APP_MAIL_FROM_ADDRESS = 'noreply@lms.webe.li';
const APP_MAIL_FROM_NAME = 'liike';

/** Use the deployment URL, never an invented subdomain or forwarded/Host header. */
function app_public_base_url(): string
{
    $base = getenv('APP_PUBLIC_URL') ?: getenv('APP_MAIL_BASE_URL');
    if ($base === false || $base === '') {
        $server=(string)($_SERVER['SERVER_NAME']??'');
        if($server===''||preg_match('/[\s\x00-\x1f\x7f\/@?#]/',$server))throw new RuntimeException('Configurez APP_PUBLIC_URL avec l’adresse publique de cette installation.');
        if(filter_var($server,FILTER_VALIDATE_IP,FILTER_FLAG_IPV6))$server='['.$server.']';
        $https=(!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off')||($_SERVER['REQUEST_SCHEME']??'')==='https';
        $port=filter_var($_SERVER['SERVER_PORT']??($https?443:80),FILTER_VALIDATE_INT);
        if(!$port||$port<1||$port>65535)throw new RuntimeException('Port public invalide. Configurez APP_PUBLIC_URL.');
        $script = (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $path = rtrim(str_replace('\\', '/', dirname($script)), '/.');
        if (!preg_match('#^(?:/[A-Za-z0-9_.~%-]+)*$#D', $path)) throw new RuntimeException('Chemin public invalide. Configurez APP_PUBLIC_URL.');
        $base=($https?'https':'http').'://'.$server.(($https&&$port===443)||(!$https&&$port===80)?'':':'.$port).$path;
    }
    $parts = parse_url($base);
    if (!filter_var($base, FILTER_VALIDATE_URL) || !is_array($parts)
        || !in_array($parts['scheme'] ?? '', ['https','http'], true)
        || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
        || preg_match('/[\x00-\x20\x7f]/', $base)) {
        throw new RuntimeException('APP_PUBLIC_URL / APP_MAIL_BASE_URL doit être une URL absolue sans paramètres.');
    }
    return rtrim($base, '/');
}

function app_mail_link(string $parameter, string $token): string
{
    return app_public_base_url().'/?'.rawurlencode($parameter).'='.rawurlencode($token);
}

function app_mail_address_valid(string $address): bool
{
    return strlen($address) <= 254
        && !preg_match('/[\x00-\x20\x7f,;]/', $address)
        && filter_var($address, FILTER_VALIDATE_EMAIL) !== false;
}

function app_mail_headers(string $cc='',string $bcc=''): string
{
    foreach([$cc,$bcc] as $address)if($address!==''&&!app_mail_address_valid($address))throw new InvalidArgumentException('Adresse de copie invalide.');
    return ($cc!==''?"Cc: ".$cc."\r\n":'').($bcc!==''?"Bcc: ".$bcc."\r\n":'')."From: ".APP_MAIL_FROM_NAME." <".APP_MAIL_FROM_ADDRESS.">\r\n"
        ."Reply-To: ".APP_MAIL_FROM_ADDRESS."\r\n"
        ."MIME-Version: 1.0\r\n"
        ."Content-Type: text/plain; charset=UTF-8";
}

function deliver_app_mail(string $recipient, string $subject, string $body,string $cc='',string $bcc='',bool $authentication=false): bool
{
    if (getenv('APP_MAIL_ENABLED') === '0') return false;
    foreach ([$recipient, $cc, $bcc] as $index => $address) {
        if (($index === 0 || $address !== '') && !app_mail_address_valid($address)) {
            error_log('liike mail: adresse refusée');
            return false;
        }
    }
    if (trim($subject) === '' || strlen($subject) > 500 || preg_match('/[\x00-\x1f\x7f]/', $subject)
        || !mb_check_encoding($subject, 'UTF-8') || strlen($body) > 100000 || str_contains($body, "\0")) {
        error_log('liike mail: contenu refusé');
        return false;
    }
    $addresses = array_values(array_unique(array_map('strtolower', array_filter([$recipient, $cc, $bcc]))));
    $cost = count($addresses);
    $scope=$authentication?'delivery:authentication':'delivery:global';
    $rules = [
        [$scope, 60, $authentication?60:30, $cost],
        [$scope, 3600, $authentication?600:300, $cost],
        [$scope, 86400, $authentication?3000:1000, $cost],
    ];
    foreach ($addresses as $address) {
        $scope = ($authentication?'delivery:auth-email:':'delivery:email:').hash('sha256', $address);
        $rules[] = [$scope, 3600, 10, 1];
        $rules[] = [$scope, 86400, 30, 1];
    }
    // Reserve before calling mail(): failed transport attempts also consume budget.
    if (!app_mail_reserve($rules)) {
        error_log('liike mail: envoi différé par la protection');
        return false;
    }
    try {
        $config=SmtpConfig::read(dirname(__DIR__));
        if($config['enabled']){
            $mail=SmtpConfig::mailer($config,dirname(__DIR__));$mail->addAddress($recipient);
            if($cc!=='')$mail->addCC($cc);if($bcc!=='')$mail->addBCC($bcc);
            $mail->Subject=$subject;$mail->Body=$body;return $mail->send();
        }
        return @mail(
            $recipient,
            mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n"),
            $body,
            app_mail_headers($cc,$bcc),
            '-f'.APP_MAIL_FROM_ADDRESS
        );
    } catch (Throwable $exception) {
        error_log('liike mail: transport indisponible ('.get_class($exception).')');
        return false;
    }
}
