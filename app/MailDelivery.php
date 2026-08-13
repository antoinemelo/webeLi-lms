<?php

declare(strict_types=1);

const APP_MAIL_FROM_ADDRESS = 'noreply@lms.webe.li';
const APP_MAIL_FROM_NAME = 'liike';

function app_mail_headers(): string
{
    return "From: ".APP_MAIL_FROM_NAME." <".APP_MAIL_FROM_ADDRESS.">\r\n"
        ."Reply-To: ".APP_MAIL_FROM_ADDRESS."\r\n"
        ."MIME-Version: 1.0\r\n"
        ."Content-Type: text/plain; charset=UTF-8";
}

function deliver_app_mail(string $recipient, string $subject, string $body): bool
{
    return @mail(
        $recipient,
        $subject,
        $body,
        app_mail_headers(),
        '-f'.APP_MAIL_FROM_ADDRESS
    );
}
