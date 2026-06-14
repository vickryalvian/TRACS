<?php

final class CalendarService
{
    private mysqli $conn;
    private int $uid;
    private array $actor;
    private string $role;
    private int $divisionId;
    private DateTimeZone $timezone;

    public function __construct(mysqli $conn, int $uid, array $actor)
    {
        $this->conn = $conn;
        $this->uid = $uid;
        $this->actor = $actor;
        $this->role = (string)($actor['role_slug'] ?? 'agent');
        $this->divisionId = (int)($actor['division_id'] ?? 0);
        $this->timezone = new DateTimeZone('Asia/Jakarta');
    }

    public function getEvents(string $start, string $end): array
    {
        $events = [];
        $sources = [];
        $collectors = [
            'cases' => 'collectCases',
            'reminders' => 'collectReminders',
            'tasks' => 'collectTasks',
            'meetings' => 'collectMeetings',
            'meeting_actions' => 'collectMeetingActions',
            'shifts' => 'collectShifts',
            'holidays' => 'collectHolidays',
            'maintenance' => 'collectMaintenanceNotifications',
            'domains' => 'collectDomainExpirations',
            'birthdays' => 'collectBirthdays',
            'user_dates' => 'collectInternshipDates',
            'calendar' => 'collectManualEvents',
        ];

        foreach ($collectors as $source => $method) {
            try {
                $rows = $this->{$method}($start, $end);
                array_push($events, ...$rows);
                $sources[$source] = ['available' => true, 'count' => count($rows)];
            } catch (Throwable $e) {
                error_log("TRACS calendar source {$source}: " . $e->getMessage());
                $sources[$source] = ['available' => false, 'count' => 0];
            }
        }

        usort($events, static function (array $a, array $b): int {
            return [$a['date'], $a['start_time'] ?? '', $a['title']]
                <=> [$b['date'], $b['start_time'] ?? '', $b['title']];
        });

        return [
            'range' => ['start' => $start, 'end' => $end, 'timezone' => 'Asia/Jakarta'],
            'events' => $events,
            'sources' => $sources,
            'generated_at' => (new DateTimeImmutable('now', $this->timezone))->format(DATE_ATOM),
        ];
    }

    private function collectCases(string $start, string $end): array
    {
        if (!$this->hasPermission(['cases.view', 'cases.manage'])
            || !tracs_table_exists($this->conn, 'tracs_cases')
            || !tracs_column_exists($this->conn, 'tracs_cases', 'next_check_at')) {
            return [];
        }

        [$scope, $scopeTypes, $scopeParams] = $this->ownerScope('c.user_id', 'u.division_id');
        $rows = $this->fetchAll(
            "SELECT c.id,c.user_id,c.title,c.notes,c.status,c.priority,c.next_check_at,c.created_at,c.updated_at,
                    COALESCE(NULLIF(u.name,''),u.email) AS owner_name,u.division_id,d.name AS division_name
             FROM tracs_cases c
             LEFT JOIN tracs_users u ON u.id=c.user_id
             LEFT JOIN tracs_divisions d ON d.id=u.division_id
             WHERE c.next_check_at BETWEEN ? AND ? {$scope}",
            'ss' . $scopeTypes,
            [$start . ' 00:00:00', $end . ' 23:59:59', ...$scopeParams]
        );

        $now = new DateTimeImmutable('now', $this->timezone);
        return array_map(function (array $row) use ($now): array {
            $due = new DateTimeImmutable((string)$row['next_check_at'], $this->timezone);
            $status = (string)$row['status'] === 'completed'
                ? 'done'
                : ($due < $now ? 'overdue' : $this->status((string)$row['status']));
            return $this->event([
                'id' => 'case_' . $row['id'],
                'source' => 'cases',
                'source_id' => (int)$row['id'],
                'type' => 'case',
                'title' => (string)$row['title'],
                'date' => $due->format('Y-m-d'),
                'start_time' => $due->format('H:i'),
                'status' => $status,
                'priority' => (string)$row['priority'],
                'assignee' => $this->assignee($row['user_id'], $row['owner_name']),
                'division' => $this->division($row['division_id'], $row['division_name']),
                'notes' => (string)($row['notes'] ?? ''),
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
                'meta' => [
                    'case_status' => ucfirst(str_replace('_', ' ', $status)),
                    'ticket_id' => '#' . $row['id'],
                    'url' => 'cases.php?case_id=' . $row['id'],
                    'actions' => ['open_case'],
                ],
            ]);
        }, $rows);
    }

