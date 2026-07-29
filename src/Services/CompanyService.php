<?php
namespace Services;

use Core\Response;
use Models\Company;

class CompanyService
{
    private Company $company;

    public function __construct()
    {
        $this->company = new Company();
    }

    public function handle(?string $param): void
    {
        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === 'GET' && $param === null) {
            Response::success(['companies' => $this->company->all()]);
            return;
        }
        if ($method === 'GET' && is_numeric($param)) {
            $c = $this->company->find((int)$param);
            if (!$c) Response::error('Not found', 404);
            Response::success($c);
            return;
        }
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            if (empty($data['name'])) Response::error('name is required');
            $id = $this->company->create($data);
            Response::success(['id' => $id], 'Company created');
            return;
        }
        if ($method === 'PUT' && is_numeric($param)) {
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $this->company->update((int)$param, $data);
            Response::success([], 'Company updated');
            return;
        }
        if ($method === 'DELETE' && is_numeric($param)) {
            $this->company->delete((int)$param);
            Response::success([], 'Company deleted');
            return;
        }

        Response::error('Invalid request', 400);
    }
}
