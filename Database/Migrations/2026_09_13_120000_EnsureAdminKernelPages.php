<?php

namespace Project\Database\Migrations;

use Core\Database\Migration;
use Services\AdminMenuCatalogBuilder;

/**
 * Страницы ядра в разделе «Администрирование»: справочники (tblview) и страницы (catalog).
 * Старый конструктор /manager/constructor/pages/ не трогаем.
 */
class EnsureAdminKernelPages extends Migration
{
    private const ADMIN_ID = 13;

    private const LIGHT_PATTERN = 3;

    public function getName(): string
    {
        return '2026_09_13_120000_EnsureAdminKernelPages';
    }

    public function up(): void
    {
        if (!$this->tableExists('catalog')) {
            return;
        }

        $viewsId = $this->ensurePage(
            'views',
            'Справочники',
            '/manager/admin/views/',
            'manager/views.php',
            'System/menu-unfold-line'
        );
        $pagesId = $this->ensurePage(
            'pages',
            'Страницы',
            '/manager/admin/pages/',
            'manager/pages.php',
            'Document/pages-line'
        );

        if (!$this->tableExists('t_menu')) {
            return;
        }

        $menu = new AdminMenuCatalogBuilder($this->db);
        if ($viewsId > 0) {
            $menu->pinPage($viewsId, 'p_13', AdminMenuCatalogBuilder::CODE, 'System/menu-unfold-line', 0);
        }
        if ($pagesId > 0) {
            $menu->pinPage($pagesId, 'p_13', AdminMenuCatalogBuilder::CODE, 'Document/pages-line', 1);
        }
    }

    public function down(): void
    {
        // Страницы могут уже править руками — не удаляем.
    }

    private function ensurePage(
        string $mapurl,
        string $name,
        string $url,
        string $php,
        string $iconclass
    ): int {
        $existing = $this->db->reset()
            ->select(['id'])
            ->from('catalog')
            ->where(['mapurl' => $mapurl, 'id_up' => self::ADMIN_ID])
            ->getOne();

        if ($existing !== null) {
            $id = (int)$existing->id;
            $this->execute(
                'UPDATE `catalog`
                 SET `name` = ?, `url` = ?, `php` = ?, `pattern` = ?, `status` = 1, `iconclass` = ?
                 WHERE `id` = ?',
                [$name, $url, $php, self::LIGHT_PATTERN, $iconclass, $id]
            );
            return $id;
        }

        $now = time();
        $this->execute(
            'INSERT INTO `catalog` (
                `id_up`, `name`, `html`, `php`, `ord`, `icon`, `usearch`, `ismmenu`, `arrows`,
                `view`, `secondaryview`, `lang`, `domen`, `pattern`, `mapurl`, `url`, `navig`,
                `topid`, `createdate`, `moddate`, `subelem`, `status`, `iconclass`
            ) VALUES (
                ?, ?, NULL, ?, 0, \'\', 0, 0, 0,
                0, 0, 0, \'0\', ?, ?, ?, \'\',
                2, ?, ?, 0, 1, ?
            )',
            [self::ADMIN_ID, $name, $php, self::LIGHT_PATTERN, $mapurl, $url, $now, $now, $iconclass]
        );

        $created = $this->db->reset()
            ->select(['id'])
            ->from('catalog')
            ->where(['mapurl' => $mapurl, 'id_up' => self::ADMIN_ID])
            ->getOne();

        return $created !== null ? (int)$created->id : 0;
    }
}
