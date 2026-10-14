<?php

namespace Api\JsApi;

use Core\Api\AbstractApiHandler;
use Core\Request;
use Core\Response;
use Services\AuthFormsService;

class AuthRegisterHandler extends AbstractApiHandler
{
    public function getEndpoint(): string
    {
        return 'form.reg';
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

        $post = $request->post();
        if (!is_array($post)) {
            $post = [];
        }

        return AuthFormResponse::fromResult((new AuthFormsService())->register($post));
    }
}
