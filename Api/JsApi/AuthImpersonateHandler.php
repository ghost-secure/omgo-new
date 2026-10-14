<?php

namespace Api\JsApi;

use Core\Api\AbstractApiHandler;
use Core\Container;
use Core\Request;
use Core\Response;

/**
 * POST /jsapi/auth.impersonate — вход под пользователем / выход из него.
 */
class AuthImpersonateHandler extends AbstractApiHandler
{
    public function getEndpoint(): string
    {
        return 'auth.impersonate';
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
        $auth = $container && $container->has('auth') ? $container->get('auth') : null;
        if (!$auth || !method_exists($auth, 'isAuthenticated') || !$auth->isAuthenticated()) {
            return $this->json(false, 'Нужна авторизация', 403);
        }

        $action = strtolower(trim((string) $request->input('action', 'start')));
        if ($action === 'exit') {
            if (!method_exists($auth, 'exitImpersonation') || !$auth->exitImpersonation()) {
                return $this->json(false, 'Сейчас нет режима входа под пользователем', 400);
            }

            return $this->json(true, null, 200);
        }

        $userId = (int) $request->input('user_id', 0);
        if ($userId <= 0 || !method_exists($auth, 'loginAsUser') || !$auth->loginAsUser($userId)) {
            return $this->json(false, 'Нельзя войти под этим пользователем', 400);
        }

        return $this->json(true, null, 200);
    }

    private function json(bool $success, ?string $error, int $status): Response
    {
        $payload = ['success' => $success];
        if ($error !== null) {
            $payload['error'] = $error;
        }

        return new Response(
            json_encode($payload, JSON_UNESCAPED_UNICODE),
            $status,
            ['Content-Type' => 'application/json; charset=UTF-8']
        );
    }
}
