<?php
/**
 * Alert Ticker Module - Data Model
 * Handles critical alerts and urgent cases for ticker display
 */

class AlertTickerModel {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Get critical alerts for ticker display
     * Includes: critical priority cases, overdue cases, stuck cases
     */
    public function getAlerts($user_id) {
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
                OR (next_check_at < NOW() AND status != 'completed')
                OR status = 'stuck'
            )
            ORDER BY 
                CASE 
                    WHEN priority = 'critical' THEN 1
                    WHEN status = 'stuck' THEN 2
                    WHEN next_check_at < NOW() THEN 3
                    ELSE 4
                END ASC,
                next_check_at ASC
            LIMIT 20
        ";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $alerts = [];
        while ($row = $result->fetch_assoc()) {
            $alerts[] = $row;
        }
        
        $stmt->close();
        return $alerts;
    }
    
    /**
     * Get count of critical alerts
     */
    public function getCriticalCount($user_id) {
        $query = "
            SELECT COUNT(*) as count FROM tracs_cases
            WHERE user_id = ?
            AND (
                priority = 'critical'
                OR (next_check_at < NOW() AND status != 'completed')
                OR status = 'stuck'
            )
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
    
    /**
     * Get overdue cases count
     */
    public function getOverdueCount($user_id) {
        $query = "
            SELECT COUNT(*) as count FROM tracs_cases
            WHERE user_id = ?
            AND next_check_at < NOW()
            AND status != 'completed'
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
    
    /**
     * Get stuck cases count
     */
    public function getStuckCount($user_id) {
        $query = "
            SELECT COUNT(*) as count FROM tracs_cases
            WHERE user_id = ?
            AND status = 'stuck'
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