    private function collectReminders(string $start, string $end): array
    {
        if (!$this->hasPermission(['reminders.view', 'reminders.manage'])
            || !tracs_table_exists($this->conn, 'tracs_reminders')) {
            return [];
        }

        [$scope, $scopeTypes, $scopeParams] = $this->ownerScope('r.user_id', 'u.division_id');
        $rows = $this->fetchAll(
            "SELECT r.id,r.user_id,r.title,r.description,r.due_date,r.priority,r.is_completed,r.created_at,r.updated_at,
                    COALESCE(NULLIF(u.name,''),u.email) AS owner_name,u.division_id,d.name AS division_name
             FROM tracs_reminders r
             LEFT JOIN tracs_users u ON u.id=r.user_id
             LEFT JOIN tracs_divisions d ON d.id=u.division_id
             WHERE r.due_date BETWEEN ? AND ? AND r.archived_at IS NULL {$scope}",
            'ss' . $scopeTypes,
            [$start . ' 00:00:00', $end . ' 23:59:59', ...$scopeParams]
        );

        $now = new DateTimeImmutable('now', $this->timezone);
        return array_map(function (array $row) use ($now): array {
            $due = new DateTimeImmutable((string)$row['due_date'], $this->timezone);
            $status = !empty($row['is_completed']) ? 'done' : ($due < $now ? 'overdue' : 'upcoming');
            return $this->event([
                'id' => 'reminder_' . $row['id'],
                'source' => 'reminders',
                'source_id' => (int)$row['id'],
                'type' => 'reminder',
                'title' => (string)$row['title'],
                'date' => $due->format('Y-m-d'),
                'start_time' => $due->format('H:i'),
                'status' => $status,
                'priority' => (string)$row['priority'],
                'assignee' => $this->assignee($row['user_id'], $row['owner_name']),
                'division' => $this->division($row['division_id'], $row['division_name']),
                'notes' => (string)($row['description'] ?? ''),
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
                'meta' => [
                    'url' => 'reminders.php?reminder_id=' . $row['id'],
                    'actions' => ['view_source', 'mark_done'],
                    'can_mark_done' => (int)$row['user_id'] === $this->uid
                        && empty($row['is_completed'])
                        && $this->hasPermission(['reminders.manage']),
                ],
            ]);
        }, $rows);
    }

    private function collectTasks(string $start, string $end): array
    {
        if (!$this->hasPermission(['checklist.view', 'checklist.manage', 'tasks.view_own', 'tasks.monitor'])
            || !tracs_table_exists($this->conn, 'tracs_side_tasks')) {
            return [];
        }

        $deadline = tracs_column_exists($this->conn, 'tracs_side_tasks', 'reset_at')
            ? 'COALESCE(t.reset_at,t.ticker_visible_until)'
            : 't.ticker_visible_until';
        [$scope, $scopeTypes, $scopeParams] = $this->ownerScope('t.user_id', 'u.division_id');
        $rows = $this->fetchAll(
            "SELECT t.id,t.user_id,t.title,t.description,t.is_completed,t.recurrence_type,t.created_at,t.updated_at,
                    {$deadline} AS deadline,COALESCE(NULLIF(u.name,''),u.email) AS owner_name,
                    u.division_id,d.name AS division_name
             FROM tracs_side_tasks t
             LEFT JOIN tracs_users u ON u.id=t.user_id
             LEFT JOIN tracs_divisions d ON d.id=u.division_id
             WHERE {$deadline} BETWEEN ? AND ? AND t.archived_at IS NULL {$scope}",
            'ss' . $scopeTypes,
            [$start . ' 00:00:00', $end . ' 23:59:59', ...$scopeParams]
        );

        $now = new DateTimeImmutable('now', $this->timezone);
        return array_map(function (array $row) use ($now): array {
            $due = new DateTimeImmutable((string)$row['deadline'], $this->timezone);
            $status = !empty($row['is_completed']) ? 'done' : ($due < $now ? 'overdue' : 'upcoming');
            return $this->event([
                'id' => 'task_' . $row['id'],
                'source' => 'tasks',
                'source_id' => (int)$row['id'],
                'type' => 'task',
                'title' => (string)$row['title'],
                'date' => $due->format('Y-m-d'),
                'start_time' => $due->format('H:i'),
                'status' => $status,
                'priority' => 'medium',
                'assignee' => $this->assignee($row['user_id'], $row['owner_name']),
                'division' => $this->division($row['division_id'], $row['division_name']),
                'notes' => (string)($row['description'] ?? ''),
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
                'meta' => [
                    'url' => 'checklist.php?task_id=' . $row['id'],
                    'recurrence' => (string)($row['recurrence_type'] ?? 'none'),
                    'actions' => ['view_source', 'mark_done'],
                    'can_mark_done' => (int)$row['user_id'] === $this->uid
                        && empty($row['is_completed'])
                        && $this->hasPermission(['checklist.manage']),
                ],
            ]);
        }, $rows);
    }

