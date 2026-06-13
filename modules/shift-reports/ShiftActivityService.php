<?php
/**
 * Shift activity collection for operational handover.
 */

require_once __DIR__ . '/../../core/shift_config.php';

class ShiftActivityService {
    private mysqli $conn;
    private int $uid;
    private array $tables = [];
    private array $columns = [];

    public function __construct(mysqli $connection, int $user_id) {
        $this->conn = $connection;
        $this->uid = $user_id;
    }

    public function detectCurrentShift(?DateTimeInterface $now = null): string {
        return tracs_detect_shift($now);
    }

    public function currentShiftWindow(?string $shift = null, ?string $date = null): array {
        return tracs_current_shift_window($shift, $date);
    }

    public function logActivity(
        string $activityType,
        int $referenceId,
        string $title,
        ?string $description = null,
        string $status = 'info',
        ?string $shiftName = null
    ): bool {
        $shiftName = $shiftName ?: $this->detectCurrentShift();
        if ($this->tableExists('tracs_shift_activities')) {
            return $this->insertActivity($activityType, $referenceId, $title, $description, $status, $shiftName);
        }
        return $this->insertFallbackReport($activityType, $referenceId, $title, $description, $status, $shiftName);
    }

    public function buildCurrentHandover(?string $shiftName = null, ?string $date = null): array {
        $shiftName = $shiftName ?: $this->detectCurrentShift();
        $date = $date ?: date('Y-m-d');
        $window = $this->currentShiftWindow($shiftName, $date);

        $completed = $this->completedActivities($window);
        $updates = $this->importantUpdates($window);
        $attention = $this->attentionItems($window);

        return [
            'shift_name' => $shiftName,
            'date' => $date,
            'window' => $window,
            'summary' => $this->summarize($completed, $updates, $attention),
            'completed' => $completed,
            'updates' => $updates,
            'attention' => $attention,
        ];
    }

