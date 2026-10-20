<?php

namespace Services;

use Core\Container;
use Core\Database\QueryBuilder;

/**
 * Проектный сборщик меню из t_menu.
 * Возвращает только данные; рендер живет в проектном шаблоне (project/UC/...).
 */
class MenuBuilder
{
    private QueryBuilder $db;

    public function __construct()
    {
        $this->db = Container::getInstance()->get(QueryBuilder::class);
    }

    /**
     * Собрать меню по коду.
     *
     * @return array{
     *   code:string,
     *   nodes:array<int,array<string,mixed>>
     * }
     */
    public function getMenuData(string $code): array
    {
        try {
            $menuRow = $this->loadMenuRow($code);
            if (empty($menuRow)) {
                return ['code' => $code, 'nodes' => []];
            }

            $jsonColumn = $this->detectMenuJsonColumn(array_keys($menuRow));
            if ($jsonColumn === null || empty($menuRow[$jsonColumn])) {
                return ['code' => $code, 'nodes' => []];
            }

            $nodes = json_decode((string)$menuRow[$jsonColumn], true);
            if (!is_array($nodes) || empty($nodes)) {
                return ['code' => $code, 'nodes' => []];
            }

            $normalized = $this->normalizeNodes($nodes);
            if (empty($normalized)) {
                return ['code' => $code, 'nodes' => []];
            }

            $pages = $this->loadCatalogMap($normalized);
            $tree = $this->buildTree($normalized, $pages);
            $tree = $this->restrictByRole($tree);
            RemixIconCache::warmTree($tree);
            return [
                'code' => $code,
                'nodes' => $tree,
            ];
        } catch (\Throwable $e) {
            return ['code' => $code, 'nodes' => []];
        }
    }

    private function loadMenuRow(string $code): array
    {
        $row = $this->db->reset()
            ->select(['*'])
            ->from('t_menu')
            ->where(['varc_code' => $code])
            ->getOne();
        return $row ? (array)$row : [];
    }

    private function detectMenuJsonColumn(array $columns): ?string
    {
        if (in_array('catlink_menu', $columns, true)) {
            return 'catlink_menu';
        }

        foreach ($columns as $column) {
            if (strpos($column, 'catlink_') === 0) {
                return $column;
            }
        }
        return null;
    }

    private function normalizeNodes(array $nodes): array
    {
        $out = [];
        foreach ($nodes as $node) {
            if (!is_array($node)) {
                continue;
            }

            $id = isset($node['id']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$node['id']) : '';
            if ($id === '') {
                continue;
            }

            $type = (isset($node['type']) && $node['type'] === 'group') ? 'group' : 'page';
            $parent = null;
            if (isset($node['parent']) && $node['parent'] !== null && $node['parent'] !== '') {
                $parent = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$node['parent']);
                if ($parent === '') {
                    $parent = null;
                }
            }
            $order = isset($node['order']) ? (int)$node['order'] : 0;
            $icon = $this->normalizeIconKey((string)($node['icon'] ?? ''));

            if ($type === 'group') {
                $title = trim((string)($node['title'] ?? ''));
                if ($title === '') {
                    continue;
                }
                $normalized = [
                    'id' => $id,
                    'type' => 'group',
                    'title' => $title,
                    'parent' => $parent,
                    'order' => $order,
                ];
                if ($icon !== '') {
                    $normalized['icon'] = $icon;
                }
                $out[] = $normalized;
            } else {
                $pageId = isset($node['page_id']) ? (int)$node['page_id'] : 0;
                if ($pageId <= 0) {
                    continue;
                }
                $normalized = [
                    'id' => $id,
                    'type' => 'page',
                    'page_id' => $pageId,
                    'parent' => $parent,
                    'order' => $order,
                ];
                if ($icon !== '') {
                    $normalized['icon'] = $icon;
                }
                $out[] = $normalized;
            }
        }

