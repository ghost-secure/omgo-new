<?php

namespace Services\Report;

use Core\Config;
use Core\Database\QueryBuilder;

/**
 * Страница строк отчёта и текст ячеек.
 * Легаси: Object\Report\Report.
 *
 * created_add (заявка и t_events) — DATETIME. Поля date_* — unix.
 */
class AnalysisReportQuery
{
    /** @var array<int, string>|null */
    private ?array $months = null;

    /** @var array<string, string>|null */
    private ?array $viewExpr = null;

    public function __construct(
        private readonly QueryBuilder $db,
        private readonly Config $config
    ) {
    }

    /**
     * @param array<string, mixed> $filter code, where, exclude, createdBefore, createdFrom
     * @return list<array<string, mixed>>
     */
    public function rows(string $code, array $filter, int $limit, int $offset): array
    {
        $columns = AnalysisReportColumns::columns($code);
        $select = $this->selectList($columns);
        $q = $this->db->reset()->select($select)->from('t_analysis v');
        foreach (AnalysisReportColumns::joins() as $join) {
            $q->join($join['table'], $join['on'], $join['type']);
        }
        $this->applyFilter($q, $filter);
        $q->order(['v.cat' => 'ASC']);
        if ($limit > 0) {
            $q->limit($limit);
            if ($offset > 0) {
                $q->offset($offset);
            }
        }

        $rows = $q->get();

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $row
     * @return list<string>
     */
    public function cells(string $code, array $row): array
    {
        $cells = [];
        foreach (AnalysisReportColumns::columns($code) as $column) {
            $cells[] = $this->format($column, $row);
        }

        return $cells;
    }

    /**
     * @param list<array<string, mixed>> $columns
     * @return list<string>
     */
    private function selectList(array $columns): array
    {
        $viewExpr = $this->viewExpr();
        $select = [];
        $seen = [];
        $add = function (string $alias, string $expr) use (&$select, &$seen): void {
            if (isset($seen[$alias])) {
                return;
            }
            $seen[$alias] = true;
            $select[] = $expr . ' AS `' . $alias . '`';
        };

        foreach ($columns as $column) {
            $key = (string) $column['key'];
            $format = $column['format'] ?? null;
            if (!empty($column['expr'])) {
                $add($key, (string) $column['expr']);
            } elseif ($format === AnalysisReportColumns::FMT_VIEW) {
                $add($key, $viewExpr[$key] ?? ('v.' . $this->ident($key)));
            } elseif ($format === AnalysisReportColumns::FMT_USER) {
                $add($key, $this->userExpr($key));
            } else {
                $add($key, 'v.' . $this->ident($key));
            }

            if (!empty($column['fallback'])) {
                $fallback = $this->ident((string) $column['fallback']);
                $add($fallback, 'v.' . $fallback);
            }
            foreach ($column['with'] ?? [] as $alias => $expr) {
                if (is_int($alias)) {
                    $name = $this->ident((string) $expr);
                    $add($name, 'v.' . $name);
                } else {
                    $add($this->ident((string) $alias), (string) $expr);
                }
            }
        }

        return $select;
    }

    /**
     * Подписи справочников полного набора lab. Урезанные отчёты берут то же выражение.
     *
     * @return array<string, string>
     */
    private function viewExpr(): array
    {
        if ($this->viewExpr !== null) {
            return $this->viewExpr;
        }

        $fields = [];
        foreach (AnalysisReportColumns::columns(AnalysisReportColumns::LAB) as $column) {
            if (($column['format'] ?? null) === AnalysisReportColumns::FMT_VIEW && empty($column['expr'])) {
                $fields[] = (string) $column['key'];
            }
        }
        $expr = [];
        if ($fields !== []) {
            $meta = $this->db->reset()
                ->select(['`column` AS field', 'column_ext AS extfield', 'table_ext AS exttbl'])
                ->from('tblfields')
                ->where(['tablename' => 't_analysis'])
                ->whereIn('`column`', $fields)
                ->get();
            foreach ($meta as $row) {
                $field = (string) ($row['field'] ?? '');
                if ($field === '' || isset($expr[$field])) {
                    continue;
                }
                $extfield = (string) ($row['extfield'] ?? '');
                $exttbl = (string) ($row['exttbl'] ?? '');
                if ($extfield !== '' && $exttbl !== '' && $this->isIdent($extfield) && $this->isIdent($exttbl)) {
                    $expr[$field] = '(SELECT `' . $extfield . '` FROM `' . $exttbl . '` WHERE cat = v.' . $this->ident($field) . ')';
                } else {
                    $expr[$field] = '(SELECT name FROM catalog WHERE id = v.' . $this->ident($field) . ')';
                }
            }
        }
        $this->viewExpr = $expr;

        return $expr;
    }

    private function userExpr(string $field): string
    {
        $concat = "TRIM(CONCAT(COALESCE(p.varc_surname, ''), ' ', COALESCE(p.varc_name, ''), ' ', COALESCE(p.varc_patron, '')))";

        return '(SELECT ' . $concat . ' FROM t_users p WHERE p.cat = v.' . $this->ident($field) . ')';
    }

    /**
     * @param array<string, mixed> $filter
     */
    private function applyFilter(QueryBuilder $q, array $filter): void
    {
        if (!empty($filter['exclude']) && is_array($filter['exclude'])) {
            $ids = [];
            foreach ($filter['exclude'] as $id) {
                $ids[] = (int) $id;
            }
            if ($ids !== []) {
                $q->whereNotIn('v.view_test', $ids);
            }
        }
        if (!empty($filter['where']) && is_array($filter['where'])) {
            $q->where($filter['where']);
        }
        if (array_key_exists('createdBefore', $filter)) {
            $q->where('v.created_add < ?', [(string) $filter['createdBefore']]);
        }
        if (array_key_exists('createdFrom', $filter)) {
            $q->where('v.created_add >= ?', [(string) $filter['createdFrom']]);
        }
    }

    /**
     * @param array<string, mixed> $column
     * @param array<string, mixed> $row
     */
    private function format(array $column, array $row): string
    {
        $value = $row[$column['key']] ?? null;
        if (!empty($column['fallback']) && $this->blank($value)) {
            $value = $row[$column['fallback']] ?? null;
        }

        $format = $column['format'] ?? null;
        if ($format === AnalysisReportColumns::FMT_DATE) {
            return $this->formatDate($value, 'd.m.Y');
        }
        if ($format === AnalysisReportColumns::FMT_DATETIME) {
            return $this->formatDate($value, 'd.m.Y H:i:s');
        }
        if ($format === AnalysisReportColumns::FMT_MONTHYEAR) {
            return $this->formatMonthYear($value);
        }
        if ($format === AnalysisReportColumns::FMT_STAT) {
            return (string) $value === '1' || $value === 1 ? 'Да' : 'Нет';
        }
        if ($format === AnalysisReportColumns::FMT_MAP) {
            if ($this->blank($value)) {
                return '';
            }
            $map = $column['map'] ?? [];

            return isset($map[(int) $value]) ? (string) $map[(int) $value] : '';
        }
        if ($format === AnalysisReportColumns::FMT_STRIPTAGS) {
            if ($this->blank($value)) {
                return '';
            }
            $text = strip_tags(html_entity_decode((string) $value, ENT_QUOTES, 'UTF-8'));
            if (!empty($column['crop'])) {
                return $this->cropText($text, (int) $column['crop']);
            }

            return trim($text);
        }
        if ($format === AnalysisReportColumns::FMT_FILE) {
            return $this->blank($value) ? '' : $this->absoluteUrl((string) $value);
        }
        if ($format === AnalysisReportColumns::FMT_IMAGES) {
            return $this->formatImages($value);
        }
        if ($format === AnalysisReportColumns::FMT_CONCAT) {
            return $this->formatConcat($value, $column, $row);
        }
        if ($format === AnalysisReportColumns::FMT_CNT) {
            return $this->formatCnt($value);
        }
        if ($this->blank($value)) {
            return '';
        }

        return trim(stripslashes(html_entity_decode((string) $value, ENT_QUOTES, 'UTF-8')));
    }

    private function formatDate(mixed $value, string $mask): string
    {
        $ts = $this->timestamp($value);

        return $ts > 0 ? date($mask, $ts) : '';
    }

    private function formatMonthYear(mixed $value): string
    {
        $ts = $this->timestamp($value);
        if ($ts <= 0) {
            return '';
        }
        $name = $this->monthName((int) date('n', $ts));

        return trim($name . ' ' . date('Y', $ts));
    }

    private function timestamp(mixed $value): int
    {
        if ($this->blank($value)) {
            return 0;
        }
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value) === 1) {
            $ts = strtotime($value);

            return $ts === false ? 0 : $ts;
        }
        $ts = (int) $value;

