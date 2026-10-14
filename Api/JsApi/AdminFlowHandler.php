<?php

namespace Api\JsApi;

use Core\Api\AbstractApiHandler;
use Core\Container;
use Core\Request;
use Core\Response;
use UC\FlowSignature;

/**
 * GET /jsapi/admin/flow?id=…&action=…&dataid=… — HTML-фрагмент для модального окна админки.
 */
class AdminFlowHandler extends AbstractApiHandler
{
    public function getEndpoint(): string
    {
        return 'admin.flow';
    }

    public function getAllowedMethods(): array
    {
        return ['GET', 'HEAD', 'OPTIONS'];
    }

    protected function execute(Request $request, array $pathParts): Response
    {
        if ($request->getMethod() === 'OPTIONS') {
            return new Response('', 204);
        }

        try {
            $container = Container::getInstance();
            if ($container && method_exists($container, 'has') && $container->has('auth')) {
                $auth = $container->get('auth');
                if ($auth && method_exists($auth, 'isAuthenticated') && !$auth->isAuthenticated()) {
                    return new Response('Forbidden', 403, ['Content-Type' => 'text/plain; charset=UTF-8']);
                }
            }

            $scenarioId = trim((string) $request->get('id', ''));
            if ($scenarioId === '' || !preg_match('/^[a-z0-9_-]+$/i', $scenarioId)) {
                return new Response('Bad request', 400, ['Content-Type' => 'text/plain; charset=UTF-8']);
            }

            $allowed = $this->loadAllowedList();
            if (!in_array($scenarioId, $allowed, true)) {
                return new Response('Not found', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
            }

            $flowAction = trim((string) $request->get('action', 'default'));
            if ($flowAction === '' || !preg_match('/^[a-z0-9._-]+$/i', $flowAction)) {
                $flowAction = 'default';
            }

            $flowDataId = trim((string) $request->get('dataid', ''));
            if (strlen($flowDataId) > 64) {
                return new Response('Bad request', 400, ['Content-Type' => 'text/plain; charset=UTF-8']);
            }

            $flowTable = trim((string) $request->get('table', ''));
            if ($flowTable !== '' && !preg_match('/^[a-z0-9_]+$/i', $flowTable)) {
                $flowTable = '';
            }

            $hash = trim((string) $request->get('hash', ''));
            if ($hash === '' || !FlowSignature::verify($scenarioId, $flowAction, $flowDataId, $flowTable, $hash)) {
                return new Response(
                    '<div class="alert alert-danger" role="alert">Доступ запрещён: неверная подпись параметров сценария.</div>',
                    403,
                    ['Content-Type' => 'text/html; charset=UTF-8']
                );
            }

            $path = PHP . '/flow/' . $scenarioId . '.php';
            if (!is_file($path)) {
                return new Response('Not found', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
            }

            /** @var string $flowHash подпись (для data-атрибутов во фрагменте) */
            $flowHash = $hash;
            /** @var string $flowScenarioId совпадает с GET id */
            $flowScenarioId = $scenarioId;

            ob_start();
            try {
                /** @psalm-suppress UnresolvableInclude */
                include $path;
            } catch (\Throwable $e) {
                ob_end_clean();
                error_log('AdminFlowHandler include error: ' . $e->getMessage());
                if (defined('APP_DEBUG') && APP_DEBUG) {
                    return new Response(
                        '<div class="alert alert-danger">Ошибка сценария: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</div>',
                        500,
                        ['Content-Type' => 'text/html; charset=UTF-8']
                    );
                }
                return new Response('Error', 500, ['Content-Type' => 'text/plain; charset=UTF-8']);
            }

            $html = ob_get_clean();
            if (!is_string($html)) {
                $html = '';
            }

            return new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        } catch (\Throwable $e) {
            error_log('AdminFlowHandler error: ' . $e->getMessage());
            return new Response('Error', 500, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }
    }

    /**
     * @return list<string>
     */
    private function loadAllowedList(): array
    {
        $file = PROJECT_PATH . '/config/admin_flow.php';
        if (!is_file($file)) {
            return [];
        }
        $cfg = require $file;
        if (!is_array($cfg) || !isset($cfg['allowed']) || !is_array($cfg['allowed'])) {
            return [];
        }
        $out = [];
        foreach ($cfg['allowed'] as $id) {
            if (is_string($id) && preg_match('/^[a-z0-9_-]+$/i', $id)) {
                $out[] = $id;
            }
        }
        return $out;
    }
}
