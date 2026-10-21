(function () {
    'use strict';

    class PacientCardPlugin {
        constructor(element) {
            this.form = element;
            this.depth = 0;
        }

        async init() {
            if (!this.form) {
                return this;
            }
            this.bind();
            this.applyAll();
            this.form.addEventListener('submit', (event) => this.onSubmit(event));
            return this;
        }

        bind() {
            this.form.addEventListener('change', (event) => {
                const target = event.target;
                if (!(target instanceof HTMLElement) || this.depth > 8) {
                    return;
                }
                if (target.matches('[data-form-toggle]')) {
                    if (target instanceof HTMLInputElement && target.type === 'radio') {
                        this.applyRadio(target);
                    } else if (target instanceof HTMLInputElement) {
                        this.applyCheck(target);
                    }
                }
                if (target.matches('[data-form-interactive]')) {
                    this.applyInteractive(target);
                }
                if (target.matches('[data-brca-diagnoz]')) {
                    this.applyBrca(false);
                }
                if (target.matches('[name="fields[stat_materialgisto]"], [name="fields[stat_materialblood]"], [name="fields[stat_materialnormal]"]')) {
                    this.applyAktMaterial();
                }
                const anamnesRow = target.closest('[data-anamnes]');
                if (anamnesRow && target.matches('select')) {
                    this.applyAnamnesOther(anamnesRow);
                }
                this.clearInvalid(target);
            });

            this.form.addEventListener('click', (event) => {
                const button = event.target instanceof Element ? event.target.closest('button') : null;
                if (!button || !this.form.contains(button)) {
                    return;
                }
                if (button.hasAttribute('data-anamnes-add')) {
                    this.addAnamnes();
                }
                if (button.hasAttribute('data-anamnes-remove')) {
                    const row = button.closest('[data-anamnes]');
                    if (row) {
                        row.remove();
                    }
                }
                if (button.hasAttribute('data-multifile-add')) {
                    const key = button.getAttribute('data-multifile-add');
                    const next = this.form.querySelector('[data-multifile="' + key + '"][hidden]');
                    if (next) {
                        next.hidden = false;
                    }
                }
                if (button.hasAttribute('data-multifile-remove')) {
                    const row = button.closest('[data-multifile]');
                    if (row) {
                        this.clear(row);
                        row.hidden = true;
                    }
                }
            });
        }

        applyAll() {
            this.form.querySelectorAll('input[type="checkbox"][data-form-toggle]').forEach((input) => {
                this.applyCheck(input);
            });
            const seen = new Set();
            this.form.querySelectorAll('input[type="radio"][data-form-toggle]').forEach((input) => {
                if (seen.has(input.name)) {
                    return;
                }
                seen.add(input.name);
                this.applyRadio(input);
            });
            this.form.querySelectorAll('[data-form-interactive]').forEach((select) => {
                this.applyInteractive(select);
            });
            this.applyBrca(true);
            this.applyAktMaterial();
            this.form.querySelectorAll('[data-anamnes]').forEach((row) => {
                if (row.closest('template')) {
                    return;
                }
                this.applyAnamnesOther(row);
            });
        }

        addAnamnes() {
            const list = this.form.querySelector('[data-anamnes-list]');
            const tpl = this.form.querySelector('template[data-anamnes-template]');
            if (!list || !tpl) {
                return;
            }
            const row = tpl.content.firstElementChild.cloneNode(true);
            list.appendChild(row);
            if (window.Project && typeof window.Project.scanElement === 'function') {
                window.Project.scanElement(row);
            }
            this.applyAnamnesOther(row);
        }

        applyAnamnesOther(row) {
            const select = row.querySelector('select');
            const extra = row.querySelector('[data-anamnes-other]');
            if (!extra) {
                return;
            }
            this.show(extra, this.isAnamnesOther(select));
        }

        isAnamnesOther(select) {
            if (!(select instanceof HTMLSelectElement)) {
                return false;
            }
            const option = select.options[select.selectedIndex];
            if (!option || option.value === '') {
                return false;
            }
            if (String(option.value) === '6') {
                return true;
            }
            return option.textContent.trim().toLowerCase() === 'другое';
        }

        applyCheck(input) {
            let name = input.getAttribute('data-form-toggle') || '';
            let invert = false;
            if (name.charAt(0) === '!') {
                invert = true;
                name = name.slice(1);
            }
            const on = invert ? !input.checked : input.checked;
            this.show(this.wrapper(name), on);
        }

        applyRadio(input) {
            const group = this.form.querySelectorAll('input[type="radio"][name="' + CSS.escape(input.name) + '"]');
            group.forEach((item) => {
                const name = item.getAttribute('data-form-toggle') || '';
                if (name === '' || name === '-') {
                    return;
                }
                this.show(this.wrapper(name), item.checked);
            });
        }

        applyInteractive(select) {
            let rules;
            try {
                rules = JSON.parse(select.getAttribute('data-form-interactive') || '[]');
            } catch (e) {
                return;
            }
            if (!Array.isArray(rules)) {
                return;
            }
            const value = String(select.value);
            rules.forEach((rule) => {
                const listed = (list) => Array.isArray(list) && list.map(String).indexOf(value) !== -1;
                const on = (rule.v != null && listed(rule.v)) || (rule.h != null && !listed(rule.h));
                this.show(this.wrapper(rule.w), on);
            });
        }

        applyBrca(init) {
            const source = this.form.querySelector('[data-brca-diagnoz]');
            if (!source) {
                return;
            }
            let maps;
            try {
                maps = JSON.parse(source.getAttribute('data-brca-maps') || '{}');
            } catch (e) {
                return;
            }
            const diagnoz = String(source.value);
            Object.keys(maps).forEach((field) => {
                const select = this.form.querySelector('[name="fields[' + field + ']"]');
                const options = maps[field] && maps[field][diagnoz] ? maps[field][diagnoz] : {};
                if (!select) {
                    return;
                }
                const previous = init ? String(select.value) : '';
                select.innerHTML = '<option value=""></option>';
                let chosen = false;
                Object.keys(options).forEach((id) => {
                    const option = document.createElement('option');
                    option.value = id;
                    option.textContent = options[id];
                    if (id === previous) {
                        option.selected = true;
                        chosen = true;
                    }
                    select.appendChild(option);
                });
                if (!chosen) {
                    const first = select.querySelector('option[value]:not([value=""])');
                    if (first && !init) {
                        first.selected = true;
                    }
                }
                if (select.hasAttribute('data-form-interactive')) {
                    this.applyInteractive(select);
                }
                this.refreshSelect(select);
            });
        }

        applyAktMaterial() {
            const box = this.wrapper('aktmaterial');
            if (!box) {
                return;
            }
            const on = (name) => {
                const input = this.form.querySelector('[name="' + name + '"]');
                return input instanceof HTMLInputElement && input.checked;
            };
            const onlyTumor = on('fields[stat_materialgisto]') && !on('fields[stat_materialblood]') && !on('fields[stat_materialnormal]');
            box.hidden = !onlyTumor;
        }

        wrapper(name) {
            if (!name) {
                return null;
            }
            return this.form.querySelector('[data-wrapper="' + CSS.escape(name) + '"]');
        }

        show(wrapper, on) {
            if (!wrapper || this.depth > 8) {
                return;
            }
            if (on) {
                wrapper.hidden = false;
                return;
            }
            if (wrapper.hidden) {
                return;
            }
            this.depth += 1;
            wrapper.hidden = true;
            this.clear(wrapper);
            this.depth -= 1;
        }

        clear(root) {
            root.querySelectorAll('input, select, textarea').forEach((el) => {
                if (el.type === 'hidden' || el.disabled) {
                    return;
                }
                if (el.type === 'checkbox' || el.type === 'radio') {
                    if (el.checked) {
                        el.checked = false;
                        el.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                    return;
                }
                if (el.value !== '') {
                    el.value = '';
                    el.dispatchEvent(new Event('change', { bubbles: true }));
                    this.refreshSelect(el);
                }
            });
        }

        refreshSelect(select) {
            if (!(select instanceof HTMLSelectElement) || !window.Project || typeof window.Project.getInstance !== 'function') {
                return;
            }
            const wrap = select.closest('[data-plugin="custom-select"]');
            const instance = wrap ? window.Project.getInstance(wrap) : null;
            if (!instance || typeof instance.loadOptions !== 'function') {
                return;
            }
            instance.loadOptions();
            instance.syncFromOriginal();
            instance.renderOptions();
        }

        validateRequired(errorBox, errorText) {
            this.form.querySelectorAll('.form-field.is-invalid').forEach((field) => {
                field.classList.remove('is-invalid');
            });
            const seenRadio = new Set();
            let ok = true;
            this.form.querySelectorAll('[data-required]').forEach((el) => {
                if (el.closest('[hidden]')) {
                    return;
                }
                if (el.type === 'radio') {
                    if (seenRadio.has(el.name)) {
                        return;
                    }
                    seenRadio.add(el.name);
                    const group = this.form.querySelectorAll('input[type="radio"][name="' + CSS.escape(el.name) + '"]');
                    const filled = Array.from(group).some((item) => item.checked);
                    this.markRequired(el, filled);
                    if (!filled) {
                        ok = false;
                    }
                    return;
                }
                let filled = String(el.value || '').trim() !== '';
                if (el.type === 'checkbox') {
                    filled = el.checked;
                } else if (el.type === 'file') {
                    filled = el.files && el.files.length > 0;
                }
                this.markRequired(el, filled);
                if (!filled) {
                    ok = false;
                }
            });
            if (!ok && errorText) {
                errorText.textContent = 'Заполните обязательные поля';
            }
            if (!ok && errorBox) {
                errorBox.hidden = false;
            }
            return ok;
        }

        markRequired(el, filled) {
            const field = el.closest('.form-field');
            if (field) {
                field.classList.toggle('is-invalid', !filled);
            }
        }

        clearInvalid(el) {
            const field = el.closest('.form-field');
            if (!field || !field.classList.contains('is-invalid')) {
                return;
            }
            if (el.type === 'radio' || el.type === 'checkbox') {
                const group = el.name
                    ? this.form.querySelectorAll('input[name="' + CSS.escape(el.name) + '"]')
                    : [el];
                const filled = Array.from(group).some((item) => item.checked);
                if (filled) {
                    field.classList.remove('is-invalid');
                }
                return;
            }
            if (String(el.value || '').trim() !== '') {
                field.classList.remove('is-invalid');
            }
        }

        async onSubmit(event) {
            event.preventDefault();
            const errorBox = this.form.querySelector('[data-pacient-card-error]');
            const errorText = errorBox ? errorBox.querySelector('div') : null;
            if (!this.validateRequired(errorBox, errorText)) {
                return;
            }
            if (errorBox) {
                errorBox.hidden = true;
            }
            const button = this.form.querySelector('button[type="submit"]');
            if (button) {
                button.disabled = true;
            }
            try {
                const csrf = this.form.querySelector('[name="_csrf_token"]');
                if (csrf && !csrf.value) {
                    const tokenResponse = await fetch('/jsapi/csrf', { credentials: 'same-origin' });
                    const tokenData = await tokenResponse.json();
                    if (tokenData && tokenData.token) {
                        csrf.value = tokenData.token;
                    }
                }
                const response = await fetch('/jsapi/form', {
                    method: 'POST',
                    body: new FormData(this.form),
                    credentials: 'same-origin'
                });
                const data = await response.json();
                if (data && data.success && data.redirect) {
                    window.location.assign(data.redirect);
                    return;
                }
                let text = data && typeof data.error === 'string' ? data.error : '';
                const prefix = 'Ошибка сохранения: ';
                if (text.indexOf(prefix) === 0) {
                    text = text.slice(prefix.length);
                }
                if (data && data.errors && typeof data.errors === 'object') {
                    text = Object.values(data.errors).join(' ');
                }
                if (errorText) {
                    errorText.textContent = text || this.saveError();
                }
                if (errorBox) {
                    errorBox.hidden = false;
                }
            } catch (err) {
                if (errorText) {
                    errorText.textContent = this.saveError();
                }
                if (errorBox) {
                    errorBox.hidden = false;
                }
            } finally {
                if (button) {
                    button.disabled = false;
                }
            }
        }

        saveError() {
            const text = this.form.getAttribute('data-save-error');
            return text && text !== '' ? text : 'Не удалось сохранить карточку';
        }

        destroy() {}
    }

    window.registerProjectPlugin('pacient-card', PacientCardPlugin);
})();
