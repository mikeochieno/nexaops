<?php
namespace Services;

use Core\Response;
use Core\Database;
use Models\Log;

/**
 * CollectService — receives logs pushed from external apps.
 * Supports both single and batch ingestion.
 */
class CollectService
{
    private Log $log;
    private Database $db;

    public function __construct()
    {
        $this->log = new Log();
        $this->db = Database::getInstance();
    }

    public function handle(?string $param): void
    {
        if ($param === 'log' || $param === 'logs') {
            $this->ingestLogs();
            return;
        }

        if ($param === 'ai-usage') {
            $this->ingestAIUsage();
            return;
        }

        if ($param === 'sync') {
            $this->syncFromSources();
            return;
        }

        Response::error('Invalid collect endpoint', 400);
    }

    private function ingestLogs(): void
    {
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!$payload) {
            Response::error('Invalid JSON payload');
            return;
        }

        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? ($payload['api_key'] ?? '');
        $app = $this->resolveApp($apiKey, $payload['app_id'] ?? null);
        if (!$app) {
            Response::error('Unknown app. Provide valid api_key or app_id.', 401);
            return;
        }

        $logs = $payload['logs'] ?? [$payload];
        $inserted = $this->log->ingest($app['id'], $logs);

        Response::success([
            'app'      => $app['name'],
            'inserted' => $inserted,
        ], 'Logs ingested');
    }

    private function ingestAIUsage(): void
    {
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!$payload) {
            Response::error('Invalid JSON payload');
            return;
        }

        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? ($payload['api_key'] ?? '');
        $app = $this->resolveApp($apiKey, $payload['app_id'] ?? null);
        if (!$app) {
            Response::error('Unknown app. Provide valid api_key or app_id.', 401);
            return;
        }

        $this->db->insert('ai_usage', [
            'app_id'            => $app['id'],
            'provider'          => $payload['provider'] ?? 'openai',
            'model'             => $payload['model'] ?? 'unknown',
            'operation'         => $payload['operation'] ?? 'chat',
            'tokens_prompt'     => $payload['tokens_prompt'] ?? 0,
            'tokens_completion' => $payload['tokens_completion'] ?? 0,
            'tokens_used'       => ($payload['tokens_prompt'] ?? 0) + ($payload['tokens_completion'] ?? 0),
            'cost_usd'          => $payload['cost_usd'] ?? 0,
            'latency_ms'        => $payload['latency_ms'] ?? 0,
            'success'           => $payload['success'] ?? 1,
            'user_id'           => $payload['user_id'] ?? 'system',
            'metadata'          => json_encode($payload['metadata'] ?? []),
            'created_at'        => date('Y-m-d H:i:s'),
        ]);

        Response::success([], 'AI usage recorded');
    }

    /**
     * Pull logs from registered external sources (demo sales action_log, ai_app logs).
     */
    private function syncFromSources(): void
    {
        $sources = $this->db->fetchAll(
            "SELECT * FROM log_sources WHERE is_active = 1"
        );

        $synced = 0;
        foreach ($sources as $source) {
            try {
                $synced += $this->syncSource($source);
            } catch (\Exception $e) {
                error_log("[SyncError] Source {$source['id']}: " . $e->getMessage());
            }
        }

        Response::success(['synced' => $synced, 'sources' => count($sources)]);
    }

    private function syncSource(array $source): int
    {
        $type = $source['source_type'];
        $inserted = 0;

        if ($type === 'php_action_log') {
            $inserted = $this->syncPHPActionLog($source);
        } elseif ($type === 'python_log') {
            $inserted = $this->syncPythonLog($source);
        } elseif ($type === 'android_log') {
            $inserted = $this->syncAndroidLog($source);
        }

        $this->db->update('log_sources', [
            'last_sync_at' => date('Y-m-d H:i:s'),
            'last_sync_count' => $inserted,
        ], 'id = ?', [$source['id']]);

        return $inserted;
    }

    private function syncPHPActionLog(array $source): int
    {
        $path = $source['source_path'];
        if (!file_exists($path)) return 0;

        $appId = $source['app_id'];
        $lastPos = (int)($source['last_offset'] ?? 0);
        $size = filesize($path);
        if ($size <= $lastPos) return 0;

        $fp = fopen($path, 'r');
        fseek($fp, $lastPos);
        $newData = fread($fp, $size - $lastPos);
        fclose($fp);

        $this->db->update('log_sources', ['last_offset' => $size], 'id = ?', [$source['id']]);

        $lines = explode("\n", trim($newData));
        $logs = [];
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            $logs[] = [
                'user_id'    => 'sales_app',
                'action'     => 'LOG_ENTRY',
                'description'=> $line,
                'level'      => 'info',
                'created_at' => date('Y-m-d H:i:s'),
            ];
        }

        if (!empty($logs)) {
            return $this->log->ingest($appId, $logs);
        }
        return 0;
    }

    private function syncPythonLog(array $source): int
    {
        $path = $source['source_path'];
        if (!file_exists($path)) return 0;

        $appId = $source['app_id'];
        $lastPos = (int)($source['last_offset'] ?? 0);
        $size = filesize($path);
        if ($size <= $lastPos) return 0;

        $fp = fopen($path, 'r');
        fseek($fp, $lastPos);
        $newData = fread($fp, $size - $lastPos);
        fclose($fp);

        $this->db->update('log_sources', ['last_offset' => $size], 'id = ?', [$source['app_id']]);

        $lines = explode("\n", trim($newData));
        $logs = [];
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            // Parse Python log format: 2026-07-27 10:00:00 LEVEL: message
            $level = 'info';
            if (preg_match('/\b(ERROR|CRITICAL)\b/', $line)) $level = 'error';
            elseif (preg_match('/\b(WARNING)\b/', $line)) $level = 'warning';

            $logs[] = [
                'user_id'    => 'ai_app',
                'action'     => 'AI_LOG',
                'description'=> $line,
                'level'      => $level,
                'created_at' => date('Y-m-d H:i:s'),
            ];
        }

        if (!empty($logs)) {
            return $this->log->ingest($appId, $logs);
        }
        return 0;
    }

    private function syncAndroidLog(array $source): int
    {
        // Android apps send via HTTP; this handles local ADB logcat dumps
        $path = $source['source_path'];
        if (!file_exists($path)) return 0;

        return $this->syncPythonLog($source); // same line-based format
    }

    private function resolveApp(?string $apiKey, ?int $appId): ?array
    {
        if ($apiKey) {
            return $this->db->fetch("SELECT * FROM apps WHERE api_key = ?", [$apiKey]);
        }
        if ($appId) {
            return $this->db->fetch("SELECT * FROM apps WHERE id = ?", [$appId]);
        }
        return null;
    }
}
