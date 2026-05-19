<?php

require_once __DIR__ . '/../../core/user_management.php';

class TaskManagementModel {
    private mysqli $conn;

    public function __construct(mysqli $connection) {
        $this->conn = $connection;
    }

    public function schemaReady(): bool {
        return tracs_table_exists($this->conn, 'tracs_tasks')
            && tracs_table_exists($this->conn, 'tracs_task_assignments')
            && tracs_table_exists($this->conn, 'tracs_task_logs')
            && tracs_column_exists($this->conn, 'tracs_task_assignments', 'assigned_at')
            && tracs_column_exists($this->conn, 'tracs_task_assignments', 'started_at')
            && tracs_column_exists($this->conn, 'tracs_task_assignments', 'reviewed_at');
    }

    public function users(): array {
        $result = $this->conn->query("
            SELECT u.id, u.name, u.email, u.division_id, r.slug AS role_slug, r.name AS role_name, d.name AS division_name
            FROM tracs_users u
            LEFT JOIN tracs_roles r ON r.id = u.role_id
            LEFT JOIN tracs_divisions d ON d.id = u.division_id
            WHERE u.is_active = 1 AND COALESCE(u.status, 'active') = 'active'
            ORDER BY COALESCE(NULLIF(u.name,''), u.email) ASC
        ");
        return $result ? array_map('tracs_normalize_user_row', $result->fetch_all(MYSQLI_ASSOC)) : [];
    }

    public function roles(): array {
        $result = $this->conn->query("SELECT id, name, slug FROM tracs_roles ORDER BY hierarchy_level DESC, name ASC");
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function divisions(): array {
        $result = $this->conn->query("SELECT id, name, code FROM tracs_divisions WHERE status='active' ORDER BY name ASC");
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function assigneeIds(array $userIds, array $roleIds, array $divisionIds): array {
        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds), fn($id) => $id > 0)));
        $where = [];
        $types = '';
        $params = [];
        if ($roleIds) {
            $roleIds = array_values(array_unique(array_filter(array_map('intval', $roleIds), fn($id) => $id > 0)));
            if ($roleIds) {
                $where[] = 'u.role_id IN (' . implode(',', array_fill(0, count($roleIds), '?')) . ')';
                $types .= str_repeat('i', count($roleIds));
                array_push($params, ...$roleIds);
            }
        }
        if ($divisionIds) {
            $divisionIds = array_values(array_unique(array_filter(array_map('intval', $divisionIds), fn($id) => $id > 0)));
            if ($divisionIds) {
                $where[] = 'u.division_id IN (' . implode(',', array_fill(0, count($divisionIds), '?')) . ')';
                $types .= str_repeat('i', count($divisionIds));
                array_push($params, ...$divisionIds);
            }
        }
        if ($where) {
            $stmt = $this->conn->prepare("
                SELECT u.id
                FROM tracs_users u
                WHERE u.is_active = 1
                  AND COALESCE(u.status, 'active') = 'active'
                  AND (" . implode(' OR ', $where) . ")
            ");
            if ($stmt) {
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $ids[] = (int)$row['id'];
                }
                $stmt->close();
            }
        }
        return array_values(array_unique(array_filter($ids, fn($id) => $id > 0)));
    }

    public function refreshOverdueStatuses(?int $actorId = null): int {
        $actorId = $actorId ?: 0;
        $stmt = $this->conn->prepare("
            SELECT ta.id, ta.task_id, ta.user_id, t.title, TIMESTAMPDIFF(SECOND, t.due_at, NOW()) AS overdue_seconds
            FROM tracs_task_assignments ta
            INNER JOIN tracs_tasks t ON t.id = ta.task_id
            WHERE ta.status NOT IN ('completed_on_time','completed_late','reviewed','cancelled','overdue')
              AND t.due_at IS NOT NULL
              AND t.due_at < NOW()
            LIMIT 250
        ");
        if (!$stmt) {
            return 0;
        }
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        if (!$rows) {
            return 0;
        }

        $update = $this->conn->prepare("
            UPDATE tracs_task_assignments
            SET status = 'overdue', overdue_seconds = ?, updated_by = ?, updated_at = NOW()
            WHERE id = ?
        ");
        if (!$update) {
            return 0;
        }
        $count = 0;
        foreach ($rows as $row) {
            $overdue = max(0, (int)($row['overdue_seconds'] ?? 0));
            $assignmentId = (int)$row['id'];
            $update->bind_param('iii', $overdue, $actorId, $assignmentId);
            if ($update->execute()) {
                $count++;
                $this->log((int)$row['task_id'], $assignmentId, $actorId, 'overdue', 'Task became overdue.');
            }
        }
        $update->close();
        return $count;
    }

    public function createTask(array $data, array $assigneeIds, int $actorId, string $actorName): int {
        $this->conn->begin_transaction();
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO tracs_tasks
                  (title, description, category, priority, assignment_scope, due_at, recurrence_type, reference_url, requires_review, created_by, assigned_by, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->bind_param(
                'ssssssssiii',
                $data['title'],
                $data['description'],
                $data['category'],
                $data['priority'],
                $data['assignment_scope'],
                $data['due_at'],
                $data['recurrence_type'],
                $data['reference_url'],
                $data['requires_review'],
                $actorId,
                $actorId
            );
            if (!$stmt->execute()) {
                throw new RuntimeException('Task could not be created.');
            }
            $taskId = (int)$stmt->insert_id;
            $stmt->close();

            foreach ($assigneeIds as $userId) {
                $assignmentId = $this->createAssignment($taskId, $userId, $actorId, $actorName, $data);
                $this->log($taskId, $assignmentId, $actorId, 'assigned', 'Task assigned.');
            }
            $this->conn->commit();
            return $taskId;
        } catch (Throwable $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    private function createAssignment(int $taskId, int $userId, int $actorId, string $actorName, array $data): int {
        $checklistId = null;
        $reminderId = null;
        $title = $data['title'];
        $desc = trim(($data['description'] ?? '') . (!empty($data['reference_url']) ? "\nReference: " . $data['reference_url'] : ''));

        $stmt = $this->conn->prepare("
            INSERT INTO tracs_side_tasks
              (user_id, title, description, is_completed, recurrence_type, created_by, created_by_name, created_at, updated_at)
            VALUES (?, ?, ?, 0, ?, ?, ?, NOW(), NOW())
        ");
        if ($stmt) {
            $stmt->bind_param('isssis', $userId, $title, $desc, $data['recurrence_type'], $actorId, $actorName);
            $stmt->execute();
            $checklistId = (int)$stmt->insert_id;
            $stmt->close();
        }

        if (!empty($data['due_at'])) {
            $remPriority = match ($data['priority']) {
                'urgent' => 'critical',
                'high' => 'high',
                'low' => 'low',
                default => 'medium',
            };
            $stmt = $this->conn->prepare("
                INSERT INTO tracs_reminders
                  (user_id, title, description, due_date, priority, is_completed, created_by, created_by_name, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, 0, ?, ?, NOW(), NOW())
            ");
            if ($stmt) {
                $stmt->bind_param('issssis', $userId, $title, $desc, $data['due_at'], $remPriority, $actorId, $actorName);
                $stmt->execute();
                $reminderId = (int)$stmt->insert_id;
                $stmt->close();
            }
        }

        $stmt = $this->conn->prepare("
            INSERT INTO tracs_task_assignments
              (task_id, user_id, status, assigned_by, assigned_at, linked_checklist_task_id, linked_reminder_id, created_at, updated_at)
            VALUES (?, ?, 'assigned', ?, NOW(), ?, ?, NOW(), NOW())
        ");
        $stmt->bind_param('iiiii', $taskId, $userId, $actorId, $checklistId, $reminderId);
        if (!$stmt->execute()) {
            throw new RuntimeException('Task assignment could not be created.');
        }
        $assignmentId = (int)$stmt->insert_id;
        $stmt->close();
        if ($checklistId && tracs_column_exists($this->conn, 'tracs_side_tasks', 'linked_assignment_id')) {
            $stmt = $this->conn->prepare("UPDATE tracs_side_tasks SET linked_assignment_id = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('ii', $assignmentId, $checklistId);
                $stmt->execute();
                $stmt->close();
            }
        }
        if ($reminderId && tracs_column_exists($this->conn, 'tracs_reminders', 'linked_assignment_id')) {
            $stmt = $this->conn->prepare("UPDATE tracs_reminders SET linked_assignment_id = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('ii', $assignmentId, $reminderId);
                $stmt->execute();
                $stmt->close();
            }
        }
        return $assignmentId;
    }

    public function listTasks(array $filters, int $actorId, bool $canMonitor): array {
        $where = [$canMonitor ? '1=1' : 'ta.user_id = ?'];
        $types = $canMonitor ? '' : 'i';
        $params = $canMonitor ? [] : [$actorId];
        foreach (['priority' => 't.priority', 'category' => 't.category'] as $key => $column) {
            if (!empty($filters[$key])) {
                $where[] = "{$column} = ?";
                $types .= 's';
                $params[] = (string)$filters[$key];
            }
        }
        if (!empty($filters['status'])) {
            if ((string)$filters['status'] === 'overdue') {
                $where[] = "ta.status NOT IN ('completed_on_time','completed_late','reviewed','cancelled') AND t.due_at IS NOT NULL AND t.due_at < NOW()";
            } else {
                $where[] = 'ta.status = ?';
                $types .= 's';
                $params[] = (string)$filters['status'];
            }
        }
        if (!empty($filters['user_id'])) {
            $where[] = 'ta.user_id = ?';
            $types .= 'i';
            $params[] = (int)$filters['user_id'];
        }
        if (!empty($filters['division_id'])) {
            $where[] = 'u.division_id = ?';
            $types .= 'i';
            $params[] = (int)$filters['division_id'];
        }
        if (!empty($filters['role_id'])) {
            $where[] = 'u.role_id = ?';
            $types .= 'i';
            $params[] = (int)$filters['role_id'];
        }
        if (!empty($filters['intern_only'])) {
            $where[] = "r.slug = 'intern'";
        }
        if (!empty($filters['due_date'])) {
            $where[] = 'DATE(t.due_at) = ?';
            $types .= 's';
            $params[] = (string)$filters['due_date'];
        }
        $stmt = $this->conn->prepare("
            SELECT t.*, ta.id AS assignment_id, ta.user_id, ta.status AS stored_status,
                   CASE
                     WHEN ta.status IN ('completed_on_time','completed_late','reviewed','cancelled','reassigned') THEN ta.status
                     WHEN t.due_at IS NOT NULL AND t.due_at < NOW() THEN 'overdue'
                     ELSE ta.status
                   END AS assignment_status,
                   ta.progress_note, ta.completion_note, ta.review_note,
                   ta.assigned_at, ta.started_at, ta.completed_at, ta.reviewed_at, ta.cancelled_at,
                   ta.completion_seconds, ta.overdue_seconds, ta.start_delay_seconds,
                   ta.updated_at AS assignment_updated_at,
                   ta.linked_checklist_task_id, ta.linked_reminder_id,
                   COALESCE(NULLIF(u.name,''), u.email) AS assignee_name,
                   r.name AS role_name, r.slug AS role_slug, d.name AS division_name,
                   COALESCE(NULLIF(cb.name,''), cb.email) AS created_by_name,
                   COALESCE(NULLIF(ab.name,''), ab.email) AS assigned_by_name
            FROM tracs_task_assignments ta
            INNER JOIN tracs_tasks t ON t.id = ta.task_id
            INNER JOIN tracs_users u ON u.id = ta.user_id
            LEFT JOIN tracs_roles r ON r.id = u.role_id
            LEFT JOIN tracs_divisions d ON d.id = u.division_id
            LEFT JOIN tracs_users cb ON cb.id = t.created_by
            LEFT JOIN tracs_users ab ON ab.id = ta.assigned_by
            WHERE " . implode(' AND ', $where) . "
            ORDER BY COALESCE(t.due_at, t.created_at) ASC, FIELD(t.priority,'urgent','high','normal','low'), t.created_at DESC
            LIMIT 300
        ");
        if (!$stmt) {
            return [];
        }
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function summary(bool $canMonitor, int $actorId): array {
        $scope = $canMonitor ? '1=1' : 'ta.user_id=' . (int)$actorId;
        $sql = "
            SELECT
              COUNT(*) AS total_assigned,
              SUM(ta.status NOT IN ('completed_on_time','completed_late','reviewed','cancelled')) AS active_tasks,
              SUM(ta.status IN ('assigned','not_started')) AS not_started,
              SUM(ta.status='in_progress') AS in_progress,
              SUM(ta.status IN ('completed_on_time','completed_late','reviewed') AND DATE(ta.completed_at)=CURDATE()) AS completed_today,
              SUM(ta.status IN ('completed_on_time','completed_late','reviewed') AND YEARWEEK(ta.completed_at, 1)=YEARWEEK(CURDATE(), 1)) AS completed_week,
              SUM(ta.status IN ('completed_on_time','completed_late','reviewed') AND DATE_FORMAT(ta.completed_at, '%Y-%m')=DATE_FORMAT(CURDATE(), '%Y-%m')) AS completed_month,
              SUM(ta.status NOT IN ('completed_on_time','completed_late','reviewed','cancelled') AND t.due_at IS NOT NULL AND t.due_at < NOW()) AS overdue_tasks,
              SUM(ta.status='completed_late') AS completed_late,
              SUM(ta.status='need_review') AS need_review,
              SUM(r.slug='intern' AND ta.status NOT IN ('completed_on_time','completed_late','reviewed','cancelled')) AS intern_tasks,
              AVG(NULLIF(ta.completion_seconds,0)) AS avg_completion_seconds,
              MIN(NULLIF(ta.completion_seconds,0)) AS fastest_completion_seconds,
              MAX(NULLIF(ta.completion_seconds,0)) AS slowest_completion_seconds,
              ROUND(100 * SUM(ta.status IN ('completed_on_time','completed_late','reviewed')) / NULLIF(COUNT(*),0)) AS completion_rate,
              ROUND(100 * SUM(ta.status IN ('completed_on_time','reviewed')) / NULLIF(SUM(ta.status IN ('completed_on_time','completed_late','reviewed')),0)) AS on_time_rate,
              ROUND(100 * SUM(ta.status='completed_late' OR (ta.status NOT IN ('completed_on_time','completed_late','reviewed','cancelled') AND t.due_at IS NOT NULL AND t.due_at < NOW())) / NULLIF(COUNT(*),0)) AS overdue_rate
            FROM tracs_task_assignments ta
            INNER JOIN tracs_tasks t ON t.id = ta.task_id
            INNER JOIN tracs_users u ON u.id = ta.user_id
            LEFT JOIN tracs_roles r ON r.id = u.role_id
            WHERE {$scope}
        ";
        $row = $this->conn->query($sql)?->fetch_assoc() ?: [];
        foreach ($row as $key => $value) {
            $row[$key] = (int)$value;
        }
        return $row;
    }

    public function performance(): array {
        $result = $this->conn->query("
            SELECT ta.user_id, COALESCE(NULLIF(u.name,''), u.email) AS user_name, r.name AS role_name, r.slug AS role_slug,
                   d.name AS division_name, COUNT(*) AS assigned_tasks,
                   SUM(ta.status IN ('completed_on_time','completed_late','reviewed')) AS completed_tasks,
                   SUM(ta.status NOT IN ('completed_on_time','completed_late','reviewed','cancelled') AND t.due_at IS NOT NULL AND t.due_at < NOW()) AS overdue_tasks,
                   AVG(NULLIF(ta.start_delay_seconds,0)) AS avg_start_seconds,
                   AVG(NULLIF(ta.completion_seconds,0)) AS avg_completion_seconds,
                   ROUND(100 * SUM(ta.status IN ('completed_on_time','reviewed')) / NULLIF(SUM(ta.status IN ('completed_on_time','completed_late','reviewed')),0)) AS on_time_rate,
                   SUM(ta.status='need_review') AS need_review,
                   SUM(ta.status IN ('not_started','in_progress') OR (ta.status NOT IN ('completed_on_time','completed_late','reviewed','cancelled') AND t.due_at > NOW())) AS current_workload,
                   MAX(ta.updated_at) AS recent_activity
            FROM tracs_task_assignments ta
            INNER JOIN tracs_tasks t ON t.id = ta.task_id
            INNER JOIN tracs_users u ON u.id = ta.user_id
            LEFT JOIN tracs_roles r ON r.id = u.role_id
            LEFT JOIN tracs_divisions d ON d.id = u.division_id
            GROUP BY ta.user_id
            ORDER BY overdue_tasks DESC, completed_tasks DESC, user_name ASC
            LIMIT 60
        ");
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function internMonitoring(): array {
        if (!tracs_table_exists($this->conn, 'user_intern_profiles')) {
            return [];
        }
        $result = $this->conn->query("
            SELECT u.id AS user_id, COALESCE(NULLIF(u.name,''), u.email) AS user_name, ip.*,
                   COALESCE(NULLIF(m.name,''), m.email) AS mentor_name,
                   COUNT(ta.id) AS assigned_tasks,
                   SUM(ta.status IN ('completed_on_time','completed_late','reviewed')) AS completed_tasks,
                   SUM(ta.status NOT IN ('completed_on_time','completed_late','reviewed','cancelled') AND t.due_at IS NOT NULL AND t.due_at < NOW()) AS overdue_tasks,
                   SUM(ta.status='need_review') AS review_tasks,
                   AVG(NULLIF(ta.completion_seconds,0)) AS avg_completion_seconds
            FROM user_intern_profiles ip
            INNER JOIN tracs_users u ON u.id = ip.user_id
            LEFT JOIN tracs_users m ON m.id = ip.mentor_user_id
            LEFT JOIN tracs_task_assignments ta ON ta.user_id = u.id
            LEFT JOIN tracs_tasks t ON t.id = ta.task_id
            GROUP BY ip.id
            ORDER BY ip.internship_end_date ASC
            LIMIT 80
        ");
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function updateAssignment(int $assignmentId, int $actorId, string $status, string $progressNote, string $reviewNote, bool $canMonitor): bool {
        $current = $this->assignmentWithTask($assignmentId, $canMonitor ? null : $actorId);
        if (!$current) {
            return false;
        }
        $fields = ['status = ?', 'progress_note = ?', 'review_note = ?', 'updated_by = ?', 'updated_at = NOW()'];
        $types = 'sssi';
        $params = [$status, $progressNote, $reviewNote, $actorId];
        if ($status === 'in_progress' && empty($current['started_at'])) {
            $fields[] = 'started_at = NOW()';
            $fields[] = 'start_delay_seconds = TIMESTAMPDIFF(SECOND, assigned_at, NOW())';
        }
        if ($status === 'completed') {
            $status = $this->completionStatus($current['due_at'] ?? null);
            $params[0] = $status;
            $fields[] = 'completed_at = NOW()';
            $fields[] = 'completed_by = ?';
            $fields[] = 'completion_seconds = TIMESTAMPDIFF(SECOND, COALESCE(started_at, assigned_at, created_at), NOW())';
            $fields[] = 'overdue_seconds = CASE WHEN ? IS NOT NULL AND NOW() > ? THEN TIMESTAMPDIFF(SECOND, ?, NOW()) ELSE 0 END';
            $types .= 'isss';
            $params[] = $actorId;
            $params[] = $current['due_at'];
            $params[] = $current['due_at'];
            $params[] = $current['due_at'];
        } elseif ($status === 'reviewed') {
            $fields[] = 'reviewed_at = NOW()';
            $fields[] = 'reviewed_by = ?';
            $types .= 'i';
            $params[] = $actorId;
        } elseif ($status === 'cancelled') {
            $fields[] = 'cancelled_at = NOW()';
        } elseif ($status === 'reassigned') {
            $fields[] = 'assigned_at = NOW()';
            $fields[] = 'assigned_by = ?';
            $types .= 'i';
            $params[] = $actorId;
        }
        $sql = "UPDATE tracs_task_assignments SET " . implode(', ', $fields) . " WHERE id = ?";
        $types .= 'i';
        $params[] = $assignmentId;
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param($types, ...$params);
        $ok = $stmt->execute() && $stmt->affected_rows > 0;
        $stmt->close();
        if ($ok) {
            $this->log((int)$current['task_id'], $assignmentId, $actorId, $status, $status . ($progressNote ? ': ' . $progressNote : ''));
        }
        return $ok;
    }

    private function assignmentWithTask(int $assignmentId, ?int $userId): ?array {
        $sql = "
            SELECT ta.*, t.due_at
            FROM tracs_task_assignments ta
            INNER JOIN tracs_tasks t ON t.id = ta.task_id
            WHERE ta.id = ?" . ($userId ? " AND ta.user_id = ?" : "") . "
            LIMIT 1
        ";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return null;
        }
        if ($userId) {
            $stmt->bind_param('ii', $assignmentId, $userId);
        } else {
            $stmt->bind_param('i', $assignmentId);
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    private function completionStatus(?string $dueAt): string {
        if (!$dueAt || !strtotime($dueAt)) {
            return 'completed_on_time';
        }
        return time() > strtotime($dueAt) ? 'completed_late' : 'completed_on_time';
    }

    public function syncFromChecklist(int $checklistTaskId, int $actorId, bool $done): void {
        $stmt = $this->conn->prepare("SELECT id, task_id, user_id FROM tracs_task_assignments WHERE linked_checklist_task_id = ? LIMIT 1");
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('i', $checklistTaskId);
        $stmt->execute();
        $assignment = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$assignment || (int)$assignment['user_id'] !== $actorId) {
            return;
        }
        $task = $this->assignmentWithTask((int)$assignment['id'], $actorId);
        $status = $done ? $this->completionStatus($task['due_at'] ?? null) : 'in_progress';
        $completed = $done
            ? ', completed_at=NOW(), completed_by=?, completion_seconds=TIMESTAMPDIFF(SECOND, COALESCE(started_at, assigned_at, created_at), NOW()), overdue_seconds=CASE WHEN ? IS NOT NULL AND NOW() > ? THEN TIMESTAMPDIFF(SECOND, ?, NOW()) ELSE 0 END'
            : ', completed_at=NULL, completed_by=NULL, completion_seconds=NULL, overdue_seconds=0';
        $started = $done ? '' : ', started_at=COALESCE(started_at, NOW()), start_delay_seconds=COALESCE(start_delay_seconds, TIMESTAMPDIFF(SECOND, assigned_at, NOW()))';
        $stmt = $this->conn->prepare("UPDATE tracs_task_assignments SET status=?, updated_by=?, updated_at=NOW() {$started} {$completed} WHERE id=?");
        if ($stmt) {
            if ($done) {
                $due = $task['due_at'] ?? null;
                $stmt->bind_param('siisssi', $status, $actorId, $actorId, $due, $due, $due, $assignment['id']);
            } else {
                $stmt->bind_param('sii', $status, $actorId, $assignment['id']);
            }
            $stmt->execute();
            $stmt->close();
        }
        $this->log((int)$assignment['task_id'], (int)$assignment['id'], $actorId, $done ? 'completed' : 'reopened', 'Synced from checklist.');
    }

    public function log(int $taskId, ?int $assignmentId, int $actorId, string $action, string $note): void {
        $stmt = $this->conn->prepare("
            INSERT INTO tracs_task_logs (task_id, assignment_id, actor_user_id, action, note, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        if ($stmt) {
            $assignmentId = $assignmentId ?: null;
            $stmt->bind_param('iiiss', $taskId, $assignmentId, $actorId, $action, $note);
            $stmt->execute();
            $stmt->close();
        }
    }

    public function taskLogs(int $assignmentId): array {
        $stmt = $this->conn->prepare("
            SELECT tl.*, COALESCE(NULLIF(u.name,''), u.email, 'System') AS actor_name
            FROM tracs_task_logs tl
            LEFT JOIN tracs_users u ON u.id = tl.actor_user_id
            WHERE tl.assignment_id = ?
            ORDER BY tl.created_at DESC
            LIMIT 30
        ");
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('i', $assignmentId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function breakdowns(bool $canMonitor, int $actorId): array {
        $scope = $canMonitor ? '1=1' : 'ta.user_id=' . (int)$actorId;
        return [
            'priority' => $this->breakdownQuery("t.priority", $scope),
            'category' => $this->breakdownQuery("t.category", $scope),
            'division' => $this->breakdownQuery("COALESCE(d.name, 'No Division')", $scope),
            'role' => $this->breakdownQuery("COALESCE(r.name, 'No Role')", $scope),
            'assigner' => $this->breakdownQuery("COALESCE(NULLIF(ab.name,''), ab.email, 'System')", $scope),
        ];
    }

    private function breakdownQuery(string $labelExpression, string $scope): array {
        $result = $this->conn->query("
            SELECT {$labelExpression} AS label,
                   COUNT(*) AS total,
                   SUM(ta.status IN ('assigned','not_started','in_progress','need_review','overdue','reassigned')) AS active,
                   SUM(ta.status IN ('completed_on_time','completed_late','reviewed')) AS completed,
                   SUM(ta.status='completed_late' OR (ta.status NOT IN ('completed_on_time','completed_late','reviewed','cancelled') AND t.due_at IS NOT NULL AND t.due_at < NOW())) AS overdue,
                   AVG(NULLIF(ta.completion_seconds,0)) AS avg_completion_seconds
            FROM tracs_task_assignments ta
            INNER JOIN tracs_tasks t ON t.id = ta.task_id
            INNER JOIN tracs_users u ON u.id = ta.user_id
            LEFT JOIN tracs_roles r ON r.id = u.role_id
            LEFT JOIN tracs_divisions d ON d.id = u.division_id
            LEFT JOIN tracs_users ab ON ab.id = ta.assigned_by
            WHERE {$scope}
            GROUP BY label
            ORDER BY total DESC, label ASC
            LIMIT 12
        ");
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }
}
