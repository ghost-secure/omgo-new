<?php

namespace Services;

use Core\Database\QueryBuilder;
use Core\Reference\TableMetadataManager;
use Helpers\Crypt;

/**
 * Проверочные коды `t_code`.
 * Легаси: `Object\Code\Code` и пул из `aftersave/t_events.php` (`WF_CODEPULL`).
 */
class CodeService
{
    public const TBL = 't_code';
    public const TBL_TYPE = 't_analiztype';
    public const CLIP_LENGTH = 3;
    public const POOL_MAX = 500;

    private const UNIQUE_ATTEMPTS = 8;

    public function __construct(private QueryBuilder $db)
    {
    }

    /**
     * Радио легаси-формы пула (`wf/codes.php`).
     *
     * @return array<int, string>
     */
    public function poolTypes(): array
    {
        return [
            AnalysisService::TYPE_HRD => 'HRD (рак яичников)',
            AnalysisService::TYPE_AKT => 'AKT (рак молочной железы)',
            AnalysisService::TYPE_BRCA => 'BRCA (рак молочной железы, рак поджелудочной железы, рак яичников)',
            AnalysisService::TYPE_HRR => 'HRR (рак простаты)',
            AnalysisService::TYPE_PTEN => 'PTEN',
        ];
    }

    /**
     * Легаси `Code::makeCode`: 3 латинские буквы имени типа + два куска по 4 символа.
     * «ИГХ PTEN» → префикс PTE, BRCA → BRC.
     */
    public function makeCode(int $typeId): string
    {
        $prefix = substr($this->typeLatinName($typeId), 0, self::CLIP_LENGTH);
        if ($prefix === '') {
            throw new \RuntimeException('У типа анализа нет латинского имени для кода');
        }

        return implode('-', [
            $prefix,
            Crypt::generateRandomString(4),
            Crypt::generateRandomString(4),
        ]);
    }

    public function makeUniqueCode(int $typeId): string
    {
        for ($i = 0; $i < self::UNIQUE_ATTEMPTS; $i++) {
            $code = $this->makeCode($typeId);
            if (!$this->codeExists($code)) {
                return $code;
            }
        }

        throw new \RuntimeException('Не удалось собрать уникальный код');
    }

    /**
     * N строк `t_code` с `view_analiz` и сгенерированным `varc_code`.
     */
    public function generatePool(int $typeId, int $count): int
    {
        if (!array_key_exists($typeId, $this->poolTypes())) {
            throw new \RuntimeException('Недопустимый тип анализа');
        }
        if ($count < 1 || $count > self::POOL_MAX) {
            throw new \RuntimeException('Количество кодов: от 1 до ' . self::POOL_MAX);
        }

        $this->db->beginTransaction();
        try {
            $metadata = new TableMetadataManager();
            for ($i = 0; $i < $count; $i++) {
                $record = $metadata->createActiveRecord(self::TBL, [
                    'view_analiz' => $typeId,
                    'varc_code' => $this->makeUniqueCode($typeId),
                ]);
                if (!$record->save()) {
                    throw new \RuntimeException('Ошибка сохранения кода');
                }
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            try {
                $this->db->rollback();
            } catch (\Throwable) {
            }
            throw $e;
        }

        return $count;
    }

    /**
     * Код уже выдан: в легаси свободный код — `date_activate IS NULL`.
     * Заполненный `user_activate` тоже считается активацией.
     *
     * @param array<string, mixed> $record
     */
    public static function isActivated(array $record): bool
    {
        if ((int) ($record['user_activate'] ?? 0) > 0) {
            return true;
        }

        $date = $record['date_activate'] ?? null;
        if ($date === null || $date === '') {
            return false;
        }
        if (is_numeric($date)) {
            return (int) $date > 0;
        }

        return trim((string) $date) !== '';
    }

    /**
     * SQL «код активирован» — то же правило, что {@see isActivated}.
     */
    public static function activatedSql(string $table = self::TBL): string
    {
        self::assertSqlIdent($table);

        return '(IFNULL(' . $table . '.user_activate, 0) > 0'
            . ' OR (' . $table . '.date_activate IS NOT NULL AND ' . $table . '.date_activate <> 0))';
    }

    /**
     * SQL «код ещё не активирован».
     */
    public static function idleSql(string $table = self::TBL): string
    {
        self::assertSqlIdent($table);

        return '(IFNULL(' . $table . '.user_activate, 0) = 0'
            . ' AND (' . $table . '.date_activate IS NULL OR ' . $table . '.date_activate = 0))';
    }

    /**
     * Подзапрос ФИО из `t_users` (фамилия, имя, отчество) для колонки `author_` / `user_`.
     * Связь как у ядра: значение колонки = `t_users.cat`.
     */
    public static function userFioSelect(string $table, string $column): string
    {
        if (!preg_match('/^[a-z0-9_]+$/', $table) || !preg_match('/^[a-z0-9_]+$/', $column)) {
            throw new \InvalidArgumentException('Некорректное поле для ФИО');
        }

        return "(SELECT NULLIF(TRIM(CONCAT_WS(' ', u.varc_surname, u.varc_name, u.varc_patron)), '')"
            . " FROM t_users u WHERE u.cat = {$table}.{$column}) AS {$column}_fio";
    }

    /**
     * @param array<string, mixed> $record
     */
    public static function userFioPlain(array $record, string $column): string
    {
        $fio = trim((string) ($record[$column . '_fio'] ?? ''));
        if ($fio !== '') {
            return $fio;
        }

        $parts = [];
        foreach ([$column . '_surname', $column . '_varc_name', $column . '_patron'] as $key) {
            $part = trim((string) ($record[$key] ?? ''));
            if ($part !== '') {
                $parts[] = $part;
            }
        }

        return implode(' ', $parts);
    }

    /**
     * @param array<string, mixed> $record
     */
    public static function userFioHtml(array $record, string $column): string
    {
        $fio = self::userFioPlain($record, $column);
        if ($fio === '') {
            return '<span class="text-muted">—</span>';
        }

        return htmlspecialchars($fio, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function typeLatinName(int $typeId): string
    {
        if ($typeId <= 0) {
            return '';
        }

        $row = $this->db->reset()
            ->select(['varc_name'])
            ->from(self::TBL_TYPE)
            ->where(['cat' => $typeId])
            ->getOne();
        $arr = is_object($row) ? (array) $row : (is_array($row) ? $row : []);
        $name = (string) ($arr['varc_name'] ?? '');
        $latin = preg_replace('/[^a-zA-Z]/u', '', $name);

        return is_string($latin) ? $latin : '';
    }

    private function codeExists(string $code): bool
    {
        $row = $this->db->reset()
            ->select(['cat'])
            ->from(self::TBL)
            ->where(['varc_code' => $code])
            ->getOne();

        return !empty($row);
    }

    private static function assertSqlIdent(string $name): void
    {
        if (!preg_match('/^[a-z0-9_]+$/', $name)) {
            throw new \InvalidArgumentException('Некорректное имя таблицы');
        }
    }
}
