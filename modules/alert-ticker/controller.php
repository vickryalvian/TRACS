<?php
require_once __DIR__ . '/model.php';
require_once __DIR__ . '/SmartTickerEngine.php';

class AlertTickerController {
    private $model;
    private $conn;
    private $user_id;

    public function __construct($connection, $user_id) {
        $this->model   = new AlertTickerModel($connection);
        $this->conn    = $connection;
        $this->user_id = $user_id;
    }

    public function getAlerts() { return $this->model->getAlerts($this->user_id); }

    public function getAlertStats() {
        return [
            'critical' => $this->model->getCriticalCount($this->user_id),
            'overdue'  => $this->model->getOverdueCount($this->user_id),
            'stuck'    => $this->model->getStuckCount($this->user_id),
        ];
    }

    /** Get custom user-defined ticker messages */
    public function getCustomMessages(): array {
        // Auto-create table if not exists
        $this->conn->query("CREATE TABLE IF NOT EXISTS tracs_ticker_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            text VARCHAR(500) NOT NULL,
            class ENUM('normal','info','urgent','critical') DEFAULT 'normal',
            enabled TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT NOW(),
            INDEX(user_id)
        )");
        $uid = (int)$this->user_id;
        $res = $this->conn->query("SELECT id,text,class FROM tracs_ticker_messages WHERE user_id=$uid AND enabled=1 ORDER BY created_at DESC");
        if (!$res) return [];
        $rows = [];
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        return $rows;
    }

    public function formatAlertsForTicker(): array {
        return (new SmartTickerEngine($this->conn, (int)$this->user_id))->formatForTicker();
    }

    public function getTickerMessage(): string {
        $stats = $this->getAlertStats();
        if (!$stats['critical'] && !$stats['overdue'] && !$stats['stuck'])
            return 'All systems operational';
        $parts = [];
        if ($stats['critical'] > 0) $parts[] = $stats['critical'].' critical';
        if ($stats['stuck']    > 0) $parts[] = $stats['stuck'].' stuck';
        if ($stats['overdue']  > 0) $parts[] = $stats['overdue'].' overdue';
        return implode(' · ', $parts);
    }
}