    private function collectMeetings(string $start, string $end): array
    {
        if (!$this->hasPermission(['moms.view', 'moms.manage'])
            || !tracs_table_exists($this->conn, 'tracs_moms')
            || !tracs_column_exists($this->conn, 'tracs_moms', 'meeting_at')) {
            return [];
        }

        [$scope, $scopeTypes, $scopeParams] = $this->ownerScope('m.created_by', 'u.division_id');
        $rows = $this->fetchAll(
            "SELECT m.id,m.title,m.type,m.objective,m.participants,m.meeting_at,m.meeting_url,m.status,
                    m.created_by,m.created_at,m.updated_at,COALESCE(NULLIF(u.name,''),u.email) AS owner_name,
                    u.division_id,d.name AS division_name
             FROM tracs_moms m
             LEFT JOIN tracs_users u ON u.id=m.created_by
             LEFT JOIN tracs_divisions d ON d.id=u.division_id
             WHERE m.meeting_at BETWEEN ? AND ? {$scope}",
            'ss' . $scopeTypes,
            [$start . ' 00:00:00', $end . ' 23:59:59', ...$scopeParams]
        );

        return array_map(function (array $row): array {
            $at = new DateTimeImmutable((string)$row['meeting_at'], $this->timezone);
            return $this->event([
                'id' => 'meeting_' . $row['id'],
                'source' => 'meetings',
                'source_id' => (int)$row['id'],
                'type' => 'meeting',
                'title' => (string)$row['title'],
                'date' => $at->format('Y-m-d'),
                'start_time' => $at->format('H:i'),
                'status' => $this->status((string)$row['status']),
                'priority' => (string)$row['type'] === 'urgent' ? 'high' : 'medium',
                'assignee' => $this->assignee($row['created_by'], $row['owner_name']),
                'division' => $this->division($row['division_id'], $row['division_name']),
                'notes' => (string)($row['objective'] ?? ''),
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
                'meta' => [
                    'participants' => (string)($row['participants'] ?? ''),
                    'meeting_url' => (string)($row['meeting_url'] ?? ''),
                    'url' => 'mom.php?mom_id=' . $row['id'],
                    'actions' => ['open_meeting'],
                ],
            ]);
        }, $rows);
    }

    private function collectMeetingActions(string $start, string $end): array
    {
        if (!$this->hasPermission(['moms.view', 'moms.manage'])
            || !tracs_table_exists($this->conn, 'tracs_mom_actions')
            || !tracs_table_exists($this->conn, 'tracs_moms')) {
            return [];
        }

        [$scope, $scopeTypes, $scopeParams] = $this->ownerScope('m.created_by', 'u.division_id');
        $rows = $this->fetchAll(
            "SELECT a.id,a.mom_id,a.title,a.description,a.assigned_to,a.priority,a.status,a.due_date,
                    a.created_at,a.updated_at,m.created_by,COALESCE(NULLIF(u.name,''),u.email) AS owner_name,
                    u.division_id,d.name AS division_name
             FROM tracs_mom_actions a
             INNER JOIN tracs_moms m ON m.id=a.mom_id
             LEFT JOIN tracs_users u ON u.id=m.created_by
             LEFT JOIN tracs_divisions d ON d.id=u.division_id
             WHERE a.due_date BETWEEN ? AND ? {$scope}",
            'ss' . $scopeTypes,
            [$start . ' 00:00:00', $end . ' 23:59:59', ...$scopeParams]
        );

        $now = new DateTimeImmutable('now', $this->timezone);
        return array_map(function (array $row) use ($now): array {
            $due = new DateTimeImmutable((string)$row['due_date'], $this->timezone);
            $done = in_array((string)$row['status'], ['completed', 'cancelled'], true);
            return $this->event([
                'id' => 'mom_action_' . $row['id'],
                'source' => 'meeting_actions',
                'source_id' => (int)$row['id'],
                'type' => 'task',
                'title' => (string)$row['title'],
                'date' => $due->format('Y-m-d'),
                'start_time' => $due->format('H:i'),
                'status' => $done ? $this->status((string)$row['status']) : ($due < $now ? 'overdue' : $this->status((string)$row['status'])),
                'priority' => (string)$row['priority'],
                'assignee' => ['id' => null, 'name' => (string)($row['assigned_to'] ?: $row['owner_name'])],
                'division' => $this->division($row['division_id'], $row['division_name']),
                'notes' => (string)($row['description'] ?? ''),
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
                'meta' => [
                    'url' => 'mom.php?mom_id=' . $row['mom_id'],
                    'meeting_id' => (int)$row['mom_id'],
                    'actions' => ['open_meeting'],
                ],
            ]);
        }, $rows);
    }

