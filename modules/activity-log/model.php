<?php
/**
 * Activity Log Module - Data Model
 * Handles all database queries for activity logs
 */

class ActivityLogModel {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Get recent activity logs for the user
     */
    public function getActivityByUser($user_id, $limit = 10) {
        $query = "
            SELECT 
                l.id,
                l.action,
                l.description,
                l.module,
                l.reference_id,
                l.created_at,
                l.user_id AS created_by,
                COALESCE(NULLIF(u.name,''), u.email, 'System') AS creator_name
            FROM tracs_activity_logs l
            LEFT JOIN tracs_users u ON l.user_id = u.id
            WHERE l.user_id = ?
            ORDER BY l.created_at DESC
            LIMIT ?
        ";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param('ii', $user_id, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $logs = [];
        while ($row = $result->fetch_assoc()) {
            $logs[] = $row;
        }
        
        $stmt->close();
        return $logs;
    }
    
    /**
     * Get activity logs for a specific module
     */
    public function getActivityByModule($user_id, $module, $limit = 10) {
        $query = "
            SELECT 
                l.id,
                l.action,
                l.description,
                l.module,
                l.reference_id,
                l.created_at,
                l.user_id AS created_by,
                COALESCE(NULLIF(u.name,''), u.email, 'System') AS creator_name
            FROM tracs_activity_logs l
            LEFT JOIN tracs_users u ON l.user_id = u.id
            WHERE l.user_id = ? AND l.module = ?
            ORDER BY l.created_at DESC
            LIMIT ?
        ";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param('isi', $user_id, $module, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $logs = [];
        while ($row = $result->fetch_assoc()) {
            $logs[] = $row;
        }
        
        $stmt->close();
        return $logs;
    }
    
    /**
     * Log an activity
     */
    public function logActivity($user_id, $action, $module, $description, $reference_id = null) {
        $query = "
            INSERT INTO tracs_activity_logs (user_id, action, module, description, reference_id, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param('isssi', $user_id, $action, $module, $description, $reference_id);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    /**
     * Get activity count today
     */
    public function getActivityCountToday($user_id) {
        $query = "
            SELECT COUNT(*) as count FROM tracs_activity_logs
            WHERE user_id = ? AND DATE(created_at) = CURDATE()
        ";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return 0;
        }
        
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return $row['count'] ?? 0;
    }
}
?>
