<?php

namespace UC;

use Core\Config;
use Core\Container;
use Core\Database\QueryBuilder;
use Services\AnalysisService;

/**
 * Поля заключения: подпись справочника, дата, «заполнено», материалы.
 * Условия остаются в template.php типа анализа.
 */
final class ConclusionView
{
    public static function begin(): string
    {
        $config = Config::getInstance();
        $config->load();
        $root = rtrim((string) $config->get('app.base_url', ''), '/');

        return '<html lang="en"><head><base href="' . $root . '/">'
            . '<meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
            . '<title>OMGO</title><style>'
            . '.page {font-family: Tahoma; max-width: 1100px; padding: 40px;}'
            . '.center {text-align: center;}'
            . '.xlarge {font-size: 120%}'
            . '.large {font-size: 120%}'
            . '.bold {font-weight: bold;}'
            . '.mt {margin-top: 15px;}'
            . '</style></head><body><div class="page">';
    }

    public static function end(): string
    {
        return '</div></body></html>';
    }

    /**
     * @param array<string, mixed> $record
     */
    public static function heading(array $record): string
    {
        $html = '<div class="center">' . self::nl2br($record, 'lab_fullname') . '</div>';
        $html .= '<div class="center">' . self::text($record, 'lab_name') . '</div>';
        $html .= '<div class="center mt large">ЗАКЛЮЧЕНИЕ</div>';
        $html .= '<div class="center mt large">' . self::text($record, 'type_fullname') . '</div>';
        $html .= '<div class="mt"></div>';
        $html .= '<div><b>ФИО пациента: </b>' . self::text($record, 'pacient_name') . ', ' . self::dateText($record, 'pacient_bd') . '</div>';
        $html .= '<div><b>Направляющий врач: </b>' . self::text($record, 'doctor_name') . '</div>';
        $html .= '<div><b>Диагноз: </b>' . self::name($record, 'view_diagnoz') . '</div>';
        $html .= '<div><b>Дата получения материала на&nbsp;исследование: </b>' . self::date($record, 'date_accept') . '</div>';
        $html .= self::materials($record);
        $html .= '<div><b>Номер пациента в исследовании: </b>' . (int) ($record['cat'] ?? 0) . '</div>';

        return $html;
    }

    /**
     * @param array<string, mixed> $record
     */
    public static function analysisTitle(array $record, bool $genes, bool $limits): string
    {
        $html = '<div class="mt"></div><div class="large">Анализ</div>';
        $html .= '<div>' . self::text($record, 'type_maxname') . '</div>';
        $html .= self::when($record, 'txt_metodica', '<div class="bold">Описание методики исследования:</div><div>' . self::text($record, 'txt_metodica') . '</div>');
        if ($genes) {
            $html .= self::when($record, 'txt_gen', '<div class="bold">Список исследованных генов:</div><div>' . self::text($record, 'txt_gen') . '</div>');
        }
        if ($limits) {
            $html .= self::when($record, 'txt_limit', '<div class="bold text-size-75">Ограничения методики:</div><div>' . self::text($record, 'txt_limit') . '</div>');
        }

        return $html;
    }

    /**
     * @param array<string, mixed> $record
     */
    public static function hrd(array $record): string
    {
        if ((int) ($record['view_test'] ?? 0) !== AnalysisService::TYPE_HRD) {
            return '';
        }

        $html = self::when(
            $record,
            'cnt_genom',
            '<div><strong>Показатель геномной нестабильности (GSS): </strong>' . self::number($record, 'cnt_genom') . '</div>'
        );
        if (self::filled($record, 'stat_genom')) {
            $html .= '<div><strong>Показатель геномной нестабильности (GSS): </strong>не определен</div>';
        }
        $html .= self::when(
            $record,
            'view_hrd',
            '<div><strong>Статус HRD (дефицита гомологичной рекомбинации): </strong>' . self::name($record, 'view_hrd') . '</div>'
        );

        return $html;
    }

    /**
     * @param array<string, mixed> $record
     */
    public static function metrics(array $record): string
    {
        if (!self::filled($record, 'cnt_readdepth') && !self::filled($record, 'cnt_snpdepth') && !self::filled($record, 'cnt_brcadepth')) {
            return '';
        }

        $html = '<div class="mt"></div><div class="large">Метрики качества</div>';
        $html .= self::when($record, 'cnt_readdepth', '<div><strong>Средняя глубина прочтений: </strong>' . self::number($record, 'cnt_readdepth') . '</div>');
        $html .= self::when($record, 'cnt_snpdepth', '<div><strong>Средняя глубина прочтений SNP: </strong>' . self::number($record, 'cnt_snpdepth') . '</div>');
        $html .= self::when($record, 'cnt_brcadepth', '<div><strong>Средняя глубина прочтений BRCA1/2: </strong>' . self::number($record, 'cnt_brcadepth') . '</div>');

        return $html;
    }

    /**
     * @param array<string, mixed> $record
     */
    public static function signature(array $record): string
    {
        return '<div class="mt">'
            . '<div><strong>Текущая дата: </strong>' . self::date($record, 'date_execauto') . '</div>'
            . '<div><strong>Исследование выполнил(а): </strong>' . self::text($record, 'lab_resp') . '</div>'
            . '<div><strong>Контакты лаборатории: </strong>тел.: ' . self::text($record, 'lab_phone')
            . ', e-mail: ' . self::text($record, 'lab_email') . '</div>';
    }

