<?php
/**
 * Case Module - Data Model
 * Handles all database queries for cases
 */

class CaseModel {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Get all cases for the current user, sorted by next_check_at
     */
    public function getCasesByUser($user_id) {
        $query = "
            SELECT 
                id, 
                title, 
                status, 
                priority, 
                next_check_at,
                created_at,
                updated_at
            FROM tracs_cases
            WHERE user_id = ?
            ORDER BY next_check_at ASC
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
            SELECT * FROM tracs_cases
            WHERE id = ? AND user_id = ?
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
                id,
                title,
                status,
                priority,
                next_check_at
            FROM tracs_cases
            WHERE user_id = ?
            AND (
                priority = 'critical'
                OR next_check_at < NOW()
                OR status = 'stuck'
            )
            ORDER BY priority DESC, next_check_at ASC
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
                id,
                title,
                status,
                priority,
                next_check_at
            FROM tracs_cases
            WHERE user_id = ?
            AND DATE(next_check_at) = CURDATE()
            ORDER BY next_check_at ASC
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
