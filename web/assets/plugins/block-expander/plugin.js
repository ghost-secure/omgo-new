(function () {
    'use strict';

    class BlockExpanderPlugin {
        constructor(element) {
            this.element = element;
            this.button = null;
            this.onClick = this.onClick.bind(this);
        }

        async init() {
            if (!this.element || !this.element.querySelector('[data-block-expander-more]')) {
                return this;
            }

            this.button = this.ensureButton();
            this.apply(this.element.classList.contains('is-open'));
            this.button.addEventListener('click', this.onClick);
            return this;
        }

        ensureButton() {
            let button = this.element.querySelector('[data-block-expander-toggle]');
            if (button) {
                return button;
            }

            button = document.createElement('button');
            button.type = 'button';
            button.className = 'block-expander__toggle text-xs';
            button.setAttribute('data-block-expander-toggle', '');
            button.innerHTML = '<span class="nav-item__icon block-expander__icon" data-icon="Arrows/arrow-down-s-line"></span>'
                + '<span class="block-expander__label"></span>';
            this.element.appendChild(button);

            const icon = button.querySelector('[data-icon]');
            if (icon && window.IconSystem && typeof window.IconSystem.replaceIcon === 'function') {
                window.IconSystem.replaceIcon(icon);
            }

            return button;
        }

        onClick() {
            this.apply(!this.element.classList.contains('is-open'));
        }

        apply(open) {
            this.element.classList.toggle('is-open', open);
            const label = this.button.querySelector('.block-expander__label');
            const more = this.element.dataset.labelMore || 'Больше';
            const less = this.element.dataset.labelLess || 'Меньше';
            if (label) {
                label.textContent = open ? less : more;
            }
            this.button.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        destroy() {
            if (this.button) {
                this.button.removeEventListener('click', this.onClick);
            }
        }
    }

    window.registerProjectPlugin('block-expander', BlockExpanderPlugin);
})();
