(function () {
    'use strict';

    class AnalysisEventPlugin {
        constructor(element) {
            this.element = element;
        }

        async init() {
            if (document.documentElement.dataset.analysisEventHandler === 'true') {
                return this;
            }
            document.documentElement.dataset.analysisEventHandler = 'true';
            document.addEventListener('click', (event) => this.onClick(event), true);
            document.addEventListener('change', (event) => this.onChange(event), true);
            return this;
        }

        async onClick(event) {
            const instant = event.target.closest('[data-analysis-instant]');
            if (instant) {
                event.preventDefault();
                event.stopPropagation();
                await this.submit(instant, {
                    comment: '',
                    doc: '',
                    lab: 0,
                    status: instant.dataset.flowStatus ? Number(instant.dataset.flowStatus) : 0
                }, instant);
                return;
            }

            const save = event.target.closest('[data-analysis-save]');
            if (!save) {
                return;
            }
            event.preventDefault();
            const root = save.closest('[data-flow-id]');
            const form = save.closest('form');
            if (!root || !form) {
                return;
            }
            const comment = (form.querySelector('[name="comment"]')?.value || '').trim();
            const doc = (form.querySelector('[name="doc"]')?.value || '').trim();
            const labInput = form.querySelector('[name="lab"]:checked');
            const statusInput = form.querySelector('[name="status"]:checked');
            const surname = (form.querySelector('[name="pacient_surname"]')?.value || '').trim();
            const personName = (form.querySelector('[name="pacient_name"]')?.value || '').trim();
            const birthday = (form.querySelector('[name="pacient_birthday"]')?.value || '').trim();
            if (form.getAttribute('data-require-comment') === '1' && comment === '') {
                window.Notify?.error('Нужен комментарий');
                return;
            }
            if (form.getAttribute('data-require-doc') === '1' && doc === '') {
                window.Notify?.error('Нужен номер накладной');
                return;
            }
            if (form.querySelector('[name="pacient_surname"]') && (surname === '' || personName === '' || birthday === '')) {
                window.Notify?.error('Заполните фамилию, имя и дату рождения');
                return;
            }
            await this.submit(root, {
                comment: comment,
                doc: doc,
                lab: labInput ? Number(labInput.value) : 0,
                status: statusInput ? Number(statusInput.value) : 0,
                pacient_surname: surname,
                pacient_name: personName,
                pacient_patron: (form.querySelector('[name="pacient_patron"]')?.value || '').trim(),
                pacient_birthday: birthday,
                pacient_male: Number(form.querySelector('[name="pacient_male"]')?.value || 0),
                pacient_region: Number(form.querySelector('[name="pacient_region"]')?.value || 0)
            }, save, true);
        }

        async onChange(event) {
            const box = event.target.closest('[data-analysis-stat]');
            if (!box) {
                return;
            }
            const previous = !box.checked;
            const ok = await this.submit(box, {
                comment: '',
                doc: '',
                lab: 0,
                status: 0,
                value: box.checked ? 1 : 0
            }, box, false, true);
            if (!ok) {
                box.checked = previous;
            }
        }

        async submit(source, fields, busyEl, closeWindow, quiet) {
            if (!window.ApiService || typeof window.ApiService.post !== 'function') {
                window.Notify?.error('ApiService недоступен');
                return false;
            }
            if (busyEl) {
                busyEl.disabled = true;
            }
            try {
                const csrfRes = await window.ApiService.get('/jsapi/csrf');
                const csrfToken = csrfRes && (csrfRes.token ?? csrfRes.data?.token);
                if (!csrfToken) {
                    throw new Error('Не удалось получить CSRF-токен');
                }
                const result = await window.ApiService.post('/jsapi/admin/analysis-event', {
                    flow_id: source.dataset.flowId || 'analysis',
                    flow_action: source.dataset.flowAction || '',
                    flow_dataid: source.dataset.flowDataid || '',
                    flow_table: source.dataset.flowTable || 't_analysis',
                    flow_hash: source.dataset.flowHash || '',
                    comment: fields.comment || '',
                    doc: fields.doc || '',
                    lab: fields.lab || 0,
                    status: fields.status || 0,
                    value: fields.value || 0,
                    pacient_surname: fields.pacient_surname || '',
                    pacient_name: fields.pacient_name || '',
                    pacient_patron: fields.pacient_patron || '',
                    pacient_birthday: fields.pacient_birthday || '',
                    pacient_male: fields.pacient_male || 0,
                    pacient_region: fields.pacient_region || 0,
                    _csrf_token: csrfToken
                });
                if (!result?.success && result?.status !== 'ok') {
                    throw new Error(result?.error || 'Ошибка сохранения');
                }
                if (!quiet) {
                    window.Notify?.success(result.message || 'Операция выполнена успешно');
                }
                await this.refreshDynblocks(document.querySelector('.content-area'));
                if (closeWindow && window.WindowPlugin && typeof window.WindowPlugin.closeTop === 'function') {
                    window.WindowPlugin.closeTop();
                }
                return true;
            } catch (err) {
                window.Notify?.error(err?.message || 'Ошибка');
                return false;
            } finally {
                if (busyEl) {
                    busyEl.disabled = false;
                }
            }
        }

        async refreshDynblocks(scope) {
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
        }
    }

    window.registerProjectPlugin('analysis-event', AnalysisEventPlugin);
})();
