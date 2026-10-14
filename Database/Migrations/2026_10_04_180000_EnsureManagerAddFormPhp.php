<?php

namespace Project\Database\Migrations;

use Core\Database\Migration;

/**
 * Узел /manager/add/ должен отдавать ядерную форму, а не пропавший легаси core-add.php.
 */
class EnsureManagerAddFormPhp extends Migration
{
    public function getName(): string
    {
        return '2026_10_04_180000_EnsureManagerAddFormPhp';
    }

    public function up(): void
    {
        if (!$this->tableExists('catalog')) {
            return;
        }

        $this->execute(
            'UPDATE `catalog` SET `php` = ? WHERE `mapurl` = ? AND `url` = ?',
            ['system/form.php', 'add', '/manager/add/']
        );
    }

    public function down(): void
    {
        // Легаси-файл core-add.php на этом сайте отсутствует — не возвращаем.
    }
}
