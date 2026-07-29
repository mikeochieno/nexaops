<?php
namespace Services;

use Core\Response;
use Models\App;

class AppService
{
    private App $app;

    public function __construct()
    {
        $this->app = new App();
    }

    public function handle(?string $param): void
    {
        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === 'GET' && $param === null) {
            Response::success(['apps' => $this->app->all()]);
        }
        if ($method === 'GET' && is_numeric($param)) {
            $stats = $this->app->stats((int)$param);
            if (!$stats) Response::error('App not found', 404);
            Response::success($stats);
        }
        if ($method === 'POST' && ($param === null || $param === 'create')) {
            $data = $this->getPayload();
            if (empty($data['name'])) Response::error('name is required');
            if (empty($data['api_key'])) {
                $data['api_key'] = bin2hex(random_bytes(32));
            }
            $id = $this->app->create($data);
            Response::success(['id' => $id, 'api_key' => $data['api_key']], 'App created');
        }
        if ($method === 'PUT' && is_numeric($param)) {
            $data = $this->getPayload();
            $this->app->update((int)$param, $data);
            Response::success([], 'App updated');
        }
        if ($method === 'DELETE' && is_numeric($param)) {
            $this->app->delete((int)$param);
            Response::success([], 'App deleted');
        }
        if ($param === 'stats') {
            Response::success(['apps' => $this->app->all()]);
        }

        Response::error('Invalid request', 400);
    }

    private function getPayload(): array
    {
        $raw = file_get_contents('php://input');
        return json_decode($raw, true) ?: $_POST;
    }
}