    private function collectShifts(string $start, string $end): array
    {
        if (!$this->hasPermission(['shifts.view', 'shifts.manage'])
            || !tracs_table_exists($this->conn, 'shift_assignments')) {
            return [];
        }

        [$scope, $scopeTypes, $scopeParams] = $this->ownerScope('a.user_id', 'u.division_id');
        $rows = $this->fetchAll(
            "SELECT a.id,a.user_id,a.assignment_date,a.start_datetime,a.end_datetime,a.is_cross_day,
                    a.assignment_type,a.status,a.is_overtime,a.is_holiday_assignment,a.approval_status,
                    a.notes,a.created_at,a.updated_at,st.shift_name,
                    COALESCE(NULLIF(u.name,''),u.email) AS owner_name,u.division_id,d.name AS division_name
             FROM shift_assignments a
             LEFT JOIN shift_templates st ON st.id=a.shift_template_id
             LEFT JOIN tracs_users u ON u.id=a.user_id
             LEFT JOIN tracs_divisions d ON d.id=u.division_id
             WHERE a.start_datetime<=? AND a.end_datetime>=? {$scope}",
            'ss' . $scopeTypes,
            [$end . ' 23:59:59', $start . ' 00:00:00', ...$scopeParams]
        );

        return array_map(function (array $row): array {
            $startAt = new DateTimeImmutable((string)$row['start_datetime'], $this->timezone);
            $endAt = new DateTimeImmutable((string)$row['end_datetime'], $this->timezone);
            $isOvertime = !empty($row['is_overtime'])
                || in_array((string)$row['assignment_type'], ['lembur', 'holiday_coverage', 'emergency_coverage'], true);
            $endTime = $endAt->format('H:i');
            $endDate = $endAt->format('Y-m-d');
            if (!empty($row['is_cross_day']) && $endTime === '00:00') {
                $endTime = '24:00';
                $endDate = (string)$row['assignment_date'];
            }
            return $this->event([
                'id' => 'shift_' . $row['id'],
                'source' => 'shifts',
                'source_id' => (int)$row['id'],
                'type' => $isOvertime ? 'overtime' : 'shift',
                'title' => trim(((string)($row['shift_name'] ?: ucfirst(str_replace('_', ' ', $row['assignment_type'])))) . ' · ' . (string)$row['owner_name']),
                'date' => (string)$row['assignment_date'],
                'end_date' => $endDate,
                'start_time' => $startAt->format('H:i'),
                'end_time' => $endTime,
                'status' => $this->status((string)$row['status']),
                'priority' => $isOvertime ? 'high' : 'medium',
                'assignee' => $this->assignee($row['user_id'], $row['owner_name']),
                'division' => $this->division($row['division_id'], $row['division_name']),
                'notes' => (string)($row['notes'] ?? ''),
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
                'meta' => [
                    'assignment_type' => (string)$row['assignment_type'],
                    'approval_status' => (string)$row['approval_status'],
                    'is_holiday_assignment' => (bool)$row['is_holiday_assignment'],
                    'url' => 'shifting-assignment.php?assignment_id=' . $row['id'],
                    'actions' => ['open_shift_assignment'],
                ],
            ]);
        }, $rows);
    }

