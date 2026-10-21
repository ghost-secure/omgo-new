<?php

namespace Services\Report;

use Core\Auth\UserAccount;
use Core\Database\QueryBuilder;
use Services\RoleService;

/**
 * Готовые xlsx в runtime/cache/reports и какой файл отдать роли.
 * Легаси: phpfiles/report/pacient.php. Поздняя роль переписывает файл.
 */
class AnalysisReportFiles
{
    public const KEEP_ROLLING = 30;

    public function __construct(private readonly QueryBuilder $db)
    {
    }

    public function directory(): string
    {
        $dir = rtrim(CACHE_PATH, '/\\') . DIRECTORY_SEPARATOR . 'reports';
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Не удалось создать ' . $dir);
        }

        return $dir;
    }

    /**
     * Кнопка «Отчет». Врач её не включает и не снимает: лаб+врач кнопку видит.
     * Суперадмин (tblusers.role = 1) видит всегда.
     *
     * @param array<string, mixed>|null $user
     */
    public function showMenu(?array $user): bool
    {
        if ($user === null) {
            return false;
        }
        $has = $this->has($user);
        $show = false;
        if ($has(RoleService::ROLE_LAB)) {
            $show = true;
        }
        if ($has(RoleService::ROLE_SPONSOR) || $has(RoleService::ROLE_SPONSOR_AUDIT)) {
            $show = true;
        }
        if ($has(RoleService::ROLE_ADMIN)) {
            $show = true;
        }
        if ((int) ($user['role'] ?? 0) === UserAccount::SUPERUSER) {
            $show = true;
        }

        return $show;
    }

    /**
     * Ключ файла и подпись скачивания. null — этой роли файл не положен.
     *
     * @param array<string, mixed>|null $user
     * @return array{key: string, title: string}|null
     */
    public function audience(?array $user): ?array
    {
        if ($user === null) {
            return null;
        }
        $has = $this->has($user);
        $super = (int) ($user['role'] ?? 0) === UserAccount::SUPERUSER;
        $file = null;

        if ($has(RoleService::ROLE_ADMIN) || $super) {
            $file = ['key' => 'lab', 'title' => 'Отчет лаборатории'];
        }
        if ($has(RoleService::ROLE_LAB)) {
            $profile = is_array($user['profile'] ?? null) ? $user['profile'] : [];
            $labId = (int) ($profile['view_lab'] ?? 0);
            $key = 'lab';
            if ($labId > 0 && !$this->labExportsAll($labId)) {
                $key = 'lab' . $labId;
            }
            $file = ['key' => $key, 'title' => 'Отчет лаборатории'];
        }
        if ($has(RoleService::ROLE_SPONSOR)) {
            $file = ['key' => 'sponsor', 'title' => 'Отчет спонсора'];
        }
        if ($has(RoleService::ROLE_SPONSOR_AUDIT)) {
            $file = ['key' => 'auditor', 'title' => 'Отчет DX manager'];
        }

        return $file;
    }

    public function menuHtml(): string
    {
        $before = $this->link('/report/pacient/', AnalysisReportColumns::LABEL_BEFORE);
        $after = $this->link('/report/pacient/?target=new', AnalysisReportColumns::LABEL_AFTER);

        return '<div data-plugin="dropdown">'
            . '<button type="button" class="btn btn-outline-secondary" data-dropdown-toggle>'
            . '<span class="dynblock-toolbar__btn-label">Отчет</span>'
            . '<span class="nav-item__icon" data-icon="System/download-line"></span>'
            . '</button>'
            . '<div class="dropdown-menu report-download-menu" data-dropdown-menu hidden>'
            . $before
            . $after
            . '</div>'
            . '</div>';
    }

    /**
     * Архив — один файл. Актуальный — самый новый alltest-{key}-*.xlsx.
     */
    public function pathFor(string $key, bool $current): ?string
    {
        $key = $this->safeKey($key);
        if ($key === null) {
            return null;
        }
        $dir = $this->directory();
        if (!$current) {
            $path = $dir . DIRECTORY_SEPARATOR . 'before-alltest-' . $key . '.xlsx';

            return is_file($path) ? $path : null;
        }
        $files = glob($dir . DIRECTORY_SEPARATOR . 'alltest-' . $key . '-*.xlsx');
        if ($files === false || $files === []) {
            return null;
        }
        usort($files, static function (string $a, string $b): int {
            return filemtime($b) <=> filemtime($a);
        });

        return is_file($files[0]) ? $files[0] : null;
    }

    public function archivePath(string $key): string
    {
        return $this->directory() . DIRECTORY_SEPARATOR . 'before-alltest-' . $this->requireKey($key) . '.xlsx';
    }

    public function rollingPath(string $key, string $ymd): string
    {
        if (preg_match('/^\d{8}$/', $ymd) !== 1) {
            throw new \InvalidArgumentException('Дата файла: ' . $ymd);
        }

        return $this->directory() . DIRECTORY_SEPARATOR . 'alltest-' . $this->requireKey($key) . '-' . $ymd . '.xlsx';
    }

    public function pruneRolling(string $key, int $keep = self::KEEP_ROLLING): void
    {
        $key = $this->requireKey($key);
        $files = glob($this->directory() . DIRECTORY_SEPARATOR . 'alltest-' . $key . '-*.xlsx');
        if ($files === false || count($files) <= $keep) {
            return;
        }
        usort($files, static function (string $a, string $b): int {
            return filemtime($b) <=> filemtime($a);
        });
        foreach (array_slice($files, $keep) as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    /**
     * @param array<string, mixed> $user
     */
    private function has(array $user): \Closure
    {
        $roles = is_array($user['roles_map'] ?? null) ? $user['roles_map'] : [];

        return static fn (int $role): bool => array_key_exists($role, $roles);
    }

    private function labExportsAll(int $labId): bool
    {
        $row = $this->db->reset()
            ->select(['stat_exportallpacient'])
            ->from('t_lab')
            ->where(['cat' => $labId])
            ->limit(1)
            ->getOne();
        if (!$row) {
            return true;
        }

        return (int) ($row->stat_exportallpacient ?? 0) !== 0;
    }

    private function link(string $href, string $label): string
    {
        return '<a href="'
            . htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '" target="_blank">'
            . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</a>';
    }

    private function requireKey(string $key): string
    {
        $safe = $this->safeKey($key);
        if ($safe === null) {
            throw new \InvalidArgumentException('Ключ отчёта: ' . $key);
        }

        return $safe;
    }

    private function safeKey(string $key): ?string
    {
        return preg_match('/^[a-z]+\d*$/', $key) === 1 ? $key : null;
    }
}
