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
     * Reminders are fully public: every authenticated user sees every
     * reminder, regardless of who created it. $user_id is unused but kept
     * for backward-compatible call sites.
     */
    public function getRemindersByUser($user_id) {
        $completedAtSelect = $this->columnExists('tracs_reminders', 'completed_at') ? 'r.completed_at,' : 'NULL AS completed_at,';
        $archivedAtSelect = $this->columnExists('tracs_reminders', 'archived_at') ? 'r.archived_at,' : 'NULL AS archived_at,';
        $query = "
            SELECT
                r.id,
                r.title,
                r.description,
                r.due_date,
                r.priority,
                r.is_completed,
                {$completedAtSelect}
                {$archivedAtSelect}
                r.created_at,
                r.updated_at,
                r.created_by,
                r.created_by_name,
                COALESCE(NULLIF(r.created_by_name,''), NULLIF(u.name,''), u.email, 'System') AS creator_name
            FROM tracs_reminders r
            LEFT JOIN tracs_users u ON r.created_by = u.id
            ORDER BY r.is_completed ASC, r.created_at DESC
        ";

        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $reminders = [];
        while ($row = $result->fetch_assoc()) {
            $reminders[] = $row;
        }

        $stmt->close();
        return $reminders;
    }

    private function columnExists(string $table, string $column): bool {
        $stmt = $this->conn->prepare("
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
            LIMIT 1
        ");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $exists;
    }
    
    /**
     * Get overdue reminders
     */
    public function getOverdueReminders($user_id) {
        $query = "
            SELECT 
                r.id,
                r.title,
                r.due_date,
                r.priority,
                r.created_by,
                r.created_by_name,
                COALESCE(NULLIF(r.created_by_name,''), NULLIF(u.name,''), u.email, 'System') AS creator_name
            FROM tracs_reminders r
            LEFT JOIN tracs_users u ON r.created_by = u.id
            WHERE r.user_id = ?
            AND r.due_date < NOW()
            AND r.is_completed = 0
            ORDER BY r.due_date ASC
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
                r.id,
                r.title,
                r.due_date,
                r.priority,
                r.is_completed,
                r.created_by,
                r.created_by_name,
                COALESCE(NULLIF(r.created_by_name,''), NULLIF(u.name,''), u.email, 'System') AS creator_name
            FROM tracs_reminders r
            LEFT JOIN tracs_users u ON r.created_by = u.id
            WHERE r.user_id = ?
            AND r.due_date >= CURDATE()
            AND r.due_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
            AND r.is_completed = 0
            ORDER BY r.due_date ASC
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
