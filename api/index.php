<?php
/**
 * API Router — dispatches /api/* requests to handler classes.
 */
require_once __DIR__ . '/../vendor/autoload.php';

use Core\Auth;
use Core\Response;

// Simple autoloader
spl_autoload_register(function ($class) {
    $prefix = 'Core\\';
    if (strpos($class, $prefix) === 0) {
        $relative = str_replace($prefix, '', $class);
        $path = __DIR__ . '/../src/Core/' . $relative . '.php';
        if (file_exists($path)) { require_once $path; return; }
    }
    $prefix = 'Models\\';
    if (strpos($class, $prefix) === 0) {
        $relative = str_replace($prefix, '', $class);
        $path = __DIR__ . '/../src/Models/' . $relative . '.php';
        if (file_exists($path)) { require_once $path; return; }
    }
    $prefix = 'Collectors\\';
    if (strpos($class, $prefix) === 0) {
        $relative = str_replace($prefix, '', $class);
        $path = __DIR__ . '/../src/Collectors/' . $relative . '.php';
        if (file_exists($path)) { require_once $path; return; }
    }
});

header('Content-Type: application/json; charset=utf-8');

$route = $_GET['route'] ?? '';
$parts = explode('/', trim($route, '/'));
$endpoint = $parts[0] ?? '';
$param = $parts[1] ?? null;

Auth::init();

// Public endpoints (no auth)
if ($endpoint === 'health' || $endpoint === 'collect') {
    // collect endpoints authenticate via app api_key in payload
    if ($endpoint === 'health') {
        Response::success(['status' => 'ok', 'time' => date('c')]);
    }
} else {
    Auth::require();
}

switch ($endpoint) {
    // ── Apps ──────────────────────────────────────────────────────────
    case 'apps':
        require __DIR__ . '/../src/Services/AppService.php';
        $svc = new \Services\AppService();
        $svc->handle($param);
        break;

    // ── Logs ──────────────────────────────────────────────────────────
    case 'logs':
        require __DIR__ . '/../src/Services/LogService.php';
        $svc = new \Services\LogService();
        $svc->handle($param);
        break;

    // ── AI Usage ──────────────────────────────────────────────────────
    case 'ai':
        require __DIR__ . '/../src/Services/AIService.php';
        $svc = new \Services\AIService();
        $svc->handle($param);
        break;

    // ── Dashboard stats ───────────────────────────────────────────────
    case 'dashboard':
        require __DIR__ . '/../src/Services/DashboardService.php';
        $svc = new \Services\DashboardService();
        $svc->handle($param);
        break;

    // ── Log collectors (push endpoints) ───────────────────────────────
    case 'collect':
        require __DIR__ . '/../src/Services/CollectService.php';
        $svc = new \Services\CollectService();
        $svc->handle($param);
        break;

    // ── Companies ─────────────────────────────────────────────────────
    case 'companies':
        require __DIR__ . '/../src/Services/CompanyService.php';
        $svc = new \Services\CompanyService();
        $svc->handle($param);
        break;

    default:
        Response::error('Unknown endpoint: ' . $endpoint, 404);
}
