<?php

namespace Services;

use Core\Database\QueryBuilder;

/**
 * Анализы и связанные сущности кабинета.
 * Легаси: `Object\Analysis\Analysis`.
 */
class AnalysisService
{
    public const TBL_ANALYSIS = 't_analysis';
    public const TBL_ROUTE = 't_analysis_route';

    /** Легаси `Object\Analysis\Analysis::TYPE_*` — те, что есть в радио пула кодов. */
    public const TYPE_HRD = 1;
    public const TYPE_AKT = 3;
    public const TYPE_BRCA = 6;
    public const TYPE_PTEN = 7;
    public const TYPE_HRR = 8;

    public function __construct(private QueryBuilder $db)
    {
    }

    /**
     * Строка лаборатории `t_lab` по cat.
     */
    public function getLabInfo(int $labId): ?object
    {
        if ($labId <= 0) {
            return null;
        }

        return $this->db->reset()
            ->select(['*'])
            ->from('t_lab')
            ->where(['cat' => $labId])
            ->getOne();
    }

    /**
     * Легаси `stat_alldoctors = 1`: лаборатория видит всех врачей, без фильтра по своей лабе.
     * Колонки в дампе может не быть — тогда считаем, что флага нет.
     */
    public function labSeesAllDoctors(?object $lab): bool
    {
        if ($lab === null) {
            return false;
        }

        return (int) ($lab->stat_alldoctors ?? 0) === 1;
    }

