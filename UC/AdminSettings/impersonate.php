<?php
/**
 * @var array<string, mixed> $ctx
 */
$e = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
$canList = !empty($ctx['can_impersonate']);
$impersonating = !empty($ctx['impersonating']);
if (!$canList && !$impersonating) {
    return;
}
?>
<div class="admin-settings-impersonate admin-settings__section">
    <h3>Авторизация</h3>
    <?php if ($canList && !empty($ctx['impersonate_users'])): ?>
        <div class="admin-settings-impersonate__list">
            <?php foreach ($ctx['impersonate_users'] as $row): ?>
                <?php
                $id = (int) ($row['id'] ?? 0);
                $label = trim((string) ($row['label'] ?? ''));
                if ($id <= 0 || $label === '') {
                    continue;
                }
                ?>
                <button type="button"
                        class="admin-settings-impersonate__btn"
                        data-admin-impersonate="start"
                        data-user-id="<?= $e((string) $id) ?>">
                    <?= $e($label) ?>
                </button>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if ($impersonating): ?>
        <button type="button" class="admin-settings-impersonate__exit" data-admin-impersonate="exit">
            Отмена входа
        </button>
    <?php endif; ?>
</div>
