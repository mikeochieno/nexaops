<?php
/**
 * Demo Sales Action Log Collector
 *
 * Drop-in replacement for /demo/apps/sales/action_log.php
 * that ALSO sends logs to the NexaOps management platform.
 *
 * Usage in sales scripts: include this instead of the old action_log.php
 * It preserves the original behavior AND pushes to the manager.
 */

// ── Original behavior (unchanged) ──────────────────────────────
$log_ip   = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$log_date = date("Y-m-d");
$log_time = date("H:i:s");
$insert   = mysqli_query(
    $conn,
    "INSERT INTO app_logs (user_id, log_ip, log_action, log_date, log_time) ".
    "VALUES ('$agent_id', '$log_ip', '$log_action', '$log_date', '$log_time')"
) or die(mysqli_error($conn));

// ── Push to NexaOps Manager ────────────────────────────────────
NexaOpsCollector::sendLog($agent_id, $log_action, $log_ip);

class NexaOpsCollector
{
    private static string $endpoint = 'https://223f-41-209-3-177.ngrok-free.app/api/collect/log';
    private static string $apiKey   = 'sales_app_key_2026_demo';

    public static function sendLog(string $userId, string $action, string $ip = ''): void
    {
        $payload = json_encode([
            'api_key'  => self::$apiKey,
            'logs'     => [[
                'user_id'    => $userId,
                'action'     => $action,
                'description'=> "Sales App: {$action} by user {$userId}",
                'ip'         => $ip,
                'level'      => 'info',
                'created_at' => date('Y-m-d H:i:s'),
            ]],
        ]);

        // Fire-and-forget HTTP POST (non-blocking)
        $ch = curl_init(self::$endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'X-API-Key: ' . self::$apiKey],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 3,
            CURLOPT_CONNECTTIMEOUT => 2,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    /**
     * Send a batch of logs (more efficient for bulk operations).
     */
    public static function sendBatch(array $logs): void
    {
        $payload = json_encode([
            'api_key' => self::$apiKey,
            'logs'    => array_map(fn($l) => [
                'user_id'    => $l['user_id'] ?? 'unknown',
                'action'     => $l['action'] ?? 'unknown',
                'description'=> $l['description'] ?? '',
                'ip'         => $l['ip'] ?? '',
                'level'      => $l['level'] ?? 'info',
                'created_at' => $l['created_at'] ?? date('Y-m-d H:i:s'),
            ], $logs),
        ]);

        $ch = curl_init(self::$endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'X-API-Key: ' . self::$apiKey],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
}
