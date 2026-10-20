<?php

declare(strict_types=1);

namespace Project\FormHooks;

/**
 * Код найден, пользователь ещё не подтвердил тип анализа.
 * Строка t_analysis при этом не создаётся.
 * Текст исключения читает страница /manager/pacient/ (префикс CONFIRM_TYPE).
 */
class PacientCodeConfirmException extends \RuntimeException
{
    public function __construct(int $typeId, string $typeName, string $storedCode)
    {
        parent::__construct('CONFIRM_TYPE ' . json_encode([
            'cat' => $typeId,
            'name' => $typeName,
            'code' => $storedCode,
        ], JSON_UNESCAPED_UNICODE));
    }
}
