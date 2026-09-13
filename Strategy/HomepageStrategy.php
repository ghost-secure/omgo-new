<?php

namespace Strategy;

use Core\Page\PageRepository;
use Core\Router\RouteMatch;
use Core\Router\RouteStrategy;

/**
 * Корень сайта. На test здесь витрина (slug home / виртуальная страница).
 * У OMGO публички нет: / открывает ту же страницу, что /manager/.
 */
class HomepageStrategy implements RouteStrategy
{
    public function match(array $urlParts, PageRepository $repository): ?RouteMatch
    {
        if ($urlParts !== []) {
            return null;
        }

        $cabinet = $repository->findBySlug('manager');
        if ($cabinet && $cabinet->isActive()) {
            return new RouteMatch([$cabinet], $cabinet, ['is_homepage' => true]);
        }

        return new RouteMatch([], null, [
            'http_redirect' => '/manager/',
            'http_redirect_code' => 302,
        ]);
    }
}
