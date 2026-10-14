<?php

declare(strict_types=1);

/**
 * Пул проверочных кодов.
 * Легаси: project/phpfiles/wf/codes.php (WF_CODEPULL).
 *
 * @var string $flowDataId страница, не строка грида — всегда 0
 * @var string $flowScenarioId
 * @var string $flowAction
 * @var string $flowTable
 * @var string $flowHash
 */

use Core\Container;
use Services\CodeService;

$codes = Container::getInstance()->get(CodeService::class);
$types = $codes->poolTypes();

$attrFlowId = htmlspecialchars(isset($flowScenarioId) ? (string) $flowScenarioId : 'codes', ENT_QUOTES, 'UTF-8');
$attrFlowAction = htmlspecialchars(isset($flowAction) ? (string) $flowAction : 'WF_CODEPULL', ENT_QUOTES, 'UTF-8');
$attrFlowTable = htmlspecialchars(isset($flowTable) ? (string) $flowTable : CodeService::TBL, ENT_QUOTES, 'UTF-8');
$attrFlowHash = htmlspecialchars(isset($flowHash) ? (string) $flowHash : '', ENT_QUOTES, 'UTF-8');
$attrFlowDataId = htmlspecialchars(isset($flowDataId) ? (string) $flowDataId : '0', ENT_QUOTES, 'UTF-8');
$rootId = 'codes-pool-flow';
$poolMax = CodeService::POOL_MAX;
$first = true;
?>
<style>
.codes-pool-flow .form-fields {
    display: flex;
    flex-direction: column;
    gap: var(--space-lg, 16px);
}
.codes-pool-flow .form-field {
    display: flex;
    flex-direction: column;
    gap: var(--space-xs, 4px);
}
.codes-pool-flow .form-label {
    font-weight: 500;
    margin: 0;
}
.codes-pool-flow .codes-pool-flow__types {
    display: flex;
    flex-direction: column;
    gap: var(--space-xs, 4px);
}
.codes-pool-flow .form-actions {
    margin-top: var(--space-sm, 8px);
}
</style>
<div class="codes-pool-flow" id="<?= htmlspecialchars($rootId, ENT_QUOTES, 'UTF-8') ?>"
     data-flow-id="<?= $attrFlowId ?>"
     data-flow-action="<?= $attrFlowAction ?>"
     data-flow-dataid="<?= $attrFlowDataId ?>"
     data-flow-table="<?= $attrFlowTable ?>"
     data-flow-hash="<?= $attrFlowHash ?>"
     style="font-size: var(--text-sm, 14px);">
    <form class="codes-pool-flow__form form-fields" method="POST" action="">
        <div class="form-field">
            <span class="form-label">Анализ</span>
            <div class="codes-pool-flow__types">
                <?php foreach ($types as $typeId => $typeLabel): ?>
                    <label>
                        <input type="radio"
                               name="type"
                               value="<?= (int) $typeId ?>"
                               <?= $first ? 'checked' : '' ?>>
                        <?= htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8') ?>
                    </label>
                    <?php $first = false; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="form-field">
            <label class="form-label" for="<?= htmlspecialchars($rootId, ENT_QUOTES, 'UTF-8') ?>-number">
                Количество кодов
            </label>
            <input type="number"
                   class="form-control"
                   id="<?= htmlspecialchars($rootId, ENT_QUOTES, 'UTF-8') ?>-number"
                   name="number"
                   min="1"
                   max="<?= (int) $poolMax ?>"
                   step="1"
                   value="10"
                   required>
        </div>

        <div class="form-actions">
            <button type="button" class="btn btn-primary codes-pool-flow__save">
                Создать коды
            </button>
            <span class="codes-pool-flow__status text-muted" style="margin-left: 8px; font-size: 13px;"></span>
        </div>
    </form>
</div>
<script>
(function () {
    const root = document.getElementById(<?= json_encode($rootId) ?>);
    if (!root) {
        return;
    }

    const form = root.querySelector('form');
    const saveBtn = root.querySelector('.codes-pool-flow__save');
    const statusEl = root.querySelector('.codes-pool-flow__status');
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

        const typeInput = form.querySelector('[name="type"]:checked');
        const numberInput = form.querySelector('[name="number"]');
        const typeId = typeInput ? String(typeInput.value || '').trim() : '';
        const number = numberInput ? String(numberInput.value || '').trim() : '';
        const max = <?= (int) $poolMax ?>;
        if (!/^\d+$/.test(typeId) || Number(typeId) <= 0) {
            window.Notify?.error('Выберите анализ');
            return;
        }
        if (!/^\d+$/.test(number) || Number(number) < 1 || Number(number) > max) {
            window.Notify?.error('Количество кодов: от 1 до ' + max);
            return;
        }

        saveBtn.disabled = true;
        if (statusEl) {
            statusEl.textContent = 'Создание…';
        }

        try {
            const csrfRes = await window.ApiService.get('/jsapi/csrf');
            const csrfToken = csrfRes && (csrfRes.token ?? csrfRes.data?.token);
            if (!csrfToken) {
                throw new Error('Не удалось получить CSRF-токен');
            }

            const result = await window.ApiService.post('/jsapi/admin/code-pool', {
                flow_id: root.dataset.flowId || 'codes',
                flow_action: root.dataset.flowAction || 'WF_CODEPULL',
                flow_dataid: root.dataset.flowDataid || '0',
                flow_table: root.dataset.flowTable || 't_code',
                flow_hash: root.dataset.flowHash || '',
                type: Number(typeId),
                number: Number(number),
                _csrf_token: csrfToken
            });
            if (!result?.success && result?.status !== 'ok') {
                throw new Error(result?.error || 'Ошибка сохранения');
            }

            window.Notify?.success(result.message || 'Коды созданы');
            if (statusEl) {
                statusEl.textContent = '';
            }

            await refreshDynblocks(document.querySelector('.content-area'));
            if (window.WindowPlugin && typeof window.WindowPlugin.closeTop === 'function') {
                window.WindowPlugin.closeTop();
            }
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
