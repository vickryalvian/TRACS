<?php
/**
 * Checklist Module - Data Model
 * Handles all database queries for checklists/side tasks
 */

class ChecklistModel {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Get all side tasks (checklists) for the current user
     */
    public function getTasksByUser($user_id) {
        $query = "
            SELECT 
                t.id,
                t.title,
                t.description,
                t.is_completed,
                t.created_at,
                t.updated_at,
                t.created_by,
                t.created_by_name,
                COALESCE(NULLIF(t.created_by_name,''), NULLIF(u.name,''), u.email, 'System') AS creator_name
            FROM tracs_side_tasks t
            LEFT JOIN tracs_users u ON t.created_by = u.id
            WHERE t.user_id = ?
            ORDER BY t.is_completed ASC, t.created_at DESC
        ";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $tasks = [];
        while ($row = $result->fetch_assoc()) {
            $tasks[] = $row;
        }
        
        $stmt->close();
        return $tasks;
    }
    
    /**
     * Get a single side task
     */
    public function getTaskById($task_id, $user_id) {
        $query = "
            SELECT t.*, COALESCE(NULLIF(t.created_by_name,''), NULLIF(u.name,''), u.email, 'System') AS creator_name
            FROM tracs_side_tasks t
            LEFT JOIN tracs_users u ON t.created_by = u.id
            WHERE t.id = ? AND t.user_id = ?
        ";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param('ii', $task_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $task = $result->fetch_assoc();
        $stmt->close();
        
        return $task;
    }
    
    /**
     * Get incomplete tasks for today
     */
    public function getIncompleteTasks($user_id) {
        $query = "
            SELECT 
                t.id,
                t.title,
                t.description,
                t.is_completed,
                t.created_by,
                t.created_by_name,
                COALESCE(NULLIF(t.created_by_name,''), NULLIF(u.name,''), u.email, 'System') AS creator_name
            FROM tracs_side_tasks t
            LEFT JOIN tracs_users u ON t.created_by = u.id
            WHERE t.user_id = ?
            AND t.is_completed = 0
            ORDER BY t.created_at ASC
        ";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $tasks = [];
        while ($row = $result->fetch_assoc()) {
            $tasks[] = $row;
        }
        
        $stmt->close();
        return $tasks;
    }
    
    /**
     * Update task completion status
     */
    public function updateTaskStatus($task_id, $is_completed, $user_id) {
        $query = "
            UPDATE tracs_side_tasks
            SET is_completed = ?, updated_at = NOW()
            WHERE id = ? AND user_id = ?
        ";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param('iii', $is_completed, $task_id, $user_id);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    /**
     * Add note/log to side task
     */
    public function addTaskLog($task_id, $user_id, $note) {
        $query = "
            INSERT INTO tracs_side_task_logs (task_id, user_id, note, created_at)
            VALUES (?, ?, ?, NOW())
        ";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param('iis', $task_id, $user_id, $note);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    /**
     * Get task logs
     */
    public function getTaskLogs($task_id, $user_id, $limit = 5) {
        $query = "
            SELECT 
                id,
                note,
                created_at
            FROM tracs_side_task_logs
            WHERE task_id = ? 
            AND user_id = ?
            ORDER BY created_at DESC
            LIMIT ?
        ";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param('iii', $task_id, $user_id, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $logs = [];
        while ($row = $result->fetch_assoc()) {
            $logs[] = $row;
        }
        
        $stmt->close();
        return $logs;
    }
}
?>
