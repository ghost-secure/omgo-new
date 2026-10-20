<?php

namespace UC;

/**
 * Заголовок блока формы. Не заголовок страницы: div без внешнего margin.
 * Вид — абстрактные классы. Отступ до карточки даёт gap колонки.
 */
final class FormBlockTitle
{
    public static function render(string $title): void
    {
        echo '<div class="font-bold text-xl text-blue">'
            . htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</div>';
    }
}
