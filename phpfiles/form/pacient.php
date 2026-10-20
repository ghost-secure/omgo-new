<?php

/**
 * Карточка пациента. catalog.php = form/pacient.php, Helpers\Page::PACIENT.
 * Без GUID — ввод проверочного кода. С GUID — карточка черновика, вариант по типу анализа.
 * Сохранение кода: ядерный POST /jsapi/form, table=t_analysis, хук Project\FormHooks\TAnalysisHook.
 */

use Services\AnalysisService;
use UC\Form;

$analysis = \Core\Container::getInstance()->get(AnalysisService::class);
$guid = trim((string) ($attributes['analysis_guid'] ?? ''));
$title = isset($page) && is_object($page) && isset($page->name) ? (string) $page->name : 'Форма пациента';
?>
<h1 class="page-title"><?= htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>

<?php if ($guid === ''): ?>
    <form method="post" action="/jsapi/form" data-pacient-code class="d-flex flex-column" style="gap: 1rem; max-width: 40rem;">
        <div class="form-field">
            <label for="pacient-code">Введите код, анализ будет выбран автоматически</label>
            <input id="pacient-code" type="text" name="fields[_code]" required autocomplete="off">
        </div>

        <div data-wrapper="acceptcode" class="alert alert-warning" hidden>
            Вы создаете заявку на проведение анализа <b class="js-type"></b>, продолжить?
            <input type="hidden" name="fields[typechecked]" value="">
        </div>

        <div data-wrapper="alertakt" class="alert alert-warning" hidden>
            В рамках данного теста проводится определение мутаций в генах AKT1, PIK3CA, PTEN, BRCA1 и BRCA2.
            <br><br>
            Для тестирования принимаются образцы опухоли и крови пациенток с ГР+HER2- метастатическим раком молочной железы при первых признаках прогрессирования во время первой и последующих линий терапии, а также пациенток с ГР+HER2- ранним раком молочной железы при первых признаках прогрессирования во время адъювантной терапии.
            <br><br>
            <u>Нецелесообразно тестировать:</u>
            <ul>
                <li>Пациенток с ГР+HER2- РМЖ 1-3 стадии без признаков прогрессирования заболевания</li>
                <li>Пациенток с другим подтипом РМЖ (ГР-, HER2+ или тройной негативный)</li>
            </ul>
        </div>

        <div class="alert alert-danger" data-pacient-code-error hidden></div>

        <div>
            <button type="submit" class="btn btn-primary">Продолжить</button>
        </div>

        <input type="hidden" name="op[step]" value="code">
        <input type="hidden" name="table" value="<?= htmlspecialchars(AnalysisService::TBL_ANALYSIS, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <input type="hidden" name="hash" value="<?= htmlspecialchars(Form::makeHash(AnalysisService::TBL_ANALYSIS, null), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <input type="hidden" name="_csrf_token" value="">
    </form>
    <script>
    (function () {
        const form = document.querySelector('[data-pacient-code]');
        if (!form) return;
        const codeInput = form.querySelector('[name="fields[_code]"]');
        const typeInput = form.querySelector('[name="fields[typechecked]"]');
        const accept = form.querySelector('[data-wrapper="acceptcode"]');
        const akt = form.querySelector('[data-wrapper="alertakt"]');
        const errorBox = form.querySelector('[data-pacient-code-error]');
        const typeLabel = accept ? accept.querySelector('.js-type') : null;
        const aktType = <?= (int) AnalysisService::TYPE_AKT ?>;
        let busy = false;

        function hideConfirm() {
            if (accept) accept.hidden = true;
            if (akt) akt.hidden = true;
            if (typeInput) typeInput.value = '';
        }

        function showError(text) {
            if (!errorBox) return;
            errorBox.hidden = text === '';
            errorBox.textContent = text;
        }

        if (codeInput) {
            codeInput.addEventListener('input', hideConfirm);
        }

        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            if (busy) return;
            showError('');
            busy = true;
            const button = form.querySelector('button[type="submit"]');
            if (button) button.disabled = true;
            try {
                const csrf = form.querySelector('[name="_csrf_token"]');
                if (csrf && !csrf.value) {
                    const tokenResponse = await fetch('/jsapi/csrf', { credentials: 'same-origin' });
                    const tokenData = await tokenResponse.json();
                    if (tokenData && tokenData.token) csrf.value = tokenData.token;
                }
                const response = await fetch('/jsapi/form', {
                    method: 'POST',
                    body: new FormData(form),
                    credentials: 'same-origin'
                });
                const data = await response.json();
                if (data && data.success && data.redirect) {
                    window.location.assign(data.redirect);
                    return;
                }
                let text = data && typeof data.error === 'string' ? data.error : '';
                const prefix = 'Ошибка сохранения: ';
                if (text.indexOf(prefix) === 0) text = text.slice(prefix.length);
                const marker = 'CONFIRM_TYPE ';
                const at = text.indexOf(marker);
                if (at !== -1) {
                    const payload = JSON.parse(text.slice(at + marker.length));
                    if (typeLabel) typeLabel.textContent = payload.name || '';
                    if (typeInput) typeInput.value = payload.code || '';
                    if (accept) accept.hidden = false;
                    if (akt) akt.hidden = Number(payload.cat) !== aktType;
                    return;
                }
                if (data && data.errors && typeof data.errors === 'object') {
                    text = Object.values(data.errors).join(' ');
                }
                showError(text || 'Не удалось проверить код');
            } catch (err) {
                showError('Не удалось проверить код');
            } finally {
                busy = false;
                if (button) button.disabled = false;
            }
        });
    })();
    </script>
