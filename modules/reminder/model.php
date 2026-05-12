<?php
/**
 * Reminder Module - Data Model
 * Handles all database queries for reminders
 */

class ReminderModel {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Get all reminders for the current user
     */
    public function getRemindersByUser($user_id) {
        $query = "
            SELECT 
                id,
                title,
                description,
                due_date,
                priority,
                is_completed,
                created_at
            FROM tracs_reminders
            WHERE user_id = ?
            ORDER BY due_date ASC
        ";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param('i', $user_id);
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
     * Get overdue reminders
     */
    public function getOverdueReminders($user_id) {
        $query = "
            SELECT 
                id,
                title,
                due_date,
                priority
            FROM tracs_reminders
            WHERE user_id = ?
            AND due_date < NOW()
            AND is_completed = 0
            ORDER BY due_date ASC
        ";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param('i', $user_id);
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
     * Get upcoming reminders (due today or later)
     */
    public function getUpcomingReminders($user_id, $days = 7) {
        $query = "
            SELECT 
                id,
                title,
                due_date,
                priority,
                is_completed
            FROM tracs_reminders
            WHERE user_id = ?
            AND due_date >= CURDATE()
            AND due_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
            AND is_completed = 0
            ORDER BY due_date ASC
        ";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param('ii', $user_id, $days);
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
     * Mark reminder as completed
     */
    public function markAsCompleted($reminder_id, $user_id) {
        $query = "
            UPDATE tracs_reminders
            SET is_completed = 1, updated_at = NOW()
            WHERE id = ? AND user_id = ?
        ";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param('ii', $reminder_id, $user_id);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    /**
     * Mark reminder as incomplete
     */
    public function markAsIncomplete($reminder_id, $user_id) {
        $query = "
            UPDATE tracs_reminders
            SET is_completed = 0, updated_at = NOW()
            WHERE id = ? AND user_id = ?
        ";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param('ii', $reminder_id, $user_id);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
}
?>
