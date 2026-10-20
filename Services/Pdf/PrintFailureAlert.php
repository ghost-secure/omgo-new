<?php

namespace Services\Pdf;

use Core\Config;

/**
 * Сигнал, что внешняя печать не ответила.
 * Адрес и токен пустые — запрос не уходит, сбой только в error_log.
 */
class PrintFailureAlert
{
    public function __construct(
        private string $url,
        private string $token,
        private string $project,
        private string $message
    ) {
    }

    public static function fromConfig(Config $config): self
    {
        $config->load();

        return new self(
            trim((string) $config->get('integration.printpdf.alert_url', '')),
            (string) $config->get('integration.printpdf.alert_token', ''),
            (string) $config->get('integration.printpdf.alert_project', ''),
            (string) $config->get('integration.printpdf.alert_message', '🚨 #ОМГО #Error: Проблема с генерацией отчета в PDF')
        );
    }

    public function send(string $reason): void
    {
        error_log('PrintFailureAlert: ' . $reason);
        if ($this->url === '' || $this->token === '' || !function_exists('curl_init')) {
            return;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Cache-Control: no-cache',
            'Pragma: no-cache',
            'Authorization: ' . $this->token,
        ]);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'project' => $this->project,
            'message' => $this->message,
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_exec($ch);
        curl_close($ch);
    }
}
