<?php

declare(strict_types=1);

namespace Project\FormHooks;

use Services\AnalysisService;

/**
 * Нативные поля t_analysis_route (легаси /core/php/action.php по table=).
 * user_doctor берётся из подписанного контекста флоу, не из POST.
 */
class TAnalysisRouteHook
{
    /**
     * @param array<string, mixed> $dataToSave
     * @param array<string, mixed> $context
     */
    public function beforeSave(string $table, array &$dataToSave, array $context = []): void
    {
        $doctorId = (int) ($dataToSave['user_doctor'] ?? 0);
        if ($doctorId <= 0) {
            throw new \RuntimeException('Не указан врач');
        }

        $viewType = (int) ($dataToSave['view_type'] ?? 0);
        if ($viewType !== AnalysisService::TYPE_PTEN) {
            throw new \RuntimeException('Недопустимый тип анализа');
        }

        $viewLab = (int) ($dataToSave['view_lab'] ?? 0);
        if ($viewLab <= 0) {
            throw new \RuntimeException('Выберите лабораторию');
        }

        $dataToSave['user_doctor'] = $doctorId;
        $dataToSave['view_type'] = $viewType;
        $dataToSave['view_lab'] = $viewLab;
    }

    /**
     * Точка для будущих побочных эффектов. В легаси aftersave для этой таблицы не было.
     *
     * @param array<string, mixed> $context
     */
    public function afterSave(string $table, $record, array $context = []): void
    {
    }
}
