<?php

/**
 * Плагины, которые лежат только в project/web/assets/plugins.
 * Ядро сначала ищет их там. Без этой записи загрузчик стучится в /core-assets/plugins/ и в консоли ошибка.
 */
return [
    'analysis-event',
    'block-expander',
    'omgo-grid-chrome',
    'pacient-card',
];
