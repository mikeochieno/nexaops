<?php
/**
 * NexaOps PHP Client SDK
 *
 * Drop-in library for any PHP app to send logs and AI usage
 * to the NexaOps management platform.
 *
 * Usage:
 *   $nexa = new NexaOpsClient('your_api_key');
 *   $nexa->log('LOGIN', 'User logged in', ['user_id' => '123']);
 *   $nexa->aiUsage(['model' => 'gpt-4o', 'tokens_used' => 1500, 'cost_usd' => 0.005]);
 */

class NexaOpsClient
{
    private string $apiKey;
    private string $baseUrl;
    private string $appName;
    private int $timeout;

    public function __construct(
        string $apiKey,
        string $baseUrl = 'http://localhost/app_manager/api',
        string $appName = 'unknown',
        int $timeout = 5
    ) {
        $this->apiKey  = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->appName = $appName;
        $this->timeout = $timeout;
    }

    /**
     * Send a single activity log.
     */
    public function log(string $action, string $description = '', array $meta = []): bool
    {
        return $this->post('/collect/log', [
            'api_key' => $this->apiKey,
            'logs'    => [[
                'user_id'     => $meta['user_id'] ?? php_uname('n'),
                'action'      => $action,
                'description' => $description,
                'ip'          => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'level'       => $meta['level'] ?? 'info',
                'metadata'    => $meta,
                'created_at'  => date('Y-m-d H:i:s'),
            ]],
        ]);
    }

    /**
     * Send a batch of logs.
     */
    public function logBatch(array $logs): bool
    {
        $entries = array_map(fn($l) => [
            'user_id'     => $l['user_id'] ?? php_uname('n'),
            'action'      => $l['action'] ?? 'unknown',
            'description' => $l['description'] ?? '',
            'ip'          => $l['ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? ''),
            'level'       => $l['level'] ?? 'info',
            'metadata'    => $l['metadata'] ?? [],
            'created_at'  => $l['created_at'] ?? date('Y-m-d H:i:s'),
        ], $logs);

        return $this->post('/collect/log', [
            'api_key' => $this->apiKey,
            'logs'    => $entries,
        ]);
    }

    /**
     * Record an AI usage event.
     */
    public function aiUsage(array $data): bool
    {
        $data['api_key'] = $this->apiKey;
        return $this->post('/collect/ai-usage', $data);
    }

    /**
     * Health check.
     */
    public function health(): ?array
    {
        return $this->get('/health');
    }

    // ── Internal HTTP helpers ────────────────────────────────────

    private function post(string $path, array $data): bool
    {
        $url = $this->baseUrl . $path;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-API-Key: ' . $this->apiKey,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result !== false;
    }

    private function get(string $path): ?array
    {
        $url = $this->baseUrl . $path . '?api_key=' . urlencode($this->apiKey);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
        ]);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result ? json_decode($result, true) : null;
    }
}
