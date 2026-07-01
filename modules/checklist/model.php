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
     * Get all side tasks (checklists).
     *
     * The Operational Checklist is a shared team object: every authenticated
     * user sees every item regardless of who created it. The $user_id argument
     * is retained for signature compatibility but intentionally NOT used to
     * filter — scoping reads to the creator was the production visibility bug.
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
            ORDER BY t.is_completed ASC, t.created_at DESC
        ";

        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }

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
        // Shared checklist: any user may view any item (no owner scoping).
        $query = "
            SELECT t.*, COALESCE(NULLIF(t.created_by_name,''), NULLIF(u.name,''), u.email, 'System') AS creator_name
            FROM tracs_side_tasks t
            LEFT JOIN tracs_users u ON t.created_by = u.id
            WHERE t.id = ?
        ";

        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('i', $task_id);
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
            WHERE t.is_completed = 0
            ORDER BY t.created_at ASC
        ";

        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }

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
        // Shared checklist: any authenticated user may toggle any item
        // (consistent with public/api/task-toggle.php, which toggles by id).
        $query = "
            UPDATE tracs_side_tasks
            SET is_completed = ?, updated_at = NOW()
            WHERE id = ?
        ";

        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('ii', $is_completed, $task_id);
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
            ORDER BY created_at DESC
            LIMIT ?
        ";

        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('ii', $task_id, $limit);
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
