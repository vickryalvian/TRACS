<?php

require_once __DIR__ . '/../../core/user_management.php';
require_once __DIR__ . '/../../core/notifications.php';

final class ShiftValidationException extends InvalidArgumentException {
    public array $errors;

    public function __construct(string $message, array $errors = []) {
        parent::__construct($message);
        $this->errors = $errors;
    }
}

final class ShiftingAssignmentService {
    private mysqli $conn;
    private int $actorId;
    private array $actor;
    private DateTimeZone $timezone;

    private const COUNTED_STATUSES = ['assigned', 'confirmed', 'active', 'completed'];
    private const ASSIGNMENT_STATUSES = ['assigned', 'confirmed', 'active', 'completed', 'cancelled', 'no_show', 'replaced'];
    private const ASSIGNMENT_TYPES = [
        'regular_shift', 'middle_shift', 'lembur', 'standby', 'replacement_shift',
        'holiday_coverage', 'emergency_coverage', 'training', 'off_leave',
    ];
    private const ASSIGNMENT_SOURCES = ['manual', 'monthly_template', 'copy', 'replacement'];

    public function __construct(mysqli $conn, int $actorId) {
        $this->conn = $conn;
        $this->actorId = $actorId;
        $this->actor = tracs_get_user_by_id($conn, $actorId) ?: [];
        $this->timezone = new DateTimeZone('Asia/Jakarta');
    }

    public function canManage(): bool {
        return tracs_user_can($this->conn, 'shifts.manage', $this->actorId);
    }

    public function canManageSettings(): bool {
        return tracs_user_can($this->conn, 'shifts.settings', $this->actorId);
    }

    public function canManageMonthlyTemplates(): bool {
        return $this->canManageSettings()
            || tracs_user_can($this->conn, 'shifts.monthly_templates', $this->actorId);
    }

    public function canExport(): bool {
        return tracs_user_can($this->conn, 'shifts.export', $this->actorId);
    }

    public function scopeRole(): string {
        return (string)($this->actor['role_slug'] ?? 'agent');
    }

    public function normalizeRange(?string $start, ?string $end): array {
        $today = new DateTimeImmutable('today', $this->timezone);
        $defaultStart = $today->modify('monday this week');
        $startDate = $this->parseDate($start) ?: $defaultStart;
        $endDate = $this->parseDate($end) ?: $startDate->modify('+6 days');
        if ($endDate < $startDate) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }
        if ($startDate->diff($endDate)->days > 92) {
            $endDate = $startDate->modify('+92 days');
        }
        return [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')];
    }

    public function getPageData(array $filters = []): array {
        [$start, $end] = $this->normalizeRange($filters['start'] ?? null, $filters['end'] ?? null);
        $filters['start'] = $start;
        $filters['end'] = $end;
        $assignments = $this->getAssignments($filters);
        $agents = $this->getAgents();
        $settings = $this->getSettings(isset($filters['division_id']) ? (int)$filters['division_id'] : null);
        $holidays = $this->getHolidays($start, $end);
        $today = new DateTimeImmutable('today', $this->timezone);
        $holidaySearchStart = min($start, $today->format('Y-m-d'));
        $holidaySearchEnd = max($end, $today->modify('+45 days')->format('Y-m-d'));
        $summaryHolidays = $this->getHolidays($holidaySearchStart, $holidaySearchEnd);
        $recap = $this->calculateWorkloadRecap($assignments, $agents, $settings, $start, $end);
        $jumpWarnings = $this->getJumpShiftWarnings($assignments, $settings);
        $conflicts = $this->getConflictWarnings($assignments);
        $coverage = $this->detectCoverageGaps($start, $end, $assignments, $filters, $holidays);
        $summary = $this->buildSummary($assignments, $recap, $jumpWarnings, $conflicts, $coverage, $summaryHolidays, $start, $end);
        $summary['today_coverage'] = $this->buildTodayCoverage(
            $assignments,
            $filters,
            $summaryHolidays,
            $settings
        );
        if (!empty($summary['upcoming_holiday']['holiday_date'])) {
            $summary['upcoming_holiday'] = array_merge(
                $summary['upcoming_holiday'],
                $this->getHolidayCoverageInsight((string)$summary['upcoming_holiday']['holiday_date'], $settings)
            );
        }

        return [
            'range' => ['start' => $start, 'end' => $end],
            'assignments' => $assignments,
            'agents' => $agents,
            'divisions' => $this->getDivisions(),
            'templates' => $this->getTemplates(),
            'monthly_templates' => $this->getMonthlyTemplates(),
            'assignment_types' => $this->getAssignmentTypes(),
            'settings' => $settings,
            'coverage_rules' => $this->getCoverageRules(),
            'holidays' => $holidays,
            'recap' => $recap,
            'warnings' => [
                'jumpshift' => $jumpWarnings,
                'conflicts' => $conflicts,
                'coverage' => $coverage,
            ],
            'dismissed_warning_keys' => $this->getDismissedWarningKeys(),
            'summary' => $summary,
            'permissions' => [
                'manage' => $this->canManage(),
                'settings' => $this->canManageSettings(),
                'monthly_templates' => $this->canManageMonthlyTemplates(),
                'export' => $this->canExport(),
                'scope_role' => $this->scopeRole(),
            ],
        ];
    }

    public function getAssignments(array $filters): array {
        [$start, $end] = $this->normalizeRange($filters['start'] ?? null, $filters['end'] ?? null);
        $where = [
            'a.start_datetime < DATE_ADD(?, INTERVAL 1 DAY)',
            'a.end_datetime >= ?',
        ];
        $params = [$end, $start];
        $types = 'ss';
        $this->appendScope($where, $params, $types, 'a');

        foreach (['division_id' => 'a.division_id', 'user_id' => 'a.user_id'] as $key => $column) {
            $value = (int)($filters[$key] ?? 0);
            if ($value > 0) {
                $where[] = "{$column} = ?";
                $params[] = $value;
                $types .= 'i';
            }
        }
        if (!empty($filters['assignment_type']) && in_array($filters['assignment_type'], self::ASSIGNMENT_TYPES, true)) {
            $where[] = 'a.assignment_type = ?';
            $params[] = $filters['assignment_type'];
            $types .= 's';
        }
        if (!empty($filters['status']) && in_array($filters['status'], self::ASSIGNMENT_STATUSES, true)) {
            $where[] = 'a.status = ?';
            $params[] = $filters['status'];
            $types .= 's';
        }
        if (!empty($filters['holiday_only'])) {
            $where[] = 'a.is_holiday_assignment = 1';
        }
        $query = trim((string)($filters['q'] ?? ''));
        if ($query !== '') {
            $where[] = "(COALESCE(NULLIF(u.name,''), u.email) LIKE ? OR COALESCE(t.shift_name,'') LIKE ? OR a.assignment_type LIKE ? OR COALESCE(a.notes,'') LIKE ?)";
            $like = '%' . $query . '%';
            array_push($params, $like, $like, $like, $like);
            $types .= 'ssss';
        }

        $sql = "
            SELECT a.*, COALESCE(NULLIF(u.name,''), SUBSTRING_INDEX(u.email,'@',1)) AS agent_name,
                   u.email AS agent_email, COALESCE(d.name,'Unassigned') AS division_name,
                   COALESCE(t.shift_name, 'Custom Shift') AS shift_name,
                   COALESCE(t.color_label, at.color_label, '#4f46e5') AS color_label,
                   COALESCE(at.type_name, REPLACE(a.assignment_type, '_', ' ')) AS assignment_type_name,
                   COALESCE(av.availability_status, 'available') AS availability_status
            FROM shift_assignments a
            JOIN tracs_users u ON u.id = a.user_id
            LEFT JOIN tracs_divisions d ON d.id = a.division_id
            LEFT JOIN shift_templates t ON t.id = a.shift_template_id
            LEFT JOIN shift_assignment_types at ON at.type_slug = a.assignment_type
            LEFT JOIN shift_agent_availability av ON av.user_id = a.user_id AND av.availability_date = a.assignment_date
            WHERE " . implode(' AND ', $where) . "
            ORDER BY a.start_datetime ASC, agent_name ASC, a.id ASC
        ";
        $rows = $this->fetchAll($sql, $types, $params);
        foreach ($rows as &$row) {
            $row['id'] = (int)$row['id'];
            $row['user_id'] = (int)$row['user_id'];
            $row['division_id'] = (int)($row['division_id'] ?? 0);
            $row['shift_template_id'] = (int)($row['shift_template_id'] ?? 0);
            $row['break_minutes'] = (int)$row['break_minutes'];
            $row['calculated_duration_minutes'] = (int)$row['calculated_duration_minutes'];
            $row['is_overtime'] = (bool)$row['is_overtime'];
            $row['is_holiday_assignment'] = (bool)$row['is_holiday_assignment'];
            $row['is_manual_duration_override'] = (bool)$row['is_manual_duration_override'];
            $row['is_cross_day'] = array_key_exists('is_cross_day', $row)
                ? (bool)$row['is_cross_day']
                : substr((string)$row['end_datetime'], 0, 10) > (string)$row['assignment_date'];
            $row['source'] = in_array($row['source'] ?? '', self::ASSIGNMENT_SOURCES, true)
                ? $row['source']
                : 'manual';
            $row['monthly_template_id'] = (int)($row['monthly_template_id'] ?? 0);
        }
        unset($row);
        return $rows;
    }

    public function getAssignment(int $id): ?array {
        $record = $this->getScopedAssignmentRecord($id);
        if (!$record) return null;

        $rows = $this->getAssignments([
            'start' => substr((string)$record['start_datetime'], 0, 10),
            'end' => substr((string)$record['end_datetime'], 0, 10),
        ]);
        foreach ($rows as $row) {
            if ((int)$row['id'] === $id) return $row;
        }
        return null;
    }

