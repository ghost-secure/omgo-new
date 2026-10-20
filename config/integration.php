<?php

$env = static function (string $key, $default = null) {
    $value = getenv($key);
    return ($value === false || $value === '') ? $default : $value;
};

$envBool = static function (string $key, bool $default) use ($env): bool {
    $value = $env($key, null);
    if ($value === null) {
        return $default;
    }

    return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
};

$envInt = static function (string $key, int $default) use ($env): int {
    $value = $env($key, null);
    if ($value === null || !is_numeric($value)) {
        return $default;
    }

    return (int) $value;
};

$appEnv = (string) $env('APP_ENV', 'development');

return [
    'mail' => [
        // Вне production без SMTP письмо пишется в runtime/logs/mail.log, пароль в базе уже новый.
        'testmode' => $envBool('MAIL_TESTMODE', $appEnv !== 'production'),
        'issmtp' => $envBool('MAIL_IS_SMTP', false),
        'smtp_host' => (string) $env('MAIL_SMTP_HOST', ''),
        'smtp_port' => $envInt('MAIL_SMTP_PORT', 587),
        'smtp_auth' => $envBool('MAIL_SMTP_AUTH', true),
        'smtp_user' => (string) $env('MAIL_SMTP_USER', ''),
        'smtp_pass' => (string) $env('MAIL_SMTP_PASS', ''),
        'smtp_secure' => (string) $env('MAIL_SMTP_SECURE', 'tls'),
        'from_email' => (string) $env('MAIL_FROM_EMAIL', 'noreply@oncogenetic.org'),
        'from_name' => (string) $env('MAIL_FROM_NAME', 'OMGO'),
    ],

    'dadata' => [
        'token' => (string) $env('DADATA_TOKEN', ''),
    ],

    // Внешняя печать HTML → PDF. Класс Services\Pdf\RemoteHtmlToPdf, не сборка заключения.
    'printpdf' => [
        'url' => (string) $env('PRINTPDF_URL', ''),
        'token' => (string) $env('PRINTPDF_TOKEN', ''),
        'timeout' => $envInt('PRINTPDF_TIMEOUT', 40),
        'format' => (string) $env('PRINTPDF_FORMAT', 'A4'),
        'orientation' => (string) $env('PRINTPDF_ORIENTATION', 'P'),
        'alert_url' => (string) $env('PRINTPDF_ALERT_URL', ''),
        'alert_token' => (string) $env('PRINTPDF_ALERT_TOKEN', ''),
        'alert_project' => (string) $env('PRINTPDF_ALERT_PROJECT', ''),
        'alert_message' => (string) $env('PRINTPDF_ALERT_MESSAGE', '🚨 #ОМГО #Error: Проблема с генерацией отчета в PDF'),
        // 0 — ссылка /pdf/{guid}/ отдаёт HTML, сервис не вызывается. На проде 1.
        'remote' => $envBool('PRINTPDF_REMOTE', true),
    ],
];
