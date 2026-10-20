<?php

namespace Services\Pdf;

/**
 * Тело ответа /pdf/{guid}/: HTML для сервиса печати или байты PDF.
 */
class ConclusionHttp
{
    public function __construct(
        public int $status,
        public string $contentType,
        public string $body
    ) {
    }

    public static function text(string $body, int $status = 200): self
    {
        return new self($status, 'text/html; charset=UTF-8', $body);
    }

    public static function pdf(string $body): self
    {
        return new self(200, 'application/pdf', $body);
    }
}
