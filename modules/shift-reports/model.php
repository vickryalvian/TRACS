<?php
/**
 * Shift Reports Module - Data Model
 */

class ShiftReportModel {
    private $conn;

    public function __construct($connection) {
        $this->conn = $connection;
        $this->ensureResolvedSchema();
    }

    public function getTodayReports() {
        $query = "
            SELECT r.*, u.email AS creator_email, COALESCE(NULLIF(r.created_by_name,''), NULLIF(u.name,''), u.email, 'System') AS creator_name
            FROM tracs_shift_reports r
            LEFT JOIN tracs_users u ON r.created_by = u.id
            WHERE r.active_date = CURDATE()
            ORDER BY FIELD(r.status, 'active', 'on_hold', 'resolved') ASC,
                     FIELD(r.priority, 'critical', 'high', 'medium', 'low') ASC,
                     r.created_at DESC
        ";
        $result = $this->conn->query($query);
        $reports = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $reports[] = $row;
            }
        }
        return $reports;
    }

    public function getDashboardReports(string $viewerShift) {
        $includePreviousShift3 = in_array($viewerShift, ['Shift 1', 'Shift 2'], true);
        $where = "r.active_date = CURDATE()";
        if ($includePreviousShift3) {
            $where = "({$where} OR (r.active_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND r.shift_name = 'Shift 3'))";
        }

        $query = "
            SELECT r.*, u.email AS creator_email, COALESCE(NULLIF(r.created_by_name,''), NULLIF(u.name,''), u.email, 'System') AS creator_name
            FROM tracs_shift_reports r
            LEFT JOIN tracs_users u ON r.created_by = u.id
            WHERE {$where}
            ORDER BY r.active_date ASC,
                     FIELD(r.shift_name, 'Shift 1', 'Shift 2', 'Shift 3') ASC,
                     FIELD(r.status, 'active', 'on_hold', 'resolved') ASC,
                     FIELD(r.priority, 'critical', 'high', 'medium', 'low') ASC,
                     r.created_at DESC
        ";
        $result = $this->conn->query($query);
        $reports = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $reports[] = $row;
            }
        }
        return $reports;
    }

    public function getHistory($filters = [], $limit = 50, $offset = 0) {
        $where = ["1=1"];
        $params = [];
        $types = "";

        if (!empty($filters['date'])) {
            $where[] = "r.active_date = ?";
            $params[] = $filters['date'];
            $types .= "s";
        }
        if (!empty($filters['shift'])) {
            $where[] = "r.shift_name = ?";
            $params[] = $filters['shift'];
            $types .= "s";
        }
        if (!empty($filters['status'])) {
            $where[] = "r.status = ?";
            $params[] = $filters['status'];
            $types .= "s";
        }
        if (!empty($filters['priority'])) {
            $where[] = "r.priority = ?";
            $params[] = $filters['priority'];
            $types .= "s";
        }

        $whereClause = implode(" AND ", $where);
        
        $query = "
            SELECT r.*, u.email AS creator_email, COALESCE(NULLIF(r.created_by_name,''), NULLIF(u.name,''), u.email, 'System') AS creator_name
            FROM tracs_shift_reports r
            LEFT JOIN tracs_users u ON r.created_by = u.id
            WHERE $whereClause
            ORDER BY r.active_date DESC, r.created_at DESC
            LIMIT ? OFFSET ?
        ";

        $stmt = $this->conn->prepare($query);
        if (!$stmt) return [];

        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";

        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $reports = [];
        while ($row = $result->fetch_assoc()) {
            $reports[] = $row;
        }
        $stmt->close();
        return $reports;
    }

    public function create($data, $uid) {
        $status = $this->normalizeStatus($data['status'] ?? 'active');
        $visible = $this->visibleToNextShift($status);
        $resolvedAt = $status === 'resolved' ? $this->normalizeDateTime($data['resolved_at'] ?? null) : null;
        $resolutionNote = $status === 'resolved' ? trim((string)($data['resolution_note'] ?? '')) : null;
        $stmt = $this->conn->prepare("
            INSERT INTO tracs_shift_reports 
            (shift_name, title, details, priority, active_date, status, resolution_note, resolved_at, visible_to_next_shift, created_by, created_by_name, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        if (!$stmt) return false;

        $date = !empty($data['active_date']) ? $data['active_date'] : date('Y-m-d');

        $creatorName = $data['created_by_name'] ?? '';

        $stmt->bind_param('ssssssssiis', 
            $data['shift_name'], 
            $data['title'], 
            $data['details'], 
            $data['priority'],
            $date,
            $status,
            $resolutionNote,
            $resolvedAt,
            $visible,
            $uid,
            $creatorName
        );
        $success = $stmt->execute();
        $id = $success ? $stmt->insert_id : false;
        $stmt->close();
        return $id;
    }

    public function update($id, $data) {
        $status = $this->normalizeStatus($data['status'] ?? 'active');
        $visible = $this->visibleToNextShift($status);
        $resolvedAt = $status === 'resolved' ? $this->normalizeDateTime($data['resolved_at'] ?? null) : null;
        $resolutionNote = $status === 'resolved' ? trim((string)($data['resolution_note'] ?? '')) : null;
        $stmt = $this->conn->prepare("
            UPDATE tracs_shift_reports 
            SET shift_name=?, title=?, details=?, priority=?, active_date=?, status=?, resolution_note=?, resolved_at=?, visible_to_next_shift=?, updated_at=NOW() 
            WHERE id=?
        ");
        if (!$stmt) return false;

        $stmt->bind_param('ssssssssii', 
            $data['shift_name'], 
            $data['title'], 
            $data['details'], 
            $data['priority'], 
            $data['active_date'],
            $status,
            $resolutionNote,
            $resolvedAt,
            $visible,
            $id
        );
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function resolve($id, ?string $note = null, ?string $resolvedAt = null) {
        $resolvedAt = $this->normalizeDateTime($resolvedAt);
        $stmt = $this->conn->prepare("
            UPDATE tracs_shift_reports 
            SET status='resolved', resolution_note=COALESCE(NULLIF(?, ''), resolution_note), resolved_at=COALESCE(?, NOW()), visible_to_next_shift=1, updated_at=NOW() 
            WHERE id=?
        ");
        if (!$stmt) return false;

        $note = trim((string)$note);
        $stmt->bind_param('ssi', $note, $resolvedAt, $id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function delete($id) {
        $stmt = $this->conn->prepare("DELETE FROM tracs_shift_reports WHERE id=?");
        if (!$stmt) return false;
        $stmt->bind_param('i', $id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function getById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM tracs_shift_reports WHERE id=?");
        if (!$stmt) return null;
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    private function normalizeStatus(string $status): string {
        return in_array($status, ['active', 'on_hold', 'resolved'], true) ? $status : 'active';
    }

    private function visibleToNextShift(string $status): int {
        return in_array($status, ['active', 'on_hold', 'resolved'], true) ? 1 : 0;
    }

    private function normalizeDateTime(?string $value): ?string {
        $value = trim((string)$value);
        if ($value === '') return null;
        $ts = strtotime($value);
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }

    private function ensureResolvedSchema(): void {
        static $checked = false;
        if ($checked) return;
        $checked = true;
        if (!function_exists('tracs_column_exists')) return;
        $statusColumn = $this->conn->query("SHOW COLUMNS FROM tracs_shift_reports LIKE 'status'");
        $statusType = $statusColumn ? strtolower((string)($statusColumn->fetch_assoc()['Type'] ?? '')) : '';
        if (!str_contains($statusType, 'on_hold')) {
            $this->conn->query("ALTER TABLE tracs_shift_reports MODIFY COLUMN `status` ENUM('active','on_hold','resolved') NOT NULL DEFAULT 'active'");
        }
        if (!tracs_column_exists($this->conn, 'tracs_shift_reports', 'resolution_note')) {
            $this->conn->query("ALTER TABLE tracs_shift_reports ADD COLUMN `resolution_note` TEXT NULL AFTER `status`");
        }
        if (!tracs_column_exists($this->conn, 'tracs_shift_reports', 'visible_to_next_shift')) {
            $this->conn->query("ALTER TABLE tracs_shift_reports ADD COLUMN `visible_to_next_shift` TINYINT(1) NOT NULL DEFAULT 1 AFTER `resolved_at`");
        }
    }
}
