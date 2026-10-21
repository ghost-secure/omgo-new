<?php

namespace Services\Report;

use RuntimeException;
use XMLWriter;
use ZipArchive;

/**
 * Потоковый xlsx крона отчётов. Строка пишется на диск и в книге не копится.
 * Выгрузки грида по-прежнему идут через Core\Excel.
 */
final class AnalysisReportSheet
{
    private const TITLE_STYLE = 1;
    private const HEADER_STYLE = 2;

    private ?XMLWriter $xml = null;
    private ?string $partPath = null;
    private ?string $sheetPath = null;
    private int $columns = 0;
    private int $row = 0;

    /** @var array<int, string> */
    private array $letters = [];

    public function __construct(private readonly string $path)
    {
    }

    public function begin(int $columns): void
    {
        if ($columns < 1) {
            throw new RuntimeException('В отчёте нет столбцов');
        }
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('Расширение zip не загружено');
        }
        $this->columns = $columns;
        $dir = dirname($this->path);
        if ($dir !== '' && !is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Не удалось создать ' . $dir);
        }
        $this->partPath = $this->path . '.part';
        $this->sheetPath = $this->partPath . '.sheet.xml';
        if (is_file($this->partPath)) {
            unlink($this->partPath);
        }
        if (is_file($this->sheetPath)) {
            unlink($this->sheetPath);
        }
        $this->letters = [];
        for ($i = 1; $i <= $columns; $i++) {
            $this->letters[$i] = $this->letter($i);
        }

        $xml = new XMLWriter();
        if (!$xml->openUri($this->sheetPath)) {
            throw new RuntimeException('Не удалось открыть лист отчёта');
        }
        $this->xml = $xml;
        $xml->startDocument('1.0', 'UTF-8', 'yes');
        $xml->startElement('worksheet');
        $xml->writeAttribute('xmlns', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $this->writeViews();
        $this->writeCols();
        $xml->startElement('sheetData');
        $this->row = 0;
    }

    public function title(string $text): void
    {
        $this->row++;
        $this->startRow(30);
        $this->inline($this->letters[1] . $this->row, $text, self::TITLE_STYLE);
        $this->xml()->endElement();
    }

    /**
     * @param list<string> $titles
     */
    public function header(array $titles): void
    {
        $this->row++;
        $this->startRow(32);
        $col = 1;
        foreach ($titles as $title) {
            if ($col > $this->columns) {
                break;
            }
            $this->inline($this->letters[$col] . $this->row, $title, self::HEADER_STYLE);
            $col++;
        }
        $this->xml()->endElement();
    }

    /**
     * @param list<string> $cells
     */
    public function data(array $cells): void
    {
        $this->row++;
        $this->startRow(null);
        $col = 1;
        foreach ($cells as $cell) {
            if ($col > $this->columns) {
                break;
            }
            $this->inline($this->letters[$col] . $this->row, $cell, null);
            $col++;
        }
        $this->xml()->endElement();
        if ($this->row % 500 === 0) {
            $this->xml()->flush();
        }
    }

    public function finish(): void
    {
        $xml = $this->xml();
        $xml->endElement();
        $this->writeMerge();
        $xml->endElement();
        $xml->endDocument();
        $xml->flush();
        $this->xml = null;

        $this->zip();
        $this->dropSheet();
        if (is_file($this->path)) {
            unlink($this->path);
        }
        if (!is_file($this->partPath) || !rename($this->partPath, $this->path)) {
            if ($this->partPath !== null && is_file($this->partPath)) {
                unlink($this->partPath);
            }
            throw new RuntimeException('Не удалось сохранить ' . $this->path);
        }
        $this->partPath = null;
    }

    public function abort(): void
    {
        if ($this->xml !== null) {
            $this->xml->flush();
            $this->xml = null;
        }
        $this->dropSheet();
        if ($this->partPath !== null && is_file($this->partPath)) {
            unlink($this->partPath);
        }
        $this->partPath = null;
    }

