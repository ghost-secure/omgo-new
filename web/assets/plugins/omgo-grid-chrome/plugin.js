(function () {
    'use strict';

    class OmgoGridChromePlugin {
        constructor(element) {
            this.element = element;
        }

        async init() {
            const section = this.element.closest('.admin-table-section');
            if (!section || section.dataset.omgoGridChrome === '1') {
                return this;
            }
            section.dataset.omgoGridChrome = '1';
            this.bindFilterToggle(section);
            this.splitFilters(section);
            return this;
        }

        bindFilterToggle(section) {
            const button = section.querySelector('.dynblock-filters-toolbar-toggle');
            const filters = section.querySelector('.dynblock-filters');
            const coreButton = filters
                ? filters.querySelector('[data-toolbar-action="toggle-filters-collapse"]')
                : null;
            if (!button || !coreButton) {
                return;
            }
            const icon = coreButton.querySelector('[data-icon]');
            if (icon) {
                const paintClose = () => {
                    icon.setAttribute('data-icon', 'System/close-line');
                    icon.removeAttribute('data-icon-replaced');
                    if (window.IconSystem && typeof window.IconSystem.replaceIcon === 'function') {
                        window.IconSystem.replaceIcon(icon);
                    }
                };
                paintClose();
                setTimeout(paintClose, 50);
            }
            coreButton.setAttribute('aria-label', 'Закрыть');
            coreButton.setAttribute('data-tooltip', 'Закрыть');
            button.addEventListener('click', () => {
                coreButton.click();
            });
        }

        splitFilters(section) {
            const button = section.querySelector('.dynblock-filters-toolbar-toggle');
            const primary = (this.element.dataset.filterPrimary || '')
                .split(',')
                .map((name) => name.trim())
                .filter(Boolean);
            if (primary.length === 0) {
                return;
            }

            const row = section.querySelector('.dynblock-filters .filters-row');
            if (!row) {
                return;
            }

            const primarySet = new Set(primary);
            const filters = row.closest('.dynblock-filters');
            const extended = document.createElement('div');
            extended.className = 'filters-row';
            extended.setAttribute('data-block-expander-more', '');
            let hasValue = false;

            row.querySelectorAll(':scope > .filter-item').forEach((item) => {
                const column = this.columnOf(item);
                if (column === '' || primarySet.has(column)) {
                    return;
                }
                if (this.fieldHasValue(item)) {
                    hasValue = true;
                }
                extended.appendChild(item);
            });

            if (extended.childElementCount === 0 || !filters) {
                return;
            }

            if (hasValue && button) {
                button.classList.add('is-extended-active');
            }
            row.after(extended);
            this.placeFilterFooter(filters);
            filters.classList.add('block-expander');
            if (hasValue) {
                filters.classList.add('is-open');
            }
            filters.dataset.labelMore = 'Больше фильтров';
            filters.dataset.labelLess = 'Меньше фильтров';
            if (!filters.hasAttribute('data-plugin')) {
                filters.setAttribute('data-plugin', 'block-expander');
            }
            if (window.Project && typeof window.Project.scanElement === 'function') {
                window.Project.scanElement(filters);
            }
        }

        placeFilterFooter(filters) {
            const actions = filters.querySelector('.filter-actions');
            if (!actions) {
                return;
            }
            const footer = document.createElement('div');
            footer.className = 'dynblock-filters__footer';
            const toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.className = 'block-expander__toggle text-xs';
            toggle.setAttribute('data-block-expander-toggle', '');
            toggle.innerHTML = '<span class="nav-item__icon block-expander__icon" data-icon="Arrows/arrow-down-s-line"></span>'
                + '<span class="block-expander__label"></span>';
            footer.appendChild(toggle);
            footer.appendChild(actions);
            filters.appendChild(footer);
            const icon = toggle.querySelector('[data-icon]');
            if (icon && window.IconSystem && typeof window.IconSystem.replaceIcon === 'function') {
                window.IconSystem.replaceIcon(icon);
            }
        }

        columnOf(item) {
            const marked = item.querySelector('[data-filter-field]');
            if (marked) {
                return marked.getAttribute('data-filter-field') || '';
            }
            const named = item.querySelector('input[name], select[name]');
            if (!named) {
                return '';
            }
            return (named.getAttribute('name') || '').replace(/\[\]$/, '');
        }

        fieldHasValue(item) {
            const boxes = item.querySelectorAll('input[type="checkbox"]');
            for (let i = 0; i < boxes.length; i++) {
                if (boxes[i].checked) {
                    return true;
                }
            }
            const fields = item.querySelectorAll('input:not([type="checkbox"]), select');
            for (let i = 0; i < fields.length; i++) {
                const field = fields[i];
                if (field.tagName === 'SELECT') {
                    const selected = field.selectedOptions || [];
                    for (let j = 0; j < selected.length; j++) {
                        if (selected[j].value !== '') {
                            return true;
                        }
                    }
                    continue;
                }
                if (String(field.value || '').trim() !== '') {
                    return true;
                }
            }
            return false;
        }
    }

    window.registerProjectPlugin('omgo-grid-chrome', OmgoGridChromePlugin);
})();
