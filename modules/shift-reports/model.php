<?php
/**
 * Shift Reports Module - Data Model
 */

class ShiftReportModel {
    private $conn;

    public function __construct($connection) {
        $this->conn = $connection;
    }

    public function getTodayReports() {
        $query = "
            SELECT r.*, u.email AS creator_email, COALESCE(NULLIF(r.created_by_name,''), NULLIF(u.name,''), u.email, 'System') AS creator_name
            FROM tracs_shift_reports r
            LEFT JOIN tracs_users u ON r.created_by = u.id
            WHERE r.active_date = CURDATE()
            ORDER BY r.status ASC, 
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
        $stmt = $this->conn->prepare("
            INSERT INTO tracs_shift_reports 
            (shift_name, title, details, priority, active_date, status, created_by, created_by_name, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, 'active', ?, ?, NOW(), NOW())
        ");
        if (!$stmt) return false;

        $date = !empty($data['active_date']) ? $data['active_date'] : date('Y-m-d');

        $creatorName = $data['created_by_name'] ?? '';

        $stmt->bind_param('sssssis', 
            $data['shift_name'], 
            $data['title'], 
            $data['details'], 
            $data['priority'],
            $date,
            $uid,
            $creatorName
        );
        $success = $stmt->execute();
        $id = $success ? $stmt->insert_id : false;
        $stmt->close();
        return $id;
    }

    public function update($id, $data) {
        $stmt = $this->conn->prepare("
            UPDATE tracs_shift_reports 
            SET shift_name=?, title=?, details=?, priority=?, active_date=?, updated_at=NOW() 
            WHERE id=?
        ");
        if (!$stmt) return false;

        $stmt->bind_param('sssssi', 
            $data['shift_name'], 
            $data['title'], 
            $data['details'], 
            $data['priority'], 
            $data['active_date'],
            $id
        );
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function resolve($id) {
        $stmt = $this->conn->prepare("
            UPDATE tracs_shift_reports 
            SET status='resolved', resolved_at=NOW(), updated_at=NOW() 
            WHERE id=?
        ");
        if (!$stmt) return false;

        $stmt->bind_param('i', $id);
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
}
