<?php

namespace Strategy;

use Core\Page\PageRepository;
use Core\Router\RouteMatch;
use Core\Router\RouteStrategy;

/**
 * Корень сайта. Публички нет: / уходит на список пациентов, как /manager/.
 * Сам /manager/ обрабатывает LegacyUrlStrategy (приоритет ниже этой стратегии).
 */
class HomepageStrategy implements RouteStrategy
{
    public function match(array $urlParts, PageRepository $repository): ?RouteMatch
    {
        if ($urlParts !== []) {
            return null;
        }

        return new RouteMatch([], null, [
            'http_redirect' => LegacyUrlStrategy::PACIENT_LIST_URL,
            'http_redirect_code' => 302,
        ]);
    }
}
