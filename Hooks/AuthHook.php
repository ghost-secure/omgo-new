<?php

namespace Project\Hooks;

use Core\Auth\UserAccount;
use Core\Config;
use Core\Container;
use Core\Hooks\HookInterface;
use Core\Request;
use Core\Response;
use Core\Security\RedirectValidator;
use Services\AuthService;

/**
 * Гейт входа. Режим задаётся в project/config/app.php → auth.gate.
 * all — весь сайт (OMGO). manager — только /manager/* (как test). off — не трогать.
 */
class AuthHook implements HookInterface
{
    public function getName(): string
    {
        return 'auth.gate';
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function execute(Request $request, Container $container)
    {
        $config = $container->get(Config::class);
        $gate = (string)$config->get('auth.gate', 'manager');
        if ($gate === 'off') {
            return null;
        }

        $loginUrl = (string)$config->get('auth.login_url', '/login');
        $path = $request->getPath();

        if ($this->isPublicPath($path, $loginUrl, $config)) {
            return null;
        }

        if ($gate === 'manager' && strpos($path, '/manager') !== 0) {
            return null;
        }

        /** @var AuthService|null $auth */
        $auth = $container->has('auth') ? $container->get('auth') : null;
        if (!$auth || !$auth->isAuthenticated()) {
            return $this->redirectToLogin($request, $loginUrl);
        }

        $user = $auth->getUser();
        $role = (int)($user['role'] ?? 0);
        $allowed = $config->get('auth.manager_roles', [
            UserAccount::SUPERUSER,
            UserAccount::ADMINUSER,
            UserAccount::MANAGER,
        ]);
        if (!is_array($allowed)) {
            $allowed = [1, 2, 3];
        }
        $allowed = array_map('intval', $allowed);

        if ($gate === 'all' || strpos($path, '/manager') === 0) {
            if (!in_array($role, $allowed, true)) {
                $auth->logout();
                return $this->redirectToLogin($request, $loginUrl);
            }
        }

        return null;
    }

    private function isPublicPath(string $requestPath, string $loginUrl, Config $config): bool
    {
        $public = $config->get('auth.public_paths', []);
        if (!is_array($public)) {
            $public = [];
        }
        $public[] = rtrim($loginUrl, '/') ?: $loginUrl;
        $public[] = '/manager/login';

        $normalized = rtrim($requestPath, '/') ?: '/';
        foreach ($public as $item) {
            $item = rtrim((string)$item, '/') ?: '/';
            if ($item !== '' && $normalized === $item) {
                return true;
            }
            if (strpos($item, '/jsapi') === 0 && strpos($normalized, $item) === 0) {
                return true;
            }
        }

        return false;
    }

    private function redirectToLogin(Request $request, string $loginUrl): Response
    {
        $validated = RedirectValidator::validate($request->getPathWithQuery(), '/manager/');
        $url = $loginUrl . '?redirect=' . urlencode($validated);

        return new Response('', 302, ['Location' => $url]);
    }
}
