<?php
namespace Services;

use Core\Response;
use Models\AIUsage;

class AIService
{
    private AIUsage $ai;

    public function __construct()
    {
        $this->ai = new AIUsage();
    }

    public function handle(?string $param): void
    {
        if ($param === 'record') {
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            if (empty($data['app_id'])) Response::error('app_id is required');
            $id = $this->ai->record($data);
            Response::success(['id' => $id], 'AI usage recorded');
            return;
        }

        if ($param === 'stats' && isset($_GET['app_id'])) {
            $days = (int)($_GET['days'] ?? 7);
            Response::success($this->ai->statsForApp((int)$_GET['app_id'], $days));
            return;
        }

        if ($param === 'global' || $param === 'stats') {
            $days = max(1, min(365, (int)($_GET['days'] ?? 7)));
            $companyId = (int)($_GET['company_id'] ?? 0);
            $appId     = (int)($_GET['app_id'] ?? 0);
            $from = $this->cleanDate($_GET['from'] ?? null);
            $to   = $this->cleanDate($_GET['to'] ?? null);
            Response::success(array_merge(
                ['days' => $days, 'today' => date('Y-m-d'),
                 'filters' => ['company_id' => $companyId, 'app_id' => $appId, 'from' => $from, 'to' => $to]],
                $this->ai->globalStats($days, $companyId, $appId, $from, $to)
            ));
            return;
        }

        Response::error('Invalid AI endpoint', 400);
    }

    private function cleanDate(?string $v): ?string
    {
        $v = trim((string)$v);
        if ($v === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return null;
        return $v;
    }
}
