<?php

namespace Strategy;

use Core\Page\Page;
use Core\Page\PageRepository;
use Core\Router\RouteMatch;
use Core\Router\RouteStrategy;

class LoginStrategy implements RouteStrategy
{
    public function match(array $urlParts, PageRepository $repository): ?RouteMatch
    {
        $map = [
            'login' => 'Вход',
            'auth' => 'Вход',
            'reg' => 'Регистрация',
            'recover' => 'Восстановление пароля',
        ];
        if (count($urlParts) !== 1 || !isset($map[$urlParts[0]])) {
            return null;
        }

        $form = $urlParts[0] === 'auth' ? 'login' : $urlParts[0];
        $page = new Page(0, $map[$urlParts[0]], $form, null, null, 'auth/forms.php', 'auth.php', null, 1);

        return new RouteMatch([$page], $page, [
            'is_login' => true,
            'auth_form' => $form,
        ]);
    }
}
