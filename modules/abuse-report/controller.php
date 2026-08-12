<?php
/**
 * Abuse Report module controller.
 */

require_once __DIR__ . '/model.php';

class AbuseReportController {
    private AbuseReportModel $model;
    private int $userId;

    public function __construct(mysqli $connection, int $userId) {
        $this->model = new AbuseReportModel($connection);
        $this->userId = $userId;
    }

    public function model(): AbuseReportModel {
        return $this->model;
    }

    public function getReports(): array {
        return $this->model->getReports();
    }

    public function getReportDetail(int $id): ?array {
        return $this->model->getReportDetail($id);
    }

    public function dashboardSummary(): array {
        return $this->model->dashboardSummary();
    }

    public function selectableUsers(): array {
        return $this->model->selectableUsers();
    }

    public function relationshipOptions(): array {
        return $this->model->relationshipOptions();
    }

    public function create(array $input, string $actorName): int {
        return $this->model->createReport($input, $this->userId, $actorName);
    }

    public function update(int $id, array $input, string $actorName): array {
        return $this->model->updateReport($id, $input, $this->userId, $actorName);
    }

    public function updateStatus(int $id, string $status, string $actorName, string $source = 'manual'): array {
        return $this->model->updateStatus($id, $status, $this->userId, $actorName, $source);
    }

    public function reorder(string $status, array $orderedIds, string $actorName): array {
        return $this->model->reorder($status, $orderedIds, $this->userId, $actorName);
    }

    public function addNote(int $reportId, string $body, string $actorName): int {
        return $this->model->addNote($reportId, $body, $this->userId, $actorName);
    }
}
