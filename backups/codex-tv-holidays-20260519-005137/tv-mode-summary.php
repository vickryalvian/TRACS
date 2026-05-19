<?php
require_once __DIR__ . '/_bootstrap.php';

function tv_table_exists(mysqli $conn, string $table): bool {
    static $cache = [];
    if (array_key_exists($table, $cache)) return $cache[$table];
    $stmt = $conn->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1");
    if (!$stmt) return $cache[$table] = false;
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $cache[$table] = $exists;
}

function tv_column_exists(mysqli $conn, string $table, string $column): bool {
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) return $cache[$key];
    $stmt = $conn->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
    if (!$stmt) return $cache[$key] = false;
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $cache[$key] = $exists;
}

function tv_rows(mysqli $conn, string $sql, string $types = '', array $params = []): array {
    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];
    if ($types !== '') $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows ?: [];
}

function tv_scalar(mysqli $conn, string $sql, string $types = '', array $params = []): int {
    $rows = tv_rows($conn, $sql, $types, $params);
    return (int)($rows[0]['n'] ?? 0);
}

function tv_clean_title(?string $value, int $limit = 72): string {
    $text = trim(preg_replace('/\s+/', ' ', (string)$value));
    // TV Mode is a public office display, so strip obvious private identifiers before sending JSON.
    $text = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[email]', $text);
    $text = preg_replace('/https?:\/\/\S+/i', '[link]', $text);
    $text = preg_replace('/\b(?:\d[\s-]?){7,}\b/', '[id]', $text);
    if ($text === '') $text = 'Untitled item';
    if (mb_strlen($text) > $limit) $text = rtrim(mb_substr($text, 0, $limit - 1)) . '...';
    return $text;
}

function tv_person(?string $value): string {
    $name = trim((string)$value);
    if ($name === '') return 'Team';
    $name = preg_replace('/@.*/', '', $name);
    return tv_clean_title($name, 28);
}

function tv_multi_values(mixed $value): array {
    if (is_array($value)) return array_values(array_filter(array_map('trim', $value)));
    $raw = trim((string)$value);
    if ($raw === '') return [];
    $decoded = json_decode($raw, true);
    $items = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [$raw];
    $out = [];
    foreach ($items as $item) {
        $item = trim((string)$item);
        if ($item !== '' && !in_array($item, $out, true)) $out[] = $item;
    }
    return $out;
}

function tv_multi_display(mixed $value, string $fallback = 'Not specified'): string {
    $items = tv_multi_values($value);
    return $items ? implode(', ', $items) : $fallback;
}

function tv_top_value(array $counts, string $fallback): string {
    if (!$counts) return $fallback;
    arsort($counts);
    return (string)array_key_first($counts);
}

function tv_age(?string $date): string {
    if (!$date) return 'No age';
    $ts = strtotime($date);
    if (!$ts) return 'No age';
    $diff = max(0, time() - $ts);
    if ($diff >= 86400) return floor($diff / 86400) . 'd';
    if ($diff >= 3600) return floor($diff / 3600) . 'h';
    return max(1, floor($diff / 60)) . 'm';
}

function tv_due(?string $date): string {
    if (!$date) return 'No due time';
    $ts = strtotime($date);
    if (!$ts) return 'No due time';
    $diff = $ts - time();
    $prefix = $diff < 0 ? 'Overdue ' : 'In ';
    $abs = abs($diff);
    if ($abs >= 86400) return $prefix . floor($abs / 86400) . 'd';
    if ($abs >= 3600) return $prefix . floor($abs / 3600) . 'h';
    return $prefix . max(1, floor($abs / 60)) . 'm';
}

function tv_current_shift(): string {
    $h = (int)date('G');
    if ($h >= 7 && $h < 15) return 'Shift 1';
    if ($h >= 15 && $h < 23) return 'Shift 2';
    return 'Shift 3';
}

