<?php

namespace Services;

class MenuView
{
    private string $code;

    private function __construct(string $code)
    {
        $this->code = $code;
    }

    public static function by(string $code): self
    {
        return new self($code);
    }

    public function render(string $pattern): string
    {
        try {
            $builder = new MenuBuilder();
            $menuData = $builder->getMenuData($this->code);
            $template = PROJECT_PATH . '/UC/menu/' . basename($pattern) . '.php';
            if (!file_exists($template)) {
                return '';
            }

            $nodes = is_array($menuData['nodes'] ?? null) ? $menuData['nodes'] : [];
            $nodes = $this->applyMenuFilterHook($nodes, $pattern);

            $items = [];
            $menuTree = $nodes;
            $menuBadges = [];

            if ($pattern === 'admin_sidebar_preview' && class_exists(\Project\Hooks\MenuBadgesHook::class)) {
                $pageIds = [];
                $collectPageIds = function (array $tree) use (&$collectPageIds, &$pageIds): void {
                    foreach ($tree as $node) {
                        if (($node['type'] ?? '') === 'page' && !empty($node['page_id'])) {
                            $pageIds[] = (int)$node['page_id'];
                        }
                        if (!empty($node['children']) && is_array($node['children'])) {
                            $collectPageIds($node['children']);
                        }
                    }
                };
                $collectPageIds($menuTree);
                $pageIds = array_values(array_unique($pageIds));

                $badgeHook = new \Project\Hooks\MenuBadgesHook();
                $menuBadges = $badgeHook->resolve($pageIds);
            }

            ob_start();
            include $template;
            return (string)ob_get_clean();
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function applyMenuFilterHook(array $nodes, string $pattern): array
    {
        if (!class_exists(\Project\Hooks\MenuFilterHook::class)) {
            return $nodes;
        }

        $hook = new \Project\Hooks\MenuFilterHook();
        if (!method_exists($hook, 'allowNode')) {
            return $nodes;
        }

        $ctx = [
            'menu_code' => $this->code,
            'pattern' => $pattern,
        ];

        $filter = function (array $items) use (&$filter, $hook, $ctx): array {
            $out = [];
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                if (!$hook->allowNode($item, $ctx)) {
                    continue;
                }
                if (!empty($item['children']) && is_array($item['children'])) {
                    $item['children'] = $filter($item['children']);
                }
                $out[] = $item;
            }
            return $out;
        };

        return $filter($nodes);
    }
}
