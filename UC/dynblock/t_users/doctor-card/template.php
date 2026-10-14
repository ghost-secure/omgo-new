<?php
/**
 * Карточка одной записи t_users. Легаси tbldynelements id=58.
 *
 * @var array $records
 * @var string|null $error
 */
if (!empty($error)) {
    echo '<div class="alert alert-danger">' . htmlspecialchars((string) $error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>';
    return;
}

$record = $records[0] ?? null;
if (!is_array($record)) {
    echo '<p>Врач не найден.</p>';
    return;
}

$fio = trim(implode(' ', array_filter([
    trim((string) ($record['varc_surname'] ?? '')),
    trim((string) ($record['varc_name'] ?? '')),
    trim((string) ($record['varc_patron'] ?? '')),
], static fn ($part) => $part !== '')));

$lines = [
    'Лаборатория' => (string) ($record['view_lab_name'] ?? ''),
    'Медучреждение' => (string) ($record['view_med_name'] ?? ''),
    'Регион' => (string) ($record['view_region_name'] ?? ''),
];
?>
<div class="doctor-passport-card">
    <h2><?= $fio !== '' ? htmlspecialchars($fio, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : 'Врач' ?></h2>
    <?php foreach ($lines as $label => $value): ?>
        <?php $value = trim($value); ?>
        <div><b><?= htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>:</b>
            <?= $value !== '' ? htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '—' ?>
        </div>
    <?php endforeach; ?>
</div>
