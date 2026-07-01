<?php
/**
 * Checklist Module - Controller
 * Handles business logic for checklists
 */

require_once __DIR__ . '/model.php';

class ChecklistController {
    private $model;
    private $user_id;
    
    public function __construct($connection, $user_id) {
        $this->model = new ChecklistModel($connection);
        $this->user_id = $user_id;
    }
    
    /**
     * Get all tasks for the user
     */
    public function getTasks() {
        return $this->model->getTasksByUser($this->user_id);
    }

    /**
     * Change signature of the shared checklist (for the real-time poller).
     */
    public function getSignature() {
        return $this->model->getSignature();
    }

    /**
     * Get incomplete tasks only
     */
    public function getIncompleteTasks() {
        return $this->model->getIncompleteTasks($this->user_id);
    }
    
    /**
     * Get task details with logs
     */
    public function getTaskWithLogs($task_id) {
        $task = $this->model->getTaskById($task_id, $this->user_id);
        if (!$task) {
            return null;
        }
        
        $task['logs'] = $this->model->getTaskLogs($task_id, $this->user_id);
        return $task;
    }
    
    /**
     * Update task status
     */
    public function updateTaskStatus($task_id, $is_completed) {
        return $this->model->updateTaskStatus($task_id, $is_completed, $this->user_id);
    }
    
    /**
     * Add note to task
     */
    public function addNote($task_id, $note) {
        return $this->model->addTaskLog($task_id, $this->user_id, $note);
    }
    
    /**
     * Get task completion percentage
     */
    public function getCompletionPercentage() {
        $allTasks = $this->model->getTasksByUser($this->user_id);
        if (empty($allTasks)) {
            return 0;
        }
        
        $completedCount = array_sum(array_map(function($task) {
            return $task['is_completed'] ? 1 : 0;
        }, $allTasks));
        
        return round(($completedCount / count($allTasks)) * 100);
    }
}
?>