        return $ts > 0 ? $ts : 0;
    }

    private function cropText(string $str, int $limit): string
    {
        $words = explode(' ', strip_tags($str));
        $out = '';
        foreach ($words as $word) {
            $out .= $word . ' ';
            if (mb_strlen($out, 'UTF-8') > $limit) {
                return $out . '..';
            }
        }

        return $str;
    }

    private function formatCnt(mixed $value): string
    {
        if ($this->blank($value)) {
            return '';
        }
        if ((int) $value - (float) $value == 0) {
            return (string) (int) $value;
        }

        return (string) $value;
    }

    /**
     * @param array<string, mixed> $column
     * @param array<string, mixed> $row
     */
    private function formatConcat(mixed $value, array $column, array $row): string
    {
        $parts = [];
        $head = trim((string) $value);
        if ($head !== '') {
            $parts[] = $head;
        }
        foreach ($column['with'] ?? [] as $alias => $expr) {
            if (is_int($alias)) {
                continue;
            }
            $extra = trim((string) ($row[$alias] ?? ''));
            if ($extra !== '') {
                $parts[] = $extra;
            }
        }

        return trim(implode(' ', $parts));
    }

    private function formatImages(mixed $value): string
    {
        if ($this->blank($value)) {
            return '';
        }
        $images = is_array($value) ? $value : json_decode((string) $value, true);
        if (!is_array($images)) {
            return '';
        }
        $out = '';
        foreach ($images as $image) {
            $url = is_array($image) ? (string) ($image['url'] ?? '') : '';
            if ($url === '') {
                continue;
            }
            $out .= $this->absoluteUrl($url) . ';';
        }

        return $out;
    }

    private function monthName(int $month): string
    {
        if ($this->months === null) {
            $this->months = [];
            $rows = $this->db->reset()->select(['cat', 'varc_name'])->from('t_date_month')->get();
            foreach ($rows as $row) {
                $this->months[(int) ($row['cat'] ?? 0)] = (string) ($row['varc_name'] ?? '');
            }
        }

        return $this->months[$month] ?? '';
    }

    private function absoluteUrl(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }
        $base = rtrim((string) $this->config->get('app.base_url', ''), '/');
        if ($path[0] !== '/') {
            $path = '/' . $path;
        }

        return $base . $path;
    }

    private function blank(mixed $value): bool
    {
        if ($value === null || $value === false) {
            return true;
        }
        if (is_string($value)) {
            $value = trim($value);
            if ($value === '' || $value === '0' || $value === '0.00' || str_starts_with($value, '0000-00-00')) {
                return true;
            }
        }

        return $value === 0;
    }

    private function ident(string $name): string
    {
        if (!$this->isIdent($name)) {
            throw new \InvalidArgumentException('Недопустимое имя поля: ' . $name);
        }

        return $name;
    }

    private function isIdent(string $name): bool
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) === 1;
    }
}
