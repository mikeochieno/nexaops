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
            $days = (int)($_GET['days'] ?? 7);
            Response::success($this->ai->globalStats($days));
            return;
        }

        Response::error('Invalid AI endpoint', 400);
    }
}
