<?php

namespace UC;

/**
 * Строка «свойство: значение». Подпись синяя и жирная, значение обычное.
 * Пустое значение — прочерк. Завтра другой цвет подписи меняется в этом классе.
 */
final class PropLine
{
    public static function render(string $label, string $value): void
    {
        self::open($label);
        echo $value !== '' ? htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '—';
        echo '</div>';
    }

    public static function renderHtml(string $label, string $html): void
    {
        self::open($label);
        echo $html !== '' ? $html : '—';
        echo '</div>';
    }

    private static function open(string $label): void
    {
        echo '<div><span class="font-bold text-blue">'
            . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . ':</span> ';
    }
}