        return $out;
    }

    /**
     * Нормализует сырой icon-URL к короткому ключу формата Namespace/name-line.
     *
     * Пример:
     * https://cdn.jsdelivr.net/npm/remixicon@4.9.1/icons/Finance/shopping-bag-2-line.svg
     * -> Finance/shopping-bag-2-line
     */
    private function normalizeIconKey(string $rawIcon): string
    {
        $icon = trim($rawIcon);
        if ($icon === '') {
            return '';
        }

        // 1) Нормальный полный URL
        $icon = str_replace(RemixIconCache::CDN_PREFIX, '', $icon);

        // 2) Универсально: если есть "/icons/", забираем хвост после него
        $iconsPos = strpos($icon, '/icons/');
        if ($iconsPos !== false) {
            $icon = substr($icon, $iconsPos + 7);
        } else {
            // 3) Legacy-случай с уже "побитым" URL (без слэшей/двоеточий)
            $legacyPos = stripos($icon, 'icons/');
            if ($legacyPos !== false) {
                $icon = substr($icon, $legacyPos + 6);
            }
        }

        // Срезаем .svg или хвостовой svg (для legacy-строк типа "...-linesvg")
        $icon = preg_replace('/(?:\.svg|svg)$/i', '', $icon);
        if ($icon === null) {
            return '';
        }

        // Декодируем URL-форму (%20, %26, ...)
        $icon = rawurldecode($icon);

        // Нормализуем каждый сегмент пути отдельно:
        // - убираем мусорные символы
        // - кодируем обратно для URL-безопасного ключа data-icon
        $segments = array_values(array_filter(explode('/', $icon), static function (string $segment): bool {
            return $segment !== '';
        }));
        if (empty($segments)) {
            return '';
        }

        $normalizedSegments = [];
        foreach ($segments as $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                continue;
            }
            $segment = preg_replace('/[^a-zA-Z0-9 _&\\-]/', '', $segment);
            if ($segment === null || $segment === '') {
                continue;
            }
            $normalizedSegments[] = rawurlencode($segment);
        }

        if (empty($normalizedSegments)) {
            return '';
        }

        $icon = implode('/', $normalizedSegments);
        if (mb_strlen($icon, 'UTF-8') > 180) {
            $icon = mb_substr($icon, 0, 180, 'UTF-8');
        }

        return $icon;
    }

    private function loadCatalogMap(array $nodes): array
    {
        $ids = [];
        foreach ($nodes as $node) {
            if (($node['type'] ?? '') === 'page' && !empty($node['page_id'])) {
                $ids[] = (int)$node['page_id'];
            }
        }
        $ids = array_values(array_unique(array_filter($ids)));
        if (empty($ids)) {
            return [];
        }

        $idsMap = array_flip($ids);
        // Только активные узлы каталога (как Page::isActive — status === 1)
        $rows = $this->db->reset()
            ->select(['id', 'name', 'url'])
            ->from('catalog')
            ->where(['status' => 1])
            ->get();

        $map = [];
        foreach ($rows as $rowObj) {
            $row = (array)$rowObj;
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0 || !isset($idsMap[$id])) {
                continue;
            }
            $map[$id] = [
                'name' => (string)($row['name'] ?? ''),
                'url' => (string)($row['url'] ?? ''),
            ];
        }
        return $map;
    }

    private function buildTree(array $nodes, array $pages): array
    {
        $childrenMap = [];
        foreach ($nodes as $node) {
            $pid = $node['parent'] ?? '__root__';
            if ($pid === null || $pid === '') {
                $pid = '__root__';
            }
            if (!isset($childrenMap[$pid])) {
                $childrenMap[$pid] = [];
            }
            $childrenMap[$pid][] = $node;
        }

        foreach ($childrenMap as &$list) {
            usort($list, static function (array $a, array $b): int {
                return ((int)($a['order'] ?? 0)) <=> ((int)($b['order'] ?? 0));
            });
        }
        unset($list);

        $build = function (string $parentId) use (&$build, $childrenMap, $pages): array {
            $result = [];
            $items = $childrenMap[$parentId] ?? [];
            foreach ($items as $item) {
                if (($item['type'] ?? '') === 'page') {
                    $pageId = (int)($item['page_id'] ?? 0);
                    if ($pageId <= 0 || !isset($pages[$pageId])) {
                        continue;
                    }
                    $item['name'] = (string)$pages[$pageId]['name'];
                    $item['url'] = trim((string)$pages[$pageId]['url']);
                    if ($item['url'] === '') {
                        $item['url'] = '#';
                    }
                }
                $id = (string)$item['id'];
                $item['children'] = $build($id);
                $result[] = $item;
            }
            return $result;
        };

        return $build('__root__');
    }

    /**
     * Состав и порядок — из t_menu. Видимость страниц — из ролей пользователя.
     * Суперадмин получает дерево целиком. Чужую страницу-родителя убираем,
     * разрешённых детей поднимаем к ближайшему оставшемуся предку.
     *
     * @param array<int, array<string, mixed>> $nodes
     * @return array<int, array<string, mixed>>
     */
    private function restrictByRole(array $nodes): array
    {
        $allowed = (new RoleService())->menuPageIds();
        if ($allowed === null) {
            return $nodes;
        }

        $allow = array_fill_keys($allowed, true);

        return $this->filterRoleNodes($nodes, $allow);
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     * @param array<int, true> $allow
     * @return array<int, array<string, mixed>>
     */
    private function filterRoleNodes(array $nodes, array $allow): array
    {
        $out = [];
        foreach ($nodes as $node) {
            $children = is_array($node['children'] ?? null) ? $node['children'] : [];
            $children = $this->filterRoleNodes($children, $allow);
            $type = (string) ($node['type'] ?? '');

            if ($type === 'group') {
                if ($children === []) {
                    continue;
                }
                $node['children'] = $children;
                $out[] = $node;
                continue;
            }

            if ($type !== 'page') {
                continue;
            }

            $pageId = (int) ($node['page_id'] ?? 0);
            if ($pageId <= 0 || !isset($allow[$pageId])) {
                foreach ($children as $child) {
                    $out[] = $child;
                }
                continue;
            }

            $node['children'] = $children;
            $out[] = $node;
        }

        return $out;
    }
}

