<?php
namespace Models;

use Core\Database;

/**
 * Log model — stores and queries application activity logs.
 */
class Log
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Ingest a batch of logs.
     */
    public function ingest(int $appId, array $logs): int
    {
        $inserted = 0;
        foreach ($logs as $entry) {
            $this->db->insert('app_logs', [
                'app_id'      => $appId,
                'user_id'     => $entry['user_id'] ?? 'system',
                'action'      => $entry['action'] ?? 'unknown',
                'description' => $entry['description'] ?? '',
                'ip_address'  => $entry['ip'] ?? '',
                'level'       => $entry['level'] ?? 'info',
                'metadata'    => is_string($entry['metadata'] ?? null)
                    ? ($entry['metadata'] ?? '{}')
                    : json_encode($entry['metadata'] ?? []),
                'source_file' => $entry['source_file'] ?? '',
                'created_at'  => $entry['created_at'] ?? date('Y-m-d H:i:s'),
            ]);
            $inserted++;
        }
        return $inserted;
    }

    /**
     * Query logs with filters.
     */
    public function query(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['app_id'])) {
            $where[] = 'l.app_id = ?';
            $params[] = $filters['app_id'];
        }
        if (!empty($filters['action'])) {
            $where[] = 'l.action = ?';
            $params[] = $filters['action'];
        }
        if (!empty($filters['user_id'])) {
            $where[] = 'l.user_id = ?';
            $params[] = $filters['user_id'];
        }
        if (!empty($filters['level'])) {
            $where[] = 'l.level = ?';
            $params[] = $filters['level'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(l.action LIKE ? OR l.description LIKE ? OR l.user_id LIKE ?)';
            $search = "%{$filters['search']}%";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        if (!empty($filters['company_id'])) {
            $where[] = 'a.company_id = ?';
            $params[] = $filters['company_id'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'l.created_at >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'l.created_at <= ?';
            $params[] = $filters['date_to'];
        }

        $whereClause = implode(' AND ', $where);

        $rows = $this->db->fetchAll(
            "SELECT l.*, a.name AS app_name
             FROM app_logs l
             LEFT JOIN apps a ON a.id = l.app_id
             WHERE {$whereClause}
             ORDER BY l.created_at DESC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );

        $countRow = $this->db->fetch(
            "SELECT COUNT(*) as total FROM app_logs l WHERE {$whereClause}",
            $params
        );

        return ['logs' => $rows, 'total' => (int)($countRow['total'] ?? 0)];
    }

    /**
     * Get log stats per app for the dashboard.
     */
    public function statsByApp(int $days = 7): array
    {
        return $this->db->fetchAll(
            "SELECT a.id, a.name, COUNT(l.id) as log_count,
                    COUNT(DISTINCT l.user_id) as unique_users,
                    COUNT(DISTINCT l.action) as unique_actions
             FROM apps a
             LEFT JOIN app_logs l ON l.app_id = a.id AND l.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             WHERE a.deleted_at IS NULL
             GROUP BY a.id, a.name
             ORDER BY log_count DESC",
            [$days]
        );
    }

    /**
     * Get hourly log distribution for charts.
     */
    public function hourlyDistribution(int $appId = 0, int $hours = 24): array
    {
        $where = $appId > 0 ? 'AND app_id = ?' : '';
        $params = $appId > 0 ? [$hours, $appId] : [$hours];

        return $this->db->fetchAll(
            "SELECT HOUR(created_at) as hour, COUNT(*) as cnt
             FROM app_logs
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR) {$where}
             GROUP BY HOUR(created_at)
             ORDER BY hour",
            $params
        );
    }

    /**
     * Most common actions across all apps.
     */
    public function topActions(int $limit = 15): array
    {
        $limit = (int) $limit;
        return $this->db->fetchAll(
            "SELECT l.action, a.name as app_name, COUNT(*) as cnt
             FROM app_logs l
             LEFT JOIN apps a ON a.id = l.app_id
             WHERE l.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY l.action, a.name
             ORDER BY cnt DESC
             LIMIT {$limit}"
        );
    }

    /**
     * Recent logs for the live feed.
     */
    public function recent(int $limit = 50): array
    {
        $limit = (int) $limit;
        return $this->db->fetchAll(
            "SELECT l.*, a.name AS app_name
             FROM app_logs l
             LEFT JOIN apps a ON a.id = l.app_id
             ORDER BY l.created_at DESC
             LIMIT {$limit}"
        );
    }
}
