<?php

namespace Api\JsApi;

use Core\Response;

final class AuthFormResponse
{
    /**
     * @param array{ok:bool,message?:string,errors?:array<string,string>,redirect?:string} $result
     */
    public static function fromResult(array $result): Response
    {
        if (!empty($result['ok'])) {
            $payload = [
                'success' => true,
                'message' => (string) ($result['message'] ?? ''),
            ];
            if (!empty($result['redirect'])) {
                $payload['redirect'] = (string) $result['redirect'];
            }

            return self::json($payload, 200);
        }

        $message = (string) ($result['message'] ?? 'Ошибка');
        $payload = [
            'success' => false,
            'message' => $message,
            'error' => $message,
        ];
        if (!empty($result['errors']) && is_array($result['errors'])) {
            $payload['errors'] = $result['errors'];
        }

        return self::json($payload, 400);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function json(array $payload, int $status): Response
    {
        return new Response(
            json_encode($payload, JSON_UNESCAPED_UNICODE),
            $status,
            ['Content-Type' => 'application/json; charset=UTF-8']
        );
    }
}
