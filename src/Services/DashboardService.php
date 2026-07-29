<?php
namespace Services;

use Core\Response;
use Core\Database;
use Models\App;
use Models\Log;
use Models\AIUsage;

class DashboardService
{
    public function handle(?string $param): void
    {
        $db = Database::getInstance();
        $log = new Log();
        $ai = new AIUsage();

        if ($param === 'overview' || $param === null) {
            // App counts
            $totalApps = $db->fetch("SELECT COUNT(*) as cnt FROM apps WHERE deleted_at IS NULL");
            $totalCompanies = $db->fetch("SELECT COUNT(*) as cnt FROM companies");
            $totalLogs = $db->fetch("SELECT COUNT(*) as cnt FROM app_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
            $totalAiCalls = $db->fetch("SELECT COUNT(*) as cnt, COALESCE(SUM(cost_usd),0) as cost FROM ai_usage WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
            $activeUsers = $db->fetch("SELECT COUNT(DISTINCT user_id) as cnt FROM app_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");

            // Recent logs
            $recentLogs = $log->recent(20);

            // Top actions
            $topActions = $log->topActions(10);

            // Per-app stats
            $appStats = $log->statsByApp(7);

            // AI daily
            $aiGlobal = $ai->globalStats(7);

            Response::success([
                'totals' => [
                    'apps'       => (int)($totalApps['cnt'] ?? 0),
                    'companies'  => (int)($totalCompanies['cnt'] ?? 0),
                    'logs_7d'    => (int)($totalLogs['cnt'] ?? 0),
                    'ai_calls_7d'=> (int)($totalAiCalls['cnt'] ?? 0),
                    'ai_cost_7d' => (float)($totalAiCalls['cost'] ?? 0),
                    'active_users' => (int)($activeUsers['cnt'] ?? 0),
                ],
                'recent_logs'   => $recentLogs,
                'top_actions'   => $topActions,
                'app_stats'     => $appStats,
                'ai_global'     => $aiGlobal,
            ]);
            return;
        }

        Response::error('Invalid dashboard endpoint', 400);
    }
}
