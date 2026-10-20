<?php

declare(strict_types=1);

/**
 * Модалка операции воркфлоу заявки.
 * Легаси: project/phpfiles/wf/analysis.php.
 * Мгновенные операции (материал получен, пригоден, израсходован, возврат, завершить анализ)
 * эту форму не открывают: кнопка в меню пишет событие сразу.
 *
 * @var string $flowDataId t_analysis.cat
 * @var string $flowScenarioId
 * @var string $flowAction id операции t_events_type.cat
 * @var string $flowTable
 * @var string $flowHash
 */

use Core\Container;
use Services\AnalysisService;
use Services\AnalysisWorkflowService;
use UC\Errors;

$analysisId = isset($flowDataId) ? (int) $flowDataId : 0;
$eventId = isset($flowAction) ? (int) $flowAction : 0;
if ($analysisId <= 0 || $eventId <= 0) {
    echo '<p class="text-muted" style="margin:0;">Не указана заявка.</p>';
    return;
}

$workflow = Container::getInstance()->get(AnalysisWorkflowService::class);
$attrFlowId = htmlspecialchars(isset($flowScenarioId) ? (string) $flowScenarioId : 'analysis', ENT_QUOTES, 'UTF-8');
$attrFlowAction = htmlspecialchars((string) $eventId, ENT_QUOTES, 'UTF-8');
$attrFlowTable = htmlspecialchars(isset($flowTable) ? (string) $flowTable : 't_analysis', ENT_QUOTES, 'UTF-8');
$attrFlowHash = htmlspecialchars(isset($flowHash) ? (string) $flowHash : '', ENT_QUOTES, 'UTF-8');
$rootId = 'analysis-event-flow-' . $analysisId . '-' . $eventId;

