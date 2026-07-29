<?php
namespace Models;

use Core\Database;

/**
 * AI Usage model — tracks LLM API calls, tokens, costs per app.
 */
class AIUsage
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Record an AI usage event.
     */
    public function record(array $data): int
    {
        return $this->db->insert('ai_usage', [
            'app_id'          => $data['app_id'],
            'provider'        => $data['provider'] ?? 'openai',
            'model'           => $data['model'] ?? 'gpt-4o-mini',
            'operation'       => $data['operation'] ?? 'chat',
            'tokens_prompt'   => $data['tokens_prompt'] ?? 0,
            'tokens_completion' => $data['tokens_completion'] ?? 0,
            'tokens_used'     => ($data['tokens_prompt'] ?? 0) + ($data['tokens_completion'] ?? 0),
            'cost_usd'        => $data['cost_usd'] ?? 0,
            'latency_ms'      => $data['latency_ms'] ?? 0,
            'success'         => $data['success'] ?? 1,
            'user_id'         => $data['user_id'] ?? 'system',
            'metadata'        => json_encode($data['metadata'] ?? []),
            'created_at'      => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Get AI usage stats for a specific app.
     */
    public function statsForApp(int $appId, int $days = 7): array
    {
        $from = date('Y-m-d', strtotime("-{$days} days"));

        $totals = $this->db->fetch(
            "SELECT COUNT(*) as total_calls,
                    SUM(tokens_used) as total_tokens,
                    SUM(cost_usd) as total_cost,
                    AVG(latency_ms) as avg_latency,
                    SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successful
             FROM ai_usage
             WHERE app_id = ? AND created_at >= ?",
            [$appId, $from]
        );

        $byProvider = $this->db->fetchAll(
            "SELECT provider, COUNT(*) as calls, SUM(tokens_used) as tokens, SUM(cost_usd) as cost
             FROM ai_usage WHERE app_id = ? AND created_at >= ?
             GROUP BY provider ORDER BY calls DESC",
            [$appId, $from]
        );

        $byModel = $this->db->fetchAll(
            "SELECT model, COUNT(*) as calls, SUM(tokens_used) as tokens, SUM(cost_usd) as cost
             FROM ai_usage WHERE app_id = ? AND created_at >= ?
             GROUP BY model ORDER BY calls DESC",
            [$appId, $from]
        );

        $daily = $this->db->fetchAll(
            "SELECT DATE(created_at) as day, COUNT(*) as calls, SUM(tokens_used) as tokens, SUM(cost_usd) as cost
             FROM ai_usage WHERE app_id = ? AND created_at >= ?
             GROUP BY DATE(created_at) ORDER BY day",
            [$appId, $from]
        );

        return [
            'totals'      => $totals,
            'by_provider' => $byProvider,
            'by_model'    => $byModel,
            'daily'       => $daily,
        ];
    }

    /**
     * Global AI usage across all apps.
     */
    public function globalStats(int $days = 7): array
    {
        $from = date('Y-m-d', strtotime("-{$days} days"));

        $totals = $this->db->fetch(
            "SELECT COUNT(*) as total_calls,
                    SUM(tokens_used) as total_tokens,
                    SUM(cost_usd) as total_cost,
                    AVG(latency_ms) as avg_latency
             FROM ai_usage WHERE created_at >= ?",
            [$from]
        );

        $byApp = $this->db->fetchAll(
            "SELECT a.name as app_name, u.app_id,
                    COUNT(*) as calls, SUM(u.tokens_used) as tokens, SUM(u.cost_usd) as cost
             FROM ai_usage u
             LEFT JOIN apps a ON a.id = u.app_id
             WHERE u.created_at >= ?
             GROUP BY u.app_id, a.name ORDER BY calls DESC",
            [$from]
        );

        $byProvider = $this->db->fetchAll(
            "SELECT provider, COUNT(*) as calls, SUM(tokens_used) as tokens, SUM(cost_usd) as cost
             FROM ai_usage WHERE created_at >= ?
             GROUP BY provider ORDER BY calls DESC",
            [$from]
        );

        $daily = $this->db->fetchAll(
            "SELECT DATE(created_at) as day, COUNT(*) as calls, SUM(tokens_used) as tokens, SUM(cost_usd) as cost
             FROM ai_usage WHERE created_at >= ?
             GROUP BY DATE(created_at) ORDER BY day",
            [$from]
        );

        return [
            'totals'      => $totals,
            'by_app'      => $byApp,
            'by_provider' => $byProvider,
            'daily'       => $daily,
        ];
    }
}