    /**
     * @param array<string, mixed> $record
     */
    public static function filled(array $record, string $field): bool
    {
        $value = $record[$field] ?? null;
        if ($value === null) {
            return false;
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value != 0.0;
        }
        $text = (string) $value;

        return $text !== '' && $text !== '0' && $text !== '0.00' && $text !== '0000-00-00';
    }

    /**
     * @param array<string, mixed> $record
     */
    public static function when(array $record, string $field, string $html): string
    {
        return self::filled($record, $field) ? $html : '';
    }

    /**
     * @param array<string, mixed> $record
     */
    public static function text(array $record, string $field): string
    {
        $value = $record[$field] ?? '';

        return stripslashes(html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * @param array<string, mixed> $record
     */
    public static function nl2br(array $record, string $field): string
    {
        return nl2br(self::text($record, $field));
    }

    /**
     * @param array<string, mixed> $record
     */
    public static function name(array $record, string $field): string
    {
        return self::text($record, $field . '_name');
    }

    /**
     * @param array<string, mixed> $record
     */
    public static function number(array $record, string $field): string
    {
        return (string) (int) ($record[$field] ?? 0);
    }

    /**
     * @param array<string, mixed> $record
     */
    public static function date(array $record, string $field): string
    {
        $raw = $record[$field] ?? null;
        if (!self::filled($record, $field) || !is_numeric($raw)) {
            return '';
        }

        return date('d.m.Y', (int) $raw);
    }

    /**
     * @param array<string, mixed> $record
     */
    public static function dateText(array $record, string $field): string
    {
        $raw = $record[$field] ?? null;
        if (!is_string($raw) || preg_match('/^\d{4}-\d{2}-\d{2}/', $raw) !== 1) {
            return self::text($record, $field);
        }
        $stamp = strtotime(substr($raw, 0, 10));

        return $stamp === false ? $raw : date('d.m.Y', $stamp);
    }

    /**
     * @param array<string, mixed> $record
     */
    public static function materials(array $record): string
    {
        if ((int) ($record['view_test'] ?? 0) === AnalysisService::TYPE_MLPA) {
            return '<b>Материал: </b> Периферическая венозная кровь';
        }

        $items = [];
        $selected = trim((string) ($record['multiselect_materialexec'] ?? ''));
        $pairs = [
            1 => ['stat_materialgisto', 'блок с опухолевой тканью', 'varc_markgisto'],
            2 => ['stat_materialblood', 'кровь', 'varc_markblood'],
            3 => ['stat_materialnormal', 'блок с нормальной тканью', 'varc_marknormal'],
        ];
        if ($selected === '') {
            foreach ($pairs as $pair) {
                if ((int) ($record[$pair[0]] ?? 0) === 1) {
                    $items[] = self::materialLine($pair[1], (string) ($record[$pair[2]] ?? ''));
                }
            }

            return implode('', $items);
        }

        $ids = array_map('intval', explode(',', $selected));
        foreach ($pairs as $id => $pair) {
            if (in_array($id, $ids, true)) {
                $items[] = self::materialLine($pair[1], (string) ($record[$pair[2]] ?? ''));
            }
        }

        return implode('', $items);
    }

    /**
     * @param array<string, mixed> $record
     */
    public static function expressionProtein(array $record): string
    {
        $result = (string) ($record['varc_result'] ?? '');
        if ($result === AnalysisService::PTEN_EXPRESSION_PRESENT) {
            return 'Рекомендовано определение мутаций в генах PIK3CA/AKT/PTEN методом NGS';
        }
        if ($result === AnalysisService::PTEN_EXPRESSION_LOSS) {
            return 'Выявлено выпадение экспрессии белка PTEN более чем в 90% клеток опухоли - иммуногистохимический признак функциональной альтерации сигнального пути PIKCA/AKT/PTEN';
        }

        return '';
    }

    public static function onko(int $analysisId): string
    {
        if ($analysisId <= 0) {
            return '';
        }

        $db = Container::getInstance()->get(QueryBuilder::class);
        $rows = $db->reset()
            ->select(['o.varc_variant', 'o.varc_allel', 'o.cnt_vardepth', 'c.varc_name AS onko_name'])
            ->from('t_analysis_onko o')
            ->join('t_ontoclass c', 'c.cat = o.view_onko', 'LEFT')
            ->where(['o.view_analysis' => $analysisId])
            ->order(['o.cat' => 'ASC'])
            ->get();
        if (!is_array($rows)) {
            return '';
        }

        $html = '';
        foreach ($rows as $row) {
            $line = is_array($row) ? $row : (array) $row;
            $html .= self::when($line, 'varc_variant', '<div><strong>Название варианта: </strong>' . self::text($line, 'varc_variant') . '</div>');
            $html .= self::when($line, 'varc_allel', '<div class="text-size-75"><b>Частота аллеля: </b>' . self::text($line, 'varc_allel') . '</div>');
            $html .= self::when($line, 'cnt_vardepth', '<div class="text-size-75"><strong>Глубина прочтения варианта: </strong>' . self::number($line, 'cnt_vardepth') . '</div>');
            $html .= self::when($line, 'onko_name', '<div><strong>Класс онкогенности: </strong>' . self::text($line, 'onko_name') . '</div>');
        }

        return $html;
    }

    private static function materialLine(string $title, string $mark): string
    {
        return '<div><b>Материал: </b> ' . $title . "\t" . $mark . '</nobr></div>';
    }
}
