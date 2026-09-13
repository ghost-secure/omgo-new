<?php

namespace Project\Database\Migrations;

use Core\Database\Migration;
use Services\AdminMenuCatalogBuilder;

/**
 * Справочник меню админки. Схема как у t_menu на новом ядре (дамп — только структура).
 * Строки магазина из дампа не копируем: JSON собирается из catalog /manager/*.
 */
class EnsureTMenu extends Migration
{
    public function getName(): string
    {
        return '2026_09_13_100000_EnsureTMenu';
    }

    public function up(): void
    {
        $this->ensureTable();
        $this->ensureDictionary();
        $this->ensureAdminLeftRow();
        $this->seedFromCatalogIfEmpty();
    }

    public function down(): void
    {
        // Таблицу и запись не трогаем: на живом сайте меню уже правили руками.
    }

    private function ensureTable(): void
    {
        if ($this->tableExists('t_menu')) {
            return;
        }

        $this->execute('
            CREATE TABLE `t_menu` (
              `cat` int(11) NOT NULL AUTO_INCREMENT,
              `varc_name` varchar(255) DEFAULT NULL,
              `catlink_pages` text DEFAULT NULL,
              `varc_code` varchar(20) DEFAULT NULL,
              PRIMARY KEY (`cat`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8
        ');
    }

    private function ensureDictionary(): void
    {
        if (!$this->tableExists('tblview') || !$this->tableExists('tblfields')) {
            return;
        }

        $tableColumn = $this->columnExists('tblview', 'tablename') ? 'tablename' : 'tbl';
        $existing = $this->db->reset()
            ->select(['id'])
            ->from('tblview')
            ->where([$tableColumn => 't_menu'])
            ->getOne();

        if ($existing === null) {
            $nameCol = $this->columnExists('tblview', 'name') ? 'name' : 'varc_name';
            $fields = ['`' . $nameCol . '`', '`' . $tableColumn . '`'];
            $values = ['?', '?'];
            $params = ['Меню', 't_menu'];

            if ($this->columnExists('tblview', 'type')) {
                $fields[] = '`type`';
                $values[] = '0';
            }
            if ($this->columnExists('tblview', 'parent')) {
                $fields[] = '`parent`';
                $values[] = 'NULL';
            }

            $this->execute(
                'INSERT INTO `tblview` (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $values) . ')',
                $params
            );
        }

        $fieldTableCol = $this->columnExists('tblfields', 'tablename') ? 'tablename' : 'tbl';
        $fieldNameCol = $this->columnExists('tblfields', 'column') ? 'column' : 'name';
        $fieldLabelCol = $this->columnExists('tblfields', 'label') ? 'label' : 'info';

        $defs = [
            ['varc_name', 'Наименование', 1, 1],
            ['varc_code', 'Мнемоника', 1, 2],
            ['catlink_pages', 'Страницы', 0, 3],
        ];

        foreach ($defs as [$column, $label, $mustset, $ord]) {
            $found = $this->db->reset()
                ->select(['id'])
                ->from('tblfields')
                ->wherebind(
                    '`' . $fieldTableCol . '` = ? AND `' . $fieldNameCol . '` = ?',
                    ['t_menu', $column]
                )
                ->getOne();
            if ($found !== null) {
                continue;
            }

            $cols = [
                '`' . $fieldTableCol . '`',
                '`' . $fieldNameCol . '`',
                '`' . $fieldLabelCol . '`',
            ];
            $placeholders = ['?', '?', '?'];
            $params = ['t_menu', $column, $label];

            if ($this->columnExists('tblfields', 'table_ext')) {
                $cols[] = '`table_ext`';
                $placeholders[] = 'NULL';
            } elseif ($this->columnExists('tblfields', 'id_outview')) {
                $cols[] = '`id_outview`';
                $placeholders[] = '0';
            }
            if ($this->columnExists('tblfields', 'column_ext')) {
                $cols[] = '`column_ext`';
                $placeholders[] = 'NULL';
            } elseif ($this->columnExists('tblfields', 'id_outfld')) {
                $cols[] = '`id_outfld`';
                $placeholders[] = '0';
            }

            if ($this->columnExists('tblfields', 'mustset')) {
                $cols[] = '`mustset`';
                $placeholders[] = '?';
                $params[] = $mustset;
            }
            if ($this->columnExists('tblfields', 'disabled')) {
                $cols[] = '`disabled`';
                $placeholders[] = '0';
            }
            if ($this->columnExists('tblfields', 'ord')) {
                $cols[] = '`ord`';
                $placeholders[] = '?';
                $params[] = $ord;
            }
            if ($this->columnExists('tblfields', 'del')) {
                $cols[] = '`del`';
                $placeholders[] = '0';
            }
            if ($this->columnExists('tblfields', 'settings')) {
                $cols[] = '`settings`';
                $placeholders[] = '?';
                $params[] = '{}';
            }

            $this->execute(
                'INSERT INTO `tblfields` (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $placeholders) . ')',
                $params
            );
        }
    }

    private function ensureAdminLeftRow(): void
    {
        $row = $this->db->reset()
            ->select(['cat'])
            ->from('t_menu')
            ->where(['varc_code' => AdminMenuCatalogBuilder::CODE])
            ->getOne();

        if ($row !== null) {
            return;
        }

        $this->execute(
            'INSERT INTO `t_menu` (`varc_name`, `varc_code`, `catlink_pages`) VALUES (?, ?, ?)',
            ['Меню администратора', AdminMenuCatalogBuilder::CODE, '[]']
        );
    }

    private function seedFromCatalogIfEmpty(): void
    {
        if (!$this->tableExists('catalog')) {
            return;
        }

        try {
            (new AdminMenuCatalogBuilder($this->db))->persist(AdminMenuCatalogBuilder::CODE, false);
        } catch (\Throwable $e) {
            // Каталог ещё не готов — запись admin_left уже есть, JSON можно собрать скриптом.
        }
    }
}
