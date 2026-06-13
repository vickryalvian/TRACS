<?php
require_once __DIR__ . '/../../core/creator_tracking.php';

/**
 * Case Module - Data Model
 * Handles all database queries for cases
 */

class CaseModel {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
        if (function_exists('tracs_ensure_case_status_values')) {
            tracs_ensure_case_status_values($this->conn);
        }
    }

    private function ensureAttachmentTable(): bool {
        try {
            return (bool)$this->conn->query("
            CREATE TABLE IF NOT EXISTS `case_attachments` (
              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `case_id` INT NOT NULL,
              `original_filename` VARCHAR(255) NOT NULL,
              `stored_filename` VARCHAR(255) NOT NULL,
              `thumbnail_filename` VARCHAR(255) NOT NULL,
              `file_path` VARCHAR(255) NOT NULL,
              `thumbnail_path` VARCHAR(255) NOT NULL,
              `mime_type` VARCHAR(100) NOT NULL,
              `file_size` INT UNSIGNED NOT NULL,
              `uploaded_by` INT NOT NULL,
              `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_case_attachments_case` (`case_id`),
              KEY `idx_case_attachments_uploaded_by` (`uploaded_by`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (Throwable $e) {
            error_log('TRACS case attachment table ensure failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all cases for the current user, sorted by next_check_at
     */
    public function getCasesByUser($user_id) {
        $hasAttachments = $this->ensureAttachmentTable();
        $attachmentSelect = $hasAttachments ? 'COALESCE(ac.attachment_count, 0)' : '0';
        $attachmentJoin = $hasAttachments ? "
            LEFT JOIN (
                SELECT case_id, COUNT(*) AS attachment_count
                FROM case_attachments
                GROUP BY case_id
            ) ac ON ac.case_id = c.id
        " : '';
        $query = "
            SELECT 
                c.id, 
                c.title, 
                c.status, 
                c.priority, 
                c.next_check_at,
                c.notes,
                c.created_at,
                c.updated_at,
                c.created_by,
                c.created_by_name,
                {$attachmentSelect} AS attachment_count,
                COALESCE(NULLIF(c.created_by_name,''), NULLIF(u.name,''), u.email, 'System') AS creator_name
            FROM tracs_cases c
            LEFT JOIN tracs_users u ON c.created_by = u.id
            {$attachmentJoin}
            WHERE c.user_id = ?
            ORDER BY FIELD(c.status, 'stuck', 'active', 'in_progress', 'pending', 'on_hold', 'completed'), c.next_check_at ASC, c.updated_at DESC
        ";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $cases = [];
        while ($row = $result->fetch_assoc()) {
            $cases[] = $row;
        }
        
        $stmt->close();
        return $cases;
    }
    
    /**
     * Get a single case by ID
     */
    public function getCaseById($case_id, $user_id) {
        $query = "
            SELECT c.*, COALESCE(NULLIF(c.created_by_name,''), NULLIF(u.name,''), u.email, 'System') AS creator_name
            FROM tracs_cases c
            LEFT JOIN tracs_users u ON c.created_by = u.id
            WHERE c.id = ? AND c.user_id = ?
        ";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param('ii', $case_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $case = $result->fetch_assoc();
        $stmt->close();
        
        return $case;
    }
    
    /**
     * Get critical and overdue cases for alert ticker
     */
    public function getAlertCases($user_id) {
        $query = "
            SELECT 
                c.id,
                c.title,
                c.status,
                c.priority,
                c.next_check_at,
                c.created_by,
                c.created_by_name,
                COALESCE(NULLIF(c.created_by_name,''), NULLIF(u.name,''), u.email, 'System') AS creator_name
            FROM tracs_cases c
            LEFT JOIN tracs_users u ON c.created_by = u.id
            WHERE c.user_id = ?
            AND (
                c.priority = 'critical'
                OR c.next_check_at < NOW()
                OR c.status = 'stuck'
            )
            ORDER BY c.priority DESC, c.next_check_at ASC
            LIMIT 10
        ";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $cases = [];
        while ($row = $result->fetch_assoc()) {
            $cases[] = $row;
        }
        
        $stmt->close();
        return $cases;
    }
    
    /**
     * Get cases due today (for Today Tasks widget)
     */
    public function getCasesFromToday($user_id) {
        $query = "
            SELECT 
                c.id,
                c.title,
                c.status,
                c.priority,
                c.next_check_at,
                c.created_by,
                c.created_by_name,
                COALESCE(NULLIF(c.created_by_name,''), NULLIF(u.name,''), u.email, 'System') AS creator_name
            FROM tracs_cases c
            LEFT JOIN tracs_users u ON c.created_by = u.id
            WHERE c.user_id = ?
            AND DATE(c.next_check_at) = CURDATE()
            ORDER BY c.next_check_at ASC
        ";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $cases = [];
        while ($row = $result->fetch_assoc()) {
            $cases[] = $row;
        }
        
        $stmt->close();
        return $cases;
    }
    
    /**
     * Update case status
     */
    public function updateCaseStatus($case_id, $status, $user_id) {
        $query = "
            UPDATE tracs_cases
            SET status = ?, updated_at = NOW()
            WHERE id = ? AND user_id = ?
        ";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param('sii', $status, $case_id, $user_id);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    /**
     * Update case next_check_at
     */
    public function updateNextCheckAt($case_id, $next_check_at, $user_id) {
        $query = "
            UPDATE tracs_cases
            SET next_check_at = ?, updated_at = NOW()
            WHERE id = ? AND user_id = ?
        ";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param('sii', $next_check_at, $case_id, $user_id);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
}
?>
