<?php

namespace UC;

/**
 * Раскрыватель подблока. Кнопку на нижней границе контейнера ставит плагин block-expander.
 * Подписи — параметры контейнера.
 *
 * Внутри разметки нужен один подблок с data-block-expander-more.
 */
final class BlockExpander
{
    /**
     * @param array{more?: string, less?: string, open?: bool, class?: string} $options
     */
    public static function open(array $options = []): string
    {
        $more = self::label($options['more'] ?? null, 'Больше');
        $less = self::label($options['less'] ?? null, 'Меньше');
        $open = !empty($options['open']);
        $extra = trim((string) ($options['class'] ?? ''));
        $class = 'block-expander' . ($open ? ' is-open' : '');
        if ($extra !== '') {
            $class .= ' ' . $extra;
        }

        return '<div class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-plugin="block-expander"'
            . ' data-label-more="' . htmlspecialchars($more, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-label-less="' . htmlspecialchars($less, ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function close(): string
    {
        return '</div>';
    }

    private static function label(mixed $value, string $fallback): string
    {
        $label = trim((string) $value);

        return $label !== '' ? $label : $fallback;
    }
}
