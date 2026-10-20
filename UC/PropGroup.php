<?php

namespace UC;

/**
 * Группа характеристик: заголовок и строки под ним.
 * Внутри группы отступ small (`gap-sm`). Между такими группами родитель держит обычный `gap-md`.
 * У группы нет внешнего margin.
 */
final class PropGroup
{
    public static function open(string $title = ''): void
    {
        echo '<div class="d-flex flex-column gap-sm w-full">';
        if ($title !== '') {
            FormBlockTitle::render($title);
        }
    }

    public static function close(): void
    {
        echo '</div>';
    }
}
