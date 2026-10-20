<?php

namespace Services\Pdf;

/**
 * Внешний рендер HTML-страницы в PDF.
 * Реализация и адрес живут отдельно от сборки заключения.
 */
interface HtmlToPdfConverter
{
    /**
     * @param array{format?: string, orientation?: string} $options
     * @return string байты PDF
     */
    public function convert(string $pageUrl, array $options = []): string;
}
