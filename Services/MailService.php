<?php

namespace Services;

use Core\Config;
use Core\Mail\AbstractMailer;

/**
 * Письма форм входа. SMTP через PHPMailer, если класс есть; иначе mail().
 * testmode пишет HTML в runtime/logs/mail.log и считает отправку успешной.
 */
class MailService extends AbstractMailer
{
    private array $config;

    public function __construct()
    {
        $config = Config::getInstance();
        $config->load();
        $this->config = (array) $config->get('integration.mail', []);
    }

    public function send($to, string $subject, string $htmlBody, array $options = []): bool
    {
        $address = trim((string) (is_array($to) ? ($to[0] ?? '') : $to));
        if ($address === '' || !filter_var($address, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if (!empty($this->config['testmode'])) {
            return $this->writeLog($address, $subject, $htmlBody);
        }

        if (!empty($this->config['issmtp']) && class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            return $this->sendSmtp($address, $subject, $htmlBody);
        }

        $from = trim((string) ($this->config['from_email'] ?? ''));
        $fromName = trim((string) ($this->config['from_name'] ?? ''));
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
        if ($from !== '') {
            $headers .= 'From: ' . ($fromName !== '' ? $fromName . ' ' : '') . '<' . $from . ">\r\n";
        }

        return @mail($address, $encodedSubject, $htmlBody, $headers);
    }

    private function sendSmtp(string $address, string $subject, string $htmlBody): bool
    {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = (string) ($this->config['smtp_host'] ?? '');
            $mail->Port = (int) ($this->config['smtp_port'] ?? 587);
            $mail->SMTPAuth = (bool) ($this->config['smtp_auth'] ?? true);
            if ($mail->SMTPAuth) {
                $mail->Username = (string) ($this->config['smtp_user'] ?? '');
                $mail->Password = (string) ($this->config['smtp_pass'] ?? '');
            }
            $secure = (string) ($this->config['smtp_secure'] ?? '');
            if ($secure !== '') {
                $mail->SMTPSecure = $secure;
            }
            $mail->CharSet = 'UTF-8';
            $mail->setFrom(
                (string) ($this->config['from_email'] ?? 'noreply@oncogenetic.org'),
                (string) ($this->config['from_name'] ?? 'OMGO')
            );
            $mail->addAddress($address);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;

            return $mail->send();
        } catch (\Throwable $e) {
            error_log('MailService SMTP: ' . $e->getMessage());

            return false;
        }
    }

    private function writeLog(string $address, string $subject, string $htmlBody): bool
    {
        $dir = defined('LOG_PATH') ? LOG_PATH : (dirname(__DIR__, 2) . '/runtime/logs');
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $line = "---- " . date('c') . " to={$address} subject={$subject}\n" . $htmlBody . "\n";

        return file_put_contents($dir . '/mail.log', $line, FILE_APPEND) !== false;
    }
}