    public function getAssignmentHistory(int $id): array {
        $assignment = $this->getAssignment($id);
        if (!$assignment) {
            throw new RuntimeException('Assignment not found.');
        }
        $history = [];
        if ($this->tableExists('tracs_activity_logs')) {
            $history = $this->fetchAll("
                SELECT l.id, l.action, l.description, l.created_at,
                       COALESCE(NULLIF(u.name,''), u.email, 'System') AS creator_name
                FROM tracs_activity_logs l
                LEFT JOIN tracs_users u ON u.id = l.user_id
                WHERE l.reference_id = ? AND l.module = 'Shifting Assignment'
                ORDER BY l.created_at DESC, l.id DESC
                LIMIT 100
            ", 'i', [$id]);
        }
        return ['assignment' => $assignment, 'history' => $history];
    }

    public function saveAssignment(array $input): array {
        $this->requireManage();
        $id = max(0, (int)($input['id'] ?? 0));
        $existing = $id > 0 ? $this->getScopedAssignmentRecord($id) : null;
        if ($id > 0 && !$existing) throw new RuntimeException('Assignment not found.');
        $userId = (int)($input['user_id'] ?? 0);
        $user = $this->getScopedAgent($userId);
        if (!$user) $this->validationError('user_id', 'Select an active agent in your scheduling scope.');

        try {
            $date = $this->validDate((string)($input['assignment_date'] ?? ''));
        } catch (InvalidArgumentException) {
            $this->validationError('assignment_date', 'Enter a valid assignment date.');
        }
        try {
            $startTime = $this->validTime((string)($input['start_time'] ?? ''));
        } catch (InvalidArgumentException) {
            $this->validationError('start_time', 'Enter a valid start time.');
        }
        try {
            $endTime = $this->validTime((string)($input['end_time'] ?? ''));
        } catch (InvalidArgumentException) {
            $this->validationError('end_time', 'Enter a valid end time.');
        }
        if ($startTime === $endTime) {
            $this->validationError('end_time', 'Shift duration cannot be zero.');
        }
        $start = new DateTimeImmutable($date . ' ' . $startTime, $this->timezone);
        $end = new DateTimeImmutable($date . ' ' . $endTime, $this->timezone);
        $isCrossDay = $end < $start;
        if ($isCrossDay) $end = $end->modify('+1 day');

        $breakRaw = filter_var($input['break_minutes'] ?? 0, FILTER_VALIDATE_INT);
        if ($breakRaw === false || $breakRaw < 0) {
            $this->validationError('break_minutes', 'Break minutes must be zero or greater.');
        }
        $breakMinutes = min(720, $breakRaw);
        $grossDuration = (int)floor(($end->getTimestamp() - $start->getTimestamp()) / 60);
        if ($breakMinutes >= $grossDuration) {
            $this->validationError('break_minutes', 'Break minutes must be less than the shift duration.');
        }
        $settings = $this->getSettings((int)($user['division_id'] ?? 0));
        $duration = $this->calculateShiftDuration($start, $end, $breakMinutes);
        try {
            $this->validateDuration($duration, $settings);
        } catch (InvalidArgumentException $e) {
            $this->validationError('end_time', $e->getMessage());
        }

        $type = (string)($input['assignment_type'] ?? 'regular_shift');
        if (!in_array($type, self::ASSIGNMENT_TYPES, true)) throw new InvalidArgumentException('Invalid assignment type.');
        $status = (string)($input['status'] ?? 'assigned');
        if (!in_array($status, self::ASSIGNMENT_STATUSES, true)) throw new InvalidArgumentException('Invalid assignment status.');
        if ($id === 0 && in_array($status, ['active', 'completed'], true)) {
            throw new InvalidArgumentException('New assignments cannot be created as active or completed.');
        }
        $templateId = max(0, (int)($input['shift_template_id'] ?? 0)) ?: null;
        if ($templateId !== null) {
            $template = $this->fetchOne('SELECT id FROM shift_templates WHERE id=? AND is_active=1 LIMIT 1', 'i', [$templateId]);
            if (!$template) throw new InvalidArgumentException('Select an active shift pattern.');
        }
        $notes = $this->cleanText($input['notes'] ?? '', 3000);
        $holiday = $this->getHolidayForDate($date);
        $isHolidayType = in_array($type, ['holiday_coverage', 'lembur', 'standby'], true);
        $isHoliday = $holiday !== null && $isHolidayType;
        $isOvertime = $this->assignmentTypeFlags($type)['count_as_overtime'];
        $approvalStatus = in_array($type, ['lembur', 'holiday_coverage', 'emergency_coverage'], true)
            ? (in_array($this->scopeRole(), ['super_admin', 'admin'], true) ? 'approved' : 'pending')
            : 'not_required';

        $conflicts = $this->detectShiftConflicts($userId, $start, $end, $id);
        if ($conflicts) {
            throw new DomainException('[CONFLICT] Agent already has an overlapping assignment.');
        }
        $availability = $this->getAvailability($userId, $date);
        if ($availability && !in_array($availability['availability_status'], ['available', 'training'], true)) {
            throw new DomainException('[AVAILABILITY] Agent is marked ' . str_replace('_', ' ', $availability['availability_status']) . ' on this date.');
        }

        $divisionId = (int)($user['division_id'] ?? 0) ?: null;
        $approvedBy = $approvalStatus === 'approved' ? $this->actorId : null;
        $approvedAt = $approvalStatus === 'approved' ? date('Y-m-d H:i:s') : null;
        $manualOverride = !empty($input['is_manual_duration_override']) ? 1 : 0;
        $source = in_array($input['source'] ?? '', self::ASSIGNMENT_SOURCES, true)
            ? (string)$input['source']
            : (in_array($existing['source'] ?? '', self::ASSIGNMENT_SOURCES, true) ? (string)$existing['source'] : 'manual');
        $monthlyTemplateId = max(0, (int)($input['monthly_template_id'] ?? ($existing['monthly_template_id'] ?? 0))) ?: null;

        $this->conn->begin_transaction();
        try {
            if ($id > 0) {
                $stmt = $this->conn->prepare("
                    UPDATE shift_assignments
                    SET user_id=?, division_id=?, shift_template_id=?, assignment_date=?, start_datetime=?, end_datetime=?,
                        break_minutes=?, calculated_duration_minutes=?, assignment_type=?, status=?, is_overtime=?,
                        is_holiday_assignment=?, is_manual_duration_override=?, approval_status=?, approved_by=?,
                        approved_at=?, notes=?, updated_by=?, updated_at=NOW()
                    WHERE id=?
                ");
                if (!$stmt) throw new RuntimeException('Unable to prepare assignment update.');
                $startSql = $start->format('Y-m-d H:i:s');
                $endSql = $end->format('Y-m-d H:i:s');
                $holidayFlag = $isHoliday ? 1 : 0;
                $overtimeFlag = $isOvertime ? 1 : 0;
                $stmt->bind_param(
                    'iiisssiissiiisissii',
                    $userId, $divisionId, $templateId, $date, $startSql, $endSql,
                    $breakMinutes, $duration, $type, $status, $overtimeFlag,
                    $holidayFlag, $manualOverride, $approvalStatus, $approvedBy,
                    $approvedAt, $notes, $this->actorId, $id
                );
                if (!$stmt->execute()) throw new RuntimeException('Unable to update assignment.');
                $stmt->close();
            } else {
                $stmt = $this->conn->prepare("
                    INSERT INTO shift_assignments
                      (user_id, division_id, shift_template_id, assignment_date, start_datetime, end_datetime,
                       break_minutes, calculated_duration_minutes, assignment_type, status, is_overtime,
                       is_holiday_assignment, is_manual_duration_override, approval_status, approved_by,
                       approved_at, notes, created_by, updated_by, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                if (!$stmt) throw new RuntimeException('Unable to prepare assignment.');
                $startSql = $start->format('Y-m-d H:i:s');
                $endSql = $end->format('Y-m-d H:i:s');
                $holidayFlag = $isHoliday ? 1 : 0;
                $overtimeFlag = $isOvertime ? 1 : 0;
                $stmt->bind_param(
                    'iiisssiissiiisissii',
                    $userId, $divisionId, $templateId, $date, $startSql, $endSql,
                    $breakMinutes, $duration, $type, $status, $overtimeFlag,
                    $holidayFlag, $manualOverride, $approvalStatus, $approvedBy,
                    $approvedAt, $notes, $this->actorId, $this->actorId
                );
                if (!$stmt->execute()) throw new RuntimeException('Unable to create assignment.');
                $id = (int)$stmt->insert_id;
                $stmt->close();
            }

            if ($this->columnExists('shift_assignments', 'source')) {
                $stmt = $this->conn->prepare('UPDATE shift_assignments SET source=?, monthly_template_id=? WHERE id=?');
                if (!$stmt) throw new RuntimeException('Unable to save assignment source.');
                $stmt->bind_param('sii', $source, $monthlyTemplateId, $id);
                if (!$stmt->execute()) throw new RuntimeException('Unable to save assignment source.');
                $stmt->close();
            }
            if ($this->columnExists('shift_assignments', 'is_cross_day')) {
                $crossDayFlag = $isCrossDay ? 1 : 0;
                $stmt = $this->conn->prepare('UPDATE shift_assignments SET is_cross_day=? WHERE id=?');
                if (!$stmt) throw new RuntimeException('Unable to save cross-day state.');
                $stmt->bind_param('ii', $crossDayFlag, $id);
                if (!$stmt->execute()) throw new RuntimeException('Unable to save cross-day state.');
                $stmt->close();
            }

            if ($isHoliday && $holiday) {
                $holidayId = $this->ensureHolidayRecord($holiday);
                $coverageStatus = in_array($status, ['completed'], true) ? 'completed' : (in_array($status, ['confirmed', 'active'], true) ? 'confirmed' : ($status === 'cancelled' ? 'cancelled' : 'assigned'));
                $stmt = $this->conn->prepare("
                    INSERT INTO holiday_coverage_assignments
                      (holiday_id, user_id, shift_assignment_id, assignment_type, start_time, end_time, notes, status, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE holiday_id=VALUES(holiday_id), user_id=VALUES(user_id),
                      assignment_type=VALUES(assignment_type), start_time=VALUES(start_time), end_time=VALUES(end_time),
                      notes=VALUES(notes), status=VALUES(status), updated_at=NOW()
                ");
                if ($stmt) {
                    $startClock = $start->format('H:i:s');
                    $endClock = $end->format('H:i:s');
                    $stmt->bind_param('iiisssss', $holidayId, $userId, $id, $type, $startClock, $endClock, $notes, $coverageStatus);
                    $stmt->execute();
                    $stmt->close();
                }
            } else {
                $stmt = $this->conn->prepare('DELETE FROM holiday_coverage_assignments WHERE shift_assignment_id=?');
                if ($stmt) {
                    $stmt->bind_param('i', $id);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            $this->conn->commit();
        } catch (Throwable $e) {
            $this->conn->rollback();
            throw $e;
        }

        $jumpWarnings = $this->detectJumpShift($userId, $start, $end, $id, $settings);
        if (empty($input['suppress_notification'])) {
            $this->notifyAssignment($id, $userId, $type, $start, $end, $jumpWarnings);
        }
        $this->reopenDismissedWarnings($userId, $date);
        $saved = $this->getScopedAssignmentRecord($id);
        $this->writeAssignmentAudit(
            $id,
            $existing ? 'updated' : 'created',
            $existing,
            $saved,
            $existing ? 'Assignment updated.' : 'Assignment created.'
        );
        return [
            'id' => $id,
            'warnings' => $jumpWarnings,
            'duration_minutes' => $duration,
            'is_cross_day' => $isCrossDay,
            'source' => $source,
        ];
    }

    public function confirmAssignment(int $id): void {
        $this->requireManage();
        $record = $this->getScopedAssignmentRecord($id);
        if (!$record) throw new RuntimeException('Assignment not found.');
        if (in_array($record['status'], ['completed', 'cancelled', 'no_show', 'replaced'], true)) {
            throw new DomainException('This assignment can no longer be confirmed.');
        }
        $stmt = $this->conn->prepare("
            UPDATE shift_assignments
            SET status='confirmed', approval_status=CASE WHEN approval_status='pending' THEN 'approved' ELSE approval_status END,
                approved_by=CASE WHEN approval_status='pending' THEN ? ELSE approved_by END,
                approved_at=CASE WHEN approval_status='pending' THEN NOW() ELSE approved_at END,
                updated_by=?, updated_at=NOW()
            WHERE id=?
        ");
        if (!$stmt) throw new RuntimeException('Unable to prepare assignment confirmation.');
        $stmt->bind_param('iii', $this->actorId, $this->actorId, $id);
        if (!$stmt->execute()) throw new RuntimeException('Unable to confirm assignment.');
        $stmt->close();
        $this->writeAssignmentAudit($id, 'approved', $record, $this->getScopedAssignmentRecord($id), 'Assignment confirmed.');
    }

    public function dismissWarning(array $input): void {
        $this->requireManage();
        if (!$this->tableExists('shift_warnings') || !$this->columnExists('shift_warnings', 'warning_key')) {
            throw new RuntimeException('Warning dismissal migration has not been applied.');
        }
        $key = $this->cleanText($input['warning_key'] ?? '', 190);
        if ($key === '' || !preg_match('/^[a-z0-9-]+:[a-z0-9]+$/', $key)) {
            throw new InvalidArgumentException('Invalid warning reference.');
        }
        $type = str_replace('-', '_', $this->cleanText($input['warning_type'] ?? 'duration', 80));
        $allowedTypes = [
            'conflict','jumpshift','overtime','under_target','over_target','coverage_gap',
            'holiday_missing_coverage','availability','duration','rest_day_violation',
            'duplicate_assignment','overlapping_assignment','agent_without_schedule',
            'approval_pending','last_minute_change','cross_day_shift_risk',
        ];
        if (!in_array($type, $allowedTypes, true)) $type = 'duration';
        $assignmentId = max(0, (int)($input['assignment_id'] ?? 0)) ?: null;
        if ($assignmentId && !$this->getScopedAssignmentRecord($assignmentId)) $assignmentId = null;
        $userId = max(0, (int)($input['user_id'] ?? 0)) ?: null;
        $affectedDate = $this->parseDate((string)($input['affected_date'] ?? ''))?->format('Y-m-d');
        $message = $this->cleanText($input['message'] ?? 'Warning dismissed.', 500);
        $note = $this->cleanText($input['note'] ?? 'Dismissed from Smart Warnings.', 500);

        $stmt = $this->conn->prepare("
            UPDATE shift_warnings
            SET shift_assignment_id=?,user_id=?,affected_date=?,warning_type=?,warning_message=?,
                is_resolved=1,resolved_by=?,resolved_at=NOW(),resolution_note=?,updated_at=NOW()
            WHERE warning_key=?
        ");
        if (!$stmt) throw new RuntimeException('Unable to prepare warning dismissal.');
        $stmt->bind_param('iisssiss', $assignmentId, $userId, $affectedDate, $type, $message, $this->actorId, $note, $key);
        if (!$stmt->execute()) throw new RuntimeException('Unable to dismiss warning.');
        $updated = $stmt->affected_rows;
        $stmt->close();
        if ($updated === 0) {
            $stmt = $this->conn->prepare("
                INSERT INTO shift_warnings
                  (warning_key,shift_assignment_id,user_id,affected_date,warning_type,warning_message,severity,
                   is_resolved,resolved_by,resolved_at,resolution_note,created_at,updated_at)
                VALUES (?,?,?,?,?,?,'warning',1,?,NOW(),?,NOW(),NOW())
            ");
            if (!$stmt) throw new RuntimeException('Unable to prepare warning dismissal.');
            $stmt->bind_param('siisssis', $key, $assignmentId, $userId, $affectedDate, $type, $message, $this->actorId, $note);
            if (!$stmt->execute()) throw new RuntimeException('Unable to dismiss warning.');
            $stmt->close();
        }
        $this->writeAssignmentAudit($assignmentId, 'warning_dismissed', null, [
            'warning_key' => $key,
            'warning_type' => $type,
            'affected_date' => $affectedDate,
        ], $note);
    }

    public function resizeAssignment(int $id, string $startValue, string $endValue): array {
        $this->requireManage();
        $record = $this->getScopedAssignmentRecord($id);
        if (!$record) throw new RuntimeException('Assignment not found.');
        $start = $this->parseDateTime($startValue);
        $end = $this->parseDateTime($endValue);
        if (!$start || !$end || $end <= $start) throw new InvalidArgumentException('Invalid resize range.');
        $settings = $this->getSettings((int)($record['division_id'] ?? 0));
        $snap = max(5, (int)$settings['timeline_snap_minutes']);
        if (((int)$start->format('i') % $snap) !== 0 || ((int)$end->format('i') % $snap) !== 0) {
            throw new InvalidArgumentException("Shift time must snap to {$snap}-minute intervals.");
        }
        $duration = $this->calculateShiftDuration($start, $end, (int)$record['break_minutes']);
        $this->validateDuration($duration, $settings);
        if ($this->detectShiftConflicts((int)$record['user_id'], $start, $end, $id)) {
            throw new DomainException('Resize rejected because it overlaps another assignment.');
        }

        $stmt = $this->conn->prepare("
            UPDATE shift_assignments
            SET assignment_date=?, start_datetime=?, end_datetime=?, calculated_duration_minutes=?,
                is_manual_duration_override=1, updated_by=?, updated_at=NOW()
            WHERE id=?
        ");
        if (!$stmt) throw new RuntimeException('Unable to prepare resize update.');
        $date = $start->format('Y-m-d');
        $startSql = $start->format('Y-m-d H:i:s');
        $endSql = $end->format('Y-m-d H:i:s');
        $stmt->bind_param('sssiii', $date, $startSql, $endSql, $duration, $this->actorId, $id);
        if (!$stmt->execute()) throw new RuntimeException('Unable to resize assignment.');
        $stmt->close();
        return [
            'id' => $id,
            'duration_minutes' => $duration,
            'warnings' => $this->detectJumpShift((int)$record['user_id'], $start, $end, $id, $settings),
        ];
    }

    public function updateAssignmentStatus(int $id, string $status): void {
        $this->requireManage();
        if (!in_array($status, self::ASSIGNMENT_STATUSES, true)) throw new InvalidArgumentException('Invalid assignment status.');
        if (!$this->getScopedAssignmentRecord($id)) throw new RuntimeException('Assignment not found.');
        $stmt = $this->conn->prepare('UPDATE shift_assignments SET status=?, updated_by=?, updated_at=NOW() WHERE id=?');
        if (!$stmt) throw new RuntimeException('Unable to prepare status update.');
        $stmt->bind_param('sii', $status, $this->actorId, $id);
        if (!$stmt->execute()) throw new RuntimeException('Unable to update assignment.');
        $stmt->close();
        $coverageStatus = $status === 'completed' ? 'completed' : ($status === 'cancelled' ? 'cancelled' : (in_array($status, ['confirmed', 'active'], true) ? 'confirmed' : 'assigned'));
        $stmt = $this->conn->prepare('UPDATE holiday_coverage_assignments SET status=?, updated_at=NOW() WHERE shift_assignment_id=?');
        if ($stmt) {
            $stmt->bind_param('si', $coverageStatus, $id);
            $stmt->execute();
            $stmt->close();
        }
    }

    public function copyLastWeek(string $startDate, ?int $divisionId = null): int {
        $this->requireManage();
        $targetStart = $this->parseDate($startDate);
        if (!$targetStart) throw new InvalidArgumentException('Invalid target week.');
        $targetStart = $targetStart->modify('monday this week');
        $sourceStart = $targetStart->modify('-7 days');
        $sourceEnd = $sourceStart->modify('+6 days');
        $rows = $this->getAssignments([
            'start' => $sourceStart->format('Y-m-d'),
            'end' => $sourceEnd->format('Y-m-d'),
            'division_id' => $divisionId,
        ]);
        $count = 0;
        foreach ($rows as $row) {
            if (!in_array($row['status'], self::COUNTED_STATUSES, true)) continue;
            $newStart = (new DateTimeImmutable($row['start_datetime'], $this->timezone))->modify('+7 days');
            $newEnd = (new DateTimeImmutable($row['end_datetime'], $this->timezone))->modify('+7 days');
            if ($this->detectShiftConflicts((int)$row['user_id'], $newStart, $newEnd, 0)) continue;
            $payload = [
                'user_id' => $row['user_id'],
                'shift_template_id' => $row['shift_template_id'],
                'assignment_date' => $newStart->format('Y-m-d'),
                'start_time' => $newStart->format('H:i'),
                'end_time' => $newEnd->format('H:i'),
                'break_minutes' => $row['break_minutes'],
                'assignment_type' => $row['assignment_type'],
                'status' => 'assigned',
                'notes' => trim((string)$row['notes'] . ' Copied from last week.'),
                'is_manual_duration_override' => $row['is_manual_duration_override'],
                'source' => 'copy',
            ];
            $this->saveAssignment($payload);
            $count++;
        }
        return $count;
    }

    public function replaceAgent(int $assignmentId, int $newUserId, ?string $notes = null): int {
        $this->requireManage();
        $existing = $this->getScopedAssignmentRecord($assignmentId);
        if (!$existing) throw new RuntimeException('Assignment not found.');
        $start = new DateTimeImmutable($existing['start_datetime'], $this->timezone);
        $end = new DateTimeImmutable($existing['end_datetime'], $this->timezone);
        $this->updateAssignmentStatus($assignmentId, 'replaced');
        $result = $this->saveAssignment([
            'user_id' => $newUserId,
            'shift_template_id' => $existing['shift_template_id'],
            'assignment_date' => $start->format('Y-m-d'),
            'start_time' => $start->format('H:i'),
            'end_time' => $end->format('H:i'),
            'break_minutes' => $existing['break_minutes'],
            'assignment_type' => 'replacement_shift',
            'status' => 'assigned',
            'notes' => $this->cleanText(($notes ?: '') . " Replaces assignment #{$assignmentId}.", 3000),
            'is_manual_duration_override' => 1,
            'source' => 'replacement',
        ]);
        return (int)$result['id'];
    }

    public function saveTemplate(array $input): int {
        $this->requireSettings();
        $id = max(0, (int)($input['id'] ?? 0));
        $name = $this->cleanText($input['shift_name'] ?? '', 120);
        if ($name === '') throw new InvalidArgumentException('Template name is required.');
        $start = $this->validTime((string)($input['start_time'] ?? ''));
        $end = $this->validTime((string)($input['end_time'] ?? ''));
        $startDt = new DateTimeImmutable('2026-01-01 ' . $start, $this->timezone);
        $endDt = new DateTimeImmutable('2026-01-01 ' . $end, $this->timezone);
        $cross = $endDt <= $startDt;
        if ($cross) $endDt = $endDt->modify('+1 day');
        $break = max(0, min(720, (int)($input['default_break_minutes'] ?? 0)));
        $duration = $this->calculateShiftDuration($startDt, $endDt, $break);
        if ($duration < 15 || $duration > 1440) throw new InvalidArgumentException('Template duration must be between 15 minutes and 24 hours.');
        $color = $this->validColor((string)($input['color_label'] ?? '#4f46e5'));
        $type = in_array($input['default_assignment_type'] ?? '', self::ASSIGNMENT_TYPES, true) ? $input['default_assignment_type'] : 'regular_shift';
        $notes = $this->cleanText($input['notes'] ?? '', 2000);
        $active = empty($input['is_active']) ? 0 : 1;
        $countWork = array_key_exists('count_as_work_hour', $input) ? (!empty($input['count_as_work_hour']) ? 1 : 0) : 1;
        if ($id > 0) {
            $stmt = $this->conn->prepare("
                UPDATE shift_templates SET shift_name=?, start_time=?, end_time=?, duration_minutes=?,
                    default_break_minutes=?, is_cross_day=?, color_label=?, default_assignment_type=?,
                    count_as_work_hour=?, is_active=?, notes=?, updated_at=NOW() WHERE id=?
            ");
            if (!$stmt) throw new RuntimeException('Unable to prepare template update.');
            $crossInt = $cross ? 1 : 0;
            $stmt->bind_param('sssiiissiisi', $name, $start, $end, $duration, $break, $crossInt, $color, $type, $countWork, $active, $notes, $id);
        } else {
            $stmt = $this->conn->prepare("
                INSERT INTO shift_templates
                  (shift_name,start_time,end_time,duration_minutes,default_break_minutes,is_cross_day,color_label,
                   default_assignment_type,count_as_work_hour,is_active,notes,created_at,updated_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())
            ");
            if (!$stmt) throw new RuntimeException('Unable to prepare template.');
            $crossInt = $cross ? 1 : 0;
            $stmt->bind_param('sssiiissiis', $name, $start, $end, $duration, $break, $crossInt, $color, $type, $countWork, $active, $notes);
        }
        if (!$stmt->execute()) throw new RuntimeException('Unable to save template.');
        if ($id <= 0) $id = (int)$stmt->insert_id;
        $stmt->close();
        return $id;
    }

    public function getMonthlyTemplates(): array {
        if (!$this->tableExists('shift_monthly_templates') || !$this->tableExists('shift_monthly_template_items')) {
            return [];
        }
        $where = ['1=1'];
        $params = [];
        $types = '';
        $role = $this->scopeRole();
        if ($role === 'supervisor') {
            $divisionId = (int)($this->actor['division_id'] ?? 0);
            if ($divisionId > 0) {
                $where[] = 'm.division_id=?';
                $params[] = $divisionId;
                $types .= 'i';
            }
        } elseif (in_array($role, ['agent', 'intern'], true)) {
            return [];
        }
        $rows = $this->fetchAll("
            SELECT m.*, COALESCE(d.name,'Unassigned') AS division_name,
                   COALESCE(NULLIF(c.name,''), c.email, 'System') AS created_by_name,
                   COUNT(i.id) AS assignment_count,
                   COUNT(DISTINCT i.agent_id) AS agent_count,
                   SUM(CASE WHEN i.generated_assignment_id IS NOT NULL THEN 1 ELSE 0 END) AS applied_count
            FROM shift_monthly_templates m
            LEFT JOIN tracs_divisions d ON d.id=m.division_id
            LEFT JOIN tracs_users c ON c.id=m.created_by
            LEFT JOIN shift_monthly_template_items i ON i.template_id=m.id
            WHERE " . implode(' AND ', $where) . "
            GROUP BY m.id
            ORDER BY FIELD(m.status,'previewed','draft','applied','archived'), m.target_month DESC, m.updated_at DESC
        ", $types, $params);
        foreach ($rows as &$row) {
            $row['id'] = (int)$row['id'];
            $row['division_id'] = (int)$row['division_id'];
            $row['assignment_count'] = (int)$row['assignment_count'];
            $row['agent_count'] = (int)$row['agent_count'];
            $row['applied_count'] = (int)$row['applied_count'];
            $row['settings'] = json_decode((string)$row['settings_json'], true) ?: [];
            if ($row['status'] === 'active') $row['status'] = 'applied';
            unset($row['settings_json']);
        }
        unset($row);
        return $rows;
    }

    public function getMonthlyTemplate(int $id): ?array {
        foreach ($this->getMonthlyTemplates() as $template) {
            if ((int)$template['id'] !== $id) continue;
            $template['items'] = $this->fetchAll("
                SELECT i.*, COALESCE(NULLIF(u.name,''), u.email) AS agent_name,
                       s.shift_name, s.color_label
                FROM shift_monthly_template_items i
                JOIN tracs_users u ON u.id=i.agent_id
                JOIN shift_templates s ON s.id=i.shift_template_id
                WHERE i.template_id=?
                ORDER BY i.assignment_date, agent_name, i.start_time
            ", 'i', [$id]);
            return $template;
        }
        return null;
    }

    public function previewMonthlyTemplate(array $input): array {
        $this->requireMonthlyTemplates();
        $templateId = max(0, (int)($input['id'] ?? $input['template_id'] ?? 0));
        if ($templateId > 0 && empty($input['name'])) {
            $template = $this->getMonthlyTemplate($templateId);
            if (!$template) throw new RuntimeException('Monthly template not found.');
            $items = $template['items'];
            $settings = $template['settings'];
            $targetMonth = (string)$template['target_month'];
            $divisionId = (int)$template['division_id'];
            $name = (string)$template['name'];
            if ($template['status'] === 'draft') {
                $stmt = $this->conn->prepare("UPDATE shift_monthly_templates SET status='previewed',updated_by=?,updated_at=NOW() WHERE id=? AND status='draft'");
                if ($stmt) {
                    $stmt->bind_param('ii', $this->actorId, $templateId);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        } else {
            $normalized = $this->normalizeMonthlyTemplateInput($input);
            $items = $this->generateMonthlyTemplateItems($normalized);
            $settings = $normalized['settings'];
            $targetMonth = $normalized['target_month'];
            $divisionId = $normalized['division_id'];
            $name = $normalized['name'];
        }
        return $this->buildMonthlyTemplatePreview($name, $targetMonth, $divisionId, $settings, $items);
    }

    public function saveMonthlyTemplate(array $input): array {
        $this->requireMonthlyTemplates();
        $this->requireMonthlyTemplateTables();
        $normalized = $this->normalizeMonthlyTemplateInput($input);
        $id = max(0, (int)($input['id'] ?? 0));
        $existing = $id > 0 ? $this->getMonthlyTemplate($id) : null;
        if ($id > 0 && !$existing) {
            throw new RuntimeException('Monthly template not found.');
        }
        if ($existing && in_array($existing['status'], ['applied', 'archived'], true)) {
            throw new DomainException('Applied or archived templates must be duplicated before editing.');
        }
        $items = $this->generateMonthlyTemplateItems($normalized);
        if (!$items) throw new InvalidArgumentException('The selected pattern does not generate any assignments.');
        $settingsJson = json_encode($normalized['settings'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($settingsJson === false) throw new RuntimeException('Unable to encode monthly template settings.');
        $this->conn->begin_transaction();
        try {
            if ($id > 0) {
                $stmt = $this->conn->prepare("
                    UPDATE shift_monthly_templates
                    SET name=?,division_id=?,target_month=?,status=?,settings_json=?,updated_by=?,updated_at=NOW(),archived_at=NULL
                    WHERE id=?
                ");
                if (!$stmt) throw new RuntimeException('Unable to prepare monthly template update.');
                $stmt->bind_param(
                    'sisssii',
                    $normalized['name'], $normalized['division_id'], $normalized['target_month'],
                    $normalized['status'], $settingsJson, $this->actorId, $id
                );
                if (!$stmt->execute()) throw new RuntimeException('Unable to update monthly template.');
                $stmt->close();
                $stmt = $this->conn->prepare('DELETE FROM shift_monthly_template_items WHERE template_id=?');
                if (!$stmt) throw new RuntimeException('Unable to refresh monthly template items.');
                $stmt->bind_param('i', $id);
                if (!$stmt->execute()) throw new RuntimeException('Unable to refresh monthly template items.');
                $stmt->close();
            } else {
                $stmt = $this->conn->prepare("
                    INSERT INTO shift_monthly_templates
                      (name,division_id,target_month,status,settings_json,created_by,updated_by,created_at,updated_at)
                    VALUES (?,?,?,?,?,?,?,NOW(),NOW())
                ");
                if (!$stmt) throw new RuntimeException('Unable to prepare monthly template.');
                $stmt->bind_param(
                    'sisssii',
                    $normalized['name'], $normalized['division_id'], $normalized['target_month'],
                    $normalized['status'], $settingsJson, $this->actorId, $this->actorId
                );
                if (!$stmt->execute()) throw new RuntimeException('Unable to create monthly template.');
                $id = (int)$stmt->insert_id;
                $stmt->close();
            }
            $stmt = $this->conn->prepare("
                INSERT INTO shift_monthly_template_items
                  (template_id,agent_id,shift_template_id,assignment_date,start_time,end_time,
                   break_minutes,assignment_type,notes,created_at)
                VALUES (?,?,?,?,?,?,?,?,?,NOW())
            ");
            if (!$stmt) throw new RuntimeException('Unable to prepare monthly template items.');
            foreach ($items as $item) {
                $stmt->bind_param(
                    'iiisssiss',
                    $id, $item['agent_id'], $item['shift_template_id'], $item['assignment_date'],
                    $item['start_time'], $item['end_time'], $item['break_minutes'],
                    $item['assignment_type'], $item['notes']
                );
                if (!$stmt->execute()) throw new RuntimeException('Unable to save monthly template items.');
            }
            $stmt->close();
            $this->conn->commit();
        } catch (Throwable $e) {
            $this->conn->rollback();
            throw $e;
        }
        return [
            'id' => $id,
            'assignment_count' => count($items),
            'preview' => $this->buildMonthlyTemplatePreview(
                $normalized['name'],
                $normalized['target_month'],
                $normalized['division_id'],
                $normalized['settings'],
                $items
            ),
        ];
    }

    public function duplicateMonthlyTemplate(int $id, string $targetMonth, ?string $name = null): array {
        $this->requireMonthlyTemplates();
        $source = $this->getMonthlyTemplate($id);
        if (!$source) throw new RuntimeException('Monthly template not found.');
        $settings = $source['settings'];
        $payload = array_merge($settings, [
            'name' => $this->cleanText($name ?: $source['name'] . ' - Copy', 160),
            'target_month' => $targetMonth,
            'division_id' => (int)$source['division_id'],
            'status' => 'draft',
            'agent_ids' => $settings['agent_ids'] ?? [],
            'rest_days' => $settings['rest_days'] ?? [],
        ]);
        return $this->saveMonthlyTemplate($payload);
    }

    public function archiveMonthlyTemplate(int $id): void {
        $this->requireMonthlyTemplates();
        if (!$this->getMonthlyTemplate($id)) throw new RuntimeException('Monthly template not found.');
        $stmt = $this->conn->prepare("
            UPDATE shift_monthly_templates
            SET status='archived',archived_at=NOW(),updated_by=?,updated_at=NOW()
            WHERE id=?
        ");
        if (!$stmt) throw new RuntimeException('Unable to archive monthly template.');
        $stmt->bind_param('ii', $this->actorId, $id);
        if (!$stmt->execute()) throw new RuntimeException('Unable to archive monthly template.');
        $stmt->close();
    }

    public function applyMonthlyTemplate(int $id, bool $applyNonConflicting = false): array {
        $this->requireMonthlyTemplates();
        $template = $this->getMonthlyTemplate($id);
        if (!$template) throw new RuntimeException('Monthly template not found.');
        if (!in_array($template['status'], ['draft', 'previewed'], true)) {
            throw new DomainException('Only draft or previewed templates can be applied.');
        }
        $alreadyApplied = $this->fetchOne("
            SELECT id FROM shift_monthly_templates
            WHERE id<>? AND division_id=? AND target_month=? AND status='applied'
            LIMIT 1
        ", 'iis', [$id, (int)$template['division_id'], (string)$template['target_month']]);
        if ($alreadyApplied) {
            throw new DomainException('Another template is already applied for this division and month. Archive or duplicate it first.');
        }
        $targetMonth = new DateTimeImmutable((string)$template['target_month'], $this->timezone);
        if ($targetMonth <= new DateTimeImmutable('first day of this month', $this->timezone)) {
            throw new DomainException('Monthly templates can only be applied to a future month.');
        }
        $preview = $this->buildMonthlyTemplatePreview(
            (string)$template['name'],
            (string)$template['target_month'],
            (int)$template['division_id'],
            $template['settings'],
            $template['items']
        );
        if ($preview['conflict_count'] > 0 && !$applyNonConflicting) {
            throw new DomainException('Conflicts detected. Review them before applying or choose apply non-conflicting.');
        }
        $conflictItemIds = array_fill_keys(array_map(
            fn($row) => (int)$row['item_id'],
            $preview['conflicts']
        ), true);
        $created = [];
        $skipped = [];
        foreach ($template['items'] as $item) {
            $itemId = (int)$item['id'];
            if (!empty($item['generated_assignment_id'])) {
                $skipped[] = ['item_id' => $itemId, 'reason' => 'Already applied'];
                continue;
            }
            if (isset($conflictItemIds[$itemId])) {
                $skipped[] = ['item_id' => $itemId, 'reason' => 'Overlapping live assignment'];
                continue;
            }
            try {
                $result = $this->saveAssignment([
                    'user_id' => (int)$item['agent_id'],
                    'shift_template_id' => (int)$item['shift_template_id'],
                    'assignment_date' => (string)$item['assignment_date'],
                    'start_time' => substr((string)$item['start_time'], 0, 5),
                    'end_time' => substr((string)$item['end_time'], 0, 5),
                    'break_minutes' => (int)$item['break_minutes'],
                    'assignment_type' => (string)$item['assignment_type'],
                    'status' => 'assigned',
                    'notes' => trim((string)$item['notes'] . ' Generated from monthly template: ' . $template['name']),
                    'suppress_notification' => 1,
                    'source' => 'monthly_template',
                    'monthly_template_id' => $id,
                ]);
                $assignmentId = (int)$result['id'];
                $stmt = $this->conn->prepare('UPDATE shift_monthly_template_items SET generated_assignment_id=? WHERE id=?');
                if ($stmt) {
                    $stmt->bind_param('ii', $assignmentId, $itemId);
                    $stmt->execute();
                    $stmt->close();
                }
                $created[] = $assignmentId;
            } catch (DomainException|InvalidArgumentException $e) {
                $skipped[] = ['item_id' => $itemId, 'reason' => $e->getMessage()];
            }
        }
        $stmt = $this->conn->prepare("UPDATE shift_monthly_templates SET status='applied',applied_at=NOW(),applied_by=?,updated_by=?,updated_at=NOW() WHERE id=?");
        if ($stmt) {
            $stmt->bind_param('iii', $this->actorId, $this->actorId, $id);
            $stmt->execute();
            $stmt->close();
        }
        $this->writeAssignmentAudit(null, 'template_applied', null, [
            'template_id' => $id,
            'assignment_ids' => $created,
            'skipped' => $skipped,
        ], 'Monthly template applied: ' . $template['name']);
        $monthEnd = $targetMonth->modify('last day of this month')->format('Y-m-d');
        $postApply = $this->getPageData([
            'start' => $targetMonth->format('Y-m-d'),
            'end' => $monthEnd,
            'division_id' => (int)$template['division_id'],
        ]);
        $postWarnings = array_merge(
            $postApply['warnings']['conflicts'] ?? [],
            $postApply['warnings']['jumpshift'] ?? [],
            $postApply['warnings']['coverage'] ?? []
        );
        return [
            'template_id' => $id,
            'template_name' => $template['name'],
            'target_month' => $template['target_month'],
            'created' => count($created),
            'assignment_ids' => $created,
            'skipped' => $skipped,
            'warnings' => $postWarnings,
            'summary' => $postApply['summary'],
        ];
    }

    public function saveHoliday(array $input): int {
        $this->requireSettings();
        $id = max(0, (int)($input['id'] ?? 0));
        $date = $this->validDate((string)($input['holiday_date'] ?? ''));
        $name = $this->cleanText($input['holiday_name'] ?? '', 180);
        if ($name === '') throw new InvalidArgumentException('Holiday name is required.');
        $type = in_array($input['holiday_type'] ?? '', ['national_holiday', 'collective_leave', 'company_holiday', 'custom'], true)
            ? $input['holiday_type'] : 'national_holiday';
        $notes = $this->cleanText($input['notes'] ?? '', 2000);
        $active = empty($input['is_active']) ? 0 : 1;
        if ($id > 0) {
            $stmt = $this->conn->prepare('UPDATE public_holidays SET holiday_date=?, holiday_name=?, holiday_type=?, is_active=?, notes=?, updated_at=NOW() WHERE id=?');
            if (!$stmt) throw new RuntimeException('Unable to prepare holiday update.');
            $stmt->bind_param('sssisi', $date, $name, $type, $active, $notes, $id);
        } else {
            $stmt = $this->conn->prepare('INSERT INTO public_holidays (holiday_date,holiday_name,holiday_type,is_active,notes,created_at,updated_at) VALUES (?,?,?,?,?,NOW(),NOW())');
            if (!$stmt) throw new RuntimeException('Unable to prepare holiday.');
            $stmt->bind_param('sssis', $date, $name, $type, $active, $notes);
        }
        if (!$stmt->execute()) throw new RuntimeException('Unable to save holiday.');
        if ($id <= 0) $id = (int)$stmt->insert_id;
        $stmt->close();
        return $id;
    }

    public function saveCoverageRule(array $input): int {
        $this->requireSettings();
        $id = max(0, (int)($input['id'] ?? 0));
        $divisionId = max(0, (int)($input['division_id'] ?? 0)) ?: null;
        $dayType = in_array($input['day_type'] ?? '', ['weekday', 'weekend', 'public_holiday', 'custom'], true) ? $input['day_type'] : 'weekday';
        $customDate = $dayType === 'custom' ? $this->validDate((string)($input['custom_date'] ?? '')) : null;
        $start = $this->validTime((string)($input['start_time'] ?? ''));
        $end = $this->validTime((string)($input['end_time'] ?? ''));
        $minimum = max(1, min(100, (int)($input['minimum_agents'] ?? 1)));
        $role = $this->cleanText($input['role_required'] ?? '', 80) ?: null;
        $notes = $this->cleanText($input['notes'] ?? '', 2000);
        $active = empty($input['is_active']) ? 0 : 1;
        if ($id > 0) {
            $stmt = $this->conn->prepare('UPDATE shift_coverage_rules SET division_id=?, day_type=?, custom_date=?, start_time=?, end_time=?, minimum_agents=?, role_required=?, notes=?, is_active=?, updated_at=NOW() WHERE id=?');
            if (!$stmt) throw new RuntimeException('Unable to prepare coverage rule update.');
            $stmt->bind_param('issssissii', $divisionId, $dayType, $customDate, $start, $end, $minimum, $role, $notes, $active, $id);
        } else {
            $stmt = $this->conn->prepare('INSERT INTO shift_coverage_rules (division_id,day_type,custom_date,start_time,end_time,minimum_agents,role_required,notes,is_active,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW())');
            if (!$stmt) throw new RuntimeException('Unable to prepare coverage rule.');
            $stmt->bind_param('issssissi', $divisionId, $dayType, $customDate, $start, $end, $minimum, $role, $notes, $active);
        }
        if (!$stmt->execute()) throw new RuntimeException('Unable to save coverage rule.');
        if ($id <= 0) $id = (int)$stmt->insert_id;
        $stmt->close();
        return $id;
    }

    public function saveSettings(array $input): void {
        $this->requireSettings();
        $divisionId = max(0, (int)($input['division_id'] ?? 0)) ?: null;
        $values = [
            'weekly_target_minutes' => $this->hoursToMinutes($input['weekly_target_hours'] ?? 40, 1, 168),
            'daily_target_minutes' => $this->hoursToMinutes($input['daily_target_hours'] ?? 8, 1, 24),
            'min_weekly_minutes' => $this->hoursToMinutes($input['min_weekly_hours'] ?? 40, 0, 168),
            'max_weekly_minutes' => $this->hoursToMinutes($input['max_weekly_hours'] ?? 48, 1, 168),
            'max_daily_minutes' => $this->hoursToMinutes($input['max_daily_hours'] ?? 12, 1, 24),
            'overtime_threshold_minutes' => $this->hoursToMinutes($input['overtime_threshold_hours'] ?? 45, 1, 168),
            'normal_working_days_per_week' => max(1, min(7, (int)($input['normal_working_days_per_week'] ?? 5))),
            'minimum_rest_between_shifts_minutes' => $this->hoursToMinutes($input['minimum_rest_hours'] ?? 8, 0, 24),
            'timeline_snap_minutes' => max(5, min(60, (int)($input['timeline_snap_minutes'] ?? 15))),
            'minimum_shift_minutes' => $this->hoursToMinutes($input['minimum_shift_hours'] ?? 1, 0.25, 12),
            'count_standby_as_work_hour' => empty($input['count_standby_as_work_hour']) ? 0 : 1,
            'holiday_minimum_agents' => max(1, min(100, (int)($input['holiday_minimum_agents'] ?? 2))),
        ];
        if ($divisionId === null) {
            $global = $this->fetchOne('SELECT id FROM shift_workload_settings WHERE division_id IS NULL ORDER BY id LIMIT 1');
            if ($global) {
                $stmt = $this->conn->prepare("
                    UPDATE shift_workload_settings
                    SET weekly_target_minutes=?,daily_target_minutes=?,min_weekly_minutes=?,max_weekly_minutes=?,
                        max_daily_minutes=?,overtime_threshold_minutes=?,normal_working_days_per_week=?,
                        minimum_rest_between_shifts_minutes=?,timeline_snap_minutes=?,minimum_shift_minutes=?,
                        count_standby_as_work_hour=?,holiday_minimum_agents=?,updated_at=NOW()
                    WHERE id=?
                ");
                if (!$stmt) throw new RuntimeException('Unable to prepare workload settings.');
                $globalId = (int)$global['id'];
                $stmt->bind_param(
                    'iiiiiiiiiiiii',
                    $values['weekly_target_minutes'], $values['daily_target_minutes'], $values['min_weekly_minutes'],
                    $values['max_weekly_minutes'], $values['max_daily_minutes'], $values['overtime_threshold_minutes'],
                    $values['normal_working_days_per_week'], $values['minimum_rest_between_shifts_minutes'],
                    $values['timeline_snap_minutes'], $values['minimum_shift_minutes'], $values['count_standby_as_work_hour'],
                    $values['holiday_minimum_agents'], $globalId
                );
                if (!$stmt->execute()) throw new RuntimeException('Unable to save workload settings.');
                $stmt->close();
                return;
            }
        }
        $stmt = $this->conn->prepare("
            INSERT INTO shift_workload_settings
              (division_id,weekly_target_minutes,daily_target_minutes,min_weekly_minutes,max_weekly_minutes,max_daily_minutes,
               overtime_threshold_minutes,normal_working_days_per_week,minimum_rest_between_shifts_minutes,timeline_snap_minutes,
               minimum_shift_minutes,count_standby_as_work_hour,holiday_minimum_agents,created_at,updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())
            ON DUPLICATE KEY UPDATE weekly_target_minutes=VALUES(weekly_target_minutes),
              daily_target_minutes=VALUES(daily_target_minutes), min_weekly_minutes=VALUES(min_weekly_minutes),
              max_weekly_minutes=VALUES(max_weekly_minutes), max_daily_minutes=VALUES(max_daily_minutes),
              overtime_threshold_minutes=VALUES(overtime_threshold_minutes),
              normal_working_days_per_week=VALUES(normal_working_days_per_week),
              minimum_rest_between_shifts_minutes=VALUES(minimum_rest_between_shifts_minutes),
              timeline_snap_minutes=VALUES(timeline_snap_minutes), minimum_shift_minutes=VALUES(minimum_shift_minutes),
              count_standby_as_work_hour=VALUES(count_standby_as_work_hour),
              holiday_minimum_agents=VALUES(holiday_minimum_agents), updated_at=NOW()
        ");
        if (!$stmt) throw new RuntimeException('Unable to prepare workload settings.');
        $stmt->bind_param(
            'iiiiiiiiiiiii',
            $divisionId, $values['weekly_target_minutes'], $values['daily_target_minutes'], $values['min_weekly_minutes'],
            $values['max_weekly_minutes'], $values['max_daily_minutes'], $values['overtime_threshold_minutes'],
            $values['normal_working_days_per_week'], $values['minimum_rest_between_shifts_minutes'],
            $values['timeline_snap_minutes'], $values['minimum_shift_minutes'], $values['count_standby_as_work_hour'],
            $values['holiday_minimum_agents']
        );
        if (!$stmt->execute()) throw new RuntimeException('Unable to save workload settings.');
        $stmt->close();
    }

    public function deactivateRecord(string $kind, int $id): void {
        $this->requireSettings();
        $table = match ($kind) {
            'template' => 'shift_templates',
            'holiday' => 'public_holidays',
            'coverage_rule' => 'shift_coverage_rules',
            default => throw new InvalidArgumentException('Invalid record type.'),
        };
        $stmt = $this->conn->prepare("UPDATE {$table} SET is_active=0, updated_at=NOW() WHERE id=?");
        if (!$stmt) throw new RuntimeException('Unable to prepare record update.');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }

    public function getTemplates(): array {
        return $this->fetchAll('SELECT * FROM shift_templates ORDER BY is_active DESC, shift_name ASC');
    }

    public function getAssignmentTypes(): array {
        return $this->fetchAll('SELECT * FROM shift_assignment_types WHERE is_active=1 ORDER BY id ASC');
    }

    public function getCoverageRules(): array {
        return $this->fetchAll("
            SELECT r.*, COALESCE(d.name,'All divisions') AS division_name
            FROM shift_coverage_rules r
            LEFT JOIN tracs_divisions d ON d.id=r.division_id
            ORDER BY r.is_active DESC, r.day_type, r.start_time
        ");
    }

    public function getSettings(?int $divisionId = null): array {
        $row = null;
        if ($divisionId) {
            $row = $this->fetchOne('SELECT * FROM shift_workload_settings WHERE division_id=? LIMIT 1', 'i', [$divisionId]);
        }
        $row = $row ?: $this->fetchOne('SELECT * FROM shift_workload_settings WHERE division_id IS NULL ORDER BY id LIMIT 1');
        return $row ?: [
            'division_id' => null,
            'weekly_target_minutes' => 2400,
            'daily_target_minutes' => 480,
            'min_weekly_minutes' => 2400,
            'max_weekly_minutes' => 2880,
            'max_daily_minutes' => 720,
            'overtime_threshold_minutes' => 2700,
            'normal_working_days_per_week' => 5,
            'minimum_rest_between_shifts_minutes' => 480,
            'timeline_snap_minutes' => 15,
            'minimum_shift_minutes' => 60,
            'count_standby_as_work_hour' => 1,
            'holiday_minimum_agents' => 2,
        ];
    }

    public function getAgents(): array {
        $where = ["u.status='active'", 'u.is_active=1', "COALESCE(r.slug,'agent') <> 'viewer'"];
        $params = [];
        $types = '';
        $role = $this->scopeRole();
        if (in_array($role, ['agent', 'intern'], true)) {
            $where[] = 'u.id=?';
            $params[] = $this->actorId;
            $types .= 'i';
        } elseif ($role === 'supervisor') {
            $divisionId = (int)($this->actor['division_id'] ?? 0);
            if ($divisionId > 0) {
                $where[] = 'u.division_id=?';
                $params[] = $divisionId;
                $types .= 'i';
            } else {
                $where[] = 'u.id=?';
                $params[] = $this->actorId;
                $types .= 'i';
            }
        }
        return $this->fetchAll("
            SELECT u.id, COALESCE(NULLIF(u.name,''), SUBSTRING_INDEX(u.email,'@',1)) AS agent_name,
                   u.email, u.division_id, COALESCE(d.name,'Unassigned') AS division_name,
                   COALESCE(r.slug,'agent') AS role_slug
            FROM tracs_users u
            LEFT JOIN tracs_divisions d ON d.id=u.division_id
            LEFT JOIN tracs_roles r ON r.id=u.role_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY division_name, agent_name
        ", $types, $params);
    }

    public function getDivisions(): array {
        $role = $this->scopeRole();
        if ($role === 'supervisor' && (int)($this->actor['division_id'] ?? 0) > 0) {
            return $this->fetchAll("SELECT id,name,code FROM tracs_divisions WHERE id=? AND status='active'", 'i', [(int)$this->actor['division_id']]);
        }
        if (in_array($role, ['agent', 'intern'], true) && (int)($this->actor['division_id'] ?? 0) > 0) {
            return $this->fetchAll("SELECT id,name,code FROM tracs_divisions WHERE id=? AND status='active'", 'i', [(int)$this->actor['division_id']]);
        }
        return $this->fetchAll("SELECT id,name,code FROM tracs_divisions WHERE status='active' ORDER BY name");
    }

    public function getHolidays(string $start, string $end): array {
        $dbRows = $this->fetchAll("
            SELECT id,holiday_date,holiday_name,holiday_type,is_active,notes,'database' AS source
            FROM public_holidays
            WHERE holiday_date BETWEEN ? AND ? AND is_active=1
            ORDER BY holiday_date,holiday_name
        ", 'ss', [$start, $end]);
        $byDate = [];
        foreach ($dbRows as $row) $byDate[(string)$row['holiday_date']][] = $row;
        foreach ($this->fallbackHolidays($start, $end) as $row) {
            if (!isset($byDate[$row['holiday_date']])) $byDate[$row['holiday_date']][] = $row;
        }
        $result = [];
        foreach ($byDate as $rows) foreach ($rows as $row) $result[] = $row;
        usort($result, fn($a, $b) => strcmp($a['holiday_date'], $b['holiday_date']));
        return $result;
    }

    public function calculateWorkloadRecap(array $assignments, array $agents, array $settings, string $start, string $end): array {
        $types = [];
        foreach ($this->getAssignmentTypes() as $type) $types[$type['type_slug']] = $type;
        $rows = [];
        foreach ($agents as $agent) {
            $rows[(int)$agent['id']] = [
                'user_id' => (int)$agent['id'],
                'agent_name' => $agent['agent_name'],
                'division_name' => $agent['division_name'],
                'working_days' => [],
                'total_minutes' => 0,
                'regular_minutes' => 0,
                'overtime_minutes_explicit' => 0,
                'holiday_minutes' => 0,
                'standby_minutes' => 0,
                'replacement_minutes' => 0,
                'off_leave_days' => 0,
                'jumpshift_count' => 0,
                'conflict_count' => 0,
                'minimum_rest_minutes' => null,
            ];
        }
        foreach ($assignments as $assignment) {
            $uid = (int)$assignment['user_id'];
            if (!isset($rows[$uid])) continue;
            if (!in_array($assignment['status'], self::COUNTED_STATUSES, true)) continue;
            $flags = $types[$assignment['assignment_type']] ?? ['count_as_work_hour' => 1, 'count_as_overtime' => 0, 'count_as_holiday_hour' => 0];
            $minutes = (int)$assignment['calculated_duration_minutes'];
            if ($assignment['assignment_type'] === 'off_leave') {
                $rows[$uid]['off_leave_days']++;
                continue;
            }
            $countWork = (bool)$flags['count_as_work_hour'];
            if ($assignment['assignment_type'] === 'standby' && empty($settings['count_standby_as_work_hour'])) $countWork = false;
            if (!$countWork) continue;
            $rows[$uid]['working_days'][$assignment['assignment_date']] = true;
            $rows[$uid]['total_minutes'] += $minutes;
            if (!empty($flags['count_as_overtime']) || !empty($assignment['is_overtime'])) {
                $rows[$uid]['overtime_minutes_explicit'] += $minutes;
            } else {
                $rows[$uid]['regular_minutes'] += $minutes;
            }
            if (!empty($flags['count_as_holiday_hour']) || !empty($assignment['is_holiday_assignment'])) $rows[$uid]['holiday_minutes'] += $minutes;
            if ($assignment['assignment_type'] === 'standby') $rows[$uid]['standby_minutes'] += $minutes;
            if ($assignment['assignment_type'] === 'replacement_shift') $rows[$uid]['replacement_minutes'] += $minutes;
        }
        $target = (int)$settings['weekly_target_minutes'];
        foreach ($this->minimumRestByUser($assignments) as $uid => $rest) {
            if (isset($rows[$uid])) $rows[$uid]['minimum_rest_minutes'] = $rest;
        }
        foreach ($this->getJumpShiftWarnings($assignments, $settings) as $warning) {
            $uid = (int)$warning['user_id'];
            if (!isset($rows[$uid])) continue;
            $rows[$uid]['jumpshift_count']++;
        }
        foreach ($this->getConflictWarnings($assignments) as $warning) {
            $uid = (int)$warning['user_id'];
            if (isset($rows[$uid])) $rows[$uid]['conflict_count']++;
        }
        foreach ($rows as &$row) {
            $row['working_days'] = count($row['working_days']);
            $row['target_minutes'] = $target;
            $row['difference_minutes'] = $row['total_minutes'] - $target;
            $row['overtime_minutes'] = max($row['overtime_minutes_explicit'], max(0, $row['total_minutes'] - $target));
            $row['regular_minutes'] = max(0, $row['total_minutes'] - $row['overtime_minutes']);
            $row['status'] = $this->getWorkloadStatus($row, $settings);
            unset($row['overtime_minutes_explicit']);
        }
        unset($row);
        return array_values($rows);
    }

    public function getJumpShiftWarnings(array $assignments, array $settings): array {
        $byUser = [];
        foreach ($assignments as $assignment) {
            if (!in_array($assignment['status'], self::COUNTED_STATUSES, true) || $assignment['assignment_type'] === 'off_leave') continue;
            $byUser[(int)$assignment['user_id']][] = $assignment;
        }
        $warnings = [];
        $minimum = (int)$settings['minimum_rest_between_shifts_minutes'];
        foreach ($byUser as $userId => $rows) {
            usort($rows, fn($a, $b) => strcmp($a['start_datetime'], $b['start_datetime']));
            for ($i = 1, $count = count($rows); $i < $count; $i++) {
                $previousEnd = strtotime($rows[$i - 1]['end_datetime']);
                $nextStart = strtotime($rows[$i]['start_datetime']);
                $rest = (int)floor(($nextStart - $previousEnd) / 60);
                if ($rest >= 0 && $rest < $minimum) {
                    $warnings[] = [
                        'type' => 'jumpshift',
                        'user_id' => $userId,
                        'agent_name' => $rows[$i]['agent_name'],
                        'previous_assignment_id' => (int)$rows[$i - 1]['id'],
                        'next_assignment_id' => (int)$rows[$i]['id'],
                        'rest_minutes' => $rest,
                        'message' => '[JUMPSHIFT] ' . $rows[$i]['agent_name'] . ' only has ' . $this->formatMinutes($rest) . ' rest before the next shift.',
                    ];
                }
            }
        }
        return $warnings;
    }

    private function minimumRestByUser(array $assignments): array {
        $byUser = [];
        foreach ($assignments as $assignment) {
            if (!in_array($assignment['status'], self::COUNTED_STATUSES, true) || $assignment['assignment_type'] === 'off_leave') continue;
            $byUser[(int)$assignment['user_id']][] = $assignment;
        }
        $minimum = [];
        foreach ($byUser as $userId => $rows) {
            usort($rows, fn($a, $b) => strcmp($a['start_datetime'], $b['start_datetime']));
            for ($i = 1, $count = count($rows); $i < $count; $i++) {
                $rest = (int)floor((strtotime($rows[$i]['start_datetime']) - strtotime($rows[$i - 1]['end_datetime'])) / 60);
                if ($rest < 0) continue;
                $minimum[$userId] = isset($minimum[$userId]) ? min($minimum[$userId], $rest) : $rest;
            }
        }
        return $minimum;
    }

    public function getConflictWarnings(array $assignments): array {
        $byUser = [];
        foreach ($assignments as $assignment) {
            if (!in_array($assignment['status'], self::COUNTED_STATUSES, true) || $assignment['assignment_type'] === 'off_leave') continue;
            $byUser[(int)$assignment['user_id']][] = $assignment;
        }
        $warnings = [];
        foreach ($byUser as $userId => $rows) {
            usort($rows, fn($a, $b) => strcmp($a['start_datetime'], $b['start_datetime']));
            for ($i = 0, $count = count($rows); $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    if ($rows[$j]['start_datetime'] >= $rows[$i]['end_datetime']) break;
                    $warnings[] = [
                        'type' => 'conflict',
                        'user_id' => $userId,
                        'agent_name' => $rows[$i]['agent_name'],
                        'assignment_ids' => [(int)$rows[$i]['id'], (int)$rows[$j]['id']],
                        'message' => '[CONFLICT] ' . $rows[$i]['agent_name'] . ' has overlapping assignments.',
                    ];
                }
            }
        }
        return $warnings;
    }

    public function detectCoverageGaps(string $start, string $end, array $assignments, array $filters, array $holidays): array {
        $divisionId = max(0, (int)($filters['division_id'] ?? 0));
        $rules = array_values(array_filter($this->getCoverageRules(), function ($rule) use ($divisionId) {
            if (empty($rule['is_active'])) return false;
            $ruleDivision = (int)($rule['division_id'] ?? 0);
            return $divisionId > 0 ? in_array($ruleDivision, [0, $divisionId], true) : $ruleDivision === 0;
        }));
        $holidayDates = [];
        foreach ($holidays as $holiday) $holidayDates[$holiday['holiday_date']] = true;
        $gaps = [];
        $cursor = new DateTimeImmutable($start, $this->timezone);
        $endDate = new DateTimeImmutable($end, $this->timezone);
        while ($cursor <= $endDate) {
            $date = $cursor->format('Y-m-d');
            $dayType = isset($holidayDates[$date]) ? 'public_holiday' : ((int)$cursor->format('N') >= 6 ? 'weekend' : 'weekday');
            foreach ($rules as $rule) {
                if ($rule['day_type'] === 'custom' && $rule['custom_date'] !== $date) continue;
                if ($rule['day_type'] !== 'custom' && $rule['day_type'] !== $dayType) continue;
                $ruleStart = new DateTimeImmutable($date . ' ' . $rule['start_time'], $this->timezone);
                $ruleEnd = new DateTimeImmutable($date . ' ' . $rule['end_time'], $this->timezone);
                if ($ruleEnd <= $ruleStart) $ruleEnd = $ruleEnd->modify('+1 day');
                $assigned = [];
                foreach ($assignments as $assignment) {
                    if (!in_array($assignment['status'], self::COUNTED_STATUSES, true) || $assignment['assignment_type'] === 'off_leave') continue;
                    if ($divisionId > 0 && (int)$assignment['division_id'] !== $divisionId) continue;
                    if ($rule['division_id'] && (int)$assignment['division_id'] !== (int)$rule['division_id']) continue;
                    $aStart = new DateTimeImmutable($assignment['start_datetime'], $this->timezone);
                    $aEnd = new DateTimeImmutable($assignment['end_datetime'], $this->timezone);
                    if ($aStart < $ruleEnd && $aEnd > $ruleStart) $assigned[(int)$assignment['user_id']] = true;
                }
                $count = count($assigned);
                $required = (int)$rule['minimum_agents'];
                if ($count < $required) {
                    $gaps[] = [
                        'type' => 'coverage_gap',
                        'date' => $date,
                        'day_type' => $dayType,
                        'start_datetime' => $ruleStart->format('Y-m-d H:i:s'),
                        'end_datetime' => $ruleEnd->format('Y-m-d H:i:s'),
                        'assigned_agents' => $count,
                        'minimum_agents' => $required,
                        'missing_agents' => $required - $count,
                        'message' => '[COVERAGE] ' . $date . ' ' . $ruleStart->format('H:i') . '-' . $ruleEnd->format('H:i') . " needs " . ($required - $count) . ' more agent(s).',
                    ];
                }
            }
            $cursor = $cursor->modify('+1 day');
        }
        return $gaps;
    }

    public function getOpsTrackSignals(): array {
        if (!$this->tableExists('shift_assignments')) return [];
        $today = new DateTimeImmutable('today', $this->timezone);
        $weekStart = $today->modify('monday this week')->format('Y-m-d');
        $weekEnd = $today->modify('sunday this week')->format('Y-m-d');
        $data = $this->getPageData(['start' => $weekStart, 'end' => $weekEnd]);
        $signals = [];
        $todayHoliday = null;
        $upcomingHoliday = null;
        foreach ($this->getHolidays($today->format('Y-m-d'), $today->modify('+14 days')->format('Y-m-d')) as $holiday) {
            if ($holiday['holiday_date'] === $today->format('Y-m-d')) $todayHoliday = $holiday;
            elseif ($upcomingHoliday === null) $upcomingHoliday = $holiday;
        }
        if ($todayHoliday) {
            $coverage = array_values(array_filter($data['assignments'], fn($a) => $a['assignment_date'] === $todayHoliday['holiday_date'] && !empty($a['is_holiday_assignment']) && in_array($a['status'], self::COUNTED_STATUSES, true)));
            if (!$coverage) {
                $signals[] = $this->signal(2, 'critical', 'holiday', '[ACTION] Public holiday today but no CS overtime or standby assignment is scheduled.');
            } else {
                $names = implode(', ', array_slice(array_unique(array_column($coverage, 'agent_name')), 0, 4));
                $signals[] = $this->signal(3, 'high', 'holiday', '[LEMBUR] Today is ' . $todayHoliday['holiday_name'] . ' - standby: ' . $names);
            }
        }
        if ($upcomingHoliday) {
            $holidayDate = new DateTimeImmutable($upcomingHoliday['holiday_date'], $this->timezone);
            $days = (int)$today->diff($holidayDate)->days;
            $coverage = $this->fetchOne("
                SELECT COUNT(*) AS total FROM shift_assignments
                WHERE assignment_date=? AND is_holiday_assignment=1
                  AND status IN ('assigned','confirmed','active','completed')
            ", 's', [$upcomingHoliday['holiday_date']]);
            if ((int)($coverage['total'] ?? 0) === 0) {
                $signals[] = $this->signal(4, 'high', 'holiday', "[ACTION] Tanggal merah in {$days} day(s) - no lembur assignment yet.");
            } else {
                $signals[] = $this->signal(9, 'low', 'holiday', '[HOLIDAY] ' . $upcomingHoliday['holiday_name'] . " in {$days} day(s) - coverage prepared.");
            }
        }
        if ($data['warnings']['coverage']) {
            $gap = $data['warnings']['coverage'][0];
            $signals[] = $this->signal(5, 'high', 'coverage', '[COVERAGE] ' . $gap['date'] . ' ' . substr($gap['start_datetime'], 11, 5) . ' missing ' . $gap['missing_agents'] . ' agent(s).');
        }
        if ($data['warnings']['jumpshift']) {
            $signals[] = $this->signal(6, 'high', 'jumpshift', $data['warnings']['jumpshift'][0]['message']);
        }
        $risk = array_values(array_filter($data['recap'], fn($r) => in_array($r['status'], ['Overtime Risk', 'Critical Overload'], true)));
        if ($risk) {
            $signals[] = $this->signal(7, 'high', 'overtime', '[OVERTIME] ' . $risk[0]['agent_name'] . ' is scheduled for ' . $this->formatMinutes((int)$risk[0]['total_minutes']) . ' this week.');
        }
        $under = array_values(array_filter($data['recap'], fn($r) => $r['status'] === 'Under Target'));
        if ($under) {
            $signals[] = $this->signal(8, 'medium', 'workload', '[WARNING] ' . count($under) . ' agent(s) below weekly target.');
        }
        usort($signals, fn($a, $b) => $a['priority_order'] <=> $b['priority_order']);
        return $signals;
    }

    private function normalizeMonthlyTemplateInput(array $input): array {
        $name = $this->cleanText($input['name'] ?? '', 160);
        if ($name === '') throw new InvalidArgumentException('Template name is required.');
        $monthValue = trim((string)($input['target_month'] ?? ''));
        if (preg_match('/^\d{4}-\d{2}$/', $monthValue)) $monthValue .= '-01';
        $target = $this->parseDate($monthValue);
        if (!$target || $target->format('d') !== '01') throw new InvalidArgumentException('Target month is invalid.');
        $currentMonth = new DateTimeImmutable('first day of this month', $this->timezone);
        if ($target <= $currentMonth) throw new InvalidArgumentException('Target month must be a future month.');

        $divisionId = max(0, (int)($input['division_id'] ?? 0));
        $divisionIds = array_map(fn($row) => (int)$row['id'], $this->getDivisions());
        if ($divisionId <= 0 || !in_array($divisionId, $divisionIds, true)) {
            throw new InvalidArgumentException('Select a division in your scheduling scope.');
        }

        $agentValues = $input['agent_ids'] ?? [];
        if (is_string($agentValues)) {
            $decoded = json_decode($agentValues, true);
            $agentValues = is_array($decoded) ? $decoded : preg_split('/\s*,\s*/', $agentValues, -1, PREG_SPLIT_NO_EMPTY);
        }
        $agentIds = array_values(array_unique(array_filter(array_map('intval', (array)$agentValues))));
        $scopedAgents = [];
        foreach ($this->getAgents() as $agent) {
            if ((int)$agent['division_id'] === $divisionId) $scopedAgents[(int)$agent['id']] = $agent;
        }
        if (!$agentIds || array_diff($agentIds, array_keys($scopedAgents))) {
            throw new InvalidArgumentException('Select at least one agent from the selected division.');
        }

        $shiftTemplateId = max(0, (int)($input['shift_template_id'] ?? 0));
        $shiftTemplate = null;
        foreach ($this->getTemplates() as $row) {
            if ((int)$row['id'] === $shiftTemplateId && !empty($row['is_active'])) {
                $shiftTemplate = $row;
                break;
            }
        }
        if (!$shiftTemplate) throw new InvalidArgumentException('Select an active base shift pattern.');

        $restValues = $input['rest_days'] ?? [];
        if (is_string($restValues)) {
            $decoded = json_decode($restValues, true);
            $restValues = is_array($decoded) ? $decoded : preg_split('/\s*,\s*/', $restValues, -1, PREG_SPLIT_NO_EMPTY);
        }
        $restDays = array_values(array_unique(array_filter(
            array_map('intval', (array)$restValues),
            fn($day) => $day >= 1 && $day <= 7
        )));
        sort($restDays);
        $weekendHandling = in_array($input['weekend_handling'] ?? '', ['exclude', 'regular', 'standby'], true)
            ? (string)$input['weekend_handling'] : 'exclude';
        $status = 'draft';
        $notes = $this->cleanText($input['notes'] ?? '', 2000);
        $settings = [
            'agent_ids' => $agentIds,
            'shift_template_id' => $shiftTemplateId,
            'rest_days' => $restDays,
            'weekend_handling' => $weekendHandling,
            'repeat_weekly_pattern' => !empty($input['repeat_weekly_pattern']),
            'rotate_agents_weekly' => !empty($input['rotate_agents_weekly']),
            'exclude_public_holidays' => !empty($input['exclude_public_holidays']),
            'include_holiday_coverage' => !empty($input['include_holiday_coverage']),
            'include_lembur_template' => !empty($input['include_lembur_template']),
            'prevent_workload_over_target' => !empty($input['prevent_workload_over_target']),
            'warn_coverage_gap' => !empty($input['warn_coverage_gap']),
            'notes' => $notes,
        ];
        return [
            'name' => $name,
            'target_month' => $target->format('Y-m-d'),
            'division_id' => $divisionId,
            'status' => $status,
            'settings' => $settings,
            'agents' => $scopedAgents,
            'shift_template' => $shiftTemplate,
        ];
    }

    private function generateMonthlyTemplateItems(array $normalized): array {
        $target = new DateTimeImmutable($normalized['target_month'], $this->timezone);
        $end = $target->modify('last day of this month');
        $settings = $normalized['settings'];
        $shift = $normalized['shift_template'];
        $holidays = [];
        foreach ($this->getHolidays($target->format('Y-m-d'), $end->format('Y-m-d')) as $holiday) {
            $holidays[$holiday['holiday_date']] = $holiday;
        }
        $settingsByDivision = $this->getSettings($normalized['division_id']);
        $maxWeekly = (int)$settingsByDivision['max_weekly_minutes'];
        $duration = (int)$shift['duration_minutes'];
        $weeklyMinutes = [];
        $items = [];
        $agentIds = $settings['agent_ids'];
        foreach ($agentIds as $agentIndex => $agentId) {
            $cursor = $target;
            while ($cursor <= $end) {
                if (!$settings['repeat_weekly_pattern'] && (int)$target->diff($cursor)->days >= 7) {
                    break;
                }
                $weekday = (int)$cursor->format('N');
                $weekIndex = intdiv((int)$target->diff($cursor)->days, 7);
                $restDays = $settings['rest_days'];
                if ($settings['rotate_agents_weekly'] && $restDays) {
                    $offset = ($agentIndex + $weekIndex) % 7;
                    $restDays = array_map(fn($day) => (($day - 1 + $offset) % 7) + 1, $restDays);
                }
                $isWeekend = $weekday >= 6;
                $date = $cursor->format('Y-m-d');
                $holiday = $holidays[$date] ?? null;
                if (in_array($weekday, $restDays, true)) {
                    $cursor = $cursor->modify('+1 day');
                    continue;
                }
                if ($isWeekend && $settings['weekend_handling'] === 'exclude') {
                    $cursor = $cursor->modify('+1 day');
                    continue;
                }
                if ($holiday && $settings['exclude_public_holidays'] && !$settings['include_holiday_coverage']) {
                    $cursor = $cursor->modify('+1 day');
                    continue;
                }
                $type = (string)$shift['default_assignment_type'];
                if ($isWeekend && $settings['weekend_handling'] === 'standby') $type = 'standby';
                if ($holiday && $settings['include_holiday_coverage']) $type = 'holiday_coverage';
                elseif ($settings['include_lembur_template']) $type = 'lembur';
                $weekKey = $cursor->modify('monday this week')->format('Y-m-d');
                $projected = ($weeklyMinutes[$agentId][$weekKey] ?? 0) + $duration;
                if ($settings['prevent_workload_over_target'] && $projected > $maxWeekly) {
                    $cursor = $cursor->modify('+1 day');
                    continue;
                }
                $weeklyMinutes[$agentId][$weekKey] = $projected;
                $items[] = [
                    'id' => 0,
                    'agent_id' => (int)$agentId,
                    'shift_template_id' => (int)$shift['id'],
                    'assignment_date' => $date,
                    'start_time' => (string)$shift['start_time'],
                    'end_time' => (string)$shift['end_time'],
                    'break_minutes' => (int)$shift['default_break_minutes'],
                    'assignment_type' => $type,
                    'notes' => $settings['notes'],
                    'generated_assignment_id' => null,
                    'agent_name' => (string)$normalized['agents'][$agentId]['agent_name'],
                    'shift_name' => (string)$shift['shift_name'],
                    'color_label' => (string)$shift['color_label'],
                ];
                $cursor = $cursor->modify('+1 day');
            }
        }
        usort($items, fn($a, $b) => [$a['assignment_date'], $a['agent_name']] <=> [$b['assignment_date'], $b['agent_name']]);
        return $items;
    }

    private function buildMonthlyTemplatePreview(
        string $name,
        string $targetMonth,
        int $divisionId,
        array $settings,
        array $items
    ): array {
        $monthStart = new DateTimeImmutable($targetMonth, $this->timezone);
        $monthEnd = $monthStart->modify('last day of this month');
        $conflicts = [];
        $warnings = [];
        $previewAssignments = [];
        $settingsRow = $this->getSettings($divisionId);
        $weeklyMinutes = [];
        $holidayCoverage = [];
        foreach ($items as $index => $item) {
            $start = new DateTimeImmutable($item['assignment_date'] . ' ' . $item['start_time'], $this->timezone);
            $end = new DateTimeImmutable($item['assignment_date'] . ' ' . $item['end_time'], $this->timezone);
            if ($end <= $start) $end = $end->modify('+1 day');
            $duration = $this->calculateShiftDuration($start, $end, (int)$item['break_minutes']);
            $itemId = (int)($item['id'] ?? 0);
            foreach ($this->detectShiftConflicts((int)$item['agent_id'], $start, $end, 0) as $conflict) {
                $conflicts[] = [
                    'item_id' => $itemId ?: $index + 1,
                    'agent_id' => (int)$item['agent_id'],
                    'agent_name' => $item['agent_name'] ?? ('Agent #' . $item['agent_id']),
                    'assignment_date' => $item['assignment_date'],
                    'start_time' => substr((string)$item['start_time'], 0, 5),
                    'end_time' => substr((string)$item['end_time'], 0, 5),
                    'existing_assignment_id' => (int)$conflict['id'],
                    'existing_status' => (string)($conflict['status'] ?? 'assigned'),
                    'message' => 'Overlaps ' . (string)($conflict['status'] ?? 'assigned') . ' assignment #' . $conflict['id'],
                ];
            }
            $availability = $this->getAvailability((int)$item['agent_id'], (string)$item['assignment_date']);
            if ($availability && !in_array($availability['availability_status'], ['available', 'training'], true)) {
                $warnings[] = [
                    'type' => 'availability',
                    'agent_name' => $item['agent_name'] ?? ('Agent #' . $item['agent_id']),
                    'date' => $item['assignment_date'],
                    'message' => 'Agent is marked ' . str_replace('_', ' ', $availability['availability_status']) . '.',
                ];
            }
            $weekKey = $start->modify('monday this week')->format('Y-m-d');
            $weeklyMinutes[$item['agent_id']][$weekKey] = ($weeklyMinutes[$item['agent_id']][$weekKey] ?? 0) + $duration;
            if ($item['assignment_type'] === 'holiday_coverage') $holidayCoverage[$item['assignment_date']] = true;
            $previewAssignments[] = [
                'id' => -($index + 1),
                'user_id' => (int)$item['agent_id'],
                'division_id' => $divisionId,
                'assignment_date' => $item['assignment_date'],
                'start_datetime' => $start->format('Y-m-d H:i:s'),
                'end_datetime' => $end->format('Y-m-d H:i:s'),
                'calculated_duration_minutes' => $duration,
                'assignment_type' => $item['assignment_type'],
                'status' => 'assigned',
            ];
        }
        foreach ($weeklyMinutes as $agentId => $weeks) {
            foreach ($weeks as $week => $total) {
                if ($total > (int)$settingsRow['max_weekly_minutes']) {
                    $warnings[] = [
                        'type' => 'workload_over_target',
                        'agent_id' => (int)$agentId,
                        'date' => $week,
                        'message' => 'Projected workload is ' . $this->formatMinutes($total) . ' for the week of ' . $week . '.',
                    ];
                } elseif ($total < (int)$settingsRow['weekly_target_minutes']) {
                    $warnings[] = [
                        'type' => 'workload_under_target',
                        'agent_id' => (int)$agentId,
                        'date' => $week,
                        'message' => 'Projected workload is ' . $this->formatMinutes($total) . ' for the week of ' . $week . '.',
                    ];
                }
            }
        }
        if (!empty($settings['include_holiday_coverage'])) {
            foreach ($this->getHolidays($monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d')) as $holiday) {
                if (empty($holidayCoverage[$holiday['holiday_date']])) {
                    $warnings[] = [
                        'type' => 'holiday_missing_coverage',
                        'date' => $holiday['holiday_date'],
                        'message' => $holiday['holiday_name'] . ' has no generated holiday coverage.',
                    ];
                }
            }
        }
        if (!empty($settings['warn_coverage_gap'])) {
            $live = $this->getAssignments([
                'start' => $monthStart->format('Y-m-d'),
                'end' => $monthEnd->format('Y-m-d'),
                'division_id' => $divisionId,
            ]);
            $holidays = $this->getHolidays($monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d'));
            foreach ($this->detectCoverageGaps(
                $monthStart->format('Y-m-d'),
                $monthEnd->format('Y-m-d'),
                array_merge($live, $previewAssignments),
                ['division_id' => $divisionId],
                $holidays
            ) as $gap) {
                $warnings[] = [
                    'type' => 'coverage_gap',
                    'date' => $gap['date'],
                    'message' => $gap['message'],
                ];
            }
        }
        return [
            'template_name' => $name,
            'target_month' => $monthStart->format('Y-m-d'),
            'agent_count' => count(array_unique(array_map(fn($item) => (int)$item['agent_id'], $items))),
            'assignment_count' => count($items),
            'conflict_count' => count($conflicts),
            'warning_count' => count($warnings),
            'conflicts' => array_slice($conflicts, 0, 100),
            'warnings' => array_slice($warnings, 0, 100),
            'items' => array_slice($items, 0, 500),
        ];
    }

    private function buildSummary(array $assignments, array $recap, array $jumps, array $conflicts, array $coverage, array $holidays, string $start, string $end): array {
        $now = new DateTimeImmutable('now', $this->timezone);
        $today = $now->format('Y-m-d');
        $todayAssignments = array_values(array_filter($assignments, fn($a) => $a['assignment_date'] === $today && in_array($a['status'], self::COUNTED_STATUSES, true)));
        $activeNow = array_values(array_filter($assignments, fn($a) => in_array($a['status'], self::COUNTED_STATUSES, true) && $a['start_datetime'] <= $now->format('Y-m-d H:i:s') && $a['end_datetime'] > $now->format('Y-m-d H:i:s')));
        $under = array_values(array_filter($recap, fn($r) => $r['status'] === 'Under Target'));
        $over = array_values(array_filter($recap, fn($r) => in_array($r['status'], ['Over Target', 'Overtime Risk', 'Critical Overload'], true)));
        $risk = array_values(array_filter($recap, fn($r) => in_array($r['status'], ['Overtime Risk', 'Critical Overload'], true)));
        $overtime = array_sum(array_column($recap, 'overtime_minutes'));
        $holidayHours = array_sum(array_column($recap, 'holiday_minutes'));
        $upcoming = null;
        foreach ($holidays as $holiday) {
            if ($holiday['holiday_date'] >= $today) {
                $upcoming = $holiday;
                break;
            }
        }
        return [
            'today_assigned' => count(array_unique(array_column($todayAssignments, 'user_id'))),
            'active_now' => count(array_unique(array_column($activeNow, 'user_id'))),
            'under_target' => count($under),
            'over_target' => count($over),
            'overtime_risk' => count($risk),
            'jumpshift_risk' => count(array_unique(array_column($jumps, 'user_id'))),
            'coverage_gaps' => count($coverage),
            'conflicts' => count($conflicts),
            'overtime_minutes' => $overtime,
            'holiday_minutes' => $holidayHours,
            'upcoming_holiday' => $upcoming,
            'scheduled_minutes' => array_sum(array_map(fn($row) => (int)$row['total_minutes'], $recap)),
            'range' => ['start' => $start, 'end' => $end],
        ];
    }

    private function buildTodayCoverage(array $assignments, array $filters, array $holidays, array $settings): array {
        $today = new DateTimeImmutable('today', $this->timezone);
        $date = $today->format('Y-m-d');
        $dayStart = $today;
        $dayEnd = $today->modify('+1 day');
        $scheduled = [];
        foreach ($assignments as $assignment) {
            if (!in_array($assignment['status'], self::COUNTED_STATUSES, true) || $assignment['assignment_type'] === 'off_leave') continue;
            $start = new DateTimeImmutable($assignment['start_datetime'], $this->timezone);
            $end = new DateTimeImmutable($assignment['end_datetime'], $this->timezone);
            if ($start < $dayEnd && $end > $dayStart) $scheduled[(int)$assignment['user_id']] = true;
        }
        $holidayDates = array_fill_keys(array_column($holidays, 'holiday_date'), true);
        $dayType = isset($holidayDates[$date]) ? 'public_holiday' : ((int)$today->format('N') >= 6 ? 'weekend' : 'weekday');
        $divisionId = max(0, (int)($filters['division_id'] ?? 0));
        $required = 0;
        foreach ($this->getCoverageRules() as $rule) {
            if (empty($rule['is_active'])) continue;
            $ruleDivision = (int)($rule['division_id'] ?? 0);
            if ($divisionId > 0 && !in_array($ruleDivision, [0, $divisionId], true)) continue;
            if ($divisionId === 0 && $ruleDivision !== 0) continue;
            if ($rule['day_type'] === 'custom' && $rule['custom_date'] !== $date) continue;
            if ($rule['day_type'] !== 'custom' && $rule['day_type'] !== $dayType) continue;
            $required = max($required, (int)$rule['minimum_agents']);
        }
        if ($dayType === 'public_holiday') {
            $required = max($required, (int)($settings['holiday_minimum_agents'] ?? 0));
        }
        $scheduledCount = count($scheduled);
        return [
            'date' => $date,
            'scheduled_agents' => $scheduledCount,
            'minimum_agents' => $required,
            'missing_agents' => max(0, $required - $scheduledCount),
        ];
    }

    private function getWorkloadStatus(array $row, array $settings): string {
        if ($row['conflict_count'] > 0) return 'Conflict';
        if ($row['jumpshift_count'] > 0) return 'Jumpshift Warning';
        $total = (int)$row['total_minutes'];
        if ($total === 0) return 'No Schedule';
        if ($total < (int)round((int)$settings['weekly_target_minutes'] * 0.9)) return 'Under Target';
        if ($total > (int)$settings['max_weekly_minutes']) return 'Critical Overload';
        if ($total > (int)$settings['overtime_threshold_minutes']) return 'Overtime Risk';
        if ($total > (int)$settings['weekly_target_minutes']) return 'Over Target';
        return 'Normal';
    }

    private function detectShiftConflicts(int $userId, DateTimeImmutable $start, DateTimeImmutable $end, int $excludeId): array {
        return $this->fetchAll("
            SELECT id,start_datetime,end_datetime,status FROM shift_assignments
            WHERE user_id=? AND id<>? AND status IN ('assigned','confirmed','active','completed')
              AND start_datetime < ? AND end_datetime > ?
        ", 'iiss', [$userId, $excludeId, $end->format('Y-m-d H:i:s'), $start->format('Y-m-d H:i:s')]);
    }

    private function detectJumpShift(int $userId, DateTimeImmutable $start, DateTimeImmutable $end, int $excludeId, array $settings): array {
        $rows = $this->fetchAll("
            SELECT id,start_datetime,end_datetime FROM shift_assignments
            WHERE user_id=? AND id<>? AND status IN ('assigned','confirmed','active','completed')
              AND end_datetime >= DATE_SUB(?, INTERVAL 2 DAY)
              AND start_datetime <= DATE_ADD(?, INTERVAL 2 DAY)
            ORDER BY start_datetime
        ", 'iiss', [$userId, $excludeId, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]);
        $minimum = (int)$settings['minimum_rest_between_shifts_minutes'];
        $warnings = [];
        foreach ($rows as $row) {
            $otherStart = new DateTimeImmutable($row['start_datetime'], $this->timezone);
            $otherEnd = new DateTimeImmutable($row['end_datetime'], $this->timezone);
            $rest = null;
            if ($otherEnd <= $start) $rest = (int)(($start->getTimestamp() - $otherEnd->getTimestamp()) / 60);
            elseif ($end <= $otherStart) $rest = (int)(($otherStart->getTimestamp() - $end->getTimestamp()) / 60);
            if ($rest !== null && $rest < $minimum) {
                $warnings[] = ['type' => 'jumpshift', 'other_assignment_id' => (int)$row['id'], 'rest_minutes' => $rest, 'message' => 'Rest between shifts is only ' . $this->formatMinutes($rest) . '.'];
            }
        }
        return $warnings;
    }

    private function getScopedAgent(int $userId): ?array {
        foreach ($this->getAgents() as $agent) if ((int)$agent['id'] === $userId) return $agent;
        return null;
    }

    private function getScopedAssignmentRecord(int $id): ?array {
        $where = ['a.id=?'];
        $params = [$id];
        $types = 'i';
        $this->appendScope($where, $params, $types, 'a');
        return $this->fetchOne('SELECT a.* FROM shift_assignments a WHERE ' . implode(' AND ', $where) . ' LIMIT 1', $types, $params);
    }

    private function appendScope(array &$where, array &$params, string &$types, string $alias): void {
        $role = $this->scopeRole();
        if (in_array($role, ['agent', 'intern'], true)) {
            $where[] = "{$alias}.user_id=?";
            $params[] = $this->actorId;
            $types .= 'i';
        } elseif ($role === 'supervisor') {
            $divisionId = (int)($this->actor['division_id'] ?? 0);
            if ($divisionId > 0) {
                $where[] = "{$alias}.division_id=?";
                $params[] = $divisionId;
                $types .= 'i';
            } else {
                $where[] = "{$alias}.user_id=?";
                $params[] = $this->actorId;
                $types .= 'i';
            }
        }
    }

    private function getAvailability(int $userId, string $date): ?array {
        return $this->fetchOne('SELECT * FROM shift_agent_availability WHERE user_id=? AND availability_date=? LIMIT 1', 'is', [$userId, $date]);
    }

    private function getHolidayCoverageInsight(string $date, array $settings): array {
        $where = [
            'a.assignment_date=?',
            "a.assignment_type IN ('holiday_coverage','lembur','standby','replacement_shift')",
            "a.status IN ('assigned','confirmed','active','completed')",
        ];
        $params = [$date];
        $types = 's';
        $this->appendScope($where, $params, $types, 'a');
        $rows = $this->fetchAll("
            SELECT DISTINCT a.user_id, COALESCE(NULLIF(u.name,''), SUBSTRING_INDEX(u.email,'@',1)) AS agent_name
            FROM shift_assignments a
            JOIN tracs_users u ON u.id=a.user_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY agent_name
        ", $types, $params);
        $minimum = max(1, (int)($settings['holiday_minimum_agents'] ?? 1));
        return [
            'assigned_agents' => array_column($rows, 'agent_name'),
            'assigned_agent_count' => count($rows),
            'missing_slots' => max(0, $minimum - count($rows)),
        ];
    }

    private function assignmentTypeFlags(string $type): array {
        return $this->fetchOne('SELECT * FROM shift_assignment_types WHERE type_slug=? LIMIT 1', 's', [$type])
            ?: ['count_as_work_hour' => 1, 'count_as_overtime' => 0, 'count_as_holiday_hour' => 0];
    }

    private function getHolidayForDate(string $date): ?array {
        $rows = $this->getHolidays($date, $date);
        return $rows[0] ?? null;
    }

    private function ensureHolidayRecord(array $holiday): int {
        if (!empty($holiday['id'])) return (int)$holiday['id'];
        $type = $holiday['holiday_type'] ?? 'national_holiday';
        $stmt = $this->conn->prepare("
            INSERT INTO public_holidays (holiday_date,holiday_name,holiday_type,is_active,notes,created_at,updated_at)
            VALUES (?,?,?,1,'Imported from TRACS fallback calendar',NOW(),NOW())
            ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id),is_active=1,updated_at=NOW()
        ");
        if (!$stmt) throw new RuntimeException('Unable to link holiday coverage.');
        $stmt->bind_param('sss', $holiday['holiday_date'], $holiday['holiday_name'], $type);
        if (!$stmt->execute()) throw new RuntimeException('Unable to link holiday coverage.');
        $id = (int)$stmt->insert_id;
        $stmt->close();
        return $id;
    }

    private function notifyAssignment(int $assignmentId, int $userId, string $type, DateTimeImmutable $start, DateTimeImmutable $end, array $jumpWarnings): void {
        tracs_create_notification($this->conn, [
            'target_user_id' => $userId,
            'notification_type' => in_array($type, ['lembur', 'holiday_coverage'], true) ? 'holiday_lembur_assignment' : 'shift_assignment',
            'related_module' => 'shifting-assignment',
            'related_entity_id' => $assignmentId,
            'trigger_type' => 'assignment_saved_' . date('YmdHis'),
            'title' => in_array($type, ['lembur', 'holiday_coverage'], true) ? 'Holiday / lembur assignment' : 'Shift assignment updated',
            'message' => ucwords(str_replace('_', ' ', $type)) . ': ' . $start->format('d M Y H:i') . '-' . $end->format('d M Y H:i') . ($jumpWarnings ? ' (rest warning)' : ''),
            'related_url' => 'shifting-assignment.php',
            'actor_user_id' => $this->actorId,
        ]);
    }

    private function fallbackHolidays(string $start, string $end): array {
        $file = __DIR__ . '/../../public/assets/data/indonesia-holidays-fallback.json';
        if (!is_file($file)) return [];
        $json = json_decode((string)file_get_contents($file), true);
        if (!is_array($json)) return [];
        $startYear = (int)substr($start, 0, 4);
        $endYear = (int)substr($end, 0, 4);
        $rows = [];
        for ($year = $startYear; $year <= $endYear; $year++) {
            foreach (($json['years'][(string)$year] ?? []) as $holiday) {
                $date = (string)($holiday['date'] ?? '');
                if ($date < $start || $date > $end) continue;
                $rows[] = [
                    'id' => 0,
                    'holiday_date' => $date,
                    'holiday_name' => (string)($holiday['name'] ?? 'Public Holiday'),
                    'holiday_type' => !empty($holiday['is_national_holiday']) ? 'national_holiday' : 'collective_leave',
                    'is_active' => 1,
                    'notes' => 'TRACS fallback calendar',
                    'source' => 'fallback',
                ];
            }
        }
        return $rows;
    }

    private function signal(int $order, string $priority, string $type, string $message): array {
        return [
            'priority_order' => $order,
            'priority' => $priority,
            'type' => $type,
            'status' => 'active',
            'label' => strtoupper(str_replace('_', ' ', $type)),
            'message' => $message,
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function calculateShiftDuration(DateTimeImmutable $start, DateTimeImmutable $end, int $breakMinutes): int {
        return max(0, (int)floor(($end->getTimestamp() - $start->getTimestamp()) / 60) - max(0, $breakMinutes));
    }

    private function validateDuration(int $duration, array $settings): void {
        if ($duration < (int)$settings['minimum_shift_minutes']) {
            throw new InvalidArgumentException('Shift is shorter than the configured minimum duration.');
        }
        if ($duration > (int)$settings['max_daily_minutes']) {
            throw new InvalidArgumentException('Shift exceeds the configured maximum daily duration.');
        }
    }

    private function parseDate(?string $value): ?DateTimeImmutable {
        $value = trim((string)$value);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return null;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $this->timezone);
        return $date && $date->format('Y-m-d') === $value ? $date : null;
    }

    private function parseDateTime(?string $value): ?DateTimeImmutable {
        $value = trim((string)$value);
        if ($value === '') return null;
        try {
            return new DateTimeImmutable($value, $this->timezone);
        } catch (Throwable) {
            return null;
        }
    }

    private function validDate(string $value): string {
        $date = $this->parseDate($value);
        if (!$date) throw new InvalidArgumentException('Invalid date.');
        return $date->format('Y-m-d');
    }

    private function validTime(string $value): string {
        $value = trim($value);
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $value)) {
            throw new InvalidArgumentException('Invalid time.');
        }
        return substr($value, 0, 5) . ':00';
    }

    private function validColor(string $value): string {
        return preg_match('/^#[0-9a-f]{6}$/i', $value) ? strtolower($value) : '#4f46e5';
    }

    private function hoursToMinutes(mixed $hours, float $min, float $max): int {
        $value = max($min, min($max, (float)$hours));
        return (int)round($value * 60);
    }

    private function cleanText(mixed $value, int $max): string {
        $value = trim(strip_tags((string)$value));
        if (function_exists('mb_substr')) return mb_substr($value, 0, $max);
        return substr($value, 0, $max);
    }

    private function formatMinutes(int $minutes): string {
        $hours = intdiv(max(0, $minutes), 60);
        $mins = max(0, $minutes) % 60;
        return $mins ? "{$hours}h {$mins}m" : "{$hours}h";
    }

    private function validationError(string $field, string $message): never {
        throw new ShiftValidationException($message, [$field => $message]);
    }

    private function writeAssignmentAudit(?int $assignmentId, string $action, ?array $before, ?array $after, string $note): void {
        if (!$this->tableExists('assignment_audit_logs')) return;
        $beforeJson = $before ? json_encode($before, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
        $afterJson = $after ? json_encode($after, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
        $stmt = $this->conn->prepare("
            INSERT INTO assignment_audit_logs
              (assignment_id,action,changed_by,before_snapshot,after_snapshot,note,changed_at)
            VALUES (?,?,?,?,?,?,NOW())
        ");
        if (!$stmt) return;
        $stmt->bind_param('isisss', $assignmentId, $action, $this->actorId, $beforeJson, $afterJson, $note);
        $stmt->execute();
        $stmt->close();
    }

    private function getDismissedWarningKeys(): array {
        if (!$this->tableExists('shift_warnings') || !$this->columnExists('shift_warnings', 'warning_key')) return [];
        return array_values(array_filter(array_column($this->fetchAll("
            SELECT warning_key FROM shift_warnings
            WHERE is_resolved=1 AND warning_key IS NOT NULL AND warning_key<>''
            ORDER BY resolved_at DESC, id DESC
            LIMIT 1000
        "), 'warning_key')));
    }

    private function reopenDismissedWarnings(int $userId, string $date): void {
        if (!$this->tableExists('shift_warnings') || !$this->columnExists('shift_warnings', 'warning_key')) return;
        $stmt = $this->conn->prepare("
            UPDATE shift_warnings
            SET is_resolved=0,resolved_by=NULL,resolved_at=NULL,resolution_note=NULL,updated_at=NOW()
            WHERE is_resolved=1 AND (user_id=? OR affected_date=?)
        ");
        if (!$stmt) return;
        $stmt->bind_param('is', $userId, $date);
        $stmt->execute();
        $stmt->close();
    }

    private function requireManage(): void {
        if (!$this->canManage()) throw new RuntimeException('Forbidden.');
    }

    private function requireSettings(): void {
        if (!$this->canManageSettings()) throw new RuntimeException('Forbidden.');
    }

    private function requireMonthlyTemplates(): void {
        if (!$this->canManageMonthlyTemplates()) throw new RuntimeException('Forbidden.');
    }

    private function requireMonthlyTemplateTables(): void {
        if (!$this->tableExists('shift_monthly_templates') || !$this->tableExists('shift_monthly_template_items')) {
            throw new RuntimeException('Monthly template migration has not been applied.');
        }
    }

    private function fetchAll(string $sql, string $types = '', array $params = []): array {
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) throw new RuntimeException('Database query could not be prepared.');
        if ($types !== '') $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Database query failed.');
        }
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    private function fetchOne(string $sql, string $types = '', array $params = []): ?array {
        $rows = $this->fetchAll($sql, $types, $params);
        return $rows[0] ?? null;
    }

    private function tableExists(string $table): bool {
        $stmt = $this->conn->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
        if (!$stmt) return false;
        $stmt->bind_param('s', $table);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    private function columnExists(string $table, string $column): bool {
        $stmt = $this->conn->prepare("
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?
            LIMIT 1
        ");
        if (!$stmt) return false;
        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $exists;
    }
}
