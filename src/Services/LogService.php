<?php
namespace Services;

use Core\Response;
use Models\Log;

class LogService
{
    private Log $log;

    public function __construct()
    {
        $this->log = new Log();
    }

    public function handle(?string $param): void
    {
        if ($param === 'recent' || ($_SERVER['REQUEST_METHOD'] === 'GET' && $param === null)) {
            $limit = (int)($_GET['limit'] ?? 50);
            Response::success(['logs' => $this->log->recent($limit)]);
            return;
        }

        if ($param === 'search') {
            $filters = [
                'app_id'    => $_GET['app_id'] ?? null,
                'company_id' => $_GET['company_id'] ?? null,
                'action'    => $_GET['action'] ?? null,
                'user_id'   => $_GET['user_id'] ?? null,
                'level'     => $_GET['level'] ?? null,
                'search'    => $_GET['q'] ?? null,
                'date_from' => $_GET['from'] ?? null,
                'date_to'   => $_GET['to'] ?? null,
            ];
            $limit  = min((int)($_GET['limit'] ?? 100), 500);
            $offset = (int)($_GET['offset'] ?? 0);
            Response::success($this->log->query($filters, $limit, $offset));
            return;
        }

        if ($param === 'stats') {
            $days = (int)($_GET['days'] ?? 7);
            Response::success(['stats' => $this->log->statsByApp($days)]);
            return;
        }

        if ($param === 'hourly') {
            $appId = (int)($_GET['app_id'] ?? 0);
            Response::success(['hourly' => $this->log->hourlyDistribution($appId)]);
            return;
        }

        if ($param === 'actions') {
            Response::success(['actions' => $this->log->topActions()]);
            return;
        }

        Response::error('Invalid log endpoint', 400);
    }
}
