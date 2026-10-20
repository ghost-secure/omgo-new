<?php

namespace Services;

use Core\Auth\UserAccount;
use Core\Container;
use Core\Database\QueryBuilder;

/**
 * Бизнес-роли кабинета (`t_roles.cat` / `t_access_matrix.view_role`).
 * Легаси: `Object\Role\Role`.
 *
 * Не путать с `tblusers.role` (группа доступа ядра: суперюзер / админ / менеджер).
 */
class RoleService
{
    public const ROLE_LAB = 1;
    public const ROLE_DOCTOR = 2;
    public const ROLE_ADMIN = 3;
    public const ROLE_SPONSOR = 5;
    public const ROLE_SPONSOR_AUDIT = 6;

    /**
     * Страницы сайдбара для текущего пользователя.
     *
     * null — ограничения нет (суперадмин, `tblusers.role` = 1).
     * Список — объединение `t_roles.catlink_parent` всех ролей из `roles_map`.
     * Пустой список — меню пустое.
     *
     * @return list<int>|null
     */
    public function menuPageIds(): ?array
    {
        $container = Container::getInstance();
        $auth = $container->has('auth') ? $container->get('auth') : null;
        $user = $auth && method_exists($auth, 'getUser') ? $auth->getUser() : null;
        if (!is_array($user)) {
            return [];
        }

        if ((int) ($user['role'] ?? 0) === UserAccount::SUPERUSER) {
            return null;
        }

        $roles = $user['roles_map'] ?? [];
        if (!is_array($roles) || $roles === []) {
            return [];
        }

        $roleIds = [];
        foreach (array_keys($roles) as $roleId) {
            $roleId = (int) $roleId;
            if ($roleId > 0) {
                $roleIds[] = $roleId;
            }
        }
        if ($roleIds === []) {
            return [];
        }

        /** @var QueryBuilder $db */
        $db = $container->get(QueryBuilder::class);
        $rows = $db->reset()
            ->select(['catlink_parent'])
            ->from('t_roles')
            ->whereIn('cat', $roleIds)
            ->get();

        $pages = [];
        foreach ($rows ?: [] as $row) {
            $raw = (string) (((array) $row)['catlink_parent'] ?? '');
            foreach ($this->pageIdsFromCatlink($raw) as $pageId) {
                $pages[$pageId] = $pageId;
            }
        }

        return array_values($pages);
    }

    /**
     * `catlink_parent`: легаси «54,57,62» или JSON узлов редактора (`page_id`).
     *
     * @return list<int>
     */
    private function pageIdsFromCatlink(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        if ($raw[0] === '[') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $this->pageIdsFromNodes($decoded);
            }
            if (preg_match_all('/"page_id"\s*:\s*(\d+)/', $raw, $matches)) {
                return array_values(array_filter(array_map('intval', $matches[1])));
            }

            return [];
        }

        $ids = [];
        foreach (explode(',', $raw) as $part) {
            $pageId = (int) trim($part);
            if ($pageId > 0) {
                $ids[] = $pageId;
            }
        }

        return $ids;
    }

    /**
     * @param array<int, mixed> $nodes
     * @return list<int>
     */
    private function pageIdsFromNodes(array $nodes): array
    {
        $ids = [];
        foreach ($nodes as $node) {
            if (!is_array($node)) {
                continue;
            }
            if (($node['type'] ?? 'page') !== 'group') {
                $pageId = (int) ($node['page_id'] ?? 0);
                if ($pageId > 0) {
                    $ids[] = $pageId;
                }
            }
            if (!empty($node['children']) && is_array($node['children'])) {
                foreach ($this->pageIdsFromNodes($node['children']) as $pageId) {
                    $ids[] = $pageId;
                }
            }
        }

        return $ids;
    }
}
