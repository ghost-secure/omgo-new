<?php
/**
 * Легенда раскраски строк. Один образец на цвет, подписи через запятую.
 * Образец повторяет ячейку: --row-tone и левая полоса --row-accent.
 * Крестик — плагин dismissible-info, срок сутки (localStorage и cookie).
 *
 * @var \UC\DynBlock\AdminTableView $view
 * @var string $tableName
 */

use Services\AnalysisService;

$legend = $view->getStatusLegend();
if ($legend === []) {
    return;
}

$groups = [];
foreach ($legend as $item) {
    $class = (string) ($item['class'] ?? '');
    $label = trim((string) ($item['label'] ?? ''));
    if ($class === '' || $label === '') {
        continue;
    }
    if (!isset($groups[$class])) {
        $token = '';
        if (preg_match('/(?:^|\s)bg-([a-z0-9]+)/', $class, $match) === 1) {
            $candidate = $match[1];
            if (in_array($candidate, AnalysisService::ROW_BG_TOKENS, true)) {
                $token = $candidate;
            }
        }
        $groups[$class] = [
            'class' => $class,
            'token' => $token,
            'labels' => [],
        ];
    }
    if (!in_array($label, $groups[$class]['labels'], true)) {
        $groups[$class]['labels'][] = $label;
    }
}
if ($groups === []) {
    return;
}

$legendKey = 'dynblock-status-legend-' . preg_replace('/[^a-z0-9_-]+/i', '-', (string) ($tableName ?? 'legend'));
?>
<div class="dynblock-status-legend"
     data-plugin="dismissible-info"
     data-info-key="<?= htmlspecialchars($legendKey, ENT_QUOTES, 'UTF-8') ?>"
     data-info-ttl-days="1">
    <span class="dynblock-status-legend__items">
        <?php foreach ($groups as $group): ?>
            <?php
            $swatchStyle = $group['token'] !== ''
                ? '--row-tone: var(--color-bg-' . $group['token'] . '); --row-accent: var(--color-' . $group['token'] . ');'
                : '';
            ?>
            <span class="dynblock-status-legend__item">
                <span class="dynblock-status-legend__swatch <?= htmlspecialchars($group['class'], ENT_QUOTES, 'UTF-8') ?>"
                      <?php if ($swatchStyle !== ''): ?>style="<?= htmlspecialchars($swatchStyle, ENT_QUOTES, 'UTF-8') ?>"<?php endif; ?>
                      aria-hidden="true"></span>
                <span class="dynblock-status-legend__label"><?= htmlspecialchars(implode(', ', $group['labels']), ENT_QUOTES, 'UTF-8') ?></span>
            </span>
        <?php endforeach; ?>
    </span>
    <button type="button"
            class="btn-icon dynblock-status-legend__dismiss"
            aria-label="Закрыть"
            data-action="dismiss-info"
            data-plugin="tooltip"
            data-tooltip="Закрыть">
        <span data-icon="System/close-line"></span>
    </button>
</div>
