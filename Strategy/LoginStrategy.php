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
        if ($urlParts === ['login']) {
            $page = new Page(0, 'Вход', 'login', null, null, 'login.php', 'light.php', null, 1);
            return new RouteMatch([$page], $page, ['is_login' => true]);
        }

        return null;
    }
}
