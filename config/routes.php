<?php

return [
    'Core\Router\JsApiStrategy' => 1000,
    'Core\Router\ApiGatewayStrategy' => 900,
    'Core\Router\FilterStrategy' => 800,
    'Core\Router\FormRouteStrategy' => 750,
    'Strategy\LoginStrategy' => 400,
    'Strategy\HomepageStrategy' => 300,
    'Core\Router\HierarchicalStrategy' => 100,
];
