<?php

/**
 * Поля карточки черновика.
 * Связи как в легаси UC\Forms: data-form-toggle, data-form-interactive, data-wrapper.
 * Чекбокс и радио — custom-control-input, как у полей справочника.
 */

function omgoH(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function omgoSeq(): int
{
    static $n = 0;

    return ++$n;
}

function omgoUseDesktopFiles(bool $desktop): void
{
    $GLOBALS['omgoDesktopFiles'] = $desktop;
}

function omgoDesktopFiles(): bool
{
    return !empty($GLOBALS['omgoDesktopFiles']);
}

function omgoColOpen(): void
{
    echo '<div class="d-flex flex-column gap-md" style="flex: 1 1 18rem">';
}

function omgoColClose(): void
{
    echo '</div>';
}

/**
 * @param list<array{w: string, v?: array<int|string>|null, h?: array<int|string>|null}> $rules
 */
function omgoInteractiveAttr(array $rules): string
{
    if ($rules === []) {
        return '';
    }
    $payload = [];
    foreach ($rules as $rule) {
        $payload[] = [
            'w' => $rule['w'],
            'v' => $rule['v'] ?? null,
            'h' => $rule['h'] ?? null,
        ];
    }

    return ' data-form-interactive="' . omgoH((string) json_encode($payload, JSON_UNESCAPED_UNICODE)) . '"';
}

function omgoField(string $label, string $name, string $value, bool $required = false, string $comment = ''): void
{
    echo '<div class="form-field d-flex flex-column gap-xs w-full">';
    \UC\FormLabel::render($label, $required);
    echo '<input type="text" name="' . omgoH($name) . '" value="' . omgoH($value) . '"' . ($required ? ' data-required' : '') . '>';
    if ($comment !== '') {
        echo '<div>' . $comment . '</div>';
    }
    echo '</div>';
}

function omgoSelect(
    string $label,
    string $name,
    array $options,
    $value,
    bool $required = false,
    array $rules = [],
    string $comment = '',
    string $extra = ''
): void {
    $current = (string) $value;
    echo '<div class="form-field d-flex flex-column gap-xs w-full">';
    \UC\FormLabel::render($label, $required);
    echo '<div data-plugin="custom-select">';
    echo '<select class="form-control" name="' . omgoH($name) . '" placeholder="Выберите"' . ($required ? ' data-required' : '') . omgoInteractiveAttr($rules) . ' ' . $extra . '>';
    echo '<option value="">Выберите</option>';
    foreach ($options as $id => $text) {
        $selected = (string) $id === $current ? ' selected' : '';
        echo '<option value="' . omgoH((string) $id) . '"' . $selected . '>' . omgoH((string) $text) . '</option>';
    }
    echo '</select></div>';
    if ($comment !== '') {
        echo '<div>' . $comment . '</div>';
    }
    echo '</div>';
}

function omgoFixed(string $label, string $name, string $value, string $text): void
{
    echo '<div class="form-field d-flex flex-column gap-xs w-full">';
    \UC\FormLabel::render($label);
    echo '<div>' . omgoH($text) . '</div>';
    echo '<input type="hidden" name="' . omgoH($name) . '" value="' . omgoH($value) . '">';
    echo '</div>';
}

function omgoCheck(string $label, string $name, bool $checked, string $toggle = '', bool $locked = false, string $value = '1'): void
{
    $id = 'omgo-check-' . omgoSeq();
    echo '<div class="d-flex align-center gap-sm">';
    echo '<input type="checkbox" class="custom-control-input" id="' . $id . '" value="' . omgoH($value) . '"';
    if (!$locked) {
        echo ' name="' . omgoH($name) . '"';
    }
    if ($toggle !== '') {
        echo ' data-form-toggle="' . omgoH($toggle) . '"';
    }
    if ($checked) {
        echo ' checked';
    }
    if ($locked) {
        echo ' disabled';
    }
    echo '>';
    echo '<label for="' . $id . '" class="custom-control-label checkbox-label">' . omgoH($label) . '</label>';
    echo '</div>';
    if ($locked && $checked) {
        echo '<input type="hidden" name="' . omgoH($name) . '" value="' . omgoH($value) . '">';
    }
}

function omgoRadio(string $label, string $name, array $options, $value, array $toggles = [], bool $required = false, bool $html = false): void
{
    $current = (string) $value;
    echo '<div class="form-field d-flex flex-column gap-sm w-full">';
    if ($label !== '') {
        \UC\FormLabel::render($label, $required);
    }
    echo '<div class="d-flex flex-column gap-sm">';
    foreach ($options as $id => $text) {
        $uid = 'omgo-radio-' . omgoSeq();
        $toggle = $toggles[(string) $id] ?? '';
        $checked = (string) $id === $current ? ' checked' : '';
        echo '<div class="d-flex align-center gap-sm">';
        echo '<input type="radio" class="custom-control-input" name="' . omgoH($name) . '" id="' . $uid . '" value="' . omgoH((string) $id) . '"' . $checked . ($required ? ' data-required' : '');
        if ($toggle !== '') {
            echo ' data-form-toggle="' . omgoH($toggle) . '"';
        }
        echo '>';
        $caption = $html ? (string) $text : omgoH((string) $text);
        echo '<label for="' . $uid . '" class="custom-control-label radio-label">' . $caption . '</label>';
        echo '</div>';
    }
    echo '</div></div>';
}

function omgoFileControl(string $name): void
{
    $accept = '.pdf,.jpg,.jpeg,.png,.heic,.heif';
    if (!omgoDesktopFiles()) {
        echo '<input type="file" name="' . omgoH($name) . '" accept="' . $accept . '">';
        return;
    }
    $extensions = json_encode(['pdf', 'jpg', 'jpeg', 'png', 'heic', 'heif']);
    echo '<div class="file-dropzone" data-plugin="file-dropzone" data-max-size="' . (32 * 1024 * 1024) . '" data-extensions="' . omgoH((string) $extensions) . '">';
    echo '<div class="dropzone-area">';
    echo '<svg class="dropzone-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">';
    echo '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>';
    echo '<polyline points="17 8 12 3 7 8"></polyline>';
    echo '<line x1="12" y1="3" x2="12" y2="15"></line>';
    echo '</svg>';
    echo '<div class="dropzone-text">Перетащите файл сюда или кликните для выбора</div>';
    echo '<div class="dropzone-sub">Поддерживаются: pdf, jpg, jpeg, png, heic, heif (Макс. 32MB)</div>';
    echo '<div class="dropzone-hint">Или нажмите Ctrl+V для вставки из буфера обмена</div>';
    echo '</div>';
    echo '<input type="file" id="omgo-file-' . omgoSeq() . '" name="' . omgoH($name) . '" class="dropzone-input" accept="' . $accept . '">';
    echo '<div class="dropzone-error" style="display: none;"></div>';
    echo '</div>';
}

function omgoActionButton(string $label, string $icon, string $attrs): void
{
    echo '<button type="button" class="btn btn-outline-secondary dynblock-toolbar__icon-btn" ' . $attrs . '>';
    echo '<span class="nav-item__icon" data-icon="' . omgoH($icon) . '"></span>';
    echo '<span class="dynblock-toolbar__btn-label">' . omgoH($label) . '</span>';
    echo '</button>';
}

function omgoFiles(string $key, string $label, string $extra, int $max = 5): void
{
    echo '<div class="d-flex flex-column gap-sm w-full">';
    echo '<div class="form-field d-flex flex-column gap-xs w-full">';
    \UC\FormLabel::render($label);
    omgoFileControl('fields[_' . $key . '][]');
    echo '</div>';
    for ($i = 1; $i <= $max; $i++) {
        echo '<div data-multifile="' . omgoH($key) . '" hidden>';
        echo '<div class="d-flex align-end gap-sm">';
        echo '<div class="form-field d-flex flex-column gap-xs w-full">';
        \UC\FormLabel::render($extra);
        omgoFileControl('fields[_' . $key . '][]');
        echo '</div>';
        omgoActionButton('Удалить', 'System/delete-bin-line', 'data-multifile-remove');
        echo '</div></div>';
    }
    echo '<div>';
    omgoActionButton('Добавить файл', 'System/add-line', 'data-multifile-add="' . omgoH($key) . '"');
    echo '</div>';
    echo '</div>';
}

function omgoAnamnes(array $rows, array $options): void
{
    $filled = [];
    foreach ($rows as $row) {
        if ((int) ($row['view_anamnes'] ?? 0) > 0) {
            $filled[] = $row;
        }
    }
    echo '<div class="d-flex flex-column gap-md w-full" data-anamnes-list>';
    foreach ($filled as $row) {
        omgoAnamnesRow($options, $row);
    }
    echo '</div>';
    echo '<template data-anamnes-template>';
    omgoAnamnesRow($options, []);
    echo '</template>';
    echo '<div>';
    omgoActionButton('Добавить анамнез', 'System/add-line', 'data-anamnes-add');
    echo '</div>';
}

function omgoAnamnesRow(array $options, array $row): void
{
    $kind = (int) ($row['view_anamnes'] ?? 0);
    $other = $kind === 6;
    echo '<div data-anamnes class="d-flex flex-column gap-md">';
    echo '<div class="d-flex align-end gap-sm">';
    echo '<div class="w-full" style="flex: 1 1 auto; min-width: 0">';
    omgoSelect(
        'Семейный онкологический анамнез',
        'fields[anamnes][view_anamnes][]',
        $options,
        $kind > 0 ? (string) $kind : ''
    );
    echo '</div>';
    omgoActionButton('Удалить', 'System/delete-bin-line', 'data-anamnes-remove');
    echo '</div>';
    echo '<div data-anamnes-other class="d-flex flex-column gap-md"' . ($other ? '' : ' hidden') . '>';
    omgoField('Укажите свое название', 'fields[anamnes][varc_anamnes][]', $other ? (string) ($row['varc_anamnes'] ?? '') : '');
    omgoField('Степень родства', 'fields[anamnes][varc_kin][]', $other ? (string) ($row['varc_kin'] ?? '') : '');
    echo '</div></div>';
}

function omgoPlain(string $label, string $text): void
{
    echo '<div class="form-field d-flex flex-column gap-xs w-full">';
    \UC\FormLabel::render($label);
    echo '<div>' . omgoH($text) . '</div>';
    echo '</div>';
}

function omgoArea(string $label, string $name, string $value, bool $required = false): void
{
    echo '<div class="form-field d-flex flex-column gap-xs w-full">';
    \UC\FormLabel::render($label, $required);
    echo '<textarea name="' . omgoH($name) . '"' . ($required ? ' data-required' : '') . '>' . omgoH($value) . '</textarea>';
    echo '</div>';
}

function omgoDate(string $label, string $name, string $value, bool $required = false): void
{
    echo '<div class="form-field d-flex flex-column gap-xs w-full">';
    \UC\FormLabel::render($label, $required);
    echo '<input type="date" name="' . omgoH($name) . '" value="' . omgoH($value) . '"' . ($required ? ' data-required' : '') . '>';
    echo '</div>';
}

/**
 * Материал лаборатории: галка врача (если уже стоит — не снимается), маркировка,
 * и либо радио «анализ выполнен на этом материале», либо галка «вывести в заключение».
 *
 * @param list<int>|string $selected
 */
function omgoLabMaterial(
    string $title,
    string $flag,
    bool $on,
    bool $locked,
    string $markLabel,
    string $markName,
    string $markValue,
    int $materialId,
    string $mode,
    $selected
): void {
    echo '<div class="d-flex flex-column gap-md" style="flex: 1 1 16rem">';
    omgoCheck($title, 'fields[' . $flag . ']', $on, $flag, $locked);
    echo '<div data-wrapper="' . omgoH($flag) . '" class="d-flex flex-column gap-md">';
    omgoField($markLabel, 'fields[' . $markName . ']', $markValue, true);
    if ($mode === 'check') {
        $ids = is_array($selected) ? array_map('intval', $selected) : [];
        omgoCheck(
            'Вывести в заключение',
            'fields[multiselect_materialexec][]',
            in_array($materialId, $ids, true),
            '',
            false,
            (string) $materialId
        );
    } else {
        $current = is_array($selected) ? (string) (int) ($selected[0] ?? 0) : (string) $selected;
        if ($current === '0') {
            $current = '';
        }
        omgoRadio('Анализ выполнен на этом материале', 'fields[view_materialexec]', [
            (string) $materialId => '',
        ], $current, [], true);
    }
    echo '</div></div>';
}

function omgoAssayHead(
    string $typeName,
    ?string $fixedMethodId,
    string $fixedMethodText,
    array $methods,
    $methodValue,
    array $reagents,
    $reagentValue
): void {
    omgoPlain('Выполненный анализ', $typeName);
    if ($fixedMethodId !== null) {
        omgoFixed('Метод анализа', 'fields[view_method]', $fixedMethodId, $fixedMethodText);
    } else {
        omgoSelect('Метод анализа', 'fields[view_method]', $methods, $methodValue, true);
    }
    omgoSelect('Используемые реагенты', 'fields[view_reagent]', $reagents, $reagentValue);
}

/**
 * Онкогенные варианты AKT. Кнопки те же, что у семейного анамнеза карточки пациента.
 *
 * @param list<array<string, mixed>> $rows
 * @param array<int, string> $classes
 */
function omgoOnko(array $rows, array $classes): void
{
    $filled = [];
    foreach ($rows as $row) {
        if (trim((string) ($row['varc_variant'] ?? '')) !== '') {
            $filled[] = $row;
        }
    }
    if ($filled === []) {
        $filled[] = [];
    }
    echo '<div class="d-flex flex-column gap-md w-full" data-anamnes-list>';
    foreach ($filled as $row) {
        omgoOnkoRow($classes, $row);
    }
    echo '</div>';
    echo '<template data-anamnes-template>';
    omgoOnkoRow($classes, []);
    echo '</template>';
    echo '<div>';
    omgoActionButton('Добавить другой онкогенный вариант', 'System/add-line', 'data-anamnes-add');
    echo '</div>';
}

/**
 * @param array<int, string> $classes
 * @param array<string, mixed> $row
 */
function omgoOnkoRow(array $classes, array $row): void
{
    $depth = (string) ($row['cnt_vardepth'] ?? '');
    if ($depth === '0') {
        $depth = '';
    }
    echo '<div data-anamnes class="d-flex flex-column gap-md">';
    echo '<div class="d-flex align-end gap-sm" style="flex-wrap: wrap">';
    echo '<div style="flex: 1 1 12rem; min-width: 0">';
    omgoField('Название варианта', 'fields[onko][varc_variant][]', (string) ($row['varc_variant'] ?? ''), true);
    echo '</div>';
    echo '<div style="flex: 1 1 10rem; min-width: 0">';
    omgoField('Частота аллеля', 'fields[onko][varc_allel][]', (string) ($row['varc_allel'] ?? ''));
    echo '</div>';
    echo '<div style="flex: 1 1 10rem; min-width: 0">';
    omgoField('Глубина прочтения варианта', 'fields[onko][cnt_vardepth][]', $depth);
    echo '</div>';
    echo '<div style="flex: 1 1 12rem; min-width: 0">';
    omgoSelect('Класс онкогенности', 'fields[onko][view_onko][]', $classes, $row['view_onko'] ?? '', true);
    echo '</div>';
    omgoActionButton('Удалить', 'System/delete-bin-line', 'data-anamnes-remove');
    echo '</div></div>';
}
