<?php
/**
 * Ticker Events Module
 * Handles auto-generated operational events with expiry
 */

class TickerEventController {
    private $conn;

    public function __construct($connection) {
        $this->conn = $connection;
        $this->purgeExpired();
    }

    /**
     * Create a new ticker event with 1-hour expiry
     */
    public function create(int $uid, string $message, string $type = 'info', ?string $module = null, ?int $ref_id = null): bool {
        // Only run if table exists
        $test = $this->conn->query("SHOW TABLES LIKE 'tracs_ticker_events'");
        if (!$test || $test->num_rows === 0) return false;
        if (function_exists('tracs_ensure_creator_columns')) {
            tracs_ensure_creator_columns($this->conn, 'tracs_ticker_events', 'user_id');
        }

        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $creatorName = function_exists('tracs_current_user_display') ? tracs_current_user_display($this->conn) : '';
        
        $stmt = $this->conn->prepare("
            INSERT INTO tracs_ticker_events 
            (user_id, message, type, module, reference_id, expires_at, created_by, created_by_name) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        if (!$stmt) return false;
        
        $stmt->bind_param('isssisis', $uid, $message, $type, $module, $ref_id, $expires, $uid, $creatorName);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }

    /**
     * Get active (non-expired) events for ticker
     */
    public function getActive(int $uid): array {
        // Only run if table exists
        $test = $this->conn->query("SHOW TABLES LIKE 'tracs_ticker_events'");
        if (!$test || $test->num_rows === 0) return [];

        $stmt = $this->conn->prepare("
            SELECT id, message, type 
            FROM tracs_ticker_events 
            WHERE user_id = ? 
            AND expires_at > NOW() 
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        
        if (!$stmt) return [];
        
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $events = [];
        while ($row = $result->fetch_assoc()) {
            $events[] = $row;
        }
        
        $stmt->close();
        return $events;
    }

    /**
     * Cleanup expired events
     */
    private function purgeExpired(): void {
        // Only run if table exists
        $test = $this->conn->query("SHOW TABLES LIKE 'tracs_ticker_events'");
        if (!$test || $test->num_rows === 0) return;

        $this->conn->query("DELETE FROM tracs_ticker_events WHERE expires_at <= NOW()");
    }
}
