<?php

namespace Services\Pdf;

use Core\Config;

/**
 * POST на сервис печати. Адрес, токен, таймаут и формат листа — integration.printpdf из .env.
 */
class RemoteHtmlToPdf implements HtmlToPdfConverter
{
    public function __construct(
        private string $url,
        private string $token,
        private int $timeout,
        private string $format,
        private string $orientation
    ) {
    }

    public static function fromConfig(Config $config): self
    {
        $config->load();

        return new self(
            trim((string) $config->get('integration.printpdf.url', '')),
            (string) $config->get('integration.printpdf.token', ''),
            max(1, (int) $config->get('integration.printpdf.timeout', 40)),
            (string) $config->get('integration.printpdf.format', 'A4'),
            (string) $config->get('integration.printpdf.orientation', 'P')
        );
    }

    public function convert(string $pageUrl, array $options = []): string
    {
        if ($this->url === '') {
            throw new PdfConvertException('PRINTPDF_URL is empty');
        }
        if (!function_exists('curl_init')) {
            throw new PdfConvertException('curl is not available');
        }

        $format = trim((string) ($options['format'] ?? $this->format));
        $orientation = trim((string) ($options['orientation'] ?? $this->orientation));
        $query = http_build_query([
            'url' => $pageUrl,
            'format' => $format !== '' ? $format : 'A4',
            'orientation' => $orientation !== '' ? $orientation : 'P',
            'token' => $this->token,
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false || $body === '') {
            $reason = $errno === CURLE_OPERATION_TIMEDOUT ? 'timeout' : ($error !== '' ? $error : 'empty body');
            throw new PdfConvertException('printpdf failed: ' . $reason);
        }

        return $body;
    }
}
