<?php
namespace Models;

use Core\Database;

/**
 * App model — represents a registered application (web, mobile, API, AI service).
 */
class App
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function all(): array
    {
        return $this->db->fetchAll("
            SELECT a.*, c.name AS company_name,
                   (SELECT COUNT(*) FROM app_logs WHERE app_id = a.id) AS total_logs,
                   (SELECT COUNT(*) FROM ai_usage WHERE app_id = a.id) AS ai_calls
            FROM apps a
            LEFT JOIN companies c ON c.id = a.company_id
            WHERE a.deleted_at IS NULL
            ORDER BY a.name
        ");
    }

    public function find(int $id): ?array
    {
        return $this->db->fetch("
            SELECT a.*, c.name AS company_name
            FROM apps a
            LEFT JOIN companies c ON c.id = a.company_id
            WHERE a.id = ?
        ", [$id]);
    }

    public function findByApiKey(string $apiKey): ?array
    {
        return $this->db->fetch("SELECT * FROM apps WHERE api_key = ?", [$apiKey]);
    }

    public function create(array $data): int
    {
        if (empty($data['api_key'])) {
            $data['api_key'] = bin2hex(random_bytes(32));
        }
        $data['created_at'] = date('Y-m-d H:i:s');
        return $this->db->insert('apps', $data);
    }

    public function update(int $id, array $data): int
    {
        return $this->db->update('apps', $data, 'id = ?', [$id]);
    }

    public function delete(int $id): int
    {
        return $this->db->update('apps', ['deleted_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
    }

    public function stats(int $id): array
    {
        $app = $this->find($id);
        if (!$app) return [];

        $today = $this->db->fetch(
            "SELECT COUNT(*) as cnt FROM app_logs WHERE app_id = ? AND DATE(created_at) = CURDATE()", [$id]
        );
        $week = $this->db->fetch(
            "SELECT COUNT(*) as cnt FROM app_logs WHERE app_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)", [$id]
        );
        $aiToday = $this->db->fetch(
            "SELECT COUNT(*) as cnt, COALESCE(SUM(tokens_used),0) as tokens, COALESCE(SUM(cost_usd),0) as cost
             FROM ai_usage WHERE app_id = ? AND DATE(created_at) = CURDATE()", [$id]
        );
        $aiWeek = $this->db->fetch(
            "SELECT COUNT(*) as cnt, COALESCE(SUM(tokens_used),0) as tokens, COALESCE(SUM(cost_usd),0) as cost
             FROM ai_usage WHERE app_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)", [$id]
        );

        return [
            'app'       => $app,
            'logs'      => ['today' => (int)$today['cnt'], 'week' => (int)$week['cnt']],
            'ai_today'  => ['calls' => (int)$aiToday['cnt'], 'tokens' => (int)$aiToday['tokens'], 'cost' => (float)$aiToday['cost']],
            'ai_week'   => ['calls' => (int)$aiWeek['cnt'], 'tokens' => (int)$aiWeek['tokens'], 'cost' => (float)$aiWeek['cost']],
        ];
    }

    public function logActivity(int $appId, string $userId, string $action, string $ip = '', array $meta = []): int
    {
        return $this->db->insert('app_logs', [
            'app_id'     => $appId,
            'user_id'    => $userId,
            'action'     => $action,
            'ip_address' => $ip,
            'metadata'   => json_encode($meta),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