    private function collectHolidays(string $start, string $end): array
    {
        $rows = [];
        if (tracs_table_exists($this->conn, 'public_holidays')) {
            $rows = $this->fetchAll(
                "SELECT id,holiday_date,holiday_name,holiday_type,notes,created_at,updated_at
                 FROM public_holidays
                 WHERE is_active=1 AND holiday_date BETWEEN ? AND ?",
                'ss',
                [$start, $end]
            );
        }

        $seen = [];
        $events = [];
        foreach ($rows as $row) {
            $key = $row['holiday_date'] . '|' . strtolower((string)$row['holiday_name']);
            $seen[$key] = true;
            $events[] = $this->holidayEvent($row, 'database');
        }

        $firstYear = (int)substr($start, 0, 4);
        $lastYear = (int)substr($end, 0, 4);
        for ($year = $firstYear; $year <= $lastYear; $year++) {
            foreach ($this->localHolidayRows($year) as $row) {
                if ($row['date'] < $start || $row['date'] > $end) {
                    continue;
                }
                $key = $row['date'] . '|' . strtolower((string)$row['name']);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $events[] = $this->holidayEvent([
                    'id' => null,
                    'holiday_date' => $row['date'],
                    'holiday_name' => $row['name'],
                    'holiday_type' => !empty($row['is_national_holiday']) ? 'national_holiday' : 'collective_leave',
                    'notes' => '',
                    'created_at' => null,
                    'updated_at' => null,
                ], $row['source'] ?? 'local');
            }
        }
        return $events;
    }

