<?php
/**
 * TRACS — Cancellation Feedback Model
 */
class CancellationFeedbackModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function create($data) {
        $sql = "INSERT INTO tracs_cancellation_feedback 
                (submitter_name, cancelled_service, cancellation_reason, additional_details, whmcs_reference, email_address, payment_resolution) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("sssssss", 
            $data['submitter_name'], 
            $data['cancelled_service'], 
            $data['cancellation_reason'], 
            $data['additional_details'], 
            $data['whmcs_reference'], 
            $data['email_address'], 
            $data['payment_resolution']
        );
        
        if ($stmt->execute()) {
            return $this->db->insert_id;
        }
        return false;
    }

    public function getById($id) {
        $sql = "SELECT * FROM tracs_cancellation_feedback WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    public function list($filters = [], $limit = 50, $offset = 0) {
        $sql = "SELECT * FROM tracs_cancellation_feedback WHERE 1=1";
        $params = [];
        $types = "";

        if (!empty($filters['q'])) {
            $sql .= " AND (email_address LIKE ? OR whmcs_reference LIKE ? OR submitter_name LIKE ? OR cancelled_service LIKE ?)";
            $q = "%" . $filters['q'] . "%";
            $params = array_merge($params, [$q, $q, $q, $q]);
            $types .= "ssss";
        }

        if (!empty($filters['service'])) {
            $sql .= " AND cancelled_service = ?";
            $params[] = $filters['service'];
            $types .= "s";
        }

        if (!empty($filters['reason'])) {
            $sql .= " AND cancellation_reason = ?";
            $params[] = $filters['reason'];
            $types .= "s";
        }

        if (!empty($filters['resolution'])) {
            $sql .= " AND payment_resolution = ?";
            $params[] = $filters['resolution'];
            $types .= "s";
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND created_at >= ?";
            $params[] = $filters['date_from'] . " 00:00:00";
            $types .= "s";
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND created_at <= ?";
            $params[] = $filters['date_to'] . " 23:59:59";
            $types .= "s";
        }

        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";

        $stmt = $this->db->prepare($sql);
        if ($params) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function count($filters = []) {
        $sql = "SELECT COUNT(*) as total FROM tracs_cancellation_feedback WHERE 1=1";
        $params = [];
        $types = "";

        if (!empty($filters['q'])) {
            $sql .= " AND (email_address LIKE ? OR whmcs_reference LIKE ? OR submitter_name LIKE ? OR cancelled_service LIKE ?)";
            $q = "%" . $filters['q'] . "%";
            $params = array_merge($params, [$q, $q, $q, $q]);
            $types .= "ssss";
        }

        // ... repeat filters as above ...
        if (!empty($filters['service'])) { $sql .= " AND cancelled_service = ?"; $params[] = $filters['service']; $types .= "s"; }
        if (!empty($filters['reason'])) { $sql .= " AND cancellation_reason = ?"; $params[] = $filters['reason']; $types .= "s"; }
        if (!empty($filters['resolution'])) { $sql .= " AND payment_resolution = ?"; $params[] = $filters['resolution']; $types .= "s"; }

        $stmt = $this->db->prepare($sql);
        if ($params) { $stmt->bind_param($types, ...$params); }
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc()['total'];
    }

    public function update($id, $data) {
        $sql = "UPDATE tracs_cancellation_feedback SET 
                submitter_name = ?, cancelled_service = ?, cancellation_reason = ?, 
                additional_details = ?, whmcs_reference = ?, email_address = ?, 
                payment_resolution = ? 
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("sssssssi", 
            $data['submitter_name'], 
            $data['cancelled_service'], 
            $data['cancellation_reason'], 
            $data['additional_details'], 
            $data['whmcs_reference'], 
            $data['email_address'], 
            $data['payment_resolution'],
            $id
        );
        
        return $stmt->execute();
    }

    public function delete($id) {
        $sql = "DELETE FROM tracs_cancellation_feedback WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    public function getAnalytics($month = null) {
        if (!$month) $month = date('Y-m');
        
        $analytics = [];

        // Most Cancelled Service
        $sql = "SELECT cancelled_service, COUNT(*) as count 
                FROM tracs_cancellation_feedback 
                WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
                GROUP BY cancelled_service 
                ORDER BY count DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("s", $month);
        $stmt->execute();
        $analytics['top_service'] = $stmt->get_result()->fetch_assoc();

        // Most Selected Reason
        $sql = "SELECT cancellation_reason, COUNT(*) as count 
                FROM tracs_cancellation_feedback 
                WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
                GROUP BY cancellation_reason 
                ORDER BY count DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("s", $month);
        $stmt->execute();
        $analytics['top_reason'] = $stmt->get_result()->fetch_assoc();

        // Most Used Resolution
        $sql = "SELECT payment_resolution, COUNT(*) as count 
                FROM tracs_cancellation_feedback 
                WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
                GROUP BY payment_resolution 
                ORDER BY count DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("s", $month);
        $stmt->execute();
        $analytics['top_resolution'] = $stmt->get_result()->fetch_assoc();

        return $analytics;
    }
}
