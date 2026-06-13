<?php
/**
 * Case Module - Controller
 * Handles business logic for cases
 */

require_once __DIR__ . '/model.php';

class CaseController {
    private $model;
    private $user_id;
    
    public function __construct($connection, $user_id) {
        $this->model = new CaseModel($connection);
        $this->user_id = $user_id;
    }
    
    /**
     * Get all cases for dashboard display
     */
    public function getCases() {
        return $this->model->getCasesByUser($this->user_id);
    }
    
    /**
     * Get alert cases for ticker
     */
    public function getAlertCases() {
        return $this->model->getAlertCases($this->user_id);
    }
    
    /**
     * Get cases due today
     */
    public function getCasesToday() {
        return $this->model->getCasesFromToday($this->user_id);
    }
    
    /**
     * Format case for display (with time until next check)
     */
    public function formatCase($case) {
        $now = new DateTime();

        $priorityClass = match($case['priority'] ?? 'low') {
            'critical', 'high' => 'priority-high',
            'medium' => 'priority-medium',
            default => 'priority-low'
        };
        $statusClass = match($case['status'] ?? 'pending') {
            'active'    => 'active',
            'in_progress' => 'in_progress',
            'pending'   => 'pending',
            'stuck'     => 'stuck',
            'on_hold'   => 'pending',
            'completed' => 'active',
            default     => 'pending'
        };

        if (empty($case['next_check_at'])) {
            return [
                'id'             => $case['id'],
                'title'          => $case['title'],
                'status'         => $case['status'] ?? 'pending',
                'priority'       => $case['priority'] ?? 'low',
                'next_check_at'  => null,
                'time_until'     => '—',
                'priority_class' => $priorityClass,
                'status_class'   => $statusClass,
                'created_at'     => $case['created_at'],
                'updated_at'     => $case['updated_at'],
                'notes'          => $case['notes'] ?? '',
                'created_by'     => $case['created_by'] ?? null,
                'created_by_name' => $case['created_by_name'] ?? null,
                'creator_name'   => $case['creator_name'] ?? null,
                'attachment_count' => (int)($case['attachment_count'] ?? 0),
            ];
        }
        $nextCheck = new DateTime($case['next_check_at']);
        $diff = $nextCheck->diff($now);
        
        // Determine time display
        if ($nextCheck < $now) {
            $timeUntil = 'Overdue by ' . $this->formatInterval($diff);
        } else {
            $timeUntil = 'in ' . $this->formatInterval($diff);
        }
        
        return [
            'id' => $case['id'],
            'title' => $case['title'],
            'status' => $case['status'],
            'priority' => $case['priority'],
            'next_check_at' => $case['next_check_at'],
            'time_until' => $timeUntil,
            'priority_class' => $priorityClass,
            'status_class' => $statusClass,
            'created_at' => $case['created_at'],
            'updated_at' => $case['updated_at'],
            'notes' => $case['notes'] ?? '',
            'created_by' => $case['created_by'] ?? null,
            'created_by_name' => $case['created_by_name'] ?? null,
            'creator_name' => $case['creator_name'] ?? null,
            'attachment_count' => (int)($case['attachment_count'] ?? 0),
        ];
    }
    
    /**
     * Format time interval
     */
    private function formatInterval($diff) {
        if ($diff->d > 0) {
            return $diff->d . 'd ' . $diff->h . 'h';
        } elseif ($diff->h > 0) {
            return $diff->h . 'h ' . $diff->i . 'm';
        } else {
            return $diff->i . 'm';
        }
    }
}
?>
