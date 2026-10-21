<?php

namespace Services\Report;

use Services\AnalysisWorkflowService;

/**
 * Столбцы заранее собранного Excel-отчёта по заявкам.
 * Легаси: Object\Report\Config (omgo-dev).
 *
 * lab — полный набор. sponsor — без ФИО пациента.
 * auditor — без персональных полей, длинные тексты до 100 символов.
 */
class AnalysisReportColumns
{
    public const LAB = 'lab';
    public const SPONSOR = 'sponsor';
    public const AUDITOR = 'auditor';

    /** Граница архива и актуального файла. Легаси report-alltest-new.php. */
    public const SPLIT_AT = '2026-01-01 00:00:00';
    public const LABEL_BEFORE = 'До 1 января 2026';
    public const LABEL_AFTER = 'После 1 января 2026';

    public const FMT_VIEW = 'view';
    public const FMT_USER = 'user';
    public const FMT_DATETIME = 'datetime';
    public const FMT_DATE = 'date';
    public const FMT_MONTHYEAR = 'monthyear';
    public const FMT_STAT = 'stat';
    public const FMT_MAP = 'map';
    public const FMT_STRIPTAGS = 'striptags';
    public const FMT_FILE = 'file';
    public const FMT_IMAGES = 'images';
    public const FMT_CONCAT = 'concat';
    public const FMT_CNT = 'cnt';

    /**
     * @return list<array<string, mixed>>
     */
    public static function columns(string $code): array
    {
        if ($code === self::LAB) {
            return self::all();
        }
        if ($code === self::SPONSOR) {
            return self::without(['custom_templateparam_name']);
        }
        if ($code === self::AUDITOR) {
            return self::withCrop(self::without([
                'varc_guid',
                'custom_templateparam_code',
                'custom_templateparam_name',
                'datetext_bd',
                'view_code',
                'doc_agree',
                'gallery_gisto',
                'varc_markblood',
                'varc_markgisto',
            ]), [
                'txt_conclusion' => 100,
                'txt_gen' => 100,
                'txt_limit' => 100,
                'txt_metodica' => 100,
                'txt_gencomment' => 100,
            ]);
        }

        throw new \InvalidArgumentException('Неизвестный отчёт: ' . $code);
    }

    /**
     * @return list<string>
     */
    public static function titles(string $code): array
    {
        $titles = [];
        foreach (self::columns($code) as $column) {
            $titles[] = (string) $column['title'];
        }

        return $titles;
    }

    /**
     * @return list<array{table: string, on: string, type: string}>
     */
    public static function joins(): array
    {
        return [
            ['table' => 't_pacient a', 'on' => 'a.cat = v.view_pacient', 'type' => 'LEFT'],
            ['table' => 't_users d', 'on' => 'd.cat = v.user_doctor', 'type' => 'LEFT'],
            ['table' => 't_analiztype aty', 'on' => 'aty.cat = v.view_test', 'type' => 'INNER'],
            ['table' => 't_lab l', 'on' => 'l.cat = v.view_lab', 'type' => 'LEFT'],
            ['table' => 't_lab_metodic lm', 'on' => 'lm.view_lab = l.cat AND lm.view_analiztype = v.view_test', 'type' => 'LEFT'],
        ];
    }

    /**
     * @param list<string> $keys
     * @return list<array<string, mixed>>
     */
    private static function without(array $keys): array
    {
        $skip = array_flip($keys);
        $columns = [];
        foreach (self::all() as $column) {
            if (!isset($skip[$column['key']])) {
                $columns[] = $column;
            }
        }
        if ($columns === []) {
            throw new \InvalidArgumentException('Пустой набор столбцов');
        }

        return $columns;
    }

