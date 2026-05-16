<?php
/**
 * Activity Log Module - Controller
 * Handles business logic for activity logging
 */

require_once __DIR__ . '/model.php';

class ActivityLogController {
    private $model;
    private $user_id;
    
    public function __construct($connection, $user_id) {
        $this->model = new ActivityLogModel($connection);
        $this->user_id = $user_id;
    }
    
    /**
     * Get recent activities
     */
    public function getRecentActivity($limit = 10) {
        return $this->model->getActivityByUser($this->user_id, $limit);
    }
    
    /**
     * Get activities by module
     */
    public function getActivityByModule($module, $limit = 10) {
        return $this->model->getActivityByModule($this->user_id, $module, $limit);
    }
    
    /**
     * Log an activity
     */
    public function logActivity($action, $module, $description, $reference_id = null) {
        return $this->model->logActivity($this->user_id, $action, $module, $description, $reference_id);
    }
    
    /**
     * Get activity count today
     */
    public function getTodayCount() {
        return $this->model->getActivityCountToday($this->user_id);
    }
    
    /**
     * Format activity for display
     */
    public function formatActivity($activity) {
        $createdAt = new DateTime($activity['created_at']);
        $now = new DateTime();
        $diff = $now->diff($createdAt);
        
        // Determine relative time
        if ($diff->d > 0) {
            $timeAgo = $diff->d . 'd ago';
        } elseif ($diff->h > 0) {
            $timeAgo = $diff->h . 'h ago';
        } elseif ($diff->i > 0) {
            $timeAgo = $diff->i . 'm ago';
        } else {
            $timeAgo = 'just now';
        }
        
        // Action icon mapping
        $actionIcons = [
            'created' => 'plus-circle',
            'updated' => 'edit-2',
            'deleted' => 'trash-2',
            'completed' => 'check-circle',
            'marked' => 'pin',
        ];
        
        $icon = $actionIcons[$activity['action']] ?? 'file-text';
        
        return [
            'id' => $activity['id'],
            'action' => $activity['action'],
            'description' => $activity['description'],
            'module' => $activity['module'],
            'reference_id' => $activity['reference_id'],
            'created_at' => $activity['created_at'],
            'created_by' => $activity['created_by'] ?? null,
            'creator_name' => $activity['creator_name'] ?? null,
            'time_ago' => $timeAgo,
            'icon' => $icon,
        ];
    }
}
?>
