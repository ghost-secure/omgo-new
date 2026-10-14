<?php

declare(strict_types=1);

namespace Project\FormHooks;

use Core\Container;
use Services\CodeService;

/**
 * Легаси `project/logic/aftersave/t_code.php`: у новой строки без кода
 * заполнить `varc_code` из типа анализа (`view_analiz`).
 * Пул сам пишет код до INSERT — хук его не перезаписывает.
 */
class TCodeHook
{
    /**
     * @param array<string, mixed> $dataToSave
     * @param array<string, mixed> $context
     */
    public function beforeSave(string $table, array &$dataToSave, array $context = []): void
    {
        if (empty($context['isNewRecord'])) {
            return;
        }

        $existing = trim((string) ($dataToSave['varc_code'] ?? ''));
        if ($existing !== '') {
            return;
        }

        $typeId = (int) ($dataToSave['view_analiz'] ?? 0);
        if ($typeId <= 0) {
            return;
        }

        $codes = Container::getInstance()->get(CodeService::class);
        $dataToSave['varc_code'] = $codes->makeUniqueCode($typeId);
    }

    /**
     * Активированный код из грида не удаляется.
     * Кнопка в строке скрыта тем же `CodeService::isActivated`.
     *
     * @param array<string, mixed> $context
     */
    public function beforeDelete(string $table, $record, array $context = []): bool
    {
        $attrs = [];
        if (is_object($record) && method_exists($record, 'getAttributes')) {
            $attrs = $record->getAttributes();
        } elseif (is_array($record)) {
            $attrs = $record;
        }

        if (CodeService::isActivated($attrs)) {
            throw new \RuntimeException('Активированный код удалить нельзя');
        }

        return true;
    }
}
