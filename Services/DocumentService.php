<?php

namespace Services;

use Core\Database\QueryBuilder;

/**
 * Документы кабинета: типы вкладок и строки списка.
 * Легаси: `Dataprovider::getDocType()` и выборка блока 52 (`t_docs`, фильтр `view_type`).
 */
class DocumentService
{
    public const TBL = 't_docs';
    public const TBL_TYPE = 't_docs_type';

    /** Как `limitNumber` у блока 52. */
    public const PER_TYPE = 100;

    public function __construct(private QueryBuilder $db)
    {
    }

    /**
     * Вкладки страницы. Пустой тип тоже есть: в легаси вкладка рисуется и без строк.
     *
     * @return list<array{id:int, name:string, items:list<array{id:int, name:string, date:string, file:string, video:string}>}>
     */
    public function sections(): array
    {
        $types = $this->db->reset()
            ->select(['cat', 'varc_name'])
            ->from(self::TBL_TYPE)
            ->order(['cat' => 'ASC'])
            ->get();

        $rows = $this->db->reset()
            ->select(['cat', 'view_type', 'varc_name', 'date_actual', 'doc_file', 'varc_videourl'])
            ->from(self::TBL)
            ->order(['cat' => 'ASC'])
            ->get();

        $byType = [];
        foreach ($rows ?: [] as $row) {
            $row = (array) $row;
            $typeId = (int) ($row['view_type'] ?? 0);
            if ($typeId <= 0) {
                continue;
            }
            if (!isset($byType[$typeId])) {
                $byType[$typeId] = [];
            }
            if (count($byType[$typeId]) >= self::PER_TYPE) {
                continue;
            }
            $stamp = (int) ($row['date_actual'] ?? 0);
            $byType[$typeId][] = [
                'id' => (int) ($row['cat'] ?? 0),
                'name' => trim((string) ($row['varc_name'] ?? '')),
                'date' => $stamp > 0 ? date('d.m.Y', $stamp) : '',
                'file' => self::publicPath((string) ($row['doc_file'] ?? '')),
                'video' => self::publicPath((string) ($row['varc_videourl'] ?? '')),
            ];
        }

        $sections = [];
        foreach ($types ?: [] as $type) {
            $type = (array) $type;
            $id = (int) ($type['cat'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $sections[] = [
                'id' => $id,
                'name' => trim((string) ($type['varc_name'] ?? '')),
                'items' => $byType[$id] ?? [],
            ];
        }

        return $sections;
    }

    /**
     * Адрес файла с сайта. Чужой протокол и кавычки в путь не попадают.
     */
    public static function publicPath(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '' || !str_starts_with($raw, '/') || str_starts_with($raw, '//')) {
            return '';
        }
        if (preg_match('/[\s<>"\']/', $raw) === 1) {
            return '';
        }

        return $raw;
    }
}
