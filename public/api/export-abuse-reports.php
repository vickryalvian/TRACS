<?php
require_once __DIR__ . '/_export_helpers.php';

[$from, $to] = export_date_range();

$q = trim((string)($_GET['q'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$priority = trim((string)($_GET['priority'] ?? ''));
$reporter = trim((string)($_GET['reporter'] ?? ''));
$assigned = trim((string)($_GET['assigned'] ?? ''));
$evidence = (string)($_GET['evidence'] ?? '') === '1';
$actionRequired = (string)($_GET['action_required'] ?? '') === '1';

$where = ['1=1'];
$types = '';
$params = [];
$workflowStage = "CASE WHEN r.status IN ('resolved','closed') THEN 'resolved'
                       WHEN r.status = 'action_taken' OR (r.status = 'waiting_external' AND COALESCE(r.waiting_until, DATE_ADD(COALESCE(r.waiting_started_at, r.created_at), INTERVAL CASE WHEN r.waiting_hours IN (24,48) THEN r.waiting_hours ELSE 24 END HOUR)) < NOW()) THEN 'action_required'
                       ELSE r.status END";

if (in_array($status, ['incoming', 'investigating', 'waiting_external', 'action_required', 'resolved'], true)) {
    $where[] = "{$workflowStage} = ?";
    $types .= 's';
    $params[] = $status;
}
if (in_array($priority, ['critical', 'high', 'medium', 'low'], true)) {
    $where[] = 'r.priority = ?';
    $types .= 's';
    $params[] = $priority;
}
if ($reporter !== '') {
    $where[] = 'r.reporter = ?';
    $types .= 's';
    $params[] = $reporter;
}
if ($assigned !== '') {
    $where[] = 'r.assigned_user_id = ?';
    $types .= 'i';
    $params[] = (int)$assigned;
}
if ($evidence) {
    $where[] = 'COALESCE(ev.evidence_count, 0) > 0';
}
if ($actionRequired) {
    $where[] = "r.status NOT IN ('resolved','closed') AND (r.status = 'action_taken' OR (r.status = 'waiting_external' AND COALESCE(r.waiting_until, DATE_ADD(COALESCE(r.waiting_started_at, r.created_at), INTERVAL CASE WHEN r.waiting_hours IN (24,48) THEN r.waiting_hours ELSE 24 END HOUR)) < NOW()))";
}
if ($q !== '') {
    $like = '%' . $q . '%';
    $where[] = '(r.report_number LIKE ? OR r.title LIKE ? OR r.affected_domain LIKE ? OR r.affected_ip LIKE ? OR r.reporter LIKE ? OR r.reporter_contact LIKE ? OR r.customer_name LIKE ? OR r.customer_reference LIKE ? OR COALESCE(au.name, au.email, r.assigned_staff_name, \'\') LIKE ? OR r.tags LIKE ? OR r.description LIKE ?)';
    $types .= 'sssssssssss';
    array_push($params, $like, $like, $like, $like, $like, $like, $like, $like, $like, $like, $like);
}
export_add_date_filter($where, $types, $params, 'r.created_at', $from, $to, true);

$sql = "SELECT r.report_number, r.id, r.title, r.report_type, r.status, r.priority,
               r.affected_domain, r.affected_ip, r.reporter, r.reporter_contact,
               r.customer_name, r.customer_reference, r.ticket_status, r.ticket_reference,
               r.ticket_sent_at, COALESCE(NULLIF(au.name,''), au.email, r.assigned_staff_name, '') AS assigned_staff,
               r.description, r.tags, r.nameserver_snapshot, r.nameserver_snapshot_at,
               r.sla_due_at, r.waiting_started_at, r.waiting_hours, r.waiting_until,
               COALESCE(ev.evidence_count, 0) AS evidence_count,
               le.created_at AS last_activity_at, le.event_type AS last_activity_type,
               r.resolved_at, r.closed_at, r.created_at, r.updated_at
        FROM tracs_abuse_reports r
        LEFT JOIN tracs_users au ON au.id = r.assigned_user_id
        LEFT JOIN (
            SELECT report_id, COUNT(*) AS evidence_count
            FROM tracs_abuse_report_evidence
            GROUP BY report_id
        ) ev ON ev.report_id = r.id
        LEFT JOIN (
            SELECT e.report_id, e.event_type, e.created_at
            FROM tracs_abuse_report_events e
            INNER JOIN (
                SELECT report_id, MAX(id) AS max_id
                FROM tracs_abuse_report_events
                GROUP BY report_id
            ) latest ON latest.max_id = e.id
        ) le ON le.report_id = r.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY FIELD(r.status, 'incoming','investigating','waiting_external','action_taken','resolved','closed'),
                 COALESCE(le.created_at, r.updated_at, r.created_at) DESC,
                 r.id DESC";

$result = export_query($conn, $sql, $types, $params);
export_send_csv(
    'abuse-reports-' . date('Y-m-d') . '.csv',
    ['Report ID', 'Title', 'Domain', 'IP Address', 'Report Type', 'Reporter', 'Reporter Contact', 'Status', 'Priority', 'Ticket Status', 'Ticket Reference', 'Ticket Sent At', 'Assigned Staff', 'Customer', 'Customer Reference', 'Nameservers', 'Nameserver Snapshot At', 'SLA Due At', 'Waiting Started At', 'Waiting Hours', 'Waiting Until', 'Evidence Count', 'Last Activity Type', 'Last Activity At', 'Resolved At', 'Closed At', 'Notes', 'Tags', 'Created At', 'Updated At'],
    $result,
    fn(array $row) => [
        $row['report_number'] ?: ('TRACS-AR-' . str_pad((string)($row['id'] ?? 0), 6, '0', STR_PAD_LEFT)),
        $row['title'] ?? '',
        $row['affected_domain'] ?? '',
        $row['affected_ip'] ?? '',
        $row['report_type'] ?? '',
        $row['reporter'] ?? '',
        $row['reporter_contact'] ?? '',
        $row['status'] ?? '',
        $row['priority'] ?? '',
        $row['ticket_status'] ?? '',
        $row['ticket_reference'] ?? '',
        $row['ticket_sent_at'] ?? '',
        $row['assigned_staff'] ?? '',
        $row['customer_name'] ?? '',
        $row['customer_reference'] ?? '',
        $row['nameserver_snapshot'] ?? '',
        $row['nameserver_snapshot_at'] ?? '',
        $row['sla_due_at'] ?? '',
        $row['waiting_started_at'] ?? '',
        $row['waiting_hours'] ?? '',
        $row['waiting_until'] ?? '',
        $row['evidence_count'] ?? '',
        $row['last_activity_type'] ?? '',
        $row['last_activity_at'] ?? '',
        $row['resolved_at'] ?? '',
        $row['closed_at'] ?? '',
        $row['description'] ?? '',
        $row['tags'] ?? '',
        $row['created_at'] ?? '',
        $row['updated_at'] ?? '',
    ]
);
