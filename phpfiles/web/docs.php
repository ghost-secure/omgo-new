<?php

/**
 * Документы кабинета. catalog.php = web/docs.php, /manager/dokumenty/.
 * Легаси web/docs.php: вкладки по t_docs_type, в каждой те же карточки t_docs.
 * Отдельный шаблон динблока не нужен: разметка одна.
 */

use Core\Container;
use Services\DocumentService;

$service = Container::getInstance()->get(DocumentService::class);
$sections = $service->sections();

$h = static function (string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$iconLink = static function (string $href, string $icon, string $title, bool $download) use ($h): string {
    $extra = $download ? ' download' : ' target="_blank" rel="noopener"';

    return '<a href="' . $h($href) . '" class="btn btn-icon btn-icon-outline-primary"'
        . ' data-plugin="tooltip" data-tooltip="' . $h($title) . '" data-tooltip-position="top"'
        . ' aria-label="' . $h($title) . '"' . $extra . '>'
        . '<span class="nav-item__icon" data-icon="' . $h($icon) . '"></span></a>';
};

if ($sections === []) {
    echo '<p class="text-muted">Документов нет.</p>';
    return;
}

$tabs = new \UC\TabSet('docs');
foreach ($sections as $section) {
    $tabs->add((string) $section['id'], (string) $section['name']);
}
$tabs->begin();

foreach ($sections as $section) {
    $tabs->panel((string) $section['id']);
    echo '<div class="d-flex flex-column gap-sm min-w-0 max-w-full">';
    foreach ($section['items'] as $item) {
        $title = (string) $item['name'];
        if ((string) $item['date'] !== '') {
            $title .= ' (' . $item['date'] . ')';
        }
        echo '<div class="form-card shadow-md rounded p-md d-flex align-center justify-between gap-md min-w-0 max-w-full _box">';
        echo '<div class="flex-grow-1 min-w-0">' . $h($title) . '</div>';
        echo '<div class="d-flex align-center gap-sm flex-shrink-0">';
        if ((string) $item['file'] !== '') {
            echo $iconLink((string) $item['file'], 'System/eye-line', 'Открыть в браузере', false);
            echo $iconLink((string) $item['file'], 'System/download-line', 'Скачать на компьютер', true);
        }
        if ((string) $item['video'] !== '') {
            $player = '<video class="w-full" controls playsinline src="' . $h((string) $item['video']) . '"></video>';
            echo '<span data-plugin="tooltip" data-tooltip="Просмотр видеоматериала" data-tooltip-position="top">';
            echo '<button type="button" class="btn-icon btn-icon-outline-success"'
                . ' data-plugin="window"'
                . ' data-window-title="Просмотр видеоматериала"'
                . ' data-window-width="800"'
                . ' data-window-height="480"'
                . ' data-window-content="' . $h($player) . '"'
                . ' aria-label="Просмотр видеоматериала">'
                . '<span class="nav-item__icon" data-icon="Media/play-line"></span>'
                . '</button></span>';
        }
        echo '</div></div>';
    }
    echo '</div>';
}

$tabs->finish();
