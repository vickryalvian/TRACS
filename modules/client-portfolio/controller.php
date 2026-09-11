<?php
declare(strict_types=1);

require_once __DIR__ . '/model.php';

final class ClientPortfolioController
{
    private ClientPortfolioModel $model;

    public function __construct(private mysqli $conn, private int $actorId)
    {
        $this->model = new ClientPortfolioModel($conn);
    }

    public function schemaReady(): bool
    {
        return $this->model->schemaReady();
    }

    public function context(array $user): array
    {
        $canViewAll = tracs_user_can($this->conn, 'clients.view_all', $this->actorId);
        return [
            'schema_ready' => $this->schemaReady(),
            'users' => $canViewAll ? $this->model->users() : [],
            'allowed_actions' => [
                'view_all' => $canViewAll,
                'manage' => tracs_user_can($this->conn, 'clients.manage', $this->actorId),
            ],
            'user' => [
                'id' => $this->actorId,
                'name' => (string)($user['display_name'] ?? $user['name'] ?? 'User'),
            ],
        ];
    }

    public function list(array $filters): array
    {
        return $this->model->listClients($filters, $this->actorId, tracs_user_can($this->conn, 'clients.view_all', $this->actorId));
    }

    public function detail(int $id): ?array
    {
        return $this->model->getClient($id, $this->actorId, tracs_user_can($this->conn, 'clients.view_all', $this->actorId));
    }

    public function assertCanAccess(int $id): void
    {
        if (!$this->detail($id)) throw new RuntimeException('Client not found.');
    }

    public function create(array $input, string $actorName): int
    {
        if (empty($input['service_name']) && array_filter(array_intersect_key($input, array_flip(['service_type','price','start_date','renewal_date','billing_day'])))) throw new InvalidArgumentException('Enter a service name for the service information.');
        if (empty($input['invoice_date']) && array_filter(array_intersect_key($input, array_flip(['due_date','amount','tax_invoice_required','tax_invoice_due_date'])))) throw new InvalidArgumentException('Enter an invoice date for the billing information.');
        return $this->transaction(function () use ($input, $actorName): int {
            $id = $this->model->createClient($input, $this->actorId, $actorName);
            $serviceId = null;
            if (!empty($input['service_name'])) $serviceId = $this->model->addService($id, $input, $this->actorId, $actorName);
            if (!empty($input['invoice_date'])) {
                $this->model->addBilling($id, [...$input, 'service_id' => $serviceId], $this->actorId, $actorName);
            }
            return $id;
        });
    }

    public function update(int $id, array $input, string $actorName): void
    {
        $this->assertCanAccess($id);
        $this->transaction(function () use ($id, $input, $actorName): int {
            $this->model->updateClient($id, $input, $this->actorId, $actorName);
            return $id;
        });
    }

    public function addService(int $id, array $input, string $actorName): int
    {
        $this->assertCanAccess($id);
        return $this->transaction(fn() => $this->model->addService($id, $input, $this->actorId, $actorName));
    }

    public function saveContact(int $id, array $input, string $actorName): int
    {
        $this->assertCanAccess($id);
        return $this->model->saveContact($id, $input, $this->actorId, $actorName);
    }

    public function addBilling(int $id, array $input, string $actorName): int
    {
        $this->assertCanAccess($id);
        return $this->transaction(fn() => $this->model->addBilling($id, $input, $this->actorId, $actorName));
    }

    public function addAddon(int $id, array $input, string $actorName): int
    {
        $this->assertCanAccess($id);
        return $this->model->addAddon($id, $input, $this->actorId, $actorName);
    }

    public function renewService(int $id, array $input, string $actorName): int
    {
        $this->assertCanAccess($id);
        return $this->transaction(fn() => $this->model->renewService($id, $input, $this->actorId, $actorName));
    }

    public function addFollowup(int $id, array $input, string $actorName): int
    {
        $this->assertCanAccess($id);
        return $this->transaction(fn() => $this->model->addFollowup($id, $input, $this->actorId, $actorName));
    }

    public function completeFollowup(int $followupId, string $actorName): int
    {
        $this->assertCanAccess($this->model->followupClientId($followupId));
        return $this->transaction(fn() => $this->model->completeFollowup($followupId, $this->actorId, $actorName));
    }

    public function updateFollowup(int $id, array $input, string $actorName): int
    {
        $this->assertCanAccess($this->model->followupClientId($id));
        return $this->transaction(fn() => $this->model->updateFollowup($id, $input, $this->actorId, $actorName));
    }

    private function transaction(callable $operation): int
    {
        $this->conn->begin_transaction();
        try {
            $id = $operation();
            $this->conn->commit();
            return $id;
        } catch (Throwable $error) {
            $this->conn->rollback();
            throw $error;
        }
    }
}