<?php else: ?>
    <?php
    $card = $analysis->draftByGuid($guid);
    $authUser = function_exists('auth') && auth() ? auth()->getUser() : null;
    $authUser = is_array($authUser) ? $authUser : null;
    if ($card === null):
        echo \UC\Errors::formError(['detail' => 'Заявка не найдена.']);
    elseif (!$analysis->canEditDraft($card['analysis'], $authUser)):
        echo \UC\Errors::formError(['detail' => 'Карточка отправлена, редактирование невозможно.']);
    else:
        require_once __DIR__ . '/pacient/_fields.php';
        omgoUseDesktopFiles(!($request instanceof \Core\Request) || !$request->isMobile());
        $row = $card['analysis'];
        $pacient = $card['pacient'];
        $anamnes = $card['anamnes'];
        $typeId = (int) ($row['view_test'] ?? 0);
        $analysisId = (int) ($row['cat'] ?? 0);
        $scope = $analysis->pacientListScope($authUser);
        $birthday = (string) ($pacient['datetext_bd'] ?? '');
        if ($birthday === '0000-00-00') {
            $birthday = '';
        }
        $hasPatron = $pacient === [] || !empty($pacient['stat_patron']);
        $opts = [
            'diagnoz' => $analysis->columnOptions('view_diagnoz'),
            'gistotype' => $analysis->gistotypes($typeId),
            'gistotype_all' => $analysis->columnOptions('view_gistotype'),
            'illness' => $analysis->columnOptions('view_illness'),
            'stage' => $analysis->columnOptions('view_stage'),
            'chem' => $analysis->columnOptions('view_chem'),
            'localization' => $analysis->columnOptions('view_localization'),
            'linetherapy' => $analysis->columnOptions('view_linetherapy'),
            'confirmprogress' => $analysis->columnOptions('view_confirmprogress'),
            'teraphyduration' => $analysis->columnOptions('view_teraphyduration'),
            'myonkoan' => $analysis->columnOptions('view_myonkoan'),
            'anamnes' => $analysis->columnOptions('view_myonkoan'),
        ];
        $typeFile = __DIR__ . '/pacient/' . $card['slug'] . '.php';
        $banner = 'Вы создаете заявку на проведение анализа';
        if ($card['type_name'] !== '') {
            $banner .= ' <b>' . omgoH($card['type_name']) . '</b>';
        }
        ?>
        <form method="post" action="/jsapi/form" novalidate data-plugin="pacient-card" data-pacient-card class="d-flex flex-column gap-lg">
            <?= \UC\Errors::formInfo(['detail' => $banner]) ?>
            <?= \UC\Errors::formWarning(['detail' => 'Сканы согласия, эпикриза и гистологии на этом шаге ещё не сохраняются.']) ?>

            <?php if (empty($scope['doctor'])): ?>
                <div class="d-flex flex-column gap-md">
                    <?php \UC\FormBlockTitle::render('Информация о враче'); ?>
                    <?php \UC\FormCard::open(); ?>
                        <?php omgoSelect('ФИО врача', 'fields[user_doctor]', $analysis->doctorOptions(), $row['user_doctor'] ?? '', true); ?>
                    <?php \UC\FormCard::close(); ?>
                </div>
            <?php endif; ?>

            <div class="d-flex flex-column gap-md">
                <?php \UC\FormBlockTitle::render('Информация о пациенте'); ?>
                <?php \UC\FormCard::open(); ?>
                    <?php omgoColOpen(); ?>
                        <?php
                        omgoField('Фамилия', 'fields[pacient][varc_surname]', (string) ($pacient['varc_surname'] ?? ''), true);
                        omgoField('Имя', 'fields[pacient][varc_name]', (string) ($pacient['varc_name'] ?? ''), true);
                        omgoCheck('Есть отчество', 'fields[pacient][stat_patron]', $hasPatron, 'stat_patron');
                        echo '<div data-wrapper="stat_patron">';
                        omgoField('Отчество', 'fields[pacient][varc_patron]', (string) ($pacient['varc_patron'] ?? ''));
                        echo '</div>';
                        omgoRadio('Пол', 'fields[pacient][view_male]', $analysis->males($typeId), $pacient['view_male'] ?? '', [], true);
                        ?>
                    <?php omgoColClose(); ?>
                    <?php omgoColOpen(); ?>
                        <div class="form-field d-flex flex-column gap-xs">
                            <?php \UC\FormLabel::render('Дата рождения', true); ?>
                            <input type="date" name="fields[pacient][datetext_bd]" value="<?= omgoH($birthday) ?>" data-required>
                        </div>
                        <?php omgoSelect('Регион', 'fields[pacient][view_region]', $analysis->regions(), $pacient['view_region'] ?? '', true); ?>
                    <?php omgoColClose(); ?>
                <?php \UC\FormCard::close(); ?>
            </div>

            <div class="d-flex flex-column gap-md">
                <?php \UC\FormBlockTitle::render('Информированное согласие'); ?>
                <?php \UC\FormCard::open(); ?>
                    <?php omgoFiles('agree', 'Загрузите скан информированного согласия', 'Дополнительные файлы согласия', 1); ?>
                <?php \UC\FormCard::close(); ?>
            </div>

            <div class="d-flex flex-column gap-md">
                <?php \UC\FormBlockTitle::render('Выписка'); ?>
                <?php \UC\FormCard::open(); ?>
                    <?php omgoFiles('epicris', 'Загрузите выписку в формате PDF,JPG,PNG,HEIC', 'Дополнительные файлы выписки'); ?>
                <?php \UC\FormCard::close(); ?>
            </div>

            <?php if (is_file($typeFile)) {
                include $typeFile;
            } elseif ($card['slug'] !== 'mlpa') {
                echo \UC\Errors::formError(['detail' => 'Для этого типа анализа карточка ещё не собрана.']);
            } ?>

            <div class="d-flex flex-column gap-md">
                <?php \UC\FormBlockTitle::render('Комментарий'); ?>
                <?php \UC\FormCard::open(); ?>
                    <div class="form-field d-flex flex-column gap-xs w-full">
                        <?php \UC\FormLabel::render('Укажите дополнительную информацию о пациенте или диагнозе'); ?>
                        <textarea name="fields[_comment]"></textarea>
                    </div>
                <?php \UC\FormCard::close(); ?>
            </div>

            <?= \UC\Errors::formInfo(['detail' => 'Сохраните изменения, чтобы анкета перешла в статус Черновик']) ?>
            <div data-pacient-card-error hidden>
                <?= \UC\Errors::formError(['detail' => '']) ?>
            </div>
            <div>
                <button type="submit" class="btn btn-primary">Сохранить карточку</button>
            </div>
            <input type="hidden" name="op[step]" value="card">
            <input type="hidden" name="id" value="<?= $analysisId ?>">
            <input type="hidden" name="table" value="<?= htmlspecialchars(AnalysisService::TBL_ANALYSIS, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            <input type="hidden" name="hash" value="<?= htmlspecialchars(Form::makeHash(AnalysisService::TBL_ANALYSIS, (string) $analysisId), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            <input type="hidden" name="_csrf_token" value="">
        </form>
    <?php endif; ?>
<?php endif; ?>
