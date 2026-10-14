<?php

declare(strict_types=1);

namespace UC;

use Core\Config;

/**
 * HMAC-подпись GET-параметров сценария /jsapi/admin/flow (аналог подписи форм справочника).
 * Параметры: id сценария, action, dataid, table (пустая строка, если не передан).
 */
final class FlowSignature
{
    private static function secret(): string
    {
        $config = Config::getInstance();
        $config->load();

        return (string) $config->get('security.app_secret', 'default-secret-key-change-in-production');
    }

    /**
     * Каноническая строка для подписи (порядок полей фиксирован).
     */
    public static function buildPayload(string $id, string $action, string $dataid, string $table = ''): string
    {
        return $id . "\0" . $action . "\0" . $dataid . "\0" . $table;
    }

    public static function sign(string $id, string $action, string $dataid, string $table = ''): string
    {
        return hash('sha256', self::buildPayload($id, $action, $dataid, $table) . self::secret());
    }

    public static function verify(string $id, string $action, string $dataid, string $table, string $hash): bool
    {
        if ($hash === '' || strlen($hash) !== 64 || !ctype_xdigit($hash)) {
            return false;
        }

        $expected = self::sign($id, $action, $dataid, $table);

        return hash_equals($expected, $hash);
    }
}
