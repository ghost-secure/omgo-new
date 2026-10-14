<?php

namespace Services;

/**
 * Файловый кэш Remix Icon для левого меню.
 * Качает SVG один раз в runtime/cache и отдаёт разметку для вставки в HTML.
 */
class RemixIconCache
{
    public const CDN_PREFIX = 'https://cdn.jsdelivr.net/npm/remixicon@4.9.1/icons/';

    private const VERSION_DIR = '4.9.1';

    public static function html(string $iconKey): string
    {
        try {
            $segments = self::segments($iconKey);
            if ($segments === null) {
                return '';
            }
            $path = self::filePath($segments);
            if (!is_file($path) || (int)filesize($path) < 20) {
                self::download($segments, $path);
            }
            if (!is_file($path)) {
                return '';
            }
            $raw = file_get_contents($path);
            if (!is_string($raw) || !self::isSafeSvg($raw)) {
                return '';
            }
            return self::inline($raw);
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     */
    public static function warmTree(array $nodes): void
    {
        $walk = function (array $items) use (&$walk): void {
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $icon = trim((string)($item['icon'] ?? ''));
                if ($icon !== '') {
                    self::html($icon);
                }
                $children = $item['children'] ?? null;
                if (is_array($children) && $children !== []) {
                    $walk($children);
                }
            }
        };
        $walk($nodes);
        self::html('System/menu-line');
        self::html('Arrows/arrow-down-s-line');
    }

    /**
     * @return list<string>|null
     */
    private static function segments(string $iconKey): ?array
    {
        $icon = trim($iconKey);
        if ($icon === '') {
            return null;
        }
        $icon = preg_replace('/(?:\.svg|svg)$/i', '', $icon);
        if (!is_string($icon) || $icon === '') {
            return null;
        }
        $icon = rawurldecode(str_replace('\\', '/', $icon));
        $parts = array_values(array_filter(explode('/', $icon), static function (string $segment): bool {
            return $segment !== '';
        }));
        if ($parts === [] || count($parts) > 4) {
            return null;
        }
        foreach ($parts as $part) {
            if ($part === '.' || $part === '..') {
                return null;
            }
            if (!preg_match('/^[a-zA-Z0-9 _&-]+$/', $part)) {
                return null;
            }
        }
        return $parts;
    }

    /**
     * @param list<string> $segments
     */
    private static function filePath(array $segments): string
    {
        return rtrim(CACHE_PATH, '/\\')
            . DIRECTORY_SEPARATOR . 'icons'
            . DIRECTORY_SEPARATOR . 'remixicon'
            . DIRECTORY_SEPARATOR . self::VERSION_DIR
            . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $segments) . '.svg';
    }

    /**
     * @param list<string> $segments
     */
    private static function download(array $segments, string $path): void
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return;
        }

        $url = self::CDN_PREFIX . implode('/', array_map('rawurlencode', $segments)) . '.svg';
        $ch = curl_init($url);
        if ($ch === false) {
            return;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_USERAGENT => 'omgo-icon-cache',
            CURLOPT_HTTPHEADER => ['Accept: image/svg+xml'],
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!is_string($body) || $code !== 200 || strlen($body) > 100000 || !self::isSafeSvg($body)) {
            return;
        }

        $tmp = $path . '.tmp';
        if (file_put_contents($tmp, $body, LOCK_EX) === false) {
            return;
        }
        if (!rename($tmp, $path) && is_file($tmp)) {
            @unlink($tmp);
        }
    }

    private static function isSafeSvg(string $svg): bool
    {
        if (stripos($svg, '<svg') === false) {
            return false;
        }
        if (stripos($svg, '<script') !== false || stripos($svg, '<!ENTITY') !== false) {
            return false;
        }
        if (preg_match('/javascript\s*:/i', $svg)) {
            return false;
        }
        return true;
    }

    private static function inline(string $svg): string
    {
        $svg = preg_replace('/<\?xml[^>]*\?>/', '', $svg) ?? $svg;
        $svg = preg_replace('/<!DOCTYPE[^>]*>/i', '', $svg) ?? $svg;
        $svg = preg_replace_callback('/<svg\b([^>]*)>/i', static function (array $match): string {
            $attrs = preg_replace('/\s(?:width|height|fill)="[^"]*"/i', '', $match[1]) ?? $match[1];
            return '<svg' . $attrs . ' width="100%" height="100%" fill="currentColor" aria-hidden="true" focusable="false">';
        }, $svg, 1) ?? $svg;
        return trim($svg);
    }
}
