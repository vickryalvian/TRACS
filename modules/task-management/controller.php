<?php

require_once __DIR__ . '/model.php';

class TaskManagementController {
    private TaskManagementModel $model;
    private mysqli $conn;
    private int $actorId;

    public function __construct(mysqli $connection, int $actorId) {
        $this->conn = $connection;
        $this->model = new TaskManagementModel($connection);
        $this->actorId = $actorId;
    }

    public function schemaReady(): bool {
        return $this->model->schemaReady();
    }

    public function canMonitor(): bool {
        return tracs_user_can($this->conn, 'tasks.monitor', $this->actorId);
    }

    public function canCreate(): bool {
        return tracs_user_can($this->conn, 'tasks.create', $this->actorId);
    }

    public function users(): array { return $this->model->users(); }
    public function roles(): array { return $this->model->roles(); }
    public function divisions(): array { return $this->model->divisions(); }
    public function summary(): array { return $this->model->summary($this->canMonitor(), $this->actorId); }
    public function tasks(array $filters): array { return $this->model->listTasks($filters, $this->actorId, $this->canMonitor()); }
    public function performance(): array { return $this->canMonitor() ? $this->model->performance() : []; }
    public function interns(): array { return $this->canMonitor() ? $this->model->internMonitoring() : []; }

    private function cleanText(mixed $value, int $max = 255): string {
        $value = trim((string)($value ?? ''));
        $value = preg_replace('/\s+/', ' ', $value) ?? '';
        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    }

    private function cleanLong(mixed $value, int $max = 4000): string {
        $value = trim((string)($value ?? ''));
        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    }

    private function normalizeMulti(mixed $value): array {
        $values = is_array($value) ? $value : [$value];
        return array_values(array_unique(array_filter(array_map('intval', $values), fn($id) => $id > 0)));
    }

    public function create(array $input, string $actorName): array {
        tracs_require_permission($this->conn, 'tasks.create');
        $title = $this->cleanText($input['title'] ?? '', 180);
        if ($title === '') {
            throw new InvalidArgumentException('Task title is required.');
        }
        $category = (string)($input['category'] ?? 'custom');
        $allowedCategories = ['daily_checklist','case_follow_up','domain_transfer','balance_transfer','finance_log_mutasi','ssl_check','mom_follow_up','training_task','intern_task','custom'];
        if (!in_array($category, $allowedCategories, true)) {
            throw new InvalidArgumentException('Invalid task category.');
        }
        $priority = (string)($input['priority'] ?? 'normal');
        if (!in_array($priority, ['low','normal','high','urgent'], true)) {
            throw new InvalidArgumentException('Invalid task priority.');
        }
        $recurrence = !empty($input['is_recurring']) ? 'daily' : 'none';
        $dueDate = trim((string)($input['due_date'] ?? ''));
        $dueTime = trim((string)($input['due_time'] ?? ''));
        $dueAt = null;
        if ($dueDate !== '') {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate) || !strtotime($dueDate)) {
                throw new InvalidArgumentException('Invalid due date.');
            }
            if ($dueTime !== '' && !preg_match('/^\d{2}:\d{2}$/', $dueTime)) {
                throw new InvalidArgumentException('Invalid due time.');
            }
            $dueAt = date('Y-m-d H:i:s', strtotime($dueDate . ' ' . ($dueTime ?: '23:59')));
        }
        $url = trim((string)($input['reference_url'] ?? ''));
        if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Reference URL must be a valid URL.');
        }

        $userIds = $this->normalizeMulti($input['assignee_user_ids'] ?? []);
        $roleIds = $this->normalizeMulti($input['assignee_role_ids'] ?? []);
        $divisionIds = $this->normalizeMulti($input['assignee_division_ids'] ?? []);
        $assigneeIds = $this->model->assigneeIds($userIds, $roleIds, $divisionIds);
        if (!$assigneeIds) {
            throw new InvalidArgumentException('Select at least one user, role, or division to assign.');
        }
        $scope = $userIds ? 'users' : ($roleIds ? 'roles' : 'divisions');
        $taskId = $this->model->createTask([
            'title' => $title,
            'description' => $this->cleanLong($input['description'] ?? ''),
            'category' => $category,
            'priority' => $priority,
            'assignment_scope' => $scope,
            'due_at' => $dueAt,
            'recurrence_type' => $recurrence,
            'reference_url' => $url ?: null,
            'requires_review' => !empty($input['requires_review']) ? 1 : 0,
        ], $assigneeIds, $this->actorId, $actorName);
        tracs_log_user_event($this->conn, $this->actorId, 'task_created', 'task', $taskId, null, ['assignees' => $assigneeIds, 'title' => $title]);
        if (in_array($priority, ['high', 'urgent'], true)) {
            try {
                require_once __DIR__ . '/../ticker-events/controller.php';
                (new TickerEventController($this->conn))->create($this->actorId, 'Task assigned: ' . $title, $priority === 'urgent' ? 'critical' : 'info', 'tasks', $taskId);
            } catch (Throwable $e) {
                /* Ticker updates are useful, but task creation must not depend on them. */
            }
        }
        return ['message' => 'Task created and synced to checklist/reminders.', 'task_id' => $taskId];
    }

    public function updateAssignment(array $input): array {
        $status = (string)($input['status'] ?? 'in_progress');
        if (!in_array($status, ['assigned','not_started','in_progress','completed','completed_on_time','completed_late','overdue','need_review','reviewed','cancelled','reassigned'], true)) {
            throw new InvalidArgumentException('Invalid task status.');
        }
        $ok = $this->model->updateAssignment(
            (int)($input['assignment_id'] ?? 0),
            $this->actorId,
            $status,
            $this->cleanLong($input['progress_note'] ?? '', 1200),
            $this->cleanLong($input['review_note'] ?? '', 1200),
            $this->canMonitor()
        );
        if (!$ok) {
            throw new RuntimeException('Task assignment was not found or cannot be updated.');
        }
        return ['message' => 'Task assignment updated.'];
    }

    public function syncFromChecklist(int $checklistTaskId, bool $done): void {
        if ($this->schemaReady()) {
            $this->model->syncFromChecklist($checklistTaskId, $this->actorId, $done);
        }
    }
}
