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
        $this->conn->begin_transaction();
        try {
            $id = $this->model->createClient($input, $this->actorId, $actorName);
            $this->conn->commit();
            return $id;
        } catch (Throwable $error) {
            $this->conn->rollback();
            throw $error;
        }
    }

    public function update(int $id, array $input, string $actorName): void
    {
        $this->assertCanAccess($id);
        $this->conn->begin_transaction();
        try {
            $this->model->updateClient($id, $input, $this->actorId, $actorName);
            $this->conn->commit();
        } catch (Throwable $error) {
            $this->conn->rollback();
            throw $error;
        }
    }

    public function addService(int $id, array $input, string $actorName): int
    {
        $this->assertCanAccess($id);
        return $this->model->addService($id, $input, $this->actorId, $actorName);
    }

    public function saveContact(int $id, array $input, string $actorName): int
    {
        $this->assertCanAccess($id);
        return $this->model->saveContact($id, $input, $this->actorId, $actorName);
    }

    public function addBilling(int $id, array $input, string $actorName): int
    {
        $this->assertCanAccess($id);
        return $this->model->addBilling($id, $input, $this->actorId, $actorName);
    }

    public function addAddon(int $id, array $input, string $actorName): int
    {
        $this->assertCanAccess($id);
        return $this->model->addAddon($id, $input, $this->actorId, $actorName);
    }

    public function renewService(int $id, array $input, string $actorName): int
    {
        $this->assertCanAccess($id);
        return $this->model->renewService($id, $input, $this->actorId, $actorName);
    }

    public function addFollowup(int $id, array $input, string $actorName): int
    {
        $this->assertCanAccess($id);
        return $this->model->addFollowup($id, $input, $this->actorId, $actorName);
    }

    public function completeFollowup(int $followupId, string $actorName): int
    {
        return $this->model->completeFollowup($followupId, $this->actorId, $actorName);
    }
}