    private function collectMaintenanceNotifications(string $start, string $end): array
    {
        if (!tracs_table_exists($this->conn, 'tracs_notifications')) {
            return [];
        }
        $rows = $this->fetchAll(
            "SELECT id,notification_type,related_module,related_entity_id,title,message,related_url,
                    COALESCE(scheduled_at,created_at) AS event_at,created_at,COALESCE(sent_at,created_at) AS updated_at
             FROM tracs_notifications
             WHERE target_user_id=?
               AND COALESCE(scheduled_at,created_at) BETWEEN ? AND ?
               AND (notification_type LIKE '%maintenance%' OR title LIKE '%maintenance%' OR message LIKE '%maintenance%')",
            'iss',
            [$this->uid, $start . ' 00:00:00', $end . ' 23:59:59']
        );

        return array_map(function (array $row): array {
            $at = new DateTimeImmutable((string)$row['event_at'], $this->timezone);
            return $this->event([
                'id' => 'maintenance_' . $row['id'],
                'source' => 'notifications',
                'source_id' => (int)$row['id'],
                'type' => 'maintenance',
                'title' => (string)$row['title'],
                'date' => $at->format('Y-m-d'),
                'start_time' => $at->format('H:i'),
                'status' => 'maintenance',
                'priority' => 'high',
                'assignee' => $this->assignee($this->uid, $this->actor['display_name'] ?? $this->actor['email'] ?? 'Current user'),
                'notes' => (string)$row['message'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
                'meta' => [
                    'notification_type' => (string)$row['notification_type'],
                    'url' => (string)($row['related_url'] ?? ''),
                    'related_module' => (string)$row['related_module'],
                    'actions' => ['view_source'],
                ],
            ]);
        }, $rows);
    }

    private function collectDomainExpirations(string $start, string $end): array
    {
        if (!$this->hasPermission(['domains.view', 'domains.manage'])
            || !tracs_table_exists($this->conn, 'tracs_domains')) {
            return [];
        }
        [$scope, $scopeTypes, $scopeParams] = $this->ownerScope('d.user_id', 'u.division_id');
        $rows = $this->fetchAll(
            "SELECT d.id,d.user_id,d.domain,d.registrar,d.expires_at,d.auto_renew,d.notes,d.created_at,d.updated_at,
                    COALESCE(NULLIF(u.name,''),u.email) AS owner_name,u.division_id,div.name AS division_name
             FROM tracs_domains d
             LEFT JOIN tracs_users u ON u.id=d.user_id
             LEFT JOIN tracs_divisions div ON div.id=u.division_id
             WHERE d.expires_at BETWEEN ? AND ? {$scope}",
            'ss' . $scopeTypes,
            [$start, $end, ...$scopeParams]
        );
        return array_map(function (array $row): array {
            return $this->event([
                'id' => 'domain_' . $row['id'],
                'source' => 'domains',
                'source_id' => (int)$row['id'],
                'type' => 'reminder',
                'title' => 'Domain expiry · ' . $row['domain'],
                'date' => (string)$row['expires_at'],
                'status' => 'upcoming',
                'priority' => empty($row['auto_renew']) ? 'high' : 'medium',
                'assignee' => $this->assignee($row['user_id'], $row['owner_name']),
                'division' => $this->division($row['division_id'], $row['division_name']),
                'notes' => (string)($row['notes'] ?? ''),
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
                'meta' => [
                    'registrar' => (string)($row['registrar'] ?? ''),
                    'auto_renew' => (bool)$row['auto_renew'],
                    'url' => 'domains.php?domain_id=' . $row['id'],
                    'actions' => ['view_source'],
                ],
            ]);
        }, $rows);
    }

    private function collectBirthdays(string $start, string $end): array
    {
        if (!tracs_table_exists($this->conn, 'tracs_users')) {
            return [];
        }
        $column = null;
        foreach (['birth_date', 'date_of_birth', 'birthday'] as $candidate) {
            if (tracs_column_exists($this->conn, 'tracs_users', $candidate)) {
                $column = $candidate;
                break;
            }
        }
        if ($column === null) {
            return [];
        }

        $scope = $this->hasPermission(['users.view']) ? '' : ' AND u.id=?';
        $rows = $this->fetchAll(
            "SELECT u.id,u.name,u.email,u.division_id,u.`{$column}` AS birth_date,d.name AS division_name
             FROM tracs_users u
             LEFT JOIN tracs_divisions d ON d.id=u.division_id
             WHERE u.is_active=1 AND u.`{$column}` IS NOT NULL {$scope}",
            $scope === '' ? '' : 'i',
            $scope === '' ? [] : [$this->uid]
        );
        $events = [];
        $startDate = new DateTimeImmutable($start, $this->timezone);
        $endDate = new DateTimeImmutable($end, $this->timezone);
        foreach ($rows as $row) {
            $birth = new DateTimeImmutable((string)$row['birth_date'], $this->timezone);
            for ($year = (int)$startDate->format('Y'); $year <= (int)$endDate->format('Y'); $year++) {
                $date = sprintf('%04d-%s', $year, $birth->format('m-d'));
                if ($birth->format('m-d') === '02-29' && !checkdate(2, 29, $year)) {
                    $date = sprintf('%04d-02-28', $year);
                }
                if ($date < $start || $date > $end) {
                    continue;
                }
                $name = trim((string)$row['name']) ?: (string)$row['email'];
                $events[] = $this->event([
                    'id' => 'birthday_' . $row['id'] . '_' . $year,
                    'source' => 'users',
                    'source_id' => (int)$row['id'],
                    'type' => 'birthday',
                    'title' => $name . ' birthday',
                    'date' => $date,
                    'status' => 'upcoming',
                    'priority' => 'low',
                    'assignee' => $this->assignee($row['id'], $name),
                    'division' => $this->division($row['division_id'], $row['division_name']),
                    'meta' => ['url' => 'user-management.php?user_id=' . $row['id'], 'actions' => ['view_source']],
                ]);
            }
        }
        return $events;
    }

    private function collectInternshipDates(string $start, string $end): array
    {
        if (!$this->hasPermission(['users.view']) || !tracs_table_exists($this->conn, 'user_intern_profiles')) {
            return [];
        }
        $rows = $this->fetchAll(
            "SELECT p.id,p.user_id,p.internship_start_date,p.internship_end_date,p.internship_status,
                    COALESCE(NULLIF(u.name,''),u.email) AS owner_name,u.division_id,d.name AS division_name
             FROM user_intern_profiles p
             INNER JOIN tracs_users u ON u.id=p.user_id
             LEFT JOIN tracs_divisions d ON d.id=u.division_id
             WHERE p.internship_start_date BETWEEN ? AND ? OR p.internship_end_date BETWEEN ? AND ?",
            'ssss',
            [$start, $end, $start, $end]
        );
        $events = [];
        foreach ($rows as $row) {
            foreach (['start' => 'internship_start_date', 'end' => 'internship_end_date'] as $phase => $column) {
                $date = (string)$row[$column];
                if ($date < $start || $date > $end) {
                    continue;
                }
                $events[] = $this->event([
                    'id' => 'intern_' . $phase . '_' . $row['id'],
                    'source' => 'users',
                    'source_id' => (int)$row['user_id'],
                    'type' => 'task',
                    'title' => (string)$row['owner_name'] . ' internship ' . ($phase === 'start' ? 'starts' : 'ends'),
                    'date' => $date,
                    'status' => $this->status((string)$row['internship_status']),
                    'priority' => $phase === 'end' ? 'medium' : 'low',
                    'assignee' => $this->assignee($row['user_id'], $row['owner_name']),
                    'division' => $this->division($row['division_id'], $row['division_name']),
                    'meta' => ['url' => 'intern-management.php?user_id=' . $row['user_id'], 'actions' => ['view_source']],
                ]);
            }
        }
        return $events;
    }

    private function collectManualEvents(string $start, string $end): array
    {
        if (!tracs_table_exists($this->conn, 'calendar_events')) {
            return [];
        }
        $visibility = " AND (e.visibility='all' OR e.created_by=? OR e.assigned_user_id=?";
        $types = 'ssii';
        $params = [$end, $start, $this->uid, $this->uid];
        if ($this->divisionId > 0) {
            $visibility .= " OR (e.visibility='team' AND cu.division_id=?)";
            $types .= 'i';
            $params[] = $this->divisionId;
        }
        if ($this->isGlobalAdmin()) {
            $visibility .= ' OR 1=1';
        }
        $visibility .= ')';

        $rows = $this->fetchAll(
            "SELECT e.*,COALESCE(NULLIF(au.name,''),au.email) AS assignee_name,
                    COALESCE(au.division_id,cu.division_id) AS division_id,
                    COALESCE(d.name,cd.name) AS division_name,
                    COALESCE(NULLIF(cu.name,''),cu.email) AS creator_name
             FROM calendar_events e
             LEFT JOIN tracs_users au ON au.id=e.assigned_user_id
             LEFT JOIN tracs_users cu ON cu.id=e.created_by
             LEFT JOIN tracs_divisions d ON d.id=au.division_id
             LEFT JOIN tracs_divisions cd ON cd.id=cu.division_id
             WHERE e.deleted_at IS NULL
               AND e.event_date<=?
               AND (COALESCE(e.recurrence_rule,'none')<>'none' OR e.event_date>=?)
               {$visibility}",
            $types,
            $params
        );

        $events = [];
        foreach ($rows as $row) {
            foreach ($this->occurrences((string)$row['event_date'], (string)($row['recurrence_rule'] ?? 'none'), $start, $end) as $date) {
                $event = $this->event([
                    'id' => 'calendar_' . $row['id'] . '_' . $date,
                    'source' => 'calendar',
                    'source_id' => (int)$row['id'],
                    'type' => (string)$row['event_type'],
                    'title' => (string)$row['title'],
                    'date' => $date,
                    'start_time' => $this->timeValue($row['start_time']),
                    'end_time' => $this->timeValue($row['end_time']),
                    'status' => $this->status((string)$row['status']),
                    'priority' => 'medium',
                    'assignee' => $this->assignee($row['assigned_user_id'], $row['assignee_name'] ?: $row['creator_name']),
                    'division' => $this->division($row['division_id'], $row['division_name']),
                    'notes' => (string)($row['notes'] ?? ''),
                    'created_at' => $row['created_at'],
                    'updated_at' => $row['updated_at'],
                    'meta' => [
                        'source_module' => (string)($row['source_module'] ?? 'calendar'),
                        'visibility' => (string)$row['visibility'],
                        'reminder_minutes' => $row['reminder_minutes'] !== null ? (int)$row['reminder_minutes'] : null,
                        'recurrence' => (string)($row['recurrence_rule'] ?? 'none'),
                        'editable' => $this->isElevated(),
                        'actions' => ['view_source', 'edit', 'delete'],
                    ],
                ]);
                $events[] = $event;
            }
        }
        return $events;
    }

    private function event(array $event): array
    {
        return array_merge([
            'id' => '',
            'source' => '',
            'source_id' => null,
            'type' => 'reminder',
            'title' => 'Untitled event',
            'date' => '',
            'end_date' => null,
            'start_time' => null,
            'end_time' => null,
            'status' => 'active',
            'priority' => 'medium',
            'assignee' => null,
            'division' => null,
            'notes' => '',
            'created_at' => null,
            'updated_at' => null,
            'meta' => [],
        ], $event);
    }

    private function holidayEvent(array $row, string $source): array
    {
        $date = (string)$row['holiday_date'];
        return $this->event([
            'id' => !empty($row['id']) ? 'holiday_' . $row['id'] : 'holiday_' . substr(sha1($date . $row['holiday_name']), 0, 12),
            'source' => 'holidays',
            'source_id' => !empty($row['id']) ? (int)$row['id'] : null,
            'type' => 'holiday',
            'title' => (string)$row['holiday_name'],
            'date' => $date,
            'status' => 'holiday',
            'priority' => 'medium',
            'notes' => (string)($row['notes'] ?? ''),
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
            'meta' => [
                'holiday_type' => (string)($row['holiday_type'] ?? 'national_holiday'),
                'holiday_source' => $source,
                'actions' => [],
            ],
        ]);
    }

    private function localHolidayRows(int $year): array
    {
        $files = [
            __DIR__ . '/../../public/cache/holidays/indonesia-' . $year . '.json',
            __DIR__ . '/../../public/assets/data/indonesia-holidays-fallback.json',
        ];
        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }
            $json = json_decode((string)file_get_contents($file), true);
            $rows = $json['data'] ?? $json['years'][(string)$year] ?? [];
            if (!is_array($rows) || !$rows) {
                continue;
            }
            return array_values(array_filter(array_map(static function ($row) use ($file): ?array {
                if (!is_array($row)) {
                    return null;
                }
                $date = trim((string)($row['date'] ?? ''));
                $name = trim((string)($row['name'] ?? $row['description'] ?? ''));
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $name === '') {
                    return null;
                }
                return [
                    'date' => $date,
                    'name' => $name,
                    'is_national_holiday' => array_key_exists('is_national_holiday', $row)
                        ? (bool)$row['is_national_holiday']
                        : (($row['type'] ?? 'holiday') !== 'leave'),
                    'source' => str_contains($file, '/cache/') ? 'cache' : 'static-fallback',
                ];
            }, $rows)));
        }
        return [];
    }

    private function occurrences(string $firstDate, string $rule, string $start, string $end): array
    {
        if ($rule === '' || $rule === 'none') {
            return $firstDate >= $start && $firstDate <= $end ? [$firstDate] : [];
        }
        $interval = match ($rule) {
            'daily' => '+1 day',
            'weekly' => '+1 week',
            'monthly' => '+1 month',
            'yearly' => '+1 year',
            default => null,
        };
        if ($interval === null) {
            return [];
        }
        $cursor = new DateTimeImmutable($firstDate, $this->timezone);
        $limit = new DateTimeImmutable($end, $this->timezone);
        $dates = [];
        $guard = 0;
        while ($cursor <= $limit && $guard < 800) {
            $key = $cursor->format('Y-m-d');
            if ($key >= $start) {
                $dates[] = $key;
            }
            $cursor = $cursor->modify($interval);
            $guard++;
        }
        return $dates;
    }

    private function ownerScope(string $ownerColumn, string $divisionColumn): array
    {
        if (in_array($this->role, ['super_admin', 'admin'], true)) {
            return ['', '', []];
        }
        if ($this->role === 'supervisor' && $this->divisionId > 0) {
            return [" AND ({$ownerColumn}=? OR {$divisionColumn}=?)", 'ii', [$this->uid, $this->divisionId]];
        }
        return [" AND {$ownerColumn}=?", 'i', [$this->uid]];
    }

    private function hasPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (tracs_user_can($this->conn, $permission, $this->uid)) {
                return true;
            }
        }
        return false;
    }

    private function isElevated(): bool
    {
        return in_array($this->role, ['super_admin', 'admin', 'supervisor'], true);
    }

    private function isGlobalAdmin(): bool
    {
        return in_array($this->role, ['super_admin', 'admin'], true);
    }

    private function fetchAll(string $sql, string $types = '', array $params = []): array
    {
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Unable to prepare calendar query.');
        }
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        if (!$stmt->execute()) {
            $message = $stmt->error;
            $stmt->close();
            throw new RuntimeException($message ?: 'Unable to execute calendar query.');
        }
        $result = $stmt->get_result();
        $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $rows;
    }

    private function status(string $status): string
    {
        return match (strtolower($status)) {
            'completed', 'resolved', 'implemented' => 'done',
            'cancelled', 'rejected', 'no_show', 'replaced' => 'cancelled',
            'on_hold', 'blocked', 'pending' => 'on_hold',
            'upcoming', 'assigned', 'confirmed' => 'upcoming',
            'maintenance' => 'maintenance',
            'holiday' => 'holiday',
            default => 'active',
        };
    }

    private function assignee(mixed $id, mixed $name): ?array
    {
        $name = trim((string)$name);
        if ((int)$id <= 0 && $name === '') {
            return null;
        }
        return ['id' => (int)$id > 0 ? (int)$id : null, 'name' => $name ?: 'Unassigned'];
    }

    private function division(mixed $id, mixed $name): ?array
    {
        $name = trim((string)$name);
        if ((int)$id <= 0 && $name === '') {
            return null;
        }
        return ['id' => (int)$id > 0 ? (int)$id : null, 'name' => $name ?: 'Unassigned'];
    }

    private function timeValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return substr((string)$value, 0, 5);
    }
}
