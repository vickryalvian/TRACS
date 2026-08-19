<?php
/**
 * Abuse Report module data model.
 */

require_once __DIR__ . '/../../core/user_management.php';

class AbuseReportModel {
    public const STATUSES = ['incoming', 'investigating', 'waiting_external', 'action_taken', 'resolved', 'closed'];
    public const PRIORITIES = ['low', 'medium', 'high', 'critical'];

    private mysqli $conn;

    public function __construct(mysqli $connection) {
        $this->conn = $connection;
        $this->ensureSchema();
    }

    public function ensureSchema(): bool {
        $ddl = [
            "CREATE TABLE IF NOT EXISTS `tracs_abuse_reports` (
              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `report_number` VARCHAR(32) DEFAULT NULL,
              `title` VARCHAR(220) NOT NULL,
              `report_type` VARCHAR(60) NOT NULL DEFAULT 'phishing',
              `status` ENUM('incoming','investigating','waiting_external','action_taken','resolved','closed') NOT NULL DEFAULT 'incoming',
              `priority` ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
              `affected_domain` VARCHAR(255) DEFAULT NULL,
              `affected_ip` VARCHAR(64) DEFAULT NULL,
              `reporter` VARCHAR(160) DEFAULT NULL,
              `reporter_contact` VARCHAR(190) DEFAULT NULL,
              `customer_name` VARCHAR(190) DEFAULT NULL,
              `customer_reference` VARCHAR(190) DEFAULT NULL,
              `ticket_status` ENUM('not_sent','sent') NOT NULL DEFAULT 'not_sent',
              `ticket_reference` VARCHAR(190) DEFAULT NULL,
              `ticket_url` VARCHAR(255) DEFAULT NULL,
              `ticket_sent_at` DATETIME DEFAULT NULL,
              `assigned_user_id` INT UNSIGNED DEFAULT NULL,
              `assigned_staff_name` VARCHAR(150) DEFAULT NULL,
              `description` TEXT DEFAULT NULL,
              `tags` VARCHAR(500) DEFAULT NULL,
              `nameserver_snapshot` TEXT DEFAULT NULL,
              `nameserver_snapshot_at` DATETIME DEFAULT NULL,
              `sla_due_at` DATETIME DEFAULT NULL,
              `waiting_started_at` DATETIME DEFAULT NULL,
              `waiting_hours` SMALLINT UNSIGNED NOT NULL DEFAULT 24,
              `waiting_until` DATETIME DEFAULT NULL,
              `related_domain_id` INT UNSIGNED DEFAULT NULL,
              `related_server_id` INT UNSIGNED DEFAULT NULL,
              `related_case_id` INT UNSIGNED DEFAULT NULL,
              `related_shift_report_id` INT UNSIGNED DEFAULT NULL,
              `board_order` INT NOT NULL DEFAULT 0,
              `created_by` INT UNSIGNED DEFAULT NULL,
              `created_by_name` VARCHAR(150) DEFAULT NULL,
              `updated_by` INT UNSIGNED DEFAULT NULL,
              `resolved_at` DATETIME DEFAULT NULL,
              `closed_at` DATETIME DEFAULT NULL,
              `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_abuse_report_number` (`report_number`),
              INDEX `idx_abuse_reports_board` (`status`, `board_order`),
              INDEX `idx_abuse_reports_priority` (`priority`, `status`, `sla_due_at`),
              INDEX `idx_abuse_reports_waiting` (`status`, `waiting_until`),
              INDEX `idx_abuse_reports_assigned` (`assigned_user_id`, `status`),
              INDEX `idx_abuse_reports_reporter` (`reporter`),
              INDEX `idx_abuse_reports_domain` (`affected_domain`),
              INDEX `idx_abuse_reports_created` (`created_at`),
              INDEX `idx_abuse_reports_related_case` (`related_case_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `tracs_abuse_report_events` (
              `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `report_id` INT UNSIGNED NOT NULL,
              `user_id` INT UNSIGNED DEFAULT NULL,
              `actor_name` VARCHAR(150) DEFAULT NULL,
              `event_type` VARCHAR(80) NOT NULL,
              `field_name` VARCHAR(80) DEFAULT NULL,
              `old_value` TEXT DEFAULT NULL,
              `new_value` TEXT DEFAULT NULL,
              `note` TEXT DEFAULT NULL,
              `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              INDEX `idx_abuse_events_report` (`report_id`, `created_at`),
              INDEX `idx_abuse_events_type` (`event_type`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `tracs_abuse_report_notes` (
              `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `report_id` INT UNSIGNED NOT NULL,
              `body` TEXT NOT NULL,
              `body_format` ENUM('plain','markdown') NOT NULL DEFAULT 'markdown',
              `created_by` INT UNSIGNED DEFAULT NULL,
              `created_by_name` VARCHAR(150) DEFAULT NULL,
              `edited_at` DATETIME DEFAULT NULL,
              `edit_history_json` TEXT DEFAULT NULL,
              `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              INDEX `idx_abuse_notes_report` (`report_id`, `created_at`),
              INDEX `idx_abuse_notes_created_by` (`created_by`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `tracs_abuse_report_evidence` (
              `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `report_id` INT UNSIGNED NOT NULL,
              `evidence_type` ENUM('screenshot','email','header','log','document','image','other') NOT NULL DEFAULT 'other',
              `original_filename` VARCHAR(255) NOT NULL,
              `stored_filename` VARCHAR(255) NOT NULL,
              `file_path` VARCHAR(255) NOT NULL,
              `mime_type` VARCHAR(120) NOT NULL,
              `file_size` INT UNSIGNED NOT NULL,
              `uploaded_by` INT UNSIGNED DEFAULT NULL,
              `uploaded_by_name` VARCHAR(150) DEFAULT NULL,
              `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              INDEX `idx_abuse_evidence_report` (`report_id`, `created_at`),
              INDEX `idx_abuse_evidence_uploaded_by` (`uploaded_by`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        ];

        try {
            foreach ($ddl as $sql) {
                if ($this->conn->query($sql) !== true) {
                    error_log('TRACS abuse report schema failed: ' . $this->conn->error);
                    return false;
                }
            }
            $this->ensureWorkflowColumns();
        } catch (Throwable $e) {
            error_log('TRACS abuse report schema exception: ' . $e->getMessage());
            return false;
        }

        return tracs_table_exists($this->conn, 'tracs_abuse_reports')
            && tracs_table_exists($this->conn, 'tracs_abuse_report_events')
            && tracs_table_exists($this->conn, 'tracs_abuse_report_notes')
            && tracs_table_exists($this->conn, 'tracs_abuse_report_evidence');
    }

    public static function clean(mixed $value, int $max = 500): string {
        $text = trim((string)($value ?? ''));
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
        $text = preg_replace('/\s+/', ' ', $text) ?? '';
        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, $max);
        }
        return substr($text, 0, $max);
    }

    public static function cleanLong(mixed $value, int $max = 8000): string {
        $text = trim((string)($value ?? ''));
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, $max);
        }
        return substr($text, 0, $max);
    }

    public static function statusLabel(string $status): string {
        return match ($status) {
            'incoming' => 'Incoming',
            'investigating' => 'Investigating',
            'waiting_external' => 'Waiting External',
            'action_taken', 'action_required' => 'Action Required',
            'resolved' => 'Resolved',
            'closed' => 'Closed',
            default => ucwords(str_replace('_', ' ', $status)),
        };
    }

    public static function normalizeStatus(mixed $status): string {
        $status = strtolower(trim((string)$status));
        if ($status === 'action_required') {
            return 'action_taken';
        }
        return in_array($status, self::STATUSES, true) ? $status : 'incoming';
    }

    public static function normalizePriority(mixed $priority): string {
        $priority = strtolower(trim((string)$priority));
        return in_array($priority, self::PRIORITIES, true) ? $priority : 'medium';
    }

    public static function normalizeTicketStatus(mixed $status): string {
        return strtolower(trim((string)$status)) === 'sent' ? 'sent' : 'not_sent';
    }

    public static function normalizeDateTime(mixed $value): ?string {
        $raw = trim((string)($value ?? ''));
        if ($raw === '' || strtotime($raw) === false) {
            return null;
        }
        return date('Y-m-d H:i:s', strtotime($raw));
    }

    public static function normalizeTags(mixed $tags): string {
        $raw = is_array($tags) ? implode(',', $tags) : (string)($tags ?? '');
        $parts = preg_split('/[,#]+/', $raw) ?: [];
        $clean = [];
        foreach ($parts as $part) {
            $tag = self::clean($part, 32);
            if ($tag !== '') {
                $clean[strtolower($tag)] = $tag;
            }
        }
        return self::clean(implode(', ', array_values($clean)), 500);
    }

    public static function reportNumber(int $id): string {
        return 'TRACS-AR-' . str_pad((string)$id, 6, '0', STR_PAD_LEFT);
    }

    private function defaultSla(string $priority): string {
        $hours = match ($priority) {
            'critical' => 4,
            'high' => 8,
            'low' => 72,
            default => 24,
        };
        return date('Y-m-d H:i:s', strtotime('+' . $hours . ' hours'));
    }

    private function ensureWorkflowColumns(): void {
        $columns = [
            'ticket_status' => "ALTER TABLE `tracs_abuse_reports` ADD COLUMN `ticket_status` ENUM('not_sent','sent') NOT NULL DEFAULT 'not_sent' AFTER `customer_reference`",
            'ticket_reference' => "ALTER TABLE `tracs_abuse_reports` ADD COLUMN `ticket_reference` VARCHAR(190) DEFAULT NULL AFTER `ticket_status`",
            'ticket_url' => "ALTER TABLE `tracs_abuse_reports` ADD COLUMN `ticket_url` VARCHAR(255) DEFAULT NULL AFTER `ticket_reference`",
            'ticket_sent_at' => "ALTER TABLE `tracs_abuse_reports` ADD COLUMN `ticket_sent_at` DATETIME DEFAULT NULL AFTER `ticket_url`",
            'nameserver_snapshot' => "ALTER TABLE `tracs_abuse_reports` ADD COLUMN `nameserver_snapshot` TEXT DEFAULT NULL AFTER `tags`",
            'nameserver_snapshot_at' => "ALTER TABLE `tracs_abuse_reports` ADD COLUMN `nameserver_snapshot_at` DATETIME DEFAULT NULL AFTER `nameserver_snapshot`",
            'waiting_started_at' => "ALTER TABLE `tracs_abuse_reports` ADD COLUMN `waiting_started_at` DATETIME DEFAULT NULL AFTER `sla_due_at`",
            'waiting_hours' => "ALTER TABLE `tracs_abuse_reports` ADD COLUMN `waiting_hours` SMALLINT UNSIGNED NOT NULL DEFAULT 24 AFTER `waiting_started_at`",
            'waiting_until' => "ALTER TABLE `tracs_abuse_reports` ADD COLUMN `waiting_until` DATETIME DEFAULT NULL AFTER `waiting_hours`",
        ];
        foreach ($columns as $column => $sql) {
            if (!tracs_column_exists($this->conn, 'tracs_abuse_reports', $column)) {
                $this->conn->query($sql);
            }
        }
        $this->conn->query("
            UPDATE tracs_abuse_reports
            SET waiting_started_at = COALESCE(waiting_started_at, created_at),
                waiting_hours = CASE WHEN waiting_hours IN (24,48) THEN waiting_hours ELSE 24 END,
                waiting_until = COALESCE(waiting_until, DATE_ADD(COALESCE(waiting_started_at, created_at), INTERVAL CASE WHEN waiting_hours IN (24,48) THEN waiting_hours ELSE 24 END HOUR))
            WHERE status = 'waiting_external'
              AND waiting_until IS NULL
        ");
    }

    private function waitingHours(mixed $value): int {
        $hours = (int)($value ?? 24);
        return in_array($hours, [24, 48], true) ? $hours : 24;
    }

    private function waitingDeadline(?string $startedAt, int $hours): ?string {
        if (!$startedAt || strtotime($startedAt) === false) {
            return null;
        }
        return date('Y-m-d H:i:s', strtotime($startedAt . ' +' . $hours . ' hours'));
    }

    private function cleanNameservers(mixed $value): ?string {
        $raw = trim((string)($value ?? ''));
        if ($raw === '') {
            return null;
        }
        $lines = preg_split('/[\r\n,]+/', $raw) ?: [];
        $clean = [];
        foreach ($lines as $line) {
            $ns = strtolower(self::clean($line, 253));
            if ($ns !== '') {
                $clean[$ns] = $ns;
            }
        }
        return $clean ? self::cleanLong(implode("\n", array_values($clean)), 1200) : null;
    }

    private function nullableInt(mixed $value): ?int {
        $id = (int)($value ?? 0);
        return $id > 0 ? $id : null;
    }

    private function userName(?int $userId): string {
        if (!$userId) {
            return '';
        }
        $stmt = $this->conn->prepare("SELECT COALESCE(NULLIF(name,''), email, username) AS label FROM tracs_users WHERE id = ? LIMIT 1");
        if (!$stmt) {
            return '';
        }
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return trim((string)($row['label'] ?? ''));
    }

    public function getReports(): array {
        $sql = "
            SELECT r.*,
                   COALESCE(NULLIF(au.name,''), au.email, r.assigned_staff_name, '') AS assigned_staff,
                   COALESCE(NULLIF(cu.name,''), cu.email, r.created_by_name, 'System') AS creator_name,
                   COALESCE(ev.evidence_count, 0) AS evidence_count,
                   le.created_at AS last_activity_at,
                   le.event_type AS last_activity_type
            FROM tracs_abuse_reports r
            LEFT JOIN tracs_users au ON au.id = r.assigned_user_id
            LEFT JOIN tracs_users cu ON cu.id = r.created_by
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
            ORDER BY FIELD(r.status, 'incoming','investigating','waiting_external','action_taken','resolved','closed'),
                     r.board_order ASC,
                     FIELD(r.priority, 'critical','high','medium','low'),
                     r.created_at DESC
        ";
        $result = $this->conn->query($sql);
        if (!$result) {
            return [];
        }
        return array_map([$this, 'formatReport'], $result->fetch_all(MYSQLI_ASSOC));
    }

    public function getReportById(int $id): ?array {
        if ($id <= 0) {
            return null;
        }
        $stmt = $this->conn->prepare("
            SELECT r.*,
                   COALESCE(NULLIF(au.name,''), au.email, r.assigned_staff_name, '') AS assigned_staff,
                   COALESCE(NULLIF(cu.name,''), cu.email, r.created_by_name, 'System') AS creator_name,
                   COALESCE(ev.evidence_count, 0) AS evidence_count
            FROM tracs_abuse_reports r
            LEFT JOIN tracs_users au ON au.id = r.assigned_user_id
            LEFT JOIN tracs_users cu ON cu.id = r.created_by
            LEFT JOIN (
                SELECT report_id, COUNT(*) AS evidence_count
                FROM tracs_abuse_report_evidence
                GROUP BY report_id
            ) ev ON ev.report_id = r.id
            WHERE r.id = ?
            LIMIT 1
        ");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? $this->formatReport($row) : null;
    }

    public function getReportDetail(int $id): ?array {
        $report = $this->getReportById($id);
        if (!$report) {
            return null;
        }
        $report['timeline'] = $this->getEvents($id);
        $report['notes'] = $this->getNotes($id);
        $report['evidence'] = $this->getEvidence($id);
        return $report;
    }

    public function deleteReport(int $id): array {
        $report = $this->rawReport($id, true);
        if (!$report) {
            throw new RuntimeException('Not found');
        }

        $evidence = [];
        $stmt = $this->conn->prepare("SELECT stored_filename FROM tracs_abuse_report_evidence WHERE report_id = ?");
        if (!$stmt) {
            throw new RuntimeException('Database error');
        }
        $stmt->bind_param('i', $id);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Database error');
        }
        $evidence = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach (['tracs_abuse_report_events', 'tracs_abuse_report_notes', 'tracs_abuse_report_evidence', 'tracs_abuse_reports'] as $table) {
            $stmt = $this->conn->prepare("DELETE FROM {$table} WHERE " . ($table === 'tracs_abuse_reports' ? 'id' : 'report_id') . " = ?");
            if (!$stmt) {
                throw new RuntimeException('Database error');
            }
            $stmt->bind_param('i', $id);
            if (!$stmt->execute()) {
                $stmt->close();
                throw new RuntimeException('Database error');
            }
            $deleted = $stmt->affected_rows;
            $stmt->close();
            if ($table === 'tracs_abuse_reports' && $deleted !== 1) {
                throw new RuntimeException('Not found');
            }
        }

        return [
            'id' => $id,
            'title' => (string)($report['title'] ?? 'Untitled'),
            'report_number' => (string)($report['report_number'] ?? self::reportNumber($id)),
            'evidence' => $evidence,
        ];
    }

    public function createReport(array $input, int $uid, string $actorName): int {
        $title = self::clean($input['title'] ?? '', 220);
        if ($title === '') {
            throw new RuntimeException('Title is required');
        }

        $priority = self::normalizePriority($input['priority'] ?? 'medium');
        $status = self::normalizeStatus($input['status'] ?? 'incoming');
        $assignedUserId = $this->nullableInt($input['assigned_user_id'] ?? null);
        $assignedName = $assignedUserId ? $this->userName($assignedUserId) : self::clean($input['assigned_staff_name'] ?? '', 150);
        $sla = self::normalizeDateTime($input['sla_due_at'] ?? null) ?? $this->defaultSla($priority);
        $ticketStatus = self::normalizeTicketStatus($input['ticket_status'] ?? 'not_sent');
        $ticketSentAt = $ticketStatus === 'sent'
            ? (self::normalizeDateTime($input['ticket_sent_at'] ?? null) ?? date('Y-m-d H:i:s'))
            : null;
        $nameservers = $this->cleanNameservers($input['nameserver_snapshot'] ?? null);
        $nameserverAt = $nameservers ? (self::normalizeDateTime($input['nameserver_snapshot_at'] ?? null) ?? date('Y-m-d H:i:s')) : null;
        $waitingHours = $this->waitingHours($input['waiting_hours'] ?? 24);
        $waitingStarted = $status === 'waiting_external'
            ? (self::normalizeDateTime($input['waiting_started_at'] ?? null) ?? date('Y-m-d H:i:s'))
            : null;
        $waitingUntil = $status === 'waiting_external'
            ? (self::normalizeDateTime($input['waiting_until'] ?? null) ?? $this->waitingDeadline($waitingStarted, $waitingHours))
            : null;
        $resolvedAt = $status === 'resolved' ? date('Y-m-d H:i:s') : null;
        $closedAt = $status === 'closed' ? date('Y-m-d H:i:s') : null;

        $values = [
            'report_type' => self::clean($input['report_type'] ?? 'phishing', 60) ?: 'phishing',
            'affected_domain' => self::clean($input['affected_domain'] ?? '', 255) ?: null,
            'affected_ip' => self::clean($input['affected_ip'] ?? '', 64) ?: null,
            'reporter' => self::clean($input['reporter'] ?? '', 160) ?: null,
            'reporter_contact' => self::clean($input['reporter_contact'] ?? '', 190) ?: null,
            'customer_name' => self::clean($input['customer_name'] ?? '', 190) ?: null,
            'customer_reference' => self::clean($input['customer_reference'] ?? '', 190) ?: null,
            'ticket_status' => $ticketStatus,
            'ticket_reference' => self::clean($input['ticket_reference'] ?? '', 190) ?: null,
            'ticket_url' => self::clean($input['ticket_url'] ?? '', 255) ?: null,
            'ticket_sent_at' => $ticketSentAt,
            'description' => self::cleanLong($input['description'] ?? '', 8000) ?: null,
            'tags' => self::normalizeTags($input['tags'] ?? '') ?: null,
            'nameserver_snapshot' => $nameservers,
            'nameserver_snapshot_at' => $nameserverAt,
            'waiting_started_at' => $waitingStarted,
            'waiting_hours' => $waitingHours,
            'waiting_until' => $waitingUntil,
            'related_domain_id' => $this->nullableInt($input['related_domain_id'] ?? null),
            'related_server_id' => $this->nullableInt($input['related_server_id'] ?? null),
            'related_case_id' => $this->nullableInt($input['related_case_id'] ?? null),
            'related_shift_report_id' => $this->nullableInt($input['related_shift_report_id'] ?? null),
        ];

        $fields = [
            ['title', 's', $title],
            ['report_type', 's', $values['report_type']],
            ['status', 's', $status],
            ['priority', 's', $priority],
            ['affected_domain', 's', $values['affected_domain']],
            ['affected_ip', 's', $values['affected_ip']],
            ['reporter', 's', $values['reporter']],
            ['reporter_contact', 's', $values['reporter_contact']],
            ['customer_name', 's', $values['customer_name']],
            ['customer_reference', 's', $values['customer_reference']],
            ['ticket_status', 's', $values['ticket_status']],
            ['ticket_reference', 's', $values['ticket_reference']],
            ['ticket_url', 's', $values['ticket_url']],
            ['ticket_sent_at', 's', $values['ticket_sent_at']],
            ['assigned_user_id', 'i', $assignedUserId],
            ['assigned_staff_name', 's', $assignedName],
            ['description', 's', $values['description']],
            ['tags', 's', $values['tags']],
            ['nameserver_snapshot', 's', $values['nameserver_snapshot']],
            ['nameserver_snapshot_at', 's', $values['nameserver_snapshot_at']],
            ['sla_due_at', 's', $sla],
            ['waiting_started_at', 's', $values['waiting_started_at']],
            ['waiting_hours', 'i', $values['waiting_hours']],
            ['waiting_until', 's', $values['waiting_until']],
            ['related_domain_id', 'i', $values['related_domain_id']],
            ['related_server_id', 'i', $values['related_server_id']],
            ['related_case_id', 'i', $values['related_case_id']],
            ['related_shift_report_id', 'i', $values['related_shift_report_id']],
            ['created_by', 'i', $uid],
            ['created_by_name', 's', $actorName],
            ['updated_by', 'i', $uid],
            ['resolved_at', 's', $resolvedAt],
            ['closed_at', 's', $closedAt],
        ];
        $columns = implode(', ', array_map(fn($field) => '`' . $field[0] . '`', $fields));
        $placeholders = implode(',', array_fill(0, count($fields), '?'));
        $stmt = $this->conn->prepare("
            INSERT INTO tracs_abuse_reports ({$columns}, created_at, updated_at)
            VALUES ({$placeholders}, NOW(), NOW())
        ");
        if (!$stmt) {
            throw new RuntimeException('Database error');
        }
        $types = implode('', array_column($fields, 1));
        $params = array_column($fields, 2);
        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Database error');
        }
        $id = (int)$stmt->insert_id;
        $stmt->close();

        $number = self::reportNumber($id);
        $update = $this->conn->prepare("UPDATE tracs_abuse_reports SET report_number = ?, board_order = ? WHERE id = ?");
        if ($update) {
            $order = $id;
            $update->bind_param('sii', $number, $order, $id);
            $update->execute();
            $update->close();
        }
        $this->recordEvent($id, $uid, $actorName, 'report_received', null, null, $number, $title);
        if ($assignedUserId) {
            $this->recordEvent($id, $uid, $actorName, 'assigned', 'assigned_user_id', null, (string)$assignedUserId, $assignedName);
        }
        return $id;
    }

    public function updateReport(int $id, array $input, int $uid, string $actorName): array {
        $old = $this->rawReport($id, true);
        if (!$old) {
            throw new RuntimeException('Not found');
        }

        $title = self::clean($input['title'] ?? $old['title'], 220);
        if ($title === '') {
            throw new RuntimeException('Title is required');
        }
        $priority = self::normalizePriority($input['priority'] ?? $old['priority']);
        $status = self::normalizeStatus($input['status'] ?? $old['status']);
        $assignedUserId = $this->nullableInt($input['assigned_user_id'] ?? ($old['assigned_user_id'] ?? null));
        $assignedName = $assignedUserId ? $this->userName($assignedUserId) : self::clean($input['assigned_staff_name'] ?? ($old['assigned_staff_name'] ?? ''), 150);
        $sla = self::normalizeDateTime($input['sla_due_at'] ?? ($old['sla_due_at'] ?? null));
        $ticketStatus = self::normalizeTicketStatus($input['ticket_status'] ?? ($old['ticket_status'] ?? 'not_sent'));
        $ticketSentAt = $ticketStatus === 'sent'
            ? (self::normalizeDateTime($input['ticket_sent_at'] ?? ($old['ticket_sent_at'] ?? null)) ?? date('Y-m-d H:i:s'))
            : null;
        $nameservers = $this->cleanNameservers($input['nameserver_snapshot'] ?? ($old['nameserver_snapshot'] ?? null));
        $nameserverAt = $nameservers
            ? (self::normalizeDateTime($input['nameserver_snapshot_at'] ?? ($old['nameserver_snapshot_at'] ?? null)) ?? date('Y-m-d H:i:s'))
            : null;
        $waitingHours = $this->waitingHours($input['waiting_hours'] ?? ($old['waiting_hours'] ?? 24));
        $waitingStarted = $status === 'waiting_external'
            ? (self::normalizeDateTime($input['waiting_started_at'] ?? ($old['waiting_started_at'] ?? ($old['created_at'] ?? null))) ?? date('Y-m-d H:i:s'))
            : null;
        $waitingUntil = $status === 'waiting_external'
            ? (self::normalizeDateTime($input['waiting_until'] ?? null) ?? $this->waitingDeadline($waitingStarted, $waitingHours))
            : null;
        $resolvedAt = $old['resolved_at'];
        $closedAt = $old['closed_at'];
        if ($status === 'resolved' && empty($resolvedAt)) {
            $resolvedAt = date('Y-m-d H:i:s');
        } elseif (!in_array($status, ['resolved', 'closed'], true)) {
            $resolvedAt = null;
            $closedAt = null;
        }
        if ($status === 'closed' && empty($closedAt)) {
            $closedAt = date('Y-m-d H:i:s');
            $resolvedAt = $resolvedAt ?: $closedAt;
        }

        $next = [
            'title' => $title,
            'report_type' => self::clean($input['report_type'] ?? $old['report_type'], 60) ?: 'phishing',
            'status' => $status,
            'priority' => $priority,
            'affected_domain' => self::clean($input['affected_domain'] ?? $old['affected_domain'], 255) ?: null,
            'affected_ip' => self::clean($input['affected_ip'] ?? $old['affected_ip'], 64) ?: null,
            'reporter' => self::clean($input['reporter'] ?? $old['reporter'], 160) ?: null,
            'reporter_contact' => self::clean($input['reporter_contact'] ?? $old['reporter_contact'], 190) ?: null,
            'customer_name' => self::clean($input['customer_name'] ?? $old['customer_name'], 190) ?: null,
            'customer_reference' => self::clean($input['customer_reference'] ?? $old['customer_reference'], 190) ?: null,
            'ticket_status' => $ticketStatus,
            'ticket_reference' => self::clean($input['ticket_reference'] ?? ($old['ticket_reference'] ?? ''), 190) ?: null,
            'ticket_url' => self::clean($input['ticket_url'] ?? ($old['ticket_url'] ?? ''), 255) ?: null,
            'ticket_sent_at' => $ticketSentAt,
            'assigned_user_id' => $assignedUserId,
            'assigned_staff_name' => $assignedName ?: null,
            'description' => self::cleanLong($input['description'] ?? $old['description'], 8000) ?: null,
            'tags' => self::normalizeTags($input['tags'] ?? $old['tags']) ?: null,
            'nameserver_snapshot' => $nameservers,
            'nameserver_snapshot_at' => $nameserverAt,
            'sla_due_at' => $sla,
            'waiting_started_at' => $waitingStarted,
            'waiting_hours' => $waitingHours,
            'waiting_until' => $waitingUntil,
            'related_domain_id' => $this->nullableInt($input['related_domain_id'] ?? ($old['related_domain_id'] ?? null)),
            'related_server_id' => $this->nullableInt($input['related_server_id'] ?? ($old['related_server_id'] ?? null)),
            'related_case_id' => $this->nullableInt($input['related_case_id'] ?? ($old['related_case_id'] ?? null)),
            'related_shift_report_id' => $this->nullableInt($input['related_shift_report_id'] ?? ($old['related_shift_report_id'] ?? null)),
            'resolved_at' => $resolvedAt,
            'closed_at' => $closedAt,
        ];

        $fields = [
            ['title', 's', $next['title']],
            ['report_type', 's', $next['report_type']],
            ['status', 's', $next['status']],
            ['priority', 's', $next['priority']],
            ['affected_domain', 's', $next['affected_domain']],
            ['affected_ip', 's', $next['affected_ip']],
            ['reporter', 's', $next['reporter']],
            ['reporter_contact', 's', $next['reporter_contact']],
            ['customer_name', 's', $next['customer_name']],
            ['customer_reference', 's', $next['customer_reference']],
            ['ticket_status', 's', $next['ticket_status']],
            ['ticket_reference', 's', $next['ticket_reference']],
            ['ticket_url', 's', $next['ticket_url']],
            ['ticket_sent_at', 's', $next['ticket_sent_at']],
            ['assigned_user_id', 'i', $next['assigned_user_id']],
            ['assigned_staff_name', 's', $next['assigned_staff_name']],
            ['description', 's', $next['description']],
            ['tags', 's', $next['tags']],
            ['nameserver_snapshot', 's', $next['nameserver_snapshot']],
            ['nameserver_snapshot_at', 's', $next['nameserver_snapshot_at']],
            ['sla_due_at', 's', $next['sla_due_at']],
            ['waiting_started_at', 's', $next['waiting_started_at']],
            ['waiting_hours', 'i', $next['waiting_hours']],
            ['waiting_until', 's', $next['waiting_until']],
            ['related_domain_id', 'i', $next['related_domain_id']],
            ['related_server_id', 'i', $next['related_server_id']],
            ['related_case_id', 'i', $next['related_case_id']],
            ['related_shift_report_id', 'i', $next['related_shift_report_id']],
            ['updated_by', 'i', $uid],
            ['resolved_at', 's', $next['resolved_at']],
            ['closed_at', 's', $next['closed_at']],
        ];
        $sets = implode(', ', array_map(fn($field) => '`' . $field[0] . '`=?', $fields));
        $stmt = $this->conn->prepare("
            UPDATE tracs_abuse_reports
            SET {$sets}, updated_at=NOW()
            WHERE id=?
        ");
        if (!$stmt) {
            throw new RuntimeException('Database error');
        }
        $types = implode('', array_column($fields, 1)) . 'i';
        $params = array_column($fields, 2);
        $params[] = $id;
        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Database error');
        }
        $stmt->close();

        $changes = [];
        foreach ($next as $field => $value) {
            $oldValue = $old[$field] ?? null;
            if ((string)($oldValue ?? '') !== (string)($value ?? '')) {
                $changes[$field] = ['old' => $oldValue, 'new' => $value];
                $event = match ($field) {
                    'status' => 'status_changed',
                    'priority' => 'priority_changed',
                    'assigned_user_id' => 'assigned',
                    'ticket_status', 'ticket_reference', 'ticket_url', 'ticket_sent_at' => 'ticket_updated',
                    'nameserver_snapshot', 'nameserver_snapshot_at' => 'nameserver_saved',
                    'waiting_started_at', 'waiting_hours', 'waiting_until' => 'waiting_updated',
                    default => 'updated',
                };
                $this->recordEvent($id, $uid, $actorName, $event, $field, $oldValue, $value, $title);
            }
        }
        if (!$changes) {
            $this->recordEvent($id, $uid, $actorName, 'updated', null, null, null, $title);
        }

        return $changes;
    }

    public function updateStatus(int $id, string $status, int $uid, string $actorName, string $source = 'manual'): array {
        $old = $this->rawReport($id, true);
        if (!$old) {
            throw new RuntimeException('Not found');
        }
        $status = self::normalizeStatus($status);
        $previous = (string)$old['status'];
        if ($previous === $status) {
            return [];
        }
        $resolvedAt = $old['resolved_at'];
        $closedAt = $old['closed_at'];
        if ($status === 'resolved' && empty($resolvedAt)) {
            $resolvedAt = date('Y-m-d H:i:s');
        } elseif (!in_array($status, ['resolved', 'closed'], true)) {
            $resolvedAt = null;
            $closedAt = null;
        }
        if ($status === 'closed' && empty($closedAt)) {
            $closedAt = date('Y-m-d H:i:s');
            $resolvedAt = $resolvedAt ?: $closedAt;
        }

        $stmt = $this->conn->prepare("UPDATE tracs_abuse_reports SET status=?, updated_by=?, resolved_at=?, closed_at=?, updated_at=NOW() WHERE id=?");
        if (!$stmt) {
            throw new RuntimeException('Database error');
        }
        $stmt->bind_param('sissi', $status, $uid, $resolvedAt, $closedAt, $id);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Database error');
        }
        $stmt->close();
        $this->recordEvent($id, $uid, $actorName, 'status_changed', 'status', $previous, $status, 'via ' . $source);
        return ['status' => ['old' => $previous, 'new' => $status]];
    }

    public function reorder(string $status, array $orderedIds, int $uid, string $actorName): array {
        $status = self::normalizeStatus($status);
        $ids = [];
        $seen = [];
        foreach ($orderedIds as $raw) {
            $id = (int)$raw;
            if ($id > 0 && !isset($seen[$id])) {
                $seen[$id] = true;
                $ids[] = $id;
            }
        }
        if (count($ids) > 2000) {
            throw new RuntimeException('Too many reports in one column');
        }
        if (!$ids) {
            return ['reordered' => 0, 'moved' => 0];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));
        $stmt = $this->conn->prepare("SELECT id, status, title FROM tracs_abuse_reports WHERE id IN ($placeholders) FOR UPDATE");
        if (!$stmt) {
            throw new RuntimeException('Database error');
        }
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $existing = [];
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $existing[(int)$row['id']] = $row;
        }
        $stmt->close();

        $update = $this->conn->prepare("UPDATE tracs_abuse_reports SET board_order=?, status=?, updated_by=?, updated_at=NOW() WHERE id=?");
        if (!$update) {
            throw new RuntimeException('Database error');
        }
        $moved = 0;
        $position = 0;
        foreach ($ids as $reportId) {
            if (!isset($existing[$reportId])) {
                continue;
            }
            $previous = (string)$existing[$reportId]['status'];
            if ($previous !== $status) {
                $moved++;
                $this->recordEvent($reportId, $uid, $actorName, 'status_changed', 'status', $previous, $status, 'drag_drop');
            }
            $update->bind_param('isii', $position, $status, $uid, $reportId);
            if (!$update->execute()) {
                $update->close();
                throw new RuntimeException('Database error');
            }
            $position++;
        }
        $update->close();

        return ['reordered' => count($ids), 'moved' => $moved];
    }

    public function addNote(int $reportId, string $body, int $uid, string $actorName): int {
        $body = self::cleanLong($body, 8000);
        if ($body === '') {
            throw new RuntimeException('Note is required');
        }
        if (!$this->rawReport($reportId, false)) {
            throw new RuntimeException('Not found');
        }
        $stmt = $this->conn->prepare("
            INSERT INTO tracs_abuse_report_notes (report_id, body, created_by, created_by_name, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        if (!$stmt) {
            throw new RuntimeException('Database error');
        }
        $stmt->bind_param('isis', $reportId, $body, $uid, $actorName);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Database error');
        }
        $id = (int)$stmt->insert_id;
        $stmt->close();
        $this->recordEvent($reportId, $uid, $actorName, 'note_added', null, null, null, $body);
        return $id;
    }

    public function getEvents(int $reportId, int $limit = 80): array {
        $limit = max(1, min(200, $limit));
        $stmt = $this->conn->prepare("
            SELECT e.id, e.event_type, e.field_name, e.old_value, e.new_value, e.note, e.created_at,
                   COALESCE(NULLIF(e.actor_name,''), NULLIF(u.name,''), u.email, 'System') AS actor_name
            FROM tracs_abuse_report_events e
            LEFT JOIN tracs_users u ON u.id = e.user_id
            WHERE e.report_id = ?
            ORDER BY e.created_at DESC, e.id DESC
            LIMIT ?
        ");
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('ii', $reportId, $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function getNotes(int $reportId): array {
        $stmt = $this->conn->prepare("
            SELECT n.id, n.body, n.body_format, n.edited_at, n.edit_history_json, n.created_at,
                   COALESCE(NULLIF(n.created_by_name,''), NULLIF(u.name,''), u.email, 'System') AS author_name
            FROM tracs_abuse_report_notes n
            LEFT JOIN tracs_users u ON u.id = n.created_by
            WHERE n.report_id = ?
            ORDER BY n.created_at DESC, n.id DESC
        ");
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('i', $reportId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function getEvidence(int $reportId): array {
        $stmt = $this->conn->prepare("
            SELECT id, report_id, evidence_type, original_filename, mime_type, file_size, uploaded_by, uploaded_by_name, created_at
            FROM tracs_abuse_report_evidence
            WHERE report_id = ?
            ORDER BY created_at DESC, id DESC
        ");
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('i', $reportId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        foreach ($rows as &$row) {
            $row['download_url'] = '/api/abuse-report-evidence.php?id=' . (int)$row['id'] . '&download=1';
            $row['preview_url'] = str_starts_with((string)$row['mime_type'], 'image/')
                ? '/api/abuse-report-evidence.php?id=' . (int)$row['id']
                : '';
        }
        return $rows;
    }

    public function recordEvidence(int $reportId, array $file, string $type, int $uid, string $actorName): int {
        $stmt = $this->conn->prepare("
            INSERT INTO tracs_abuse_report_evidence
              (report_id, evidence_type, original_filename, stored_filename, file_path, mime_type, file_size, uploaded_by, uploaded_by_name, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        if (!$stmt) {
            throw new RuntimeException('Database error');
        }
        $stmt->bind_param(
            'isssssiis',
            $reportId,
            $type,
            $file['original_filename'],
            $file['stored_filename'],
            $file['file_path'],
            $file['mime_type'],
            $file['file_size'],
            $uid,
            $actorName
        );
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Database error');
        }
        $id = (int)$stmt->insert_id;
        $stmt->close();
        $this->recordEvent($reportId, $uid, $actorName, 'evidence_uploaded', null, null, $file['original_filename'], $type);
        return $id;
    }

    public function fetchEvidence(int $evidenceId): ?array {
        if ($evidenceId <= 0) {
            return null;
        }
        $stmt = $this->conn->prepare("SELECT * FROM tracs_abuse_report_evidence WHERE id = ? LIMIT 1");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $evidenceId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    public function recordEvent(int $reportId, int $uid, string $actorName, string $eventType, mixed $field, mixed $oldValue, mixed $newValue, mixed $note = null): void {
        $eventType = self::clean($eventType, 80) ?: 'updated';
        $field = self::clean($field, 80) ?: null;
        $old = $oldValue === null ? null : self::cleanLong($oldValue, 4000);
        $new = $newValue === null ? null : self::cleanLong($newValue, 4000);
        $note = $note === null ? null : self::cleanLong($note, 4000);
        $actor = self::clean($actorName, 150) ?: 'System';
        $stmt = $this->conn->prepare("
            INSERT INTO tracs_abuse_report_events
              (report_id, user_id, actor_name, event_type, field_name, old_value, new_value, note, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('iissssss', $reportId, $uid, $actor, $eventType, $field, $old, $new, $note);
        $stmt->execute();
        $stmt->close();
    }

    public function dashboardSummary(): array {
        $summary = [
            'open' => 0,
            'critical' => 0,
            'waiting_external' => 0,
            'over_sla' => 0,
            'action_required' => 0,
            'resolved_today' => 0,
            'oldest_open' => null,
        ];
        $result = $this->conn->query("
            SELECT
              SUM(status NOT IN ('resolved','closed')) AS open_count,
              SUM(status NOT IN ('resolved','closed') AND priority='critical') AS critical_count,
              SUM(status='waiting_external') AS waiting_external_count,
              SUM(status NOT IN ('resolved','closed') AND (status='action_taken' OR (waiting_until IS NOT NULL AND waiting_until < NOW()))) AS action_required_count,
              SUM(status='resolved' AND DATE(resolved_at)=CURDATE()) AS resolved_today_count
            FROM tracs_abuse_reports
        ");
        if ($result && ($row = $result->fetch_assoc())) {
            $summary['open'] = (int)($row['open_count'] ?? 0);
            $summary['critical'] = (int)($row['critical_count'] ?? 0);
            $summary['waiting_external'] = (int)($row['waiting_external_count'] ?? 0);
            $summary['action_required'] = (int)($row['action_required_count'] ?? 0);
            $summary['over_sla'] = $summary['action_required'];
            $summary['resolved_today'] = (int)($row['resolved_today_count'] ?? 0);
        }
        $oldest = $this->conn->query("
            SELECT id, report_number, title, created_at, waiting_until
            FROM tracs_abuse_reports
            WHERE status NOT IN ('resolved','closed')
            ORDER BY CASE WHEN status='action_taken' OR (waiting_until IS NOT NULL AND waiting_until < NOW()) THEN 0 ELSE 1 END,
                     COALESCE(waiting_until, created_at) ASC
            LIMIT 1
        ");
        if ($oldest && ($row = $oldest->fetch_assoc())) {
            $summary['oldest_open'] = $row;
        }
        return $summary;
    }

    public function selectableUsers(): array {
        $where = "1=1";
        if (tracs_column_exists($this->conn, 'tracs_users', 'is_active')) {
            $where .= " AND is_active=1";
        }
        if (tracs_column_exists($this->conn, 'tracs_users', 'status')) {
            $where .= " AND COALESCE(status,'active')='active'";
        }
        $result = $this->conn->query("
            SELECT id, COALESCE(NULLIF(name,''), email, username) AS label
            FROM tracs_users
            WHERE {$where}
            ORDER BY label ASC
            LIMIT 300
        ");
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function relationshipOptions(): array {
        return [
            'domains' => $this->simpleOptions('tracs_domains', 'id', 'domain', 'domain'),
            'cases' => $this->simpleOptions('tracs_cases', 'id', 'title', 'id', 150, 'Case #'),
            'servers' => $this->simpleOptions('infrastructure_servers', 'id', 'name', 'name'),
        ];
    }

    private function simpleOptions(string $table, string $idColumn, string $labelColumn, string $orderColumn, int $limit = 150, string $prefix = ''): array {
        if (!tracs_table_exists($this->conn, $table) || !tracs_column_exists($this->conn, $table, $idColumn) || !tracs_column_exists($this->conn, $table, $labelColumn)) {
            return [];
        }
        $tableSql = tracs_identifier($table);
        $idSql = tracs_identifier($idColumn);
        $labelSql = tracs_identifier($labelColumn);
        $orderSql = tracs_identifier($orderColumn);
        $limit = max(1, min(300, $limit));
        $result = $this->conn->query("SELECT {$idSql} AS id, {$labelSql} AS label FROM {$tableSql} ORDER BY {$orderSql} ASC LIMIT {$limit}");
        if (!$result) {
            return [];
        }
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        if ($prefix !== '') {
            foreach ($rows as &$row) {
                $row['label'] = $prefix . (int)$row['id'] . ' - ' . (string)$row['label'];
            }
        }
        return $rows;
    }

    private function rawReport(int $id, bool $lock): ?array {
        $sql = "SELECT * FROM tracs_abuse_reports WHERE id = ? LIMIT 1" . ($lock ? " FOR UPDATE" : "");
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    private function formatReport(array $row): array {
        $id = (int)($row['id'] ?? 0);
        $status = self::normalizeStatus($row['status'] ?? 'incoming');
        $priority = self::normalizePriority($row['priority'] ?? 'medium');
        $sla = (string)($row['sla_due_at'] ?? '');
        $done = in_array($status, ['resolved', 'closed'], true);
        $overSla = !$done && $sla !== '' && strtotime($sla) !== false && strtotime($sla) < time();
        $waitingStarted = self::normalizeDateTime($row['waiting_started_at'] ?? null)
            ?? self::normalizeDateTime($row['created_at'] ?? null);
        $waitingHours = $this->waitingHours($row['waiting_hours'] ?? 24);
        $waitingUntil = self::normalizeDateTime($row['waiting_until'] ?? null) ?? $this->waitingDeadline($waitingStarted, $waitingHours);
        $waitingExpired = $waitingUntil !== null && strtotime($waitingUntil) !== false && strtotime($waitingUntil) < time();
        $actionRequired = !$done && ($status === 'action_taken' || $waitingExpired);
        $workflowStage = $done ? 'resolved' : ($actionRequired ? 'action_required' : $status);
        $row['id'] = $id;
        $row['report_number'] = (string)($row['report_number'] ?: self::reportNumber($id));
        $row['status'] = $status;
        $row['status_label'] = self::statusLabel($status);
        $row['workflow_stage'] = $workflowStage;
        $row['workflow_label'] = $workflowStage === 'action_required' ? 'Action Required' : self::statusLabel($status);
        $row['priority'] = $priority;
        $row['assigned_staff'] = trim((string)($row['assigned_staff'] ?? $row['assigned_staff_name'] ?? ''));
        $row['evidence_count'] = (int)($row['evidence_count'] ?? 0);
        $row['ticket_status'] = self::normalizeTicketStatus($row['ticket_status'] ?? 'not_sent');
        $row['ticket_sent'] = $row['ticket_status'] === 'sent';
        $row['nameserver_saved'] = trim((string)($row['nameserver_snapshot'] ?? '')) !== '';
        $row['waiting_started_at'] = $waitingStarted;
        $row['waiting_hours'] = $waitingHours;
        $row['waiting_until'] = $waitingUntil;
        $row['action_required'] = $actionRequired;
        $row['waiting_label'] = $this->waitingLabel($waitingUntil, $done);
        $row['over_sla'] = $overSla;
        $row['sla_label'] = $sla !== '' ? $this->relativeTime($sla, 'SLA') : 'No SLA';
        $row['open_age'] = $this->relativeTime((string)($row['created_at'] ?? ''), 'Open', true);
        $row['created_display'] = !empty($row['created_at']) ? date('d M Y H:i', strtotime((string)$row['created_at'])) : '';
        $row['updated_display'] = !empty($row['updated_at']) ? date('d M Y H:i', strtotime((string)$row['updated_at'])) : '';
        $row['tag_list'] = array_values(array_filter(array_map('trim', explode(',', (string)($row['tags'] ?? '')))));
        return $row;
    }

    private function waitingLabel(?string $value, bool $done): string {
        if ($done) {
            return 'Completed';
        }
        if (!$value || strtotime($value) === false) {
            return 'No waiting deadline';
        }
        $target = new DateTimeImmutable($value);
        $now = new DateTimeImmutable('now');
        $past = $target < $now;
        $diff = $target->diff($now);
        if ($diff->d > 0) {
            $text = $diff->d . 'd ' . $diff->h . 'h';
        } elseif ($diff->h > 0) {
            $text = $diff->h . 'h ' . $diff->i . 'm';
        } else {
            $text = max(0, $diff->i) . 'm';
        }
        return $past ? $text . ' overdue' : $text . ' remaining';
    }

    private function relativeTime(string $value, string $prefix = '', bool $since = false): string {
        if ($value === '' || strtotime($value) === false) {
            return '—';
        }
        $target = new DateTimeImmutable($value);
        $now = new DateTimeImmutable('now');
        $past = $target < $now;
        $diff = $target->diff($now);
        if ($diff->d > 0) {
            $text = $diff->d . 'd ' . $diff->h . 'h';
        } elseif ($diff->h > 0) {
            $text = $diff->h . 'h ' . $diff->i . 'm';
        } else {
            $text = max(0, $diff->i) . 'm';
        }
        if ($since) {
            return $text;
        }
        return trim($prefix . ' ' . ($past ? 'over by ' : 'in ') . $text);
    }
}
