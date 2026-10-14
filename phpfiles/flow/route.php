<?php

declare(strict_types=1);

/**
 * Маршрут врача: форма t_analysis_route + список alias route-list.
 * Легаси: project/phpfiles/wf/route.php (WF_ROUTE → /core/php/action.php).
 *
 * @var string $flowDataId t_users.cat врача
 * @var string $flowScenarioId
 * @var string $flowAction
 * @var string $flowTable
 * @var string $flowHash
 */

use Core\Container;
use Services\AnalysisService;

$doctorId = isset($flowDataId) ? (int) $flowDataId : 0;
if ($doctorId <= 0) {
    echo '<p class="text-muted" style="margin:0;">Не указан врач.</p>';
    return;
}

$analysis = Container::getInstance()->get(AnalysisService::class);
$labs = $analysis->getLabs();

$attrFlowId = htmlspecialchars(isset($flowScenarioId) ? (string) $flowScenarioId : 'route', ENT_QUOTES, 'UTF-8');
$attrFlowAction = htmlspecialchars(isset($flowAction) ? (string) $flowAction : 'default', ENT_QUOTES, 'UTF-8');
$attrFlowTable = htmlspecialchars(isset($flowTable) ? (string) $flowTable : 't_users', ENT_QUOTES, 'UTF-8');
$attrFlowHash = htmlspecialchars(isset($flowHash) ? (string) $flowHash : '', ENT_QUOTES, 'UTF-8');
$rootId = 'analysis-route-flow-' . $doctorId;
$typePten = AnalysisService::TYPE_PTEN;
?>
<style>
.analysis-route-flow .form-fields {
    display: flex;
    flex-direction: column;
    gap: var(--space-lg, 16px);
}
.analysis-route-flow .form-field {
    display: flex;
    flex-direction: column;
    gap: var(--space-xs, 4px);
}
.analysis-route-flow .form-label {
    font-weight: 500;
    margin: 0;
}
.analysis-route-flow .form-actions {
    margin-top: var(--space-sm, 8px);
}
.analysis-route-flow .analysis-route-flow__list-title {
    font-weight: 600;
    margin: var(--space-lg, 16px) 0 var(--space-sm, 8px);
}
</style>
<div class="analysis-route-flow" id="<?= htmlspecialchars($rootId, ENT_QUOTES, 'UTF-8') ?>"
     data-flow-id="<?= $attrFlowId ?>"
     data-flow-action="<?= $attrFlowAction ?>"
     data-flow-dataid="<?= $doctorId ?>"
     data-flow-table="<?= $attrFlowTable ?>"
     data-flow-hash="<?= $attrFlowHash ?>"
     style="font-size: var(--text-sm, 14px);">
    <form class="analysis-route-flow__form form-fields" method="POST" action="">
        <div class="form-field">
            <span class="form-label">Анализ</span>
            <label>
                <input type="radio" name="view_type" value="<?= $typePten ?>" checked>
                PTEN
            </label>
        </div>

        <div class="form-field">
            <label class="form-label" for="<?= htmlspecialchars($rootId, ENT_QUOTES, 'UTF-8') ?>-lab">
                Лаборатория
            </label>
            <div data-plugin="custom-select">
                <select name="view_lab"
                        id="<?= htmlspecialchars($rootId, ENT_QUOTES, 'UTF-8') ?>-lab"
                        class="form-control"
                        required
                        placeholder="-- Выберите --">
                    <option value="">-- Выберите --</option>
                    <?php foreach ($labs as $opt): ?>
                        <option value="<?= (int) $opt['id'] ?>"
                                data-option-title="<?= htmlspecialchars((string) $opt['name'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars((string) $opt['name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-actions">
            <button type="button" class="btn btn-primary analysis-route-flow__save">
                Сохранить
            </button>
            <span class="analysis-route-flow__status text-muted" style="margin-left: 8px; font-size: 13px;"></span>
        </div>
    </form>

    <div class="analysis-route-flow__list-title">Существующие маршруты</div>
    <div class="analysis-route-flow__list">
        <?php
        $dyn = new \UC\DynBlock('t_analysis_route', 't_analysis_route/route-list', 20, 1, [
            'short' => false,
            'staticWhere' => [
                [
                    'sql' => 't_analysis_route.user_doctor = ?',
                    'bind' => [$doctorId],
                ],
            ],
            'sort' => ['cat' => 'desc'],
        ]);
        echo $dyn->render();
        ?>
    </div>
</div>
<script>
(function () {
    const root = document.getElementById(<?= json_encode($rootId) ?>);
    if (!root) {
        return;
    }

    const form = root.querySelector('form');
    const saveBtn = root.querySelector('.analysis-route-flow__save');
    const statusEl = root.querySelector('.analysis-route-flow__status');
    if (!form || !saveBtn) {
        return;
    }

    const refreshDynblocks = async function (scope) {
        const nodes = (scope || document).querySelectorAll('[data-plugin="dynblock"]');
        for (const el of nodes) {
            const inst = window.Project && typeof window.Project.getInstance === 'function'
                ? window.Project.getInstance(el)
                : null;
            if (inst && typeof inst.handlePage === 'function') {
                const active = el.querySelector('[data-page].is-active, .pagination-wrapper .is-active[data-page]');
                const page = Number(active && active.getAttribute('data-page') ? active.getAttribute('data-page') : 1) || 1;
                await inst.handlePage(page);
            }
        }
    };

    saveBtn.addEventListener('click', async function () {
        if (!window.ApiService || typeof window.ApiService.post !== 'function') {
            window.Notify?.error('ApiService недоступен');
            return;
        }

        const typeInput = form.querySelector('[name="view_type"]:checked');
        const labSelect = form.querySelector('[name="view_lab"]');
        const viewType = typeInput ? String(typeInput.value || '').trim() : '';
        const viewLab = labSelect ? String(labSelect.value || '').trim() : '';
        if (viewLab === '' || !/^\d+$/.test(viewLab) || Number(viewLab) <= 0) {
            window.Notify?.error('Выберите лабораторию');
            return;
        }

        saveBtn.disabled = true;
        if (statusEl) {
            statusEl.textContent = 'Сохранение…';
        }

        try {
            const csrfRes = await window.ApiService.get('/jsapi/csrf');
            const csrfToken = csrfRes && (csrfRes.token ?? csrfRes.data?.token);
            if (!csrfToken) {
                throw new Error('Не удалось получить CSRF-токен');
            }

            const result = await window.ApiService.post('/jsapi/admin/analysis-route', {
                flow_id: root.dataset.flowId || 'route',
                flow_action: root.dataset.flowAction || 'default',
                flow_dataid: root.dataset.flowDataid || '',
                flow_table: root.dataset.flowTable || 't_users',
                flow_hash: root.dataset.flowHash || '',
                view_type: Number(viewType),
                view_lab: Number(viewLab),
                _csrf_token: csrfToken
            });
            if (!result?.success && result?.status !== 'ok') {
                throw new Error(result?.error || 'Ошибка сохранения');
            }

            window.Notify?.success(result.message || 'Сохранено');
            if (statusEl) {
                statusEl.textContent = '';
            }

            await refreshDynblocks(root);
            await refreshDynblocks(document.querySelector('.content-area'));
        } catch (err) {
            window.Notify?.error(err?.message || 'Ошибка');
            if (statusEl) {
                statusEl.textContent = '';
            }
        } finally {
            saveBtn.disabled = false;
        }
    });
})();
</script>