    private function writeViews(): void
    {
        $xml = $this->xml();
        $xml->startElement('sheetViews');
        $xml->startElement('sheetView');
        $xml->writeAttribute('tabSelected', '1');
        $xml->writeAttribute('workbookViewId', '0');
        $xml->startElement('pane');
        $xml->writeAttribute('ySplit', '2');
        $xml->writeAttribute('topLeftCell', 'A3');
        $xml->writeAttribute('activePane', 'bottomLeft');
        $xml->writeAttribute('state', 'frozen');
        $xml->endElement();
        $xml->endElement();
        $xml->endElement();
    }

    private function writeCols(): void
    {
        $xml = $this->xml();
        $xml->startElement('cols');
        $xml->startElement('col');
        $xml->writeAttribute('min', '1');
        $xml->writeAttribute('max', (string) $this->columns);
        $xml->writeAttribute('width', '10');
        $xml->writeAttribute('customWidth', '1');
        $xml->endElement();
        $xml->endElement();
    }

    private function writeMerge(): void
    {
        $end = min(11, $this->columns);
        $xml = $this->xml();
        $xml->startElement('mergeCells');
        $xml->writeAttribute('count', '1');
        $xml->startElement('mergeCell');
        $xml->writeAttribute('ref', 'A1:' . $this->letters[$end] . '1');
        $xml->endElement();
        $xml->endElement();
    }

    private function startRow(?int $height): void
    {
        $xml = $this->xml();
        $xml->startElement('row');
        $xml->writeAttribute('r', (string) $this->row);
        if ($height !== null) {
            $xml->writeAttribute('ht', (string) $height);
            $xml->writeAttribute('customHeight', '1');
        }
    }

    private function inline(string $ref, string $text, ?int $style): void
    {
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text) ?? '';
        $xml = $this->xml();
        $xml->startElement('c');
        $xml->writeAttribute('r', $ref);
        $xml->writeAttribute('t', 'inlineStr');
        if ($style !== null) {
            $xml->writeAttribute('s', (string) $style);
        }
        $xml->startElement('is');
        $xml->startElement('t');
        if ($text !== '' && ($text[0] === ' ' || str_ends_with($text, ' ') || str_contains($text, "\n"))) {
            $xml->writeAttribute('xml:space', 'preserve');
        }
        $xml->text($text);
        $xml->endElement();
        $xml->endElement();
        $xml->endElement();
    }

    private function zip(): void
    {
        $part = $this->partPath;
        $sheet = $this->sheetPath;
        if ($part === null || $sheet === null || !is_file($sheet)) {
            throw new RuntimeException('Лист отчёта не записан');
        }
        $zip = new ZipArchive();
        $opened = $zip->open($part, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($opened !== true) {
            throw new RuntimeException('Не удалось собрать xlsx');
        }
        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rootRels());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());
        $zip->addFromString('xl/styles.xml', $this->styles());
        $zip->addFile($sheet, 'xl/worksheets/sheet1.xml');
        if (!$zip->close()) {
            throw new RuntimeException('Не удалось закрыть xlsx');
        }
    }

    private function dropSheet(): void
    {
        if ($this->sheetPath !== null && is_file($this->sheetPath)) {
            unlink($this->sheetPath);
        }
        $this->sheetPath = null;
    }

    private function xml(): XMLWriter
    {
        if ($this->xml === null) {
            throw new RuntimeException('Лист отчёта уже закрыт');
        }

        return $this->xml;
    }

    private function letter(int $index): string
    {
        $letter = '';
        while ($index > 0) {
            $index--;
            $letter = chr(65 + ($index % 26)) . $letter;
            $index = intdiv($index, 26);
        }

        return $letter;
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function workbook(): string
    {
        $name = htmlspecialchars('Все пациенты', ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . $name . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private function workbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="3">'
            . '<font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="16"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="2">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '</fills>'
            . '<borders count="2">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border>'
            . '<left style="thick"><color auto="1"/></left>'
            . '<right style="thick"><color auto="1"/></right>'
            . '<top style="thick"><color auto="1"/></top>'
            . '<bottom style="thick"><color auto="1"/></bottom>'
            . '<diagonal/>'
            . '</border>'
            . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="3">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            . '</cellXfs>'
            . '</styleSheet>';
    }
}