    private function insertActivity(string $activityType, int $referenceId, string $title, ?string $description, string $status, string $shiftName): bool {
        $reportId = $this->currentShiftReportId($shiftName);
        $stmt = $this->conn->prepare("
            INSERT INTO tracs_shift_activities
            (shift_report_id, shift_name, activity_type, reference_id, title, description, status, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        if (!$stmt) return false;
        $uid = $this->uid;
        $stmt->bind_param('ississsi', $reportId, $shiftName, $activityType, $referenceId, $title, $description, $status, $uid);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    private function insertFallbackReport(string $activityType, int $referenceId, string $title, ?string $description, string $status, string $shiftName): bool {
        if (!$this->tableExists('tracs_shift_reports')) return false;
        $priority = $status === 'critical' ? 'critical' : ($status === 'attention' ? 'high' : 'medium');
        $reportStatus = $status === 'completed' ? 'resolved' : 'active';
        $details = trim(($description ?: '') . "\n\nAuto-collected {$activityType} activity #" . $referenceId);
        $stmt = $this->conn->prepare("
            INSERT INTO tracs_shift_reports
            (shift_name, title, details, priority, active_date, status, created_by, resolved_at, created_at, updated_at)
            VALUES (?, ?, ?, ?, CURDATE(), ?, ?, IF(?='resolved', NOW(), NULL), NOW(), NOW())
        ");
        if (!$stmt) return false;
        $uid = $this->uid;
        $stmt->bind_param('sssssiss', $shiftName, $title, $details, $priority, $reportStatus, $uid, $reportStatus, $reportStatus);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    private function currentShiftReportId(string $shiftName): ?int {
        if (!$this->tableExists('tracs_shift_reports')) return null;
        $stmt = $this->conn->prepare("
            SELECT id
            FROM tracs_shift_reports
            WHERE created_by=? AND active_date=CURDATE() AND shift_name=?
            ORDER BY FIELD(status, 'active', 'on_hold', 'resolved'), created_at DESC
            LIMIT 1
        ");
        if (!$stmt) return null;
        $stmt->bind_param('is', $this->uid, $shiftName);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? (int)$row['id'] : null;
    }

    private function completedActivities(array $window): array {
        $items = [];
        if ($this->tableExists('tracs_shift_activities')) {
            $stmt = $this->conn->prepare("
                SELECT activity_type, reference_id, title, description, status, created_at
                FROM tracs_shift_activities
                WHERE created_by=? AND shift_name=? AND status='completed' AND created_at BETWEEN ? AND ?
                ORDER BY created_at DESC
                LIMIT 20
            ");
            if ($stmt) {
                $stmt->bind_param('isss', $this->uid, $window['shift_name'], $window['start'], $window['end']);
                $stmt->execute();
                $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
            }
        }

        $items = array_merge($items, $this->completedChecklist($window), $this->completedReminders($window));
        return $this->dedupeItems($items);
    }

    private function importantUpdates(array $window): array {
        $items = [];
        $items = array_merge($items, $this->caseUpdates($window));
        $items = array_merge($items, $this->domainUpdates($window));
        $items = array_merge($items, $this->financeUpdates($window));
        $items = array_merge($items, $this->meetingUpdates($window));
        $items = array_merge($items, $this->tickerUpdates($window));
        usort($items, fn($a, $b) => strtotime($b['created_at'] ?? 'now') <=> strtotime($a['created_at'] ?? 'now'));
        return array_slice($this->dedupeItems($items), 0, 20);
    }

    private function attentionItems(array $window): array {
        $items = [];
        $items = array_merge($items, $this->pendingChecklist($window));
        $items = array_merge($items, $this->pendingReminders($window));
        $items = array_merge($items, $this->unresolvedCases($window));
        usort($items, function($a, $b) {
            $rank = ['critical' => 1, 'attention' => 2, 'pending' => 3, 'info' => 4, 'completed' => 5];
            $pa = $rank[$a['status'] ?? 'info'] ?? 9;
            $pb = $rank[$b['status'] ?? 'info'] ?? 9;
            if ($pa !== $pb) return $pa <=> $pb;
            return strcmp($a['title'] ?? '', $b['title'] ?? '');
        });
        return array_slice($this->dedupeItems($items), 0, 24);
    }

    private function completedChecklist(array $window): array {
        if (!$this->tableExists('tracs_side_tasks')) return [];
        $hasCompletedAt = $this->columnExists('tracs_side_tasks', 'completed_at');
        $timeExpr = $hasCompletedAt ? 'completed_at' : 'updated_at';
        $stmt = $this->conn->prepare("
            SELECT id, title, {$timeExpr} AS activity_at
            FROM tracs_side_tasks
            WHERE user_id=? AND is_completed=1 AND {$timeExpr} BETWEEN ? AND ?
            ORDER BY {$timeExpr} DESC
            LIMIT 20
        ");
        if (!$stmt) return [];
        $stmt->bind_param('iss', $this->uid, $window['start'], $window['end']);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return array_map(fn($r) => $this->activityRow('checklist', (int)$r['id'], 'Checklist completed: '.$r['title'], null, 'completed', $r['activity_at']), $rows);
    }

    private function completedReminders(array $window): array {
        if (!$this->tableExists('tracs_reminders')) return [];
        $hasCompletedAt = $this->columnExists('tracs_reminders', 'completed_at');
        $timeExpr = $hasCompletedAt ? 'completed_at' : 'updated_at';
        $stmt = $this->conn->prepare("
            SELECT id, title, {$timeExpr} AS activity_at
            FROM tracs_reminders
            WHERE user_id=? AND is_completed=1 AND {$timeExpr} BETWEEN ? AND ?
            ORDER BY {$timeExpr} DESC
            LIMIT 20
        ");
        if (!$stmt) return [];
        $stmt->bind_param('iss', $this->uid, $window['start'], $window['end']);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return array_map(fn($r) => $this->activityRow('reminder', (int)$r['id'], 'Reminder completed: '.$r['title'], null, 'completed', $r['activity_at']), $rows);
    }

    private function caseUpdates(array $window): array {
        if (!$this->tableExists('tracs_cases')) return [];
        $stmt = $this->conn->prepare("
            SELECT id, title, status, priority, updated_at
            FROM tracs_cases
            WHERE user_id=? AND updated_at BETWEEN ? AND ?
              AND (priority IN ('critical','high') OR status IN ('stuck','active','in_progress','pending'))
            ORDER BY updated_at DESC
            LIMIT 12
        ");
        if (!$stmt) return [];
        $stmt->bind_param('iss', $this->uid, $window['start'], $window['end']);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return array_map(function($r) {
            $status = ($r['priority'] === 'critical' || $r['status'] === 'stuck') ? 'critical' : 'attention';
            return $this->activityRow('case', (int)$r['id'], 'Case update: '.$r['title'], ucfirst($r['status']).' / '.ucfirst($r['priority']), $status, $r['updated_at']);
        }, $rows);
    }

    private function domainUpdates(array $window): array {
        if (!$this->tableExists('tracs_domains')) return [];
        $stmt = $this->conn->prepare("
            SELECT id, domain, expires_at, updated_at
            FROM tracs_domains
            WHERE user_id=? AND updated_at BETWEEN ? AND ?
            ORDER BY updated_at DESC
            LIMIT 8
        ");
        if (!$stmt) return [];
        $stmt->bind_param('iss', $this->uid, $window['start'], $window['end']);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return array_map(fn($r) => $this->activityRow('domain', (int)$r['id'], 'Domain updated: '.$r['domain'], !empty($r['expires_at']) ? 'Expires '.$r['expires_at'] : null, 'info', $r['updated_at']), $rows);
    }

    private function financeUpdates(array $window): array {
        if (!$this->tableExists('balance_transfers')) return [];
        $stmt = $this->conn->prepare("
            SELECT id, receiver_email, receiver_user_id, amount, status, updated_at
            FROM balance_transfers
            WHERE updated_at BETWEEN ? AND ?
            ORDER BY updated_at DESC
            LIMIT 8
        ");
        if (!$stmt) return [];
        $stmt->bind_param('ss', $window['start'], $window['end']);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return array_map(function($r) {
            $target = $r['receiver_email'] ?: ($r['receiver_user_id'] ?: 'recipient');
            return $this->activityRow('finance', (int)$r['id'], 'Finance transfer updated: '.number_format((float)$r['amount'], 0).' to '.$target, ucfirst($r['status']), ($r['status'] === 'pending' ? 'attention' : 'info'), $r['updated_at']);
        }, $rows);
    }

    private function meetingUpdates(array $window): array {
        if (!$this->tableExists('tracs_moms')) return [];
        $stmt = $this->conn->prepare("
            SELECT id, title, status, updated_at
            FROM tracs_moms
            WHERE created_by=? AND updated_at BETWEEN ? AND ?
            ORDER BY updated_at DESC
            LIMIT 8
        ");
        if (!$stmt) return [];
        $stmt->bind_param('iss', $this->uid, $window['start'], $window['end']);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return array_map(fn($r) => $this->activityRow('meeting', (int)$r['id'], 'Meeting updated: '.$r['title'], ucfirst($r['status']), $r['status'] === 'completed' ? 'info' : 'attention', $r['updated_at']), $rows);
    }

    private function tickerUpdates(array $window): array {
        if (!$this->tableExists('tracs_ticker_events')) return [];
        $stmt = $this->conn->prepare("
            SELECT id, message, type, module, created_at
            FROM tracs_ticker_events
            WHERE user_id=? AND created_at BETWEEN ? AND ? AND type IN ('critical','warning')
            ORDER BY created_at DESC
            LIMIT 10
        ");
        if (!$stmt) return [];
        $stmt->bind_param('iss', $this->uid, $window['start'], $window['end']);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return array_map(fn($r) => $this->activityRow('ticker', (int)$r['id'], $r['message'], $r['module'], $r['type'] === 'critical' ? 'critical' : 'attention', $r['created_at']), $rows);
    }

    private function pendingChecklist(array $window): array {
        if (!$this->tableExists('tracs_side_tasks')) return [];
        $stmt = $this->conn->prepare("
            SELECT id, title, created_at, updated_at
            FROM tracs_side_tasks
            WHERE user_id=? AND is_completed=0
            ORDER BY created_at ASC
            LIMIT 12
        ");
        if (!$stmt) return [];
        $stmt->bind_param('i', $this->uid);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return array_map(fn($r) => $this->activityRow('checklist', (int)$r['id'], 'Pending from previous shift: '.$r['title'], null, 'pending', $r['updated_at'] ?: $r['created_at']), $rows);
    }

    private function pendingReminders(array $window): array {
        if (!$this->tableExists('tracs_reminders')) return [];
        $stmt = $this->conn->prepare("
            SELECT id, title, due_date, priority, created_at, updated_at
            FROM tracs_reminders
            WHERE user_id=? AND is_completed=0
            ORDER BY
              CASE WHEN due_date IS NOT NULL AND due_date < NOW() THEN 1 WHEN priority='critical' THEN 2 WHEN priority='high' THEN 3 ELSE 4 END,
              due_date ASC
            LIMIT 12
        ");
        if (!$stmt) return [];
        $stmt->bind_param('i', $this->uid);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return array_map(function($r) {
            $overdue = !empty($r['due_date']) && strtotime($r['due_date']) < time();
            $status = $overdue || $r['priority'] === 'critical' ? 'critical' : 'pending';
            $desc = !empty($r['due_date']) ? 'Due '.$r['due_date'] : null;
            return $this->activityRow('reminder', (int)$r['id'], 'Pending reminder: '.$r['title'], $desc, $status, $r['updated_at'] ?: $r['created_at']);
        }, $rows);
    }

    private function unresolvedCases(array $window): array {
        if (!$this->tableExists('tracs_cases')) return [];
        $stmt = $this->conn->prepare("
            SELECT id, title, status, priority, next_check_at, updated_at
            FROM tracs_cases
            WHERE user_id=? AND status <> 'completed'
              AND (priority IN ('critical','high') OR status='stuck' OR next_check_at < NOW())
            ORDER BY FIELD(priority, 'critical','high','medium','low'), updated_at DESC
            LIMIT 12
        ");
        if (!$stmt) return [];
        $stmt->bind_param('i', $this->uid);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return array_map(function($r) {
            $status = ($r['priority'] === 'critical' || $r['status'] === 'stuck') ? 'critical' : 'attention';
            return $this->activityRow('case', (int)$r['id'], 'Needs next shift attention: '.$r['title'], ucfirst($r['status']).' / '.ucfirst($r['priority']), $status, $r['updated_at']);
        }, $rows);
    }

    private function activityRow(string $type, int $referenceId, string $title, ?string $description, string $status, ?string $createdAt): array {
        return [
            'activity_type' => $type,
            'reference_id' => $referenceId,
            'title' => $title,
            'description' => $description,
            'status' => $status,
            'created_at' => $createdAt ?: date('Y-m-d H:i:s'),
            'creator_name' => function_exists('tracs_current_user_display') ? tracs_current_user_display($this->conn) : 'System',
        ];
    }

    private function summarize(array $completed, array $updates, array $attention): string {
        $parts = [];
        $parts[] = count($completed).' completed item'.(count($completed) === 1 ? '' : 's');
        $parts[] = count($updates).' important update'.(count($updates) === 1 ? '' : 's');
        $critical = count(array_filter($attention, fn($i) => ($i['status'] ?? '') === 'critical'));
        $parts[] = count($attention).' handover item'.(count($attention) === 1 ? '' : 's').' needing attention';
        if ($critical > 0) $parts[] = $critical.' critical';
        return implode(' · ', $parts);
    }

    private function dedupeItems(array $items): array {
        $seen = [];
        $out = [];
        foreach ($items as $item) {
            $key = strtolower(($item['activity_type'] ?? '').'|'.($item['reference_id'] ?? '').'|'.($item['title'] ?? ''));
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $out[] = $item;
        }
        return $out;
    }

    private function tableExists(string $table): bool {
        if (array_key_exists($table, $this->tables)) return $this->tables[$table];
        $stmt = $this->conn->prepare("
            SELECT 1 FROM information_schema.TABLES
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1
        ");
        if (!$stmt) return $this->tables[$table] = false;
        $stmt->bind_param('s', $table);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $this->tables[$table] = $exists;
    }

    private function columnExists(string $table, string $column): bool {
        $key = $table.'.'.$column;
        if (array_key_exists($key, $this->columns)) return $this->columns[$key];
        if (!$this->tableExists($table)) return $this->columns[$key] = false;
        $stmt = $this->conn->prepare("
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1
        ");
        if (!$stmt) return $this->columns[$key] = false;
        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $this->columns[$key] = $exists;
    }
}
?>
