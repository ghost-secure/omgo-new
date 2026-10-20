<?php

namespace UC;

/**
 * Кнопка сценария админки проекта: HTML для /jsapi/admin/flow (admin-pages: [data-admin-flow]).
 * Опциональный бейдж — часть той же кнопки (угол иконки / текста).
 */
final class Flow
{
    /**
     * @param array{
     *   id: string,
     *   title: string,
     *   action?: string,
     *   dataid: string,
     *   ico?: string,
     *   table?: string,
     *   label?: string,
     *   badge?: string|int|float|null,
     *   badge_color?: string,
     *   variant?: string,
     * } $opts
     * `variant=toolbar` — кнопка тулбара admin-table: иконка + подпись, класс как у «Выгрузить в Excel».
     * `variant=menu` — пункт выпадающего меню: ширина по тексту, без тултипа.
     * Бейдж не выводится, если значение пустое или число &lt; 1 (для нечисловых строк — как раньше).
     */
    public static function button(array $opts): string
    {
        $rawId = (string) ($opts['id'] ?? '');
        $rawAction = (string) ($opts['action'] ?? 'default');
        $rawDataid = (string) ($opts['dataid'] ?? '');
        $rawTable = isset($opts['table']) ? (string) $opts['table'] : '';
        $rawLabel = trim((string) ($opts['label'] ?? ''));

        $id = htmlspecialchars($rawId, ENT_QUOTES, 'UTF-8');
        $title = htmlspecialchars((string) ($opts['title'] ?? ''), ENT_QUOTES, 'UTF-8');
        $action = htmlspecialchars($rawAction, ENT_QUOTES, 'UTF-8');
        $dataid = htmlspecialchars($rawDataid, ENT_QUOTES, 'UTF-8');
        $table = $rawTable !== '' ? htmlspecialchars($rawTable, ENT_QUOTES, 'UTF-8') : '';
        $ico = htmlspecialchars((string) ($opts['ico'] ?? 'Editor/list-unordered'), ENT_QUOTES, 'UTF-8');
        $label = $rawLabel !== '' ? htmlspecialchars($rawLabel, ENT_QUOTES, 'UTF-8') : '';

        $flowHash = FlowSignature::sign($rawId, $rawAction, $rawDataid, $rawTable);
        $hashAttr = ' data-flow-hash="' . htmlspecialchars($flowHash, ENT_QUOTES, 'UTF-8') . '"';

        $badgeRaw = $opts['badge'] ?? null;
        $showBadge = self::shouldShowBadge($badgeRaw);
        $badgeText = $showBadge ? htmlspecialchars((string) $badgeRaw, ENT_QUOTES, 'UTF-8') : '';
        $badgeColorToken = self::resolveBadgeColorToken((string) ($opts['badge_color'] ?? 'blue'));
        $badgeBgStyle = 'background: var(--color-' . $badgeColorToken . ');';

        $tableAttr = $table !== '' ? ' data-flow-table="' . $table . '"' : '';
        $variant = (string) ($opts['variant'] ?? '');
        $isToolbar = $variant === 'toolbar';
        $isMenu = $variant === 'menu';
        $btnClass = $isToolbar
            ? 'btn btn-outline-secondary admin-flow-btn'
            : ($isMenu
                ? 'btn btn-sm btn-outline-secondary admin-flow-btn'
                : ($label !== ''
                    ? 'btn btn-sm btn-outline-primary admin-flow-btn'
                    : 'btn-icon btn-icon-outline-primary admin-flow-btn'));
        $btnStyle = 'position:relative;overflow:visible;';
        if ($isMenu) {
            $btnStyle .= 'text-align:left;white-space:nowrap;';
        }
        $tooltipAttr = $isMenu
            ? ''
            : ' data-plugin="tooltip" data-tooltip="' . $title . '" data-tooltip-position="top"';

        $html = '<span class="admin-flow-trigger" style="display:inline-block;vertical-align:middle;">'
            . '<button type="button" class="' . $btnClass . '" data-admin-flow'
            . ' data-flow-id="' . $id . '"'
            . ' data-flow-action="' . $action . '"'
            . ' data-flow-dataid="' . $dataid . '"'
            . ' data-flow-title="' . $title . '"'
            . $tableAttr
            . $hashAttr
            . $tooltipAttr
            . ' style="' . $btnStyle . '">';

        if ($isToolbar) {
            $html .= '<span class="nav-item__icon" data-icon="' . $ico . '"></span>';
            if ($label !== '') {
                $html .= '<span class="dynblock-toolbar__btn-label">' . $label . '</span>';
            }
        } elseif ($label !== '') {
            $html .= '<span class="admin-flow-label">' . $label . '</span>';
        } else {
            $html .= '<span class="nav-item__icon" data-icon="' . $ico . '"></span>';
        }

        if ($showBadge) {
            $html .= '<span class="admin-flow-badge" aria-hidden="true" style="' . htmlspecialchars($badgeBgStyle, ENT_QUOTES, 'UTF-8') . '">'
                . $badgeText
                . '</span>';
        }

        $html .= '</button></span>';

        return $html;
    }

    /**
     * Имя токена для uikit: var(--color-&lt;token&gt;). По умолчанию blue.
     */
    private static function resolveBadgeColorToken(string $raw): string
    {
        $c = strtolower(trim($raw));
        $allowed = [
            'blue', 'red', 'green', 'yellow', 'primary', 'secondary', 'danger', 'success',
            'warning', 'info', 'muted', 'light', 'dark', 'white', 'black', 'lgrey',
        ];
        if ($c === '' || !in_array($c, $allowed, true)) {
            return 'blue';
        }

        return $c;
    }

    private static function shouldShowBadge(mixed $badgeRaw): bool
    {
        if ($badgeRaw === null) {
            return false;
        }
        if (is_int($badgeRaw) || is_float($badgeRaw)) {
            return (float) $badgeRaw >= 1.0;
        }
        $s = trim((string) $badgeRaw);
        if ($s === '') {
            return false;
        }
        if (is_numeric($s)) {
            return (float) $s >= 1.0;
        }

        return true;
    }
}