$requireComment = in_array($eventId, [
    AnalysisWorkflowService::EVENT_REJECTED_BADMATERIAL,
    AnalysisWorkflowService::EVENT_REJECTED_BADMATERIALNGS,
    AnalysisWorkflowService::EVENT_REJECTED_BADDELIVERY,
    AnalysisWorkflowService::EVENT_MLPA,
    AnalysisWorkflowService::EVENT_CHANGESTATUS,
    AnalysisWorkflowService::EVENT_CHANGELAB,
    AnalysisWorkflowService::EVENT_CHANGEPACIENT,
], true);
$auth = Container::getInstance()->has('auth') ? Container::getInstance()->get('auth') : null;
$flowUser = $auth && method_exists($auth, 'getUser') ? $auth->getUser() : null;
$flowUser = is_array($flowUser) ? $flowUser : null;
$hideRest = false;
$requireDoc = in_array($eventId, [
    AnalysisWorkflowService::EVENT_MOVING,
    AnalysisWorkflowService::EVENT_RETURN_MOVING,
], true);
$submit = 'Сохранить';
?>
<style>
.analysis-event-flow .form-fields {
    display: flex;
    flex-direction: column;
    gap: var(--space-lg, 16px);
}
.analysis-event-flow .form-field {
    display: flex;
    flex-direction: column;
    gap: var(--space-xs, 4px);
}
.analysis-event-flow .form-label {
    font-weight: 500;
    margin: 0;
}
.analysis-event-flow .form-actions {
    margin-top: var(--space-sm, 8px);
}
.analysis-event-flow textarea.form-control {
    min-height: 6rem;
}
</style>
<div class="analysis-event-flow" id="<?= htmlspecialchars($rootId, ENT_QUOTES, 'UTF-8') ?>"
     data-flow-id="<?= $attrFlowId ?>"
     data-flow-action="<?= $attrFlowAction ?>"
     data-flow-dataid="<?= $analysisId ?>"
     data-flow-table="<?= $attrFlowTable ?>"
     data-flow-hash="<?= $attrFlowHash ?>"
     style="font-size: var(--text-sm, 14px);">
    <form class="analysis-event-flow__form form-fields" method="POST" action=""
          data-require-comment="<?= $requireComment ? '1' : '0' ?>"
          data-require-doc="<?= $requireDoc ? '1' : '0' ?>">
        <?php if ($eventId === AnalysisWorkflowService::EVENT_INIT): ?>
            <?= Errors::formInfo(['detail' => 'В поле комментарий вы можете оставить пожелания по дате и адресу для курьера']) ?>
        <?php elseif ($eventId === AnalysisWorkflowService::EVENT_MOVING || $eventId === AnalysisWorkflowService::EVENT_RETURN_MOVING): ?>
            <div class="form-field">
                <label class="form-label" for="<?= htmlspecialchars($rootId, ENT_QUOTES, 'UTF-8') ?>-doc">Номер накладной</label>
                <input type="text" class="form-control" name="doc" id="<?= htmlspecialchars($rootId, ENT_QUOTES, 'UTF-8') ?>-doc" required>
            </div>
        <?php elseif ($eventId === AnalysisWorkflowService::EVENT_MLPA): ?>
            <?= Errors::formWarning(['detail' => 'Анализ MLPA проводится в случае положительного результата анализа HRD на усмотрение лаборатории. Анализ MLPA будет создан отдельной строкой и будет доступен в разделе Пациенты']) ?>
            <?php $submit = 'Провести MLPA'; ?>
        <?php elseif ($eventId === AnalysisWorkflowService::EVENT_PTEN): ?>
            <?= Errors::formInfo(['detail' => 'ИГХ PTEN может быть проведен после анализа AKT по решению лаборатории. Материал — парафиновый блок (опухоль)']) ?>
            <div class="form-field">
                <span class="form-label">Выберите лабораторию, которая выполнит PTEN</span>
                <?php foreach ($workflow->ptenLabs() as $lab): ?>
                    <label>
                        <input type="radio" name="lab" value="<?= (int) $lab['id'] ?>" <?= (int) $lab['id'] === AnalysisService::LAB_GBUZ62 ? 'checked' : '' ?>>
                        <?= htmlspecialchars($lab['name'], ENT_QUOTES, 'UTF-8') ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="form-field">
                <span class="form-label">Как отправим материал</span>
                <label>
                    <input type="radio" name="status" value="<?= AnalysisService::STATUS_MOVING ?>">
                    курьер
                </label>
                <label>
                    <input type="radio" name="status" value="<?= AnalysisService::STATUS_SELFDELIVERY ?>" checked>
                    самодоставка
                </label>
            </div>
            <?php $submit = 'Провести ИГХ PTEN'; ?>
        <?php elseif ($eventId === AnalysisWorkflowService::EVENT_NGS): ?>
            <?php $submit = 'Отправить на NGS-тестирование'; ?>
        <?php elseif ($eventId === AnalysisWorkflowService::EVENT_CHANGESTATUS): ?>
            <?= Errors::formWarning(['detail' => 'Изменение статуса пациента требуется для решения нестандартных ситуаций']) ?>
            <?php $choices = $workflow->statusChoices($analysisId, $flowUser); ?>
            <?php if ($choices === []): ?>
                <?= Errors::formError(['detail' => 'Недостаточно прав']) ?>
                <?php $hideRest = true; ?>
            <?php else: ?>
                <div class="form-field">
                    <span class="form-label">Статус</span>
                    <?php foreach ($choices as $choice): ?>
                        <label>
                            <input type="radio" name="status" value="<?= (int) $choice['id'] ?>" <?= $choice['checked'] ? 'checked' : '' ?>>
                            <?= htmlspecialchars($choice['label'], ENT_QUOTES, 'UTF-8') ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php elseif ($eventId === AnalysisWorkflowService::EVENT_CHANGELAB): ?>
            <?= Errors::formWarning(['detail' => 'Изменение лаборатории требуется для решения нестандартных ситуаций, в общем случае привязка — автоматическая от врача на момент регистрации']) ?>
            <?php $labs = $workflow->labChoices($analysisId, $flowUser); ?>
            <?php if ($labs === []): ?>
                <?= Errors::formError(['detail' => 'Недостаточно прав']) ?>
                <?php $hideRest = true; ?>
            <?php else: ?>
                <div class="form-field">
                    <span class="form-label">Лаборатория</span>
                    <?php foreach ($labs as $lab): ?>
                        <label>
                            <input type="radio" name="lab" value="<?= (int) $lab['id'] ?>" <?= $lab['checked'] ? 'checked' : '' ?>>
                            <?= htmlspecialchars($lab['name'], ENT_QUOTES, 'UTF-8') ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php elseif ($eventId === AnalysisWorkflowService::EVENT_CHANGEPACIENT): ?>
            <?php $editor = $workflow->pacientEditor($analysisId, $flowUser); ?>
            <?php if ($editor === null): ?>
                <?= Errors::formError(['detail' => 'Недостаточно прав']) ?>
                <?php $hideRest = true; ?>
            <?php else: ?>
                <div class="d-flex flex-wrap gap-md w-full">
                    <div class="form-field">
                        <label class="form-label" for="<?= htmlspecialchars($rootId, ENT_QUOTES, 'UTF-8') ?>-surname">Фамилия</label>
                        <input type="text" class="form-control" name="pacient_surname" id="<?= htmlspecialchars($rootId, ENT_QUOTES, 'UTF-8') ?>-surname" value="<?= htmlspecialchars($editor['surname'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="form-field">
                        <label class="form-label" for="<?= htmlspecialchars($rootId, ENT_QUOTES, 'UTF-8') ?>-name">Имя</label>
                        <input type="text" class="form-control" name="pacient_name" id="<?= htmlspecialchars($rootId, ENT_QUOTES, 'UTF-8') ?>-name" value="<?= htmlspecialchars($editor['name'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="form-field">
                        <label class="form-label" for="<?= htmlspecialchars($rootId, ENT_QUOTES, 'UTF-8') ?>-patron">Отчество</label>
                        <input type="text" class="form-control" name="pacient_patron" id="<?= htmlspecialchars($rootId, ENT_QUOTES, 'UTF-8') ?>-patron" value="<?= htmlspecialchars($editor['patron'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
                <div class="form-field">
                    <label class="form-label" for="<?= htmlspecialchars($rootId, ENT_QUOTES, 'UTF-8') ?>-bd">Дата рождения</label>
                    <input type="date" class="form-control" name="pacient_birthday" id="<?= htmlspecialchars($rootId, ENT_QUOTES, 'UTF-8') ?>-bd" value="<?= htmlspecialchars($editor['birthday'], ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <input type="hidden" name="pacient_male" value="<?= (int) $editor['male'] ?>">
                <input type="hidden" name="pacient_region" value="<?= (int) $editor['region'] ?>">
            <?php endif; ?>
        <?php endif; ?>

        <?php if (!$hideRest): ?>
        <div class="form-field">
            <label class="form-label" for="<?= htmlspecialchars($rootId, ENT_QUOTES, 'UTF-8') ?>-comment">Комментарий</label>
            <textarea class="form-control" name="comment" id="<?= htmlspecialchars($rootId, ENT_QUOTES, 'UTF-8') ?>-comment" <?= $requireComment ? 'required' : '' ?>></textarea>
        </div>

        <div class="form-actions">
            <button type="button" class="btn btn-primary" data-analysis-save>
                <?= htmlspecialchars($submit, ENT_QUOTES, 'UTF-8') ?>
            </button>
            <span class="analysis-event-flow__status text-muted" style="margin-left: 8px; font-size: 13px;"></span>
        </div>
        <?php endif; ?>
    </form>
</div>
