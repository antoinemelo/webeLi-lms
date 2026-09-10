<?php

declare(strict_types=1);

const APP_MAIL_FROM_ADDRESS = 'noreply@lms.webe.li';
const APP_MAIL_FROM_NAME = 'liike';

function app_mail_headers(string $cc='',string $bcc=''): string
{
    foreach([$cc,$bcc] as $address)if($address!==''&&(!filter_var($address,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$address)))throw new InvalidArgumentException('Adresse de copie invalide.');
    return ($cc!==''?"Cc: ".$cc."\r\n":'').($bcc!==''?"Bcc: ".$bcc."\r\n":'')."From: ".APP_MAIL_FROM_NAME." <".APP_MAIL_FROM_ADDRESS.">\r\n"
        ."Reply-To: ".APP_MAIL_FROM_ADDRESS."\r\n"
        ."MIME-Version: 1.0\r\n"
        ."Content-Type: text/plain; charset=UTF-8";
}

function deliver_app_mail(string $recipient, string $subject, string $body,string $cc='',string $bcc=''): bool
{
    return @mail(
        $recipient,
        $subject,
        $body,
        app_mail_headers($cc,$bcc),
        '-f'.APP_MAIL_FROM_ADDRESS
    );
}
