<?php

declare(strict_types=1);

require_once __DIR__.'/MailAbuseProtection.php';

const APP_MAIL_FROM_ADDRESS = 'noreply@lms.webe.li';
const APP_MAIL_FROM_NAME = 'liike';

/** Email links must never inherit an attacker-controlled HTTP Host header. */
function app_mail_link(string $parameter, string $token): string
{
    $base = getenv('APP_MAIL_BASE_URL');
    if ($base === false || $base === '') {
        $script = (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $path = rtrim(str_replace('\\', '/', dirname($script)), '/.');
        if (!preg_match('~^(?:/[A-Za-z0-9_-]+)*$~D', $path)) $path = '';
        $base = 'https://lms.webe.li'.$path;
    }
    $parts = parse_url($base);
    if (!filter_var($base, FILTER_VALIDATE_URL) || !is_array($parts)
        || !in_array($parts['scheme'] ?? '', ['https','http'], true)
        || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
        || preg_match('/[\x00-\x20\x7f]/', $base)) {
        throw new RuntimeException('APP_MAIL_BASE_URL doit être une URL absolue sans paramètres.');
    }
    return rtrim($base, '/').'/?'.rawurlencode($parameter).'='.rawurlencode($token);
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

function deliver_app_mail(string $recipient, string $subject, string $body,string $cc='',string $bcc=''): bool
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
    $rules = [
        ['delivery:global', 60, 30, $cost],
        ['delivery:global', 3600, 300, $cost],
        ['delivery:global', 86400, 1000, $cost],
    ];
    foreach ($addresses as $address) {
        $scope = 'delivery:email:'.hash('sha256', $address);
        $rules[] = [$scope, 3600, 10, 1];
        $rules[] = [$scope, 86400, 30, 1];
    }
    // Reserve before calling mail(): failed transport attempts also consume budget.
    if (!app_mail_reserve($rules)) {
        error_log('liike mail: envoi différé par la protection');
        return false;
    }
    try {
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
