<?php

namespace Services\Pdf;

use Core\Config;
use Core\Request;
use Services\AnalysisService;

/**
 * /pdf/{guid}/.
 * Без create и PRINTPDF_REMOTE=1 — проверка роли, одноразовый токен, внешний сервис, ответ PDF.
 * Без create и PRINTPDF_REMOTE=0 — тот же HTML, который забирает сервис, без вызова сервиса.
 * create=1 — сервис печати забирает HTML, сессии у него нет, пропуск — токен.
 */
class ConclusionPrint
{
    private const WAIT_MESSAGE = 'Время ожидания истекло, мы уже занимаемся решением проблемы.';

    public function __construct(
        private ConclusionDocument $document,
        private AnalysisService $analysis,
        private ConclusionPrintToken $tokens,
        private HtmlToPdfConverter $converter,
        private PrintFailureAlert $alert,
        private Config $config
    ) {
    }

    /**
     * @param array<string, mixed>|null $user
     */
    public function respond(string $guid, Request $request, ?array $user): ConclusionHttp
    {
        $guid = strtolower(trim($guid));
        if ($guid === '') {
            return ConclusionHttp::text('Заявка не найдена.');
        }

        if ((string) $request->get('create') === '1') {
            return $this->htmlForPrinter($guid, (string) $request->get('temp', ''));
        }

        try {
            $row = $this->document->findByGuid($guid);
        } catch (\Throwable $e) {
            error_log('ConclusionPrint find: ' . $e->getMessage());

            return ConclusionHttp::text('Заявка не найдена.');
        }
        if ($row === null) {
            return ConclusionHttp::text('Заявка не найдена.');
        }

        $denied = $this->analysis->denyReason($user, $row);
        if ($denied !== null) {
            return ConclusionHttp::text($denied);
        }

        if (!$this->remoteEnabled()) {
            try {
                return $this->htmlDocument($row);
            } catch (\Throwable $e) {
                error_log('ConclusionPrint html: ' . $e->getMessage());

                return ConclusionHttp::text('Заявка не найдена.');
            }
        }

        try {
            $token = $this->tokens->issue($guid);
            $pdf = $this->converter->convert($this->sourceUrl($guid, $token));
        } catch (PdfConvertException $e) {
            $this->alert->send($e->getMessage());

            return ConclusionHttp::text(self::WAIT_MESSAGE);
        } catch (\Throwable $e) {
            error_log('ConclusionPrint: ' . $e->getMessage());
            $this->alert->send($e->getMessage());

            return ConclusionHttp::text(self::WAIT_MESSAGE);
        }

        return ConclusionHttp::pdf($pdf);
    }

    private function htmlForPrinter(string $guid, string $token): ConclusionHttp
    {
        if ($token === '') {
            return ConclusionHttp::text('empty token');
        }
        if (!$this->tokens->consume($token, $guid)) {
            return ConclusionHttp::text('empty token');
        }

        try {
            $row = $this->document->findByGuid($guid);
            if ($row === null) {
                return ConclusionHttp::text('Заявка не найдена.');
            }

            return $this->htmlDocument($row);
        } catch (\Throwable $e) {
            error_log('ConclusionPrint html: ' . $e->getMessage());

            return ConclusionHttp::text('Заявка не найдена.');
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function htmlDocument(array $row): ConclusionHttp
    {
        return ConclusionHttp::text($this->document->html($row));
    }

    private function remoteEnabled(): bool
    {
        $this->config->load();

        return (bool) $this->config->get('integration.printpdf.remote', true);
    }

    private function sourceUrl(string $guid, string $token): string
    {
        $this->config->load();
        $base = rtrim((string) $this->config->get('app.base_url', ''), '/');

        return $base . '/pdf/' . rawurlencode($guid) . '/?create=1&temp=' . rawurlencode($token);
    }
}