    /**
     * Лаборатории для селекта маршрута. Легаси `Dataprovider::getAllLabs()`.
     *
     * @return list<array{id:int,name:string}>
     */
    public function getLabs(): array
    {
        $rows = $this->db->reset()
            ->select(['cat', 'varc_name'])
            ->from('t_lab')
            ->order(['cat' => 'ASC'])
            ->get();

        $out = [];
        if (!is_array($rows)) {
            return $out;
        }

        foreach ($rows as $row) {
            $arr = is_object($row) ? (array) $row : (is_array($row) ? $row : []);
            $id = (int) ($arr['cat'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $name = trim((string) ($arr['varc_name'] ?? ''));
            $out[] = [
                'id' => $id,
                'name' => $name !== '' ? $name : ('ID ' . $id),
            ];
        }

        return $out;
    }

    /** Мнемоники фона строки: var(--color-bg-*). Совпадают с легаси uikit. */
    public const ROW_BG_TOKENS = ['green', 'blue', 'yellow', 'red', 'lgrey'];

    /**
     * Мнемоники сплошного цвета: класс bg-{token}.
     * pink — переменная --pink, остальные — --color-{token}.
     */
    public const SOLID_COLOR_TOKENS = [
        'green', 'blue', 'yellow', 'red', 'lgrey', 'purple', 'deepgreen', 'pink',
    ];

    /**
     * Грид заявок t_analysis: столбцы легаси блока 54 (код, ФИО, дубль, регионы).
     * Фильтр по врачу или роли сюда не входит — его добавляет страница.
     * Цвета: t_analiz_status.varc_color и t_analiztype.varc_color — мнемоники, не hex.
     *
     * @return array{joins: list<array<string, string>>, addSelect: list<string>, sort: array<string, string>, sortColumnExpressions: array<string, string>, filterColumnExpressions: array<string, array<string, string>>}
     */
    public function analysisGridQuery(): array
    {
        $pacientName = "CONCAT(pac.varc_surname, ' ', pac.varc_name, ' ', pac.varc_patron)";
        $doctorName = "CONCAT(doc.varc_surname, ' ', doc.varc_name, ' ', doc.varc_patron)";
        $doctorRegion = "(SELECT CONCAT(r.varc_name, ' ', r.varc_type) FROM t_geo_region r WHERE r.cat = doc.view_region)";
        $pacientRegion = "(SELECT CONCAT(r.varc_name, ' ', r.varc_type) FROM t_geo_region r WHERE r.cat = pac.view_region)";
        $pacientMale = '(SELECT male.varc_name FROM t_male male WHERE male.cat = pac.view_male)';
        $analysisDouble = 'CASE
    WHEN EXISTS (
        SELECT 1 FROM t_analysis dup
        WHERE dup.view_pacient > 0
          AND dup.view_pacient = t_analysis.view_pacient
          AND dup.view_test = t_analysis.view_test
          AND dup.cat <> t_analysis.cat
          AND dup.view_lab <> t_analysis.view_lab
    ) THEN 2
    WHEN EXISTS (
        SELECT 1 FROM t_analysis dup
        WHERE dup.view_pacient > 0
          AND dup.view_pacient = t_analysis.view_pacient
          AND dup.view_test = t_analysis.view_test
          AND dup.cat <> t_analysis.cat
    ) THEN 1
    ELSE 0
END';

        $statusColor = '(SELECT st.varc_color FROM t_analiz_status st WHERE st.cat = t_analysis.view_status)';
        $testColor = '(SELECT ty.varc_color FROM t_analiztype ty WHERE ty.cat = t_analysis.view_test)';
        $like = static function (string $columnSql): array {
            return [
                'where' => '{cond}',
                'column_sql' => $columnSql,
                'compare' => 'like',
            ];
        };

        return [
            'joins' => [
                [
                    'type' => 'LEFT',
                    'table' => 't_pacient',
                    'alias' => 'pac',
                    'on' => 'pac.cat = t_analysis.view_pacient',
                ],
                [
                    'type' => 'LEFT',
                    'table' => 't_users',
                    'alias' => 'doc',
                    'on' => 'doc.cat = t_analysis.user_doctor',
                ],
            ],
            'addSelect' => [
                'pac.varc_code AS pacient_code',
                $pacientName . ' AS pacient_name',
                'pac.datetext_bd AS pacient_bd',
                $pacientMale . ' AS pacient_male',
                $doctorRegion . ' AS doctor_region',
                $doctorName . ' AS doctor_name',
                $pacientRegion . ' AS pacient_region',
                $analysisDouble . ' AS analysis_double',
                $statusColor . ' AS status_color',
                $testColor . ' AS test_color',
            ],
            'sort' => ['created_add' => 'desc'],
            'sortColumnExpressions' => [
                'pacient_code' => 'pac.varc_code',
                'pacient_name' => $pacientName,
                'pacient_bd' => 'pac.datetext_bd',
                'pacient_male' => $pacientMale,
                'doctor_region' => $doctorRegion,
                'doctor_name' => $doctorName,
                'pacient_region' => $pacientRegion,
                'analysis_double' => $analysisDouble,
            ],
            'filterColumnExpressions' => [
                'pacient_code' => $like('pac.varc_code'),
                'pacient_name' => $like($pacientName),
                'pacient_bd' => [
                    'where' => '{cond}',
                    'column_sql' => 'pac.datetext_bd',
                    'compare' => 'date',
                ],
                'pacient_male' => $like($pacientMale),
                'doctor_region' => $like($doctorRegion),
                'doctor_name' => $like($doctorName),
                'pacient_region' => $like($pacientRegion),
                'analysis_double' => [
                    'column_sql' => $analysisDouble,
                ],
                'pacient_surname' => $like('pac.varc_surname'),
                'doctor_surname' => $like('doc.varc_surname'),
                'date_correctmaterial' => [
                    'where' => '{cond}',
                    'column_sql' => 't_analysis.date_correctmaterial',
                    'compare' => 'unix',
                ],
                'date_execauto' => [
                    'where' => '{cond}',
                    'column_sql' => 't_analysis.date_execauto',
                    'compare' => 'unix',
                ],
            ],
        ];
    }

    /**
     * Мнемоника цвета из справочника: только [a-z0-9_-], иначе пусто.
     */
    public static function colorToken(string $raw): string
    {
        $token = strtolower(trim($raw));
        if ($token === '' || preg_match('/^[a-z0-9_-]+$/', $token) !== 1) {
            return '';
        }

        return $token;
    }

    /**
     * Класс бейджа как в легаси: bg-deepgreen, bg-pink, …
     */
    public static function badgeClass(string $raw): string
    {
        $token = self::colorToken($raw);
        if ($token === '' || !in_array($token, self::SOLID_COLOR_TOKENS, true)) {
            return '';
        }

        return 'bg-' . $token;
    }

    /**
     * Раскраска строк списка по статусу анализа и легенда.
     * Класс bg-* красит образец легенды сплошной мнемоникой, --row-tone — фон ячеек.
     *
     * @return list<array{condition: string, class: string, style: string, legend: string, title: string}>
     */
    public function statusRowStyling(): array
    {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }

        $rows = $this->db->reset()
            ->select(['cat', 'varc_name', 'varc_color'])
            ->from('t_analiz_status')
            ->order(['cat' => 'ASC'])
            ->get();

        $out = [];
        if (!is_array($rows)) {
            $cache = $out;

            return $out;
        }

        foreach ($rows as $row) {
            $arr = is_object($row) ? (array) $row : (is_array($row) ? $row : []);
            $id = (int) ($arr['cat'] ?? 0);
            $token = self::colorToken((string) ($arr['varc_color'] ?? ''));
            $name = trim((string) ($arr['varc_name'] ?? ''));
            if ($id <= 0 || $name === '' || $token === '' || !in_array($token, self::ROW_BG_TOKENS, true)) {
                continue;
            }
            $out[] = [
                'condition' => '(int) ($record[\'view_status\'] ?? 0) === ' . $id,
                'class' => 'row-tone bg-' . $token,
                'style' => '--row-tone: var(--color-bg-' . $token . ');',
                'legend' => $name,
                'title' => $name,
            ];
        }

        $cache = $out;

        return $out;
    }
}
