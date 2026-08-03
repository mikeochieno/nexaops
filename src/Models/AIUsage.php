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
                    COALESCE(SUM(tokens_used),0) as total_tokens,
                    COALESCE(SUM(cost_usd),0) as total_cost,
                    COALESCE(AVG(latency_ms),0) as avg_latency,
                    COALESCE(SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END),0) as successful
             FROM ai_usage
             WHERE app_id = ? AND created_at >= ?",
            [$appId, $from]
        );
        $totals['total_calls'] = (int)$totals['total_calls'];
        $totals['total_tokens'] = (float)$totals['total_tokens'];
        $totals['total_cost'] = (float)$totals['total_cost'];
        $totals['avg_latency'] = (float)$totals['avg_latency'];
        $totals['successful'] = (int)$totals['successful'];

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
    public function globalStats(int $days = 7, int $companyId = 0, int $appId = 0, ?string $from = null, ?string $to = null): array
    {
        $timeConds  = [];
        $timeParams = [];
        if ($from) {
            $timeConds[] = 'u.created_at >= ?';
            $timeParams[] = $from . ' 00:00:00';
        } else {
            $timeConds[] = 'u.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)';
            $timeParams[] = $days;
        }
        if ($to) {
            $timeConds[] = 'u.created_at <= ?';
            $timeParams[] = $to . ' 23:59:59';
        }

        $extraConds  = [];
        $extraParams = [];
        if ($companyId) { $extraConds[] = 'a.company_id = ?'; $extraParams[] = $companyId; }
        if ($appId)     { $extraConds[] = 'u.app_id = ?';     $extraParams[] = $appId; }

        $where = implode(' AND ', array_merge($timeConds, $extraConds));
        $params = array_merge($timeParams, $extraParams);
        $join  = $companyId ? ' LEFT JOIN apps a ON a.id = u.app_id' : '';

        $totals = $this->db->fetch(
            "SELECT COUNT(*) as total_calls,
                    COALESCE(SUM(tokens_used),0) as total_tokens,
                    COALESCE(SUM(cost_usd),0) as total_cost,
                    COALESCE(AVG(latency_ms),0) as avg_latency
             FROM ai_usage u{$join} WHERE {$where}",
            $params
        );
        $totals['total_calls'] = (int)$totals['total_calls'];
        $totals['total_tokens'] = (float)$totals['total_tokens'];
        $totals['total_cost'] = (float)$totals['total_cost'];
        $totals['avg_latency'] = (float)$totals['avg_latency'];

        $byApp = $this->db->fetchAll(
            "SELECT a.name as app_name, u.app_id,
                    COUNT(*) as calls, SUM(u.tokens_used) as tokens, SUM(u.cost_usd) as cost
             FROM ai_usage u
             LEFT JOIN apps a ON a.id = u.app_id
             WHERE {$where}
             GROUP BY u.app_id, a.name ORDER BY calls DESC",
            $params
        );

        $byProvider = $this->db->fetchAll(
            "SELECT u.provider, COUNT(*) as calls, SUM(u.tokens_used) as tokens, SUM(u.cost_usd) as cost
             FROM ai_usage u{$join} WHERE {$where}
             GROUP BY u.provider ORDER BY calls DESC",
            $params
        );

        $daily = $this->db->fetchAll(
            "SELECT DATE(u.created_at) as day, COUNT(*) as calls, SUM(u.tokens_used) as tokens, SUM(u.cost_usd) as cost
             FROM ai_usage u{$join} WHERE {$where}
             GROUP BY DATE(u.created_at) ORDER BY day",
            $params
        );

        return [
            'totals'      => $totals,
            'by_app'      => $byApp,
            'by_provider' => $byProvider,
            'daily'       => $daily,
        ];
    }
}