    /**
     * @param list<array<string, mixed>> $columns
     * @param array<string, int> $limits
     * @return list<array<string, mixed>>
     */
    private static function withCrop(array $columns, array $limits): array
    {
        foreach ($columns as $i => $column) {
            if (isset($limits[$column['key']])) {
                $columns[$i]['crop'] = $limits[$column['key']];
            }
        }

        return $columns;
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private static function col(string $key, string $title, ?string $format = null, array $extra = []): array
    {
        return array_merge([
            'key' => $key,
            'title' => $title,
            'format' => $format,
        ], $extra);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function all(): array
    {
        $badDelivery = AnalysisWorkflowService::EVENT_REJECTED_BADDELIVERY;
        $badMaterial = AnalysisWorkflowService::EVENT_REJECTED_BADMATERIAL;
        $courierSent = AnalysisWorkflowService::EVENT_MOVING;

        return [
            self::col('cat', '#ID'),
            self::col('varc_guid', 'GUID'),
            self::col('author_add', 'Автор записи', self::FMT_USER),
            self::col('created_add', 'Дата записи', self::FMT_DATETIME),
            self::col('date_correctmaterial', 'Дата поступления корректного материала', self::FMT_DATE),
            self::col('custom_templateparam_code', 'Условный код', null, [
                'expr' => 'a.varc_code',
            ]),
            self::col('custom_templateparam_name', 'Пациент', null, [
                'expr' => "CONCAT(a.varc_surname, ' ', a.varc_name, ' ', a.varc_patron)",
            ]),
            self::col('datetext_bd', 'Дата рождения', null, [
                'expr' => 'a.datetext_bd',
            ]),
            self::col('custom_templateparam_male', 'Пол', null, [
                'expr' => '(SELECT varc_name FROM t_male WHERE cat = a.view_male)',
            ]),
            self::col('custom_templateparam_pregion', 'Регион пациента', null, [
                'expr' => "(SELECT CONCAT(r.varc_name, ' ', r.varc_type) FROM t_geo_region r WHERE cat = a.view_region)",
            ]),
            self::col('view_code', 'Проверочный код', self::FMT_VIEW),
            self::col('view_test', 'Тест', self::FMT_VIEW),
            self::col('user_doctor', 'Врач', self::FMT_USER),
            self::col('custom_templateparam_region', 'Регион врача', null, [
                'expr' => "(SELECT CONCAT(r.varc_name, ' ', r.varc_type) FROM t_geo_region r WHERE cat = d.view_region)",
            ]),
            self::col('view_lab', 'Лаборатория', self::FMT_VIEW),
            self::col('custom_templateparam_med', 'ЛПУ врача', null, [
                'expr' => '(SELECT varc_name FROM t_med WHERE cat = d.view_med)',
            ]),
            self::col('doc_agree', 'Информированное согласие', self::FMT_FILE),
            self::col('view_status', 'Статус', self::FMT_VIEW),
            self::col('wrongsenddate', 'Некорректная отправка: дата', self::FMT_DATE, [
                'expr' => '(SELECT e.created_add FROM t_events e WHERE e.cnt_object = v.cat AND e.view_type = ' . $badDelivery . ' LIMIT 1)',
            ]),
            self::col('badmaterialdate', 'Материал полностью непригоден: дата', self::FMT_DATE, [
                'expr' => '(SELECT e.created_add FROM t_events e WHERE e.cnt_object = v.cat AND e.view_type = ' . $badMaterial . ' LIMIT 1)',
            ]),
            self::col('couriersenddate', 'Курьер отправлен', self::FMT_DATE, [
                'expr' => '(SELECT e.created_add FROM t_events e WHERE e.cnt_object = v.cat AND e.view_type = ' . $courierSent . ' LIMIT 1)',
            ]),
            self::col('view_diagnoz', 'Диагноз', self::FMT_VIEW),
            self::col('view_localization', 'Локализация', self::FMT_VIEW),
            self::col('view_illness', 'Заболевание', self::FMT_VIEW),
            self::col('view_gistotype', 'Гистологический подтип', self::FMT_VIEW),
            self::col('varc_gistotype', 'Гистологический подтип (другое)'),
            self::col('gallery_gisto', 'Гистологическое заключение', self::FMT_IMAGES),
            self::col('view_stage', 'Стадия', self::FMT_VIEW),
            self::col('view_progressing', 'Прогрессирование', self::FMT_VIEW),
            self::col('view_myonkoan', 'Личный онкологический анамнез', self::FMT_VIEW),
            self::col('varc_myonkoan', 'Личный онкологический анамнез (другое)'),
            self::col('stat_chem', 'Химиотерапевтическое лечение', self::FMT_STAT),
            self::col('view_chem', 'Химиотерапевтическое лечение', self::FMT_VIEW),
            self::col('stat_materialblood', 'Кровь', self::FMT_STAT),
            self::col('varc_markblood', 'Маркировка'),
            self::col('stat_materialgisto', 'Гистология', self::FMT_STAT),
            self::col('varc_markgisto', 'Номер блока'),
            self::col('cnt_vardepth', 'Глубина прочтения варианта', self::FMT_CNT),
            self::col('cnt_genom', 'Показатель геномной нестабильности (GSS)', self::FMT_CNT),
            self::col('cnt_readdepth', 'Средняя глубина прочтений', self::FMT_CNT),
            self::col('cnt_snpdepth', 'Средняя глубина прочтений SNP', self::FMT_CNT),
            self::col('cnt_brcadepth', 'Средняя глубина прочтений BRCA1/2', self::FMT_CNT),
            self::col('date_accept', 'Дата поступления', self::FMT_DATE),
            self::col('date_exec', 'Дата выполнения', self::FMT_DATE, [
                'fallback' => 'date_execauto',
            ]),
            self::col('date_execauto', 'Дата выполнения по воркфлоу', self::FMT_DATE),
            self::col('varc_variant', 'Название варианта', self::FMT_CONCAT, [
                'with' => [
                    '_varc_variant_group' => '(SELECT GROUP_CONCAT(o.varc_variant) FROM t_analysis_onko o WHERE o.view_analysis = v.cat)',
                ],
            ]),
            self::col('varc_allel', 'Частота аллеля'),
            self::col('view_reagent', 'Используемые реагенты', self::FMT_VIEW),
            self::col('view_result', 'Результат', self::FMT_VIEW),
            self::col('view_patogen', 'Класс патогенности', self::FMT_VIEW),
            self::col('view_hrd', 'Статус HRD', self::FMT_VIEW),
            self::col('view_method', 'Метод анализа', self::FMT_VIEW),
            self::col('txt_conclusion', 'Заключение', self::FMT_STRIPTAGS),
            self::col('txt_gen', 'Список исследованных генов', self::FMT_STRIPTAGS),
            self::col('txt_limit', 'Ограничения методики', self::FMT_STRIPTAGS),
            self::col('txt_metodica', 'Описание методики исследования', self::FMT_STRIPTAGS),
            self::col('txt_gencomment', 'Комментарий генетика', self::FMT_STRIPTAGS),
            self::col('stat_copiynost', 'Изменение копийности', self::FMT_MAP, [
                'map' => [1 => 'Да', 2 => 'Нет'],
            ]),
            self::col('varc_ecozone', 'Затрагивает экзоны'),
            self::col('varc_coords', 'Координаты'),
            self::col('view_linetherapy', 'Линия терапии', self::FMT_VIEW),
            self::col('date_starttherapy', 'Дата начала терапии на линии', self::FMT_MONTHYEAR),
            self::col('stat_hasprogress', 'Наличие признаков прогрессирования', self::FMT_STAT),
            self::col('view_confirmprogress', 'Подтверждение прогрессирования', self::FMT_VIEW),
            self::col('view_teraphyduration', 'Длительность терапии', self::FMT_VIEW),
            self::col('cnt_agediagnoz', 'Возраст пациента на момент постановки диагноза', self::FMT_CNT),
            self::col('cnt_glisson', 'Индекс по шкале Глисона', self::FMT_CNT),
            self::col('varc_spread', 'Распространенность'),
            self::col('varc_hormonesensitive', 'Чувствительность к гормональной терапии'),
            self::col('varc_result', 'Результат игх исследования'),
            self::col('cnt_noexpression', 'Процент клеток с отсутствием экспрессии', self::FMT_CNT),
            self::col('varc_antitelo', 'Используемое антитело/клон'),
            self::col('varc_noresultreason', 'Причина невозможности оценки'),
            self::col('varc_testpten', 'Проводилось ли тестирование на PIK3CA/AKT/PTEN (PCR/NGS)'),
        ];
    }
}
