<?php

declare(strict_types=1);

namespace Api\JsApi;

use Core\Api\AbstractApiHandler;
use Core\Container;
use Core\Form\TableFormHooks;
use Core\Reference\TableMetadataManager;
use Core\Request;
use Core\Response;
use Services\AnalysisService;
use Services\RoleService;
use UC\FlowSignature;

/**
 * POST /jsapi/admin/analysis-route — одна строка t_analysis_route (легаси action.php + table=t_analysis_route).
 */
class AnalysisRouteSaveHandler extends AbstractApiHandler
{
    public function getEndpoint(): string
    {
        return 'admin.analysis-route';
    }

    public function getAllowedMethods(): array
    {
        return ['POST', 'OPTIONS'];
    }

    protected function execute(Request $request, array $pathParts): Response
    {
        if ($request->getMethod() === 'OPTIONS') {
            return new Response('', 204);
        }

        $container = Container::getInstance();
        if ($container->has('auth')) {
            $auth = $container->get('auth');
            if ($auth && method_exists($auth, 'isAuthenticated') && !$auth->isAuthenticated()) {
                return $this->jsonError('Требуется авторизация', 403);
            }
            if ($auth && method_exists($auth, 'isRole') && $auth->isRole(RoleService::ROLE_LAB)) {
                return $this->jsonError('Недостаточно прав', 403);
            }
        }

        $flowId = trim((string) $request->input('flow_id', ''));
        $flowAction = trim((string) $request->input('flow_action', 'default'));
        if ($flowAction === '') {
            $flowAction = 'default';
        }
        $flowDataId = trim((string) $request->input('flow_dataid', ''));
        $flowTable = trim((string) $request->input('flow_table', ''));
        $flowHash = trim((string) $request->input('flow_hash', ''));

        if ($flowHash === '' || !FlowSignature::verify($flowId, $flowAction, $flowDataId, $flowTable, $flowHash)) {
            return $this->jsonError('Неверная подпись параметров', 403);
        }
        if ($flowId !== 'route') {
            return $this->jsonError('Неверный сценарий', 400);
        }
        if ($flowTable !== 't_users') {
            return $this->jsonError('Неверная таблица контекста', 400);
        }

        $doctorId = (int) $flowDataId;
        if ($doctorId <= 0) {
            return $this->jsonError('Не указан врач', 400);
        }

        $viewType = (int) $request->input('view_type', 0);
        $viewLab = (int) $request->input('view_lab', 0);

        $dataToSave = [
            'user_doctor' => $doctorId,
            'view_type' => $viewType,
            'view_lab' => $viewLab,
        ];

        try {
            $metadataManager = new TableMetadataManager();
            $record = $metadataManager->createActiveRecord(AnalysisService::TBL_ROUTE, $dataToSave);
            if (!$record->save()) {
                throw new \RuntimeException('Не удалось сохранить маршрут');
            }

            TableFormHooks::afterSave(AnalysisService::TBL_ROUTE, $record, [
                'id' => method_exists($record, 'getId') ? $record->getId() : ($record->cat ?? null),
                'operation' => 'create',
                'user_doctor' => $doctorId,
            ]);

            return new Response(
                json_encode([
                    'success' => true,
                    'status' => 'ok',
                    'message' => 'Маршрут сохранён',
                ], JSON_UNESCAPED_UNICODE),
                200,
                ['Content-Type' => 'application/json; charset=UTF-8']
            );
        } catch (\RuntimeException $e) {
            return $this->jsonError($e->getMessage(), 400);
        } catch (\Throwable $e) {
            error_log('AnalysisRouteSaveHandler: ' . $e->getMessage());

            return $this->jsonError(
                defined('APP_DEBUG') && APP_DEBUG ? $e->getMessage() : 'Ошибка сохранения',
                500
            );
        }
    }

    private function jsonError(string $message, int $code): Response
    {
        return new Response(
            json_encode([
                'success' => false,
                'status' => 'error',
                'error' => $message,
            ], JSON_UNESCAPED_UNICODE),
            $code,
            ['Content-Type' => 'application/json; charset=UTF-8']
        );
    }
}
