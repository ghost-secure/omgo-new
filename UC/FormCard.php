<?php

namespace UC;

/**
 * Белая карточка полей внутри формы.
 * Фон и цвет текста — класс form-card в project/overrides/assets/config-light.css.
 * Тень — абстрактный класс shadow-md, значение var(--shadow-md) из темы.
 */
final class FormCard
{
    public static function open(): void
    {
        echo '<div class="form-card shadow-md rounded p-md d-flex flex-wrap gap-md">';
    }

    public static function close(): void
    {
        echo '</div>';
    }
}
