<?php

namespace Services;

use Core\Config;
use Core\Container;
use Core\Database\QueryBuilder;

/**
 * Собирает JSON меню админки из catalog (/manager/*) в формате t_menu.catlink_pages.
 * Данные магазина из дампа t_menu.sql не импортирует.
 */
class AdminMenuCatalogBuilder
{
    public const CODE = 'admin_left';

    private QueryBuilder $db;

    /** @var list<string> */
    private array $skipMapurls;

    private string $urlPrefix;

    public function __construct(?QueryBuilder $db = null)
    {
        $this->db = $db ?? Container::getInstance()->get(QueryBuilder::class);

        $config = Config::getInstance();
        $this->urlPrefix = rtrim((string)$config->get('menu.catalog_prefix', '/manager'), '/') ?: '/manager';

        $skip = $config->get('menu.skip_mapurls', ['add', 'login', 'auth']);
        $this->skipMapurls = is_array($skip) ? array_map('strval', $skip) : ['add', 'login', 'auth'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function buildNodes(): array
    {
        $pages = $this->loadCatalogPages();
        if ($pages === []) {
            return [];
        }

        $root = $this->findManagerRoot($pages);
        $selected = $this->collectManagerPages($pages, $root);
        if ($selected === []) {
            return [];
        }

        return $this->toMenuNodes($selected, $root);
    }

    /**
     * @return array{saved:bool, overwritten:bool, count:int, code:string}
     */
    public function persist(string $code = self::CODE, bool $overwrite = false): array
    {
        $nodes = $this->buildNodes();
        $json = json_encode($nodes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('Не удалось закодировать JSON меню');
        }

        $row = $this->db->reset()
            ->select(['cat', 'catlink_pages'])
            ->from('t_menu')
            ->where(['varc_code' => $code])
            ->getOne();

        if ($row === null) {
            throw new \RuntimeException("В t_menu нет записи с varc_code={$code}");
        }

        $existing = trim((string)($row->catlink_pages ?? ''));
        $hasTree = $existing !== '' && $existing !== '[]' && $existing !== 'null';
        if ($hasTree && !$overwrite) {
            return [
                'saved' => false,
                'overwritten' => false,
                'count' => $this->countJsonNodes($existing),
                'code' => $code,
            ];
        }

        $this->db->reset()->execute(
            'UPDATE `t_menu` SET `catlink_pages` = ? WHERE `varc_code` = ?',
            [$json, $code]
        );

        return [
            'saved' => true,
            'overwritten' => $hasTree,
            'count' => count($nodes),
            'code' => $code,
        ];
    }

    /**
     * Добавить страницу в уже собранный JSON, не пересобирая дерево.
     *
     * @return bool true, если запись появилась или уже была
     */
    public function pinPage(
        int $pageId,
        string $parentNodeId,
        string $code = self::CODE,
        ?string $icon = null,
        int $order = 0
    ): bool {
        if ($pageId <= 0 || $parentNodeId === '') {
            return false;
        }

        $row = $this->db->reset()
            ->select(['catlink_pages'])
            ->from('t_menu')
            ->where(['varc_code' => $code])
            ->getOne();
        if ($row === null) {
            return false;
        }

        $nodes = json_decode((string)($row->catlink_pages ?? ''), true);
        if (!is_array($nodes)) {
            $nodes = [];
        }

        foreach ($nodes as $node) {
            if (is_array($node) && (int)($node['page_id'] ?? 0) === $pageId) {
                return true;
            }
        }

        $item = [
            'id' => 'p_' . $pageId,
            'type' => 'page',
            'page_id' => $pageId,
            'parent' => $parentNodeId,
            'order' => $order,
        ];
        if ($icon !== null && $icon !== '') {
            $item['icon'] = $icon;
        }
        $nodes[] = $item;

        $json = json_encode($nodes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }

        $this->db->reset()->execute(
            'UPDATE `t_menu` SET `catlink_pages` = ? WHERE `varc_code` = ?',
            [$json, $code]
        );

        return true;
    }

    /**
     * @param array<int, array<string, mixed>> $pages
     * @return array<int, array<string, mixed>>
     */
    private function collectManagerPages(array $pages, ?array $root): array
    {
        $selected = [];

        if ($root !== null) {
            $rootId = (int)$root['id'];
            $selected[$rootId] = $root;
            $this->collectDescendants($rootId, $pages, $selected);
        }

        foreach ($pages as $id => $row) {
            if ($this->urlMatchesPrefix((string)($row['url'] ?? ''))) {
                $selected[$id] = $row;
            }
        }

        foreach ($selected as $id => $row) {
            if ($this->isSkipped($row, $root)) {
                unset($selected[$id]);
            }
        }

        return $selected;
    }

    /**
     * @param array<int, array<string, mixed>> $pages
     * @param array<int, array<string, mixed>> $selected
     */
    private function collectDescendants(int $parentId, array $pages, array &$selected): void
    {
        foreach ($pages as $id => $row) {
            if ((int)($row['id_up'] ?? 0) !== $parentId) {
                continue;
            }
            if (isset($selected[$id])) {
                continue;
            }
            $selected[$id] = $row;
            $this->collectDescendants($id, $pages, $selected);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $selected
     * @return list<array<string, mixed>>
     */
    private function toMenuNodes(array $selected, ?array $root): array
    {
        $nodes = [];
        $order = 0;
        $rootId = $root !== null ? (int)$root['id'] : 0;

        if ($root !== null && isset($selected[$rootId])) {
            $nodes[] = $this->pageNode($root, null, $order);
            $order++;
        }

        $topLevel = $rootId > 0
            ? $this->childrenOf($rootId, $selected)
            : $this->orphans($selected, $rootId);

        foreach ($topLevel as $child) {
            $childId = (int)$child['id'];
            $kids = $this->childrenOf($childId, $selected);
            if ($kids !== []) {
                $groupId = 'g_' . $childId;
                $nodes[] = [
                    'id' => $groupId,
                    'type' => 'group',
                    'title' => (string)$child['name'],
                    'parent' => null,
                    'order' => $order,
                ];
                $nodes[] = $this->pageNode($child, $groupId, 0);
                $this->emitSubtree($childId, $selected, 'p_' . $childId, $nodes);
                $order++;
            } else {
                $nodes[] = $this->pageNode($child, null, $order);
                $order++;
            }
        }

        return $nodes;
    }

    /**
     * @param array<int, array<string, mixed>> $selected
     * @param list<array<string, mixed>> $nodes
     */
    private function emitSubtree(int $parentId, array $selected, string $parentNodeId, array &$nodes): void
    {
        $index = 0;
        foreach ($this->childrenOf($parentId, $selected) as $child) {
            $nodes[] = $this->pageNode($child, $parentNodeId, $index);
            $this->emitSubtree((int)$child['id'], $selected, 'p_' . (int)$child['id'], $nodes);
            $index++;
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function pageNode(array $row, ?string $parent, int $order): array
    {
        $node = [
            'id' => 'p_' . (int)$row['id'],
            'type' => 'page',
            'page_id' => (int)$row['id'],
            'parent' => $parent,
            'order' => $order,
        ];

        $icon = trim((string)($row['iconclass'] ?? ''));
        if ($icon !== '' && strpos($icon, '/') !== false) {
            $node['icon'] = $icon;
        }

        return $node;
    }

    /**
     * @param array<int, array<string, mixed>> $selected
     * @return list<array<string, mixed>>
     */
    private function childrenOf(int $parentId, array $selected): array
    {
        $out = [];
        foreach ($selected as $row) {
            if ((int)($row['id_up'] ?? 0) === $parentId && (int)$row['id'] !== $parentId) {
                $out[] = $row;
            }
        }
        usort($out, static function (array $a, array $b): int {
            $ord = ((int)($a['ord'] ?? 0)) <=> ((int)($b['ord'] ?? 0));
            return $ord !== 0 ? $ord : ((int)$a['id'] <=> (int)$b['id']);
        });
        return $out;
    }

    /**
     * @param array<int, array<string, mixed>> $selected
     * @return list<array<string, mixed>>
     */
    private function orphans(array $selected, int $rootId): array
    {
        $out = [];
        foreach ($selected as $row) {
            $id = (int)$row['id'];
            if ($id === $rootId) {
                continue;
            }
            $parent = (int)($row['id_up'] ?? 0);
            if ($parent <= 0 || !isset($selected[$parent])) {
                $out[] = $row;
            }
        }
        usort($out, static function (array $a, array $b): int {
            return ((int)($a['ord'] ?? 0)) <=> ((int)($b['ord'] ?? 0));
        });
        return $out;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadCatalogPages(): array
    {
        $rows = $this->db->reset()
            ->select(['*'])
            ->from('catalog')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $item = is_array($row) ? $row : (array)$row;
            $id = (int)($item['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $out[$id] = $item;
        }

        return $out;
    }

    /**
     * @param array<int, array<string, mixed>> $pages
     * @return array<string, mixed>|null
     */
    private function findManagerRoot(array $pages): ?array
    {
        foreach ($pages as $row) {
            $url = $this->normalizePath((string)($row['url'] ?? ''));
            $map = strtolower(trim((string)($row['mapurl'] ?? ''), '/'));
            if ($url === $this->urlPrefix || $map === ltrim($this->urlPrefix, '/')) {
                return $row;
            }
        }
        return null;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed>|null $root
     */
    private function isSkipped(array $row, ?array $root): bool
    {
        if ($root !== null && (int)$row['id'] === (int)$root['id']) {
            return false;
        }

        $map = strtolower(trim((string)($row['mapurl'] ?? ''), '/'));
        if ($map !== '' && in_array($map, $this->skipMapurls, true)) {
            return true;
        }

        $url = $this->normalizePath((string)($row['url'] ?? ''));
        foreach ($this->skipMapurls as $skip) {
            $skipPath = $this->urlPrefix . '/' . trim($skip, '/');
            if ($url === $skipPath) {
                return true;
            }
        }

        return trim((string)($row['name'] ?? '')) === '';
    }

    private function urlMatchesPrefix(string $url): bool
    {
        $path = $this->normalizePath($url);
        return $path === $this->urlPrefix || strpos($path, $this->urlPrefix . '/') === 0;
    }

    private function normalizePath(string $url): string
    {
        $path = parse_url(trim($url), PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return '';
        }
        $path = '/' . trim($path, '/');
        return $path === '/' ? '' : $path;
    }

    private function countJsonNodes(string $json): int
    {
        $decoded = json_decode($json, true);
        return is_array($decoded) ? count($decoded) : 0;
    }
}
