<?php

namespace UC;

/**
 * Подпись поля. Цвет и кегль меняются здесь: завтра «зелёные лейблы» — другая абстракция в этом классе.
 * В тёмной теме чёрный текст лейбла заменяется на белый в config-light.css.
 */
final class FormLabel
{
    public static function render(string $text, bool $required = false): void
    {
        echo '<div class="form-label font-medium text-black">';
        echo htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        if ($required) {
            echo ' *';
        }
        echo '</div>';
    }
}
