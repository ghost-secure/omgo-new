<?php
/**
 * Рендер preview-меню для админ-сайдбара.
 *
 * Ожидает:
 * @var array $menuTree
 * @var array $menuBadges
 */

if (!function_exists('menuPreviewNormalizePath')) {
    function menuPreviewNormalizePath(string $path): string
    {
        $path = parse_url(trim($path), PHP_URL_PATH) ?: '/';
        $path = '/' . trim($path, '/');
        if ($path !== '/') {
            $path .= '/';
        }
        return $path;
    }

    function menuPreviewRenderTree(array $items, array $badges, string $currentPath, bool $root = true): void
    {
        if (!$root): ?>
            <div class="sub-menu">
        <?php endif; ?>

        <?php foreach ($items as $item):
            $type = $item['type'] ?? '';
            $children = is_array($item['children'] ?? null) ? $item['children'] : [];
            if ($type === 'group'):
                $title = htmlspecialchars((string)($item['title'] ?? ''), ENT_QUOTES, 'UTF-8');
                ?>
                <div class="menu-label"><?= $title ?></div>
                <?php if (!empty($children)) {
                    menuPreviewRenderTree($children, $badges, $currentPath, true);
                } ?>
                <?php
                continue;
            endif;

            if ($type !== 'page') {
                continue;
            }

            $name = htmlspecialchars((string)($item['name'] ?? ''), ENT_QUOTES, 'UTF-8');
            $urlRaw = (string)($item['url'] ?? '#');
            $url = htmlspecialchars($urlRaw, ENT_QUOTES, 'UTF-8');
            $pageId = (int)($item['page_id'] ?? 0);
            $badge = $badges[$pageId] ?? null;
            $badgeNumber = (int)($badge['number'] ?? 0);
            $badgeColor = htmlspecialchars((string)($badge['color'] ?? '#ef4444'), ENT_QUOTES, 'UTF-8');
            $iconNameRaw = trim((string)($item['icon'] ?? ''));
            $iconName = htmlspecialchars($iconNameRaw !== '' ? $iconNameRaw : 'System/menu-line', ENT_QUOTES, 'UTF-8');
            $hasBadge = $badgeNumber > 0;
            $isActive = menuPreviewNormalizePath($urlRaw) === menuPreviewNormalizePath($currentPath);
            ?>

            <?php if (!empty($children)):
                $parentClass = 'nav-link' . ($isActive ? ' active expanded' : '');
                ?>
                <a href="<?= $url ?>" class="<?= htmlspecialchars($parentClass, ENT_QUOTES, 'UTF-8') ?>" data-page="menu-builder-preview">
                    <div class="nav-left">
                        <?php if ($root): ?>
                            <span class="nav-icon" data-icon="<?= $iconName ?>"></span>
                        <?php endif; ?>
                        <span><?= $name ?></span>
                    </div>
                    <div class="nav-right">
                        <?php if ($hasBadge): ?>
                            <span class="nav-badge" style="background:<?= $badgeColor ?>;"><?= $badgeNumber ?></span>
                        <?php endif; ?>
                        <span class="chevron" data-icon="Arrows/arrow-down-s-line"></span>
                    </div>
                </a>
                <?php menuPreviewRenderTree($children, $badges, $currentPath, false); ?>
            <?php else: ?>
                <?php if ($root):
                    $leafClass = 'nav-link' . ($isActive ? ' active' : '');
                    ?>
                    <a href="<?= $url ?>" class="<?= htmlspecialchars($leafClass, ENT_QUOTES, 'UTF-8') ?>" data-page="menu-builder-preview">
                        <div class="nav-left">
                            <span class="nav-icon" data-icon="<?= $iconName ?>"></span>
                            <span><?= $name ?></span>
                        </div>
                        <?php if ($hasBadge): ?>
                            <div class="nav-right"><span class="nav-badge" style="background:<?= $badgeColor ?>;"><?= $badgeNumber ?></span></div>
                        <?php endif; ?>
                    </a>
                <?php else:
                    $subClass = 'sub-link' . ($isActive ? ' active' : '');
                    ?>
                    <a href="<?= $url ?>" class="<?= htmlspecialchars($subClass, ENT_QUOTES, 'UTF-8') ?>" data-page="menu-builder-preview-sub">
                        <span><?= $name ?></span>
                        <?php if ($hasBadge): ?>
                            <?php if (!empty($badge['color'])): ?>
                                <span style="font-size:10px; color:<?= $badgeColor ?>; font-weight:700;"><?= $badgeNumber ?></span>
                            <?php else: ?>
                                <span style="font-size:10px; opacity:0.5;"><?= $badgeNumber ?></span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        <?php endforeach; ?>

        <?php if (!$root): ?>
            </div>
        <?php endif;
    }
}

$menuBadges = is_array($menuBadges ?? null) ? $menuBadges : [];
$currentPath = (string)($_SERVER['REQUEST_URI'] ?? '/');
if (!empty($menuTree) && is_array($menuTree)) {
    menuPreviewRenderTree($menuTree, $menuBadges, $currentPath, true);
}

