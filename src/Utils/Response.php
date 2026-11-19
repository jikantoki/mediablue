<?php

namespace mediablue\Utils;

class Response
{
    public static function json(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    public static function error(string $message, int $statusCode = 400, array $extra = []): void
    {
        self::json(array_merge([
            'success' => false,
            'error' => $message,
        ], $extra), $statusCode);
    }

    public static function success(array $data = [], string $message = 'OK'): void
    {
        self::json(array_merge([
            'success' => true,
            'message' => $message,
        ], $data));
    }
}
