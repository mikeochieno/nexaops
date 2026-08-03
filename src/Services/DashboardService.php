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
            $days = max(1, min(365, (int)($_GET['days'] ?? 7)));
            $companyId = (int)($_GET['company_id'] ?? 0);
            $appId     = (int)($_GET['app_id'] ?? 0);
            $from = $this->cleanDate($_GET['from'] ?? null);
            $to   = $this->cleanDate($_GET['to'] ?? null);

            // App counts
            $appWhere = 'WHERE deleted_at IS NULL';
            $appParams = [];
            if ($companyId) { $appWhere .= ' AND company_id = ?'; $appParams[] = $companyId; }
            if ($appId)     { $appWhere .= ' AND id = ?';         $appParams[] = $appId; }
            $totalApps = $db->fetch("SELECT COUNT(*) as cnt FROM apps {$appWhere}", $appParams);

            $compWhere = '';
            $compParams = [];
            if ($companyId) { $compWhere = 'WHERE id = ?'; $compParams[] = $companyId; }
            $totalCompanies = $db->fetch("SELECT COUNT(*) as cnt FROM companies {$compWhere}", $compParams);

            $counts = $log->counts($days, $companyId, $appId, $from, $to);
            $recentLogs = $log->recent(20, $companyId, $appId);
            $topActions = $log->topActions(10, $days, $companyId, $appId, $from, $to);
            $appStats = $log->statsByApp($days, $companyId, $appId, $from, $to);
            $aiGlobal = $ai->globalStats($days, $companyId, $appId, $from, $to);
            $logsDaily = $log->dailyTrend($days, $companyId, $appId, $from, $to);

            Response::success([
                'days' => $days,
                'today' => date('Y-m-d'),
                'filters' => [
                    'company_id' => $companyId,
                    'app_id'     => $appId,
                    'from'       => $from,
                    'to'         => $to,
                ],
                'totals' => [
                    'apps'       => (int)($totalApps['cnt'] ?? 0),
                    'companies'  => (int)($totalCompanies['cnt'] ?? 0),
                    'logs_7d'    => $counts['logs'],
                    'ai_calls_7d'=> (int)($aiGlobal['totals']['total_calls'] ?? 0),
                    'ai_cost_7d' => (float)($aiGlobal['totals']['total_cost'] ?? 0),
                    'active_users' => $counts['active_users'],
                ],
                'recent_logs'   => $recentLogs,
                'top_actions'   => $topActions,
                'app_stats'     => $appStats,
                'ai_global'     => $aiGlobal,
                'logs_daily'    => $logsDaily,
            ]);
            return;
        }

        Response::error('Invalid dashboard endpoint', 400);
    }

    private function cleanDate(?string $v): ?string
    {
        $v = trim((string)$v);
        if ($v === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return null;
        return $v;
    }
}
