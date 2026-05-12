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
                id,
                title,
                description,
                is_completed,
                created_at,
                updated_at
            FROM tracs_side_tasks
            WHERE user_id = ?
            ORDER BY is_completed ASC, created_at DESC
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
            SELECT * FROM tracs_side_tasks
            WHERE id = ? AND user_id = ?
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
                id,
                title,
                description,
                is_completed
            FROM tracs_side_tasks
            WHERE user_id = ?
            AND is_completed = 0
            ORDER BY created_at ASC
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
