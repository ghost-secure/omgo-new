<?php

namespace Project\Database\Migrations;

use Core\Database\Migration;
use Services\AdminMenuCatalogBuilder;

/**
 * Страница управления меню в разделе «Администрирование».
 * Грид t_menu, пункт в admin_left рядом со Справочниками и Страницами.
 */
class EnsureAdminMenuPage extends Migration
{
    private const ADMIN_ID = 13;

    private const LIGHT_PATTERN = 3;

    public function getName(): string
    {
        return '2026_10_11_130000_EnsureAdminMenuPage';
    }

    public function up(): void
    {
        if (!$this->tableExists('catalog')) {
            return;
        }

        $menuPageId = $this->ensurePage(
            'menu',
            'Меню',
            '/manager/admin/menu/',
            'manager/menu.php',
            'System/menu-line'
        );

        if ($menuPageId <= 0 || !$this->tableExists('t_menu')) {
            return;
        }

        $menu = new AdminMenuCatalogBuilder($this->db);
        $menu->pinPage($menuPageId, 'p_13', AdminMenuCatalogBuilder::CODE, 'System/menu-line', 2);
    }

    public function down(): void
    {
        // Страницу и пункт меню не снимаем: их могли уже поправить руками.
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