try {
    $now = date('Y-m-d H:i:s');

    $metrics = [
        'open_cases' => tv_table_exists($conn, 'tracs_cases') ? tv_scalar($conn, "SELECT COUNT(*) n FROM tracs_cases WHERE status IN ('active','pending','stuck')") : 0,
        'pending_cases' => tv_table_exists($conn, 'tracs_cases') ? tv_scalar($conn, "SELECT COUNT(*) n FROM tracs_cases WHERE status='pending'") : 0,
        'stuck_cases' => tv_table_exists($conn, 'tracs_cases') ? tv_scalar($conn, "SELECT COUNT(*) n FROM tracs_cases WHERE status IN ('active','pending','stuck') AND (status='stuck' OR created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR) OR next_check_at < NOW())") : 0,
        'solved_today' => tv_table_exists($conn, 'tracs_cases') ? tv_scalar($conn, "SELECT COUNT(*) n FROM tracs_cases WHERE status='completed' AND DATE(updated_at)=CURDATE()") : 0,
        'active_reminders' => tv_table_exists($conn, 'tracs_reminders') ? tv_scalar($conn, "SELECT COUNT(*) n FROM tracs_reminders WHERE is_completed=0") : 0,
        'overdue_reminders' => tv_table_exists($conn, 'tracs_reminders') ? tv_scalar($conn, "SELECT COUNT(*) n FROM tracs_reminders WHERE is_completed=0 AND due_date < NOW()") : 0,
        'unchecked_tasks' => tv_table_exists($conn, 'tracs_side_tasks') ? tv_scalar($conn, "SELECT COUNT(*) n FROM tracs_side_tasks WHERE is_completed=0") : 0,
        'completed_tasks_today' => tv_table_exists($conn, 'tracs_side_tasks') && tv_column_exists($conn, 'tracs_side_tasks', 'completed_at') ? tv_scalar($conn, "SELECT COUNT(*) n FROM tracs_side_tasks WHERE is_completed=1 AND DATE(COALESCE(completed_at, updated_at))=CURDATE()") : (tv_table_exists($conn, 'tracs_side_tasks') ? tv_scalar($conn, "SELECT COUNT(*) n FROM tracs_side_tasks WHERE is_completed=1 AND DATE(updated_at)=CURDATE()") : 0),
        'domain_watch' => (tv_table_exists($conn, 'domain_transfers') ? tv_scalar($conn, "SELECT COUNT(*) n FROM domain_transfers WHERE transfer_status IN ('pending transfer','locked','error epp code','pending verification','renew period')") : 0) + (tv_table_exists($conn, 'tracs_domains') ? tv_scalar($conn, "SELECT COUNT(*) n FROM tracs_domains WHERE expires_at IS NOT NULL AND expires_at <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)") : 0),
        'critical_handovers' => tv_table_exists($conn, 'tracs_shift_reports') ? tv_scalar($conn, "SELECT COUNT(*) n FROM tracs_shift_reports WHERE status='active' AND priority IN ('critical','high') AND active_date >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)") : 0,
    ];

    $caseRows = tv_table_exists($conn, 'tracs_cases') ? tv_rows($conn, "
        SELECT c.id, c.title, c.status, c.priority, c.created_at, c.updated_at, c.next_check_at,
               COALESCE(NULLIF(c.created_by_name,''), NULLIF(u.name,''), u.email, 'Team') AS owner
        FROM tracs_cases c
        LEFT JOIN tracs_users u ON c.created_by = u.id
        WHERE c.status IN ('active','pending','stuck')
        ORDER BY
          CASE WHEN c.priority='critical' THEN 1 WHEN c.status='stuck' THEN 2 WHEN c.next_check_at < NOW() THEN 3 WHEN c.priority='high' THEN 4 ELSE 5 END,
          COALESCE(c.next_check_at, c.created_at) ASC
        LIMIT 7
    ") : [];
    $cases = array_map(function($r) {
        $isAging = !empty($r['created_at']) && strtotime($r['created_at']) < strtotime('-24 hours');
        $isOverdue = !empty($r['next_check_at']) && strtotime($r['next_check_at']) < time();
        return [
            'title' => tv_clean_title($r['title'] ?? ''),
            'status' => (string)($r['status'] ?? 'active'),
            'priority' => (string)($r['priority'] ?? 'medium'),
            'age' => tv_age($r['created_at'] ?? null),
            'owner' => tv_person($r['owner'] ?? ''),
            'attention' => ($r['priority'] ?? '') === 'critical' || ($r['status'] ?? '') === 'stuck' || $isAging || $isOverdue,
        ];
    }, $caseRows);

    $reminderRows = tv_table_exists($conn, 'tracs_reminders') ? tv_rows($conn, "
        SELECT r.id, r.title, r.due_date, r.priority, r.created_at,
               COALESCE(NULLIF(r.created_by_name,''), NULLIF(u.name,''), u.email, 'Team') AS owner
        FROM tracs_reminders r
        LEFT JOIN tracs_users u ON r.created_by = u.id
        WHERE r.is_completed=0
        ORDER BY CASE WHEN r.due_date < NOW() THEN 1 WHEN r.priority='critical' THEN 2 WHEN r.due_date <= DATE_ADD(NOW(), INTERVAL 4 HOUR) THEN 3 ELSE 4 END, r.due_date ASC
        LIMIT 5
    ") : [];
    $queue = array_map(fn($r) => [
        'type' => 'Reminder',
        'title' => tv_clean_title($r['title'] ?? '', 58),
        'due' => tv_due($r['due_date'] ?? null),
        'priority' => (string)($r['priority'] ?? 'medium'),
        'owner' => tv_person($r['owner'] ?? ''),
        'urgent' => !empty($r['due_date']) && strtotime($r['due_date']) < time(),
    ], $reminderRows);

    $taskRows = tv_table_exists($conn, 'tracs_side_tasks') ? tv_rows($conn, "
        SELECT t.id, t.title, t.created_at,
               COALESCE(NULLIF(t.created_by_name,''), NULLIF(u.name,''), u.email, 'Team') AS owner
        FROM tracs_side_tasks t
        LEFT JOIN tracs_users u ON t.created_by = u.id
        WHERE t.is_completed=0
        ORDER BY t.created_at ASC
        LIMIT 4
    ") : [];
    foreach ($taskRows as $r) {
        $queue[] = [
            'type' => 'Checklist',
            'title' => tv_clean_title($r['title'] ?? '', 58),
            'due' => 'Open ' . tv_age($r['created_at'] ?? null),
            'priority' => $metrics['unchecked_tasks'] >= 10 ? 'critical' : ($metrics['unchecked_tasks'] >= 5 ? 'high' : 'medium'),
            'owner' => tv_person($r['owner'] ?? ''),
            'urgent' => $metrics['unchecked_tasks'] >= 5,
        ];
    }
    $queue = array_slice($queue, 0, 7);

    $shiftRows = tv_table_exists($conn, 'tracs_shift_reports') ? tv_rows($conn, "
        SELECT shift_name, title, priority, status, active_date, updated_at
        FROM tracs_shift_reports
        WHERE active_date >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)
        ORDER BY CASE WHEN status='active' THEN 1 ELSE 2 END, FIELD(priority,'critical','high','medium','low'), updated_at DESC
        LIMIT 4
    ") : [];
    $handover = [
        'current_shift' => tv_current_shift(),
        'active_count' => count(array_filter($shiftRows, fn($r) => ($r['status'] ?? '') === 'active')),
        'resolved_today' => tv_table_exists($conn, 'tracs_shift_reports') ? tv_scalar($conn, "SELECT COUNT(*) n FROM tracs_shift_reports WHERE status='resolved' AND DATE(resolved_at)=CURDATE()") : 0,
        'items' => array_map(fn($r) => [
            'title' => tv_clean_title($r['title'] ?? '', 58),
            'shift' => (string)($r['shift_name'] ?? 'Shift'),
            'priority' => (string)($r['priority'] ?? 'medium'),
            'status' => (string)($r['status'] ?? 'active'),
        ], $shiftRows),
    ];

    $criticalFeedbackReasons = ['Frequent downtime', 'DDoS / security-related instability', 'Slow server performance', 'Repeated Issue', 'Issue not resolved'];
    $feedbackRows = tv_table_exists($conn, 'tracs_cancellation_feedback') ? tv_rows($conn, "
        SELECT cancelled_service, cancellation_reason, whmcs_reference, payment_resolution, created_at
        FROM tracs_cancellation_feedback
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY created_at DESC
        LIMIT 12
    ") : [];
    $serviceCounts = [];
    $reasonCounts = [];
    $criticalFeedback = 0;
    foreach ($feedbackRows as $r) {
        $reasons = tv_multi_values($r['cancellation_reason'] ?? '');
        foreach (tv_multi_values($r['cancelled_service'] ?? '') as $service) {
            $serviceCounts[$service] = ($serviceCounts[$service] ?? 0) + 1;
        }
        foreach ($reasons as $reason) {
            $reasonCounts[$reason] = ($reasonCounts[$reason] ?? 0) + 1;
        }
        if (array_intersect($reasons, $criticalFeedbackReasons)) $criticalFeedback++;
    }
    $feedbackSummary = [
        'summary' => [
            'total' => tv_table_exists($conn, 'tracs_cancellation_feedback') ? tv_scalar($conn, "SELECT COUNT(*) n FROM tracs_cancellation_feedback WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)") : 0,
            'critical' => $criticalFeedback,
            'top_service' => tv_top_value($serviceCounts, 'No dominant service'),
            'top_reason' => tv_top_value($reasonCounts, 'No dominant reason'),
        ],
        'items' => array_slice(array_map(function($r) use ($criticalFeedbackReasons) {
            $service = tv_multi_display($r['cancelled_service'] ?? '', 'Cancellation feedback');
            $reason = tv_multi_display($r['cancellation_reason'] ?? '', 'Recent cancellation');
            $reference = trim((string)($r['whmcs_reference'] ?? ''));
            $reasons = tv_multi_values($r['cancellation_reason'] ?? '');
            return [
                'title' => tv_clean_title($service, 54),
                'meta' => tv_clean_title($reason, 42) . ($reference !== '' ? ' / ref ' . tv_clean_title($reference, 18) : '') . ' / ' . tv_age($r['created_at'] ?? null),
                'tone' => array_intersect($reasons, $criticalFeedbackReasons) ? 'watch' : 'info',
            ];
        }, $feedbackRows), 0, 5),
    ];
    $opsWatch = $feedbackSummary['items'];

    $activityRows = tv_table_exists($conn, 'tracs_activity_logs') ? tv_rows($conn, "
        SELECT action, module, description, created_at
        FROM tracs_activity_logs
        WHERE module IN ('case','cases','reminder','reminders','checklist','task','shift','shift-reports','finance','domains','cancellation-feedback')
        ORDER BY created_at DESC
        LIMIT 8
    ") : [];
    $activities = array_slice(array_map(fn($r) => [
        'label' => ucfirst(str_replace(['-', '_'], ' ', (string)($r['module'] ?? 'Activity'))),
        'text' => tv_clean_title($r['description'] ?? (($r['action'] ?? 'Updated') . ' item'), 70),
        'time' => tv_age($r['created_at'] ?? null) . ' ago',
    ], $activityRows), 0, 5);

    $domainAlerts = $metrics['domain_watch'];
    $financeAlerts = (tv_table_exists($conn, 'balance_transfers') ? tv_scalar($conn, "SELECT COUNT(*) n FROM balance_transfers WHERE status='pending'") : 0) + (tv_table_exists($conn, 'tracs_finance_transfers') ? tv_scalar($conn, "SELECT COUNT(*) n FROM tracs_finance_transfers WHERE status IN ('pending','failed') AND transfer_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)") : 0);
    $feedbackAlerts = tv_table_exists($conn, 'tracs_cancellation_feedback') ? tv_scalar($conn, "SELECT COUNT(*) n FROM tracs_cancellation_feedback WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)") : 0;

    $criticalSignals = $metrics['stuck_cases'] + $metrics['overdue_reminders'] + $metrics['critical_handovers'] + ($metrics['unchecked_tasks'] >= 10 ? 2 : ($metrics['unchecked_tasks'] >= 5 ? 1 : 0));
    $score = max(0, min(100, 100 - ($criticalSignals * 9) - ($domainAlerts * 4) - ($financeAlerts * 3)));
    $health = $criticalSignals >= 4 ? 'critical' : ($criticalSignals >= 2 ? 'watch' : 'stable');

    $spotlight = ['type' => 'Summary', 'severity' => $health, 'title' => 'Operations are steady', 'detail' => 'No critical queue is dominating the wall display right now.', 'meta' => 'Pulse score ' . $score];
    if ($cases && (($cases[0]['priority'] ?? '') === 'critical' || ($cases[0]['attention'] ?? false))) {
        $spotlight = ['type' => 'Case Watch', 'severity' => ($cases[0]['priority'] ?? '') === 'critical' ? 'critical' : 'watch', 'title' => $cases[0]['title'], 'detail' => ucfirst($cases[0]['status']) . ' case needs attention. Owner: ' . $cases[0]['owner'], 'meta' => 'Age ' . $cases[0]['age'] . ' / ' . ucfirst($cases[0]['priority'])];
    } elseif ($metrics['overdue_reminders'] > 0 && $queue) {
        $spotlight = ['type' => 'Reminder', 'severity' => 'critical', 'title' => $queue[0]['title'], 'detail' => 'Reminder has passed its due time and is still open.', 'meta' => $queue[0]['due']];
    } elseif ($metrics['unchecked_tasks'] >= 5) {
        $spotlight = ['type' => 'Checklist Pressure', 'severity' => $metrics['unchecked_tasks'] >= 10 ? 'critical' : 'watch', 'title' => $metrics['unchecked_tasks'] . ' unchecked tasks', 'detail' => 'Checklist pressure is above the normal operating band.', 'meta' => 'Threshold: 5 warning, 10 critical'];
    } elseif ($opsWatch) {
        $spotlight = ['type' => 'Cancellation Feedback', 'severity' => $opsWatch[0]['tone'] === 'critical' ? 'critical' : 'watch', 'title' => $opsWatch[0]['title'], 'detail' => 'Recent cancellation feedback needs retention awareness this week.', 'meta' => $opsWatch[0]['meta']];
    }

    $ticker = [];
    if ($metrics['stuck_cases'] > 0) $ticker[] = ['tone' => 'critical', 'text' => $metrics['stuck_cases'] . ' case(s) aging or stuck'];
    if ($metrics['overdue_reminders'] > 0) $ticker[] = ['tone' => 'critical', 'text' => $metrics['overdue_reminders'] . ' overdue reminder(s)'];
    if ($metrics['unchecked_tasks'] >= 5) $ticker[] = ['tone' => $metrics['unchecked_tasks'] >= 10 ? 'critical' : 'watch', 'text' => $metrics['unchecked_tasks'] . ' unchecked checklist item(s)'];
    if ($metrics['critical_handovers'] > 0) $ticker[] = ['tone' => 'watch', 'text' => $metrics['critical_handovers'] . ' handover item(s) need attention'];
    if ($domainAlerts > 0) $ticker[] = ['tone' => 'watch', 'text' => $domainAlerts . ' domain expiry watch item(s)'];
    if ($financeAlerts > 0) $ticker[] = ['tone' => 'watch', 'text' => $financeAlerts . ' finance movement watch item(s)'];
    if (!$ticker) $ticker[] = ['tone' => 'normal', 'text' => 'All systems operational'];

    ok([
        'generated_at' => $now,
        'current_shift' => tv_current_shift(),
        'health' => ['state' => $health, 'score' => $score],
        'metrics' => $metrics,
        'spotlight' => $spotlight,
        'cases' => $cases,
        'queue' => $queue,
        'handover' => $handover,
        'ops_watch' => $opsWatch,
        'feedback_summary' => $feedbackSummary,
        'activities' => $activities,
        'intelligence' => [
            'domain_alerts' => $domainAlerts,
            'finance_alerts' => $financeAlerts,
            'feedback_7d' => $feedbackAlerts,
        ],
        'ticker' => array_slice($ticker, 0, 8),
    ]);
} catch (Throwable $e) {
    error_log('TRACS TV Mode API error: ' . $e->getMessage());
    fail('Unable to load TV Mode data', 500);
}
