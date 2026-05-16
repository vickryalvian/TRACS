<?php
/**
 * Reminder Module - Controller
 * Handles business logic for reminders
 */

require_once __DIR__ . '/model.php';

class ReminderController {
    private $model;
    private $user_id;
    
    public function __construct($connection, $user_id) {
        $this->model = new ReminderModel($connection);
        $this->user_id = $user_id;
    }
    
    /**
     * Get all reminders
     */
    public function getReminders() {
        return $this->model->getRemindersByUser($this->user_id);
    }
    
    /**
     * Get overdue reminders only
     */
    public function getOverdueReminders() {
        return $this->model->getOverdueReminders($this->user_id);
    }
    
    /**
     * Get upcoming reminders
     */
    public function getUpcomingReminders($days = 7) {
        return $this->model->getUpcomingReminders($this->user_id, $days);
    }
    
    /**
     * Get today's reminders (due today)
     */
    public function getTodayReminders() {
        $query = "
            SELECT 
                id,
                title,
                due_date,
                priority,
                is_completed
            FROM tracs_reminders
            WHERE user_id = ?
            AND DATE(due_date) = CURDATE()
            AND is_completed = 0
            ORDER BY due_date ASC
        ";
        
        $stmt = $this->model->getConnection()->prepare($query);
        if (!$stmt) {
            return [];
        }
        
        $stmt->bind_param('i', $this->user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $reminders = [];
        while ($row = $result->fetch_assoc()) {
            $reminders[] = $row;
        }
        
        $stmt->close();
        return $reminders;
    }
    
    /**
     * Format reminder for display
     */
    public function formatReminder($reminder) {
        if (empty($reminder['due_date'])) {
            return array_merge($reminder, [
                'status'=>'—',
                'status_class'=>'',
                'priority_class'=>'',
                'description'=>$reminder['description']??'',
                'created_at' => $reminder['created_at'] ?? null,
                'updated_at' => $reminder['updated_at'] ?? null,
                'completed_at' => $reminder['completed_at'] ?? null,
                'archived_at' => $reminder['archived_at'] ?? null,
                'created_by' => $reminder['created_by'] ?? null,
                'created_by_name' => $reminder['created_by_name'] ?? null,
                'creator_name' => $reminder['creator_name'] ?? null,
            ]);
        }
        $dueDate = new DateTime($reminder['due_date']);
        $now = new DateTime();
        
        // Determine status text
        if ($dueDate < $now) {
            $status = 'Overdue';
            $statusClass = 'text-red-500';
        } elseif ($dueDate->format('Y-m-d') === $now->format('Y-m-d')) {
            $status = 'Today';
            $statusClass = 'text-orange-500';
        } else {
            $daysUntil = $now->diff($dueDate)->days;
            $status = $daysUntil === 1 ? 'Tomorrow' : 'in ' . $daysUntil . ' days';
            $statusClass = 'text-gray-400';
        }
        
        // Priority class
        $priorityClass = match($reminder['priority']) {
            'critical' => 'text-red-500',
            'high' => 'text-orange-500',
            'medium' => 'text-yellow-500',
            default => 'text-gray-500'
        };
        
        return [
            'id' => $reminder['id'],
            'title' => $reminder['title'],
            'due_date' => $reminder['due_date'],
            'priority' => $reminder['priority'],
            'is_completed' => $reminder['is_completed'],
            'created_at' => $reminder['created_at'] ?? null,
            'updated_at' => $reminder['updated_at'] ?? null,
            'completed_at' => $reminder['completed_at'] ?? null,
            'archived_at' => $reminder['archived_at'] ?? null,
            'created_by' => $reminder['created_by'] ?? null,
            'created_by_name' => $reminder['created_by_name'] ?? null,
            'creator_name' => $reminder['creator_name'] ?? null,
            'status' => $status,
            'status_class' => $statusClass,
            'priority_class' => $priorityClass,
            'description' => $reminder['description'] ?? '',
        ];
    }
}
?>
