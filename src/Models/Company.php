<?php
namespace Models;

use Core\Database;

/**
 * Company model — represents an organization using the platform.
 */
class Company
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function all(): array
    {
        return $this->db->fetchAll("SELECT * FROM companies ORDER BY name");
    }

    public function find(int $id): ?array
    {
        return $this->db->fetch("SELECT * FROM companies WHERE id = ?", [$id]);
    }

    public function create(array $data): int
    {
        return $this->db->insert('companies', $data);
    }

    public function update(int $id, array $data): int
    {
        return $this->db->update('companies', $data, 'id = ?', [$id]);
    }

    public function appCount(int $companyId): int
    {
        $row = $this->db->fetch("SELECT COUNT(*) as cnt FROM apps WHERE company_id = ?", [$companyId]);
        return (int)($row['cnt'] ?? 0);
    }

    public function delete(int $id): int
    {
        return $this->db->query("DELETE FROM companies WHERE id = ?", [$id])->rowCount();
    }
}
