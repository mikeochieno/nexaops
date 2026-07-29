<?php
namespace Core;

/**
 * JSON response helper.
 */
class Response
{
    public static function json(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function error(string $message, int $code = 400): void
    {
        self::json(['error' => true, 'message' => $message], $code);
    }

    public static function success(array $data = [], string $message = 'OK'): void
    {
        self::json(array_merge(['error' => false, 'message' => $message], $data));
    }
}
