<?php
namespace Core;

/**
 * Simple API key / token authentication.
 */
class Auth
{
    private static string $apiKey;

    public static function init(): void
    {
        $cfg = require __DIR__ . '/../../config/app.php';
        self::$apiKey = $cfg['api_key'];
    }

    /**
     * Validate the request. Accepts:
     *  - Header: X-API-Key: <key>
     *  - Header: Authorization: Bearer <key>
     *  - POST/GET param: api_key=<key>
     */
    public static function validate(): bool
    {
        if (empty(self::$apiKey)) self::init();

        $key = $_SERVER['HTTP_X_API_KEY']
            ?? self::extractBearer()
            ?? ($_GET['api_key'] ?? $_POST['api_key'] ?? '');

        return hash_equals(self::$apiKey, $key);
    }

    public static function require(): void
    {
        if (!self::validate()) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized', 'message' => 'Valid API key required.']);
            exit;
        }
    }

    private static function extractBearer(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
            return $m[1];
        }
        return null;
    }
}
