<?php
/**
 * TRACS Smart Ticker Engine
 * Rule-based operational intelligence feed for the live ticker.
 */

class SmartTickerEngine {
    private mysqli $conn;
    private int $uid;
    private array $tables = [];
    private array $columns = [];

    private const MAX_ITEMS = 18;
    private const RECENT_HOURS = 6;

    public function __construct(mysqli $connection, int $user_id) {
        $this->conn = $connection;
        $this->uid = $user_id;
    }

    public function buildFeed(): array {
        $items = [];

        $items = array_merge($items, $this->reminderItems());
        $items = array_merge($items, $this->checklistItems());
        $items = array_merge($items, $this->caseItems());
        $items = array_merge($items, $this->domainItems());
        $items = array_merge($items, $this->financeItems());
        $items = array_merge($items, $this->meetingItems());
        $items = array_merge($items, $this->shiftReportItems());
        $items = array_merge($items, $this->shiftingAssignmentItems());
        $items = array_merge($items, $this->tickerEventItems());
        $items = array_merge($items, $this->customMessageItems());

        $items = $this->dedupe($items);
        $items = $this->groupLowPriority($items);

        usort($items, function($a, $b) {
            $pa = $a['sort_weight'] ?? 99;
            $pb = $b['sort_weight'] ?? 99;
            if ($pa !== $pb) return $pa <=> $pb;
            return strtotime($b['created_at'] ?? 'now') <=> strtotime($a['created_at'] ?? 'now');
        });

        return array_slice($items, 0, self::MAX_ITEMS);
    }

    public function formatForTicker(): array {
        $feed = $this->buildFeed();
        if (!$feed) {
            return [[
                'text' => '[INFO] HIDUP JOKIW',
                'class' => 'normal',
                'type' => 'system',
                'priority' => 'info',
                'status' => 'active',
                'display_label' => 'Operational Intelligence'
            ]];
        }

        return array_map(function($item) {
            return [
                'id' => $item['id'] ?? null,
                'text' => $item['message'],
                'class' => $this->tickerClass($item['priority'] ?? 'info'),
                'type' => $item['type'] ?? 'info',
                'priority' => $item['priority'] ?? 'info',
                'status' => $item['status'] ?? 'updated',
                'display_label' => $item['display_label'] ?? '',
                'created_at' => $item['created_at'] ?? null,
                'expires_at' => $item['expires_at'] ?? null,
            ];
        }, $feed);
    }

    private function item(
        string $type,
        string $priority,
        string $status,
        string $label,
        string $message,
        ?string $created_at,
        ?string $expires_at = null,
        ?string $id = null
    ): array {
        $sortWeight = $this->sortWeight($priority, $status);
        if (in_array($type, ['reminder', 'checklist'], true) && !in_array($status, ['completed', 'archived'], true)) {
            $sortWeight = 0;
        }

        return [
            'id' => $id,
            'type' => $type,
            'priority' => $priority,
            'status' => $status,
            'display_label' => $label,
            'message' => $message,
            'created_at' => $created_at ?: date('Y-m-d H:i:s'),
            'expires_at' => $expires_at,
            'sort_weight' => $sortWeight,
        ];
    }

    private function reminderItems(): array {
        if (!$this->tableExists('tracs_reminders')) return [];

        $stmt = $this->conn->prepare("
            SELECT id, title, priority, due_date, created_at, updated_at
            FROM tracs_reminders
            WHERE user_id=?
              AND is_completed=0
              AND (
                priority IN ('critical','high')
                OR due_date <= DATE_ADD(NOW(), INTERVAL 24 HOUR)
                OR created_at >= DATE_SUB(NOW(), INTERVAL " . self::RECENT_HOURS . " HOUR)
              )
            ORDER BY
              CASE
                WHEN due_date IS NOT NULL AND due_date < NOW() THEN 1
                WHEN priority='critical' THEN 2
                WHEN due_date IS NOT NULL AND due_date <= DATE_ADD(NOW(), INTERVAL 2 HOUR) THEN 3
                WHEN priority='high' THEN 4
                ELSE 5
              END,
              due_date ASC
            LIMIT 8
        ");
        if (!$stmt) return [];
        $stmt->bind_param('i', $this->uid);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $items = [];
        foreach ($rows as $r) {
            $due = !empty($r['due_date']) ? strtotime($r['due_date']) : null;
            $created = $r['updated_at'] ?: $r['created_at'];
            if ($due && $due < time()) {
                $items[] = $this->item('reminder', 'critical', 'overdue', 'Overdue reminder', 'Overdue reminder: '.$r['title'], $created, null, 'reminder-'.$r['id']);
            } elseif ($due && $due <= strtotime('+2 hours')) {
                $prio = $r['priority'] === 'critical' ? 'critical' : 'high';
                $items[] = $this->item('reminder', $prio, 'due_soon', 'Reminder due soon', 'Reminder due soon: '.$r['title'], $created, null, 'reminder-'.$r['id']);
            } else {
                $prio = in_array($r['priority'], ['critical','high'], true) ? $r['priority'] : 'medium';
                $items[] = $this->item('reminder', $prio, 'pending', 'Unchecked reminder', 'Unchecked reminder: '.$r['title'], $created, null, 'reminder-'.$r['id']);
            }
        }
        return $items;
    }

    private function checklistItems(): array {
        if (!$this->tableExists('tracs_side_tasks')) return [];

        $stmt = $this->conn->prepare("
            SELECT id, title, description, created_at, updated_at
            FROM tracs_side_tasks
            WHERE user_id=? AND is_completed=0
            ORDER BY created_at ASC
            LIMIT 8
        ");
        if (!$stmt) return [];
        $stmt->bind_param('i', $this->uid);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $items = [];
        foreach ($rows as $r) {
            $items[] = $this->item('checklist', 'high', 'pending', 'Unchecked checklist', 'Unchecked checklist: '.$r['title'], $r['updated_at'] ?: $r['created_at'], null, 'checklist-'.$r['id']);
        }
        return $items;
    }

    private function caseItems(): array {
        if (!$this->tableExists('tracs_cases')) return [];

        $stmt = $this->conn->prepare("
            SELECT id, title, status, priority, next_check_at, created_at, updated_at
            FROM tracs_cases
            WHERE user_id=?
              AND status <> 'completed'
              AND (
                priority IN ('critical','high')
                OR status='stuck'
                OR next_check_at < NOW()
                OR updated_at >= DATE_SUB(NOW(), INTERVAL " . self::RECENT_HOURS . " HOUR)
                OR created_at >= DATE_SUB(NOW(), INTERVAL " . self::RECENT_HOURS . " HOUR)
              )
            ORDER BY
              CASE
                WHEN priority='critical' THEN 1
                WHEN status='stuck' THEN 2
                WHEN next_check_at < NOW() THEN 3
                WHEN priority='high' THEN 4
                ELSE 5
              END,
              updated_at DESC
            LIMIT 8
        ");
        if (!$stmt) return [];
        $stmt->bind_param('i', $this->uid);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $items = [];
        foreach ($rows as $r) {
            $created = $r['updated_at'] ?: $r['created_at'];
            if ($r['priority'] === 'critical') {
                $items[] = $this->item('case', 'critical', 'pending', 'Critical case', 'Critical case: '.$r['title'], $created, null, 'case-'.$r['id']);
            } elseif ($r['status'] === 'stuck') {
                $items[] = $this->item('case', 'critical', 'pending', 'Stuck case', 'Stuck case: '.$r['title'], $created, null, 'case-'.$r['id']);
            } elseif (!empty($r['next_check_at']) && strtotime($r['next_check_at']) < time()) {
                $items[] = $this->item('case', 'high', 'overdue', 'Case follow-up overdue', 'Overdue case follow-up: '.$r['title'], $created, null, 'case-'.$r['id']);
            } elseif (strtotime($r['created_at']) >= strtotime('-'.self::RECENT_HOURS.' hours')) {
                $items[] = $this->item('case', 'medium', 'new', 'New case', 'New case added: '.$r['title'], $created, null, 'case-'.$r['id']);
            } else {
                $items[] = $this->item('case', 'medium', 'updated', 'Case updated', 'Case updated: '.$r['title'].' moved to '.ucfirst($r['status']), $created, null, 'case-'.$r['id']);
            }
        }
        return $items;
    }

    private function domainItems(): array {
        $items = [];
        if ($this->tableExists('tracs_domains')) {
            $stmt = $this->conn->prepare("
                SELECT id, domain, expires_at, auto_renew, created_at, updated_at
                FROM tracs_domains
                WHERE user_id=?
                  AND (
                    (expires_at IS NOT NULL AND expires_at <= DATE_ADD(CURDATE(), INTERVAL 7 DAY))
                    OR updated_at >= DATE_SUB(NOW(), INTERVAL " . self::RECENT_HOURS . " HOUR)
                    OR created_at >= DATE_SUB(NOW(), INTERVAL " . self::RECENT_HOURS . " HOUR)
                  )
                ORDER BY expires_at ASC, updated_at DESC
                LIMIT 5
            ");
            if ($stmt) {
                $stmt->bind_param('i', $this->uid);
                $stmt->execute();
                foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
                    $created = $r['updated_at'] ?: $r['created_at'];
                    if (!empty($r['expires_at']) && strtotime($r['expires_at']) <= strtotime('+3 days')) {
                        $items[] = $this->item('domain', 'high', 'due_soon', 'Domain expiry risk', 'Domain expiry risk: '.$r['domain'], $created, null, 'domain-'.$r['id']);
                    } elseif (strtotime($r['created_at']) >= strtotime('-'.self::RECENT_HOURS.' hours')) {
                        $items[] = $this->item('domain', 'low', 'new', 'New domain', 'New domain added: '.$r['domain'], $created, null, 'domain-'.$r['id']);
                    } else {
                        $items[] = $this->item('domain', 'low', 'updated', 'Domain updated', 'Domain updated: '.$r['domain'], $created, null, 'domain-'.$r['id']);
                    }
                }
                $stmt->close();
            }
        }

        foreach (['domain_transfers', 'tracs_domain_transfers'] as $table) {
            if (!$this->tableExists($table)) continue;
            $hasUser = $this->columnExists($table, 'user_id');
            $timeColumn = $this->columnExists($table, 'updated_at') ? 'updated_at' : ($this->columnExists($table, 'created_at') ? 'created_at' : null);
            if (!$timeColumn) continue;
            $where = $hasUser
                ? "WHERE user_id=? AND {$timeColumn} >= DATE_SUB(NOW(), INTERVAL ".self::RECENT_HOURS." HOUR)"
                : "WHERE {$timeColumn} >= DATE_SUB(NOW(), INTERVAL ".self::RECENT_HOURS." HOUR)";
            $sql = "SELECT * FROM {$table} {$where} ORDER BY {$timeColumn} DESC LIMIT 4";
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) continue;
            if ($hasUser) $stmt->bind_param('i', $this->uid);
            $stmt->execute();
            foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
                $domain = $r['domain'] ?? $r['domain_name'] ?? ('transfer #'.($r['id'] ?? ''));
                $status = $r['status'] ?? 'updated';
                $items[] = $this->item('domain', 'medium', 'updated', 'Domain transfer updated', 'Domain transfer updated: '.$domain.' moved to '.ucfirst(str_replace('_',' ', $status)), $r['updated_at'] ?? $r['created_at'] ?? null, null, 'domain-transfer-'.($r['id'] ?? md5($domain)));
            }
            $stmt->close();
            break;
        }

        return $items;
    }

    private function financeItems(): array {
        $items = [];
        if ($this->tableExists('balance_transfers')) {
            $stmt = $this->conn->prepare("
                SELECT id, receiver_email, receiver_user_id, amount, status, transfer_date, created_at, updated_at
                FROM balance_transfers
                WHERE updated_at >= DATE_SUB(NOW(), INTERVAL " . self::RECENT_HOURS . " HOUR)
                   OR created_at >= DATE_SUB(NOW(), INTERVAL " . self::RECENT_HOURS . " HOUR)
                ORDER BY updated_at DESC
                LIMIT 4
            ");
            if ($stmt) {
                $stmt->execute();
                foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
                    $target = $r['receiver_email'] ?: ($r['receiver_user_id'] ?: 'recipient');
                    $prio = ($r['status'] ?? '') === 'pending' ? 'medium' : 'low';
                    $items[] = $this->item('finance', $prio, 'updated', 'Finance transfer updated', 'Finance transfer updated: '.number_format((float)$r['amount'], 0).' to '.$target.' is '.ucfirst($r['status']), $r['updated_at'] ?: $r['created_at'], null, 'bt-'.$r['id']);
                }
                $stmt->close();
            }
        }
        return $items;
    }

    private function meetingItems(): array {
        if (!$this->tableExists('tracs_moms')) return [];

        $stmt = $this->conn->prepare("
            SELECT id, title, status, type, meeting_at, completed_at, created_at, updated_at, summary
            FROM tracs_moms
            WHERE created_by=?
              AND (
                status='ongoing'
                OR type='urgent'
                OR (status='upcoming' AND meeting_at <= DATE_ADD(NOW(), INTERVAL 4 HOUR))
                OR updated_at >= DATE_SUB(NOW(), INTERVAL " . self::RECENT_HOURS . " HOUR)
                OR created_at >= DATE_SUB(NOW(), INTERVAL " . self::RECENT_HOURS . " HOUR)
              )
            ORDER BY updated_at DESC
            LIMIT 5
        ");
        if (!$stmt) return [];
        $stmt->bind_param('i', $this->uid);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $items = [];
        foreach ($rows as $r) {
            $created = $r['updated_at'] ?: $r['created_at'];
            if ($r['status'] === 'completed' && empty($r['summary'])) {
                $items[] = $this->item('meeting', 'medium', 'pending', 'MoM pending', 'Meeting completed: '.$r['title'].' — MoM pending', $created, null, 'mom-'.$r['id']);
            } elseif ($r['status'] === 'ongoing') {
                $items[] = $this->item('meeting', 'medium', 'pending', 'Meeting ongoing', 'Meeting ongoing: '.$r['title'], $created, null, 'mom-'.$r['id']);
            } elseif ($r['status'] === 'upcoming') {
                $items[] = $this->item('meeting', 'low', 'due_soon', 'Meeting soon', 'Meeting soon: '.$r['title'], $created, null, 'mom-'.$r['id']);
            } else {
                $items[] = $this->item('meeting', 'low', 'updated', 'Meeting updated', 'Meeting updated: '.$r['title'], $created, null, 'mom-'.$r['id']);
            }
        }
        return $items;
    }

    private function shiftReportItems(): array {
        if (!$this->tableExists('tracs_shift_reports')) return [];

        $stmt = $this->conn->prepare("
            SELECT id, title, priority, status, created_at, updated_at
            FROM tracs_shift_reports
            WHERE created_by=?
              AND (
                status='active'
                OR updated_at >= DATE_SUB(NOW(), INTERVAL " . self::RECENT_HOURS . " HOUR)
                OR created_at >= DATE_SUB(NOW(), INTERVAL " . self::RECENT_HOURS . " HOUR)
              )
            ORDER BY
              CASE WHEN priority='critical' THEN 1 WHEN priority='high' THEN 2 ELSE 3 END,
              updated_at DESC
            LIMIT 5
        ");
        if (!$stmt) return [];
        $stmt->bind_param('i', $this->uid);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $items = [];
        foreach ($rows as $r) {
            $prio = in_array($r['priority'], ['critical','high'], true) ? $r['priority'] : 'low';
            $status = $r['status'] === 'active' ? 'pending' : 'updated';
            $label = $r['status'] === 'active' ? 'Active shift report' : 'Shift report updated';
            $items[] = $this->item('shift_report', $prio, $status, $label, $label.': '.$r['title'], $r['updated_at'] ?: $r['created_at'], null, 'shift-'.$r['id']);
        }
        return $items;
    }

    private function shiftingAssignmentItems(): array {
        if (!$this->tableExists('shift_assignments')) return [];
        try {
            require_once __DIR__ . '/../shifting-assignment/ShiftingAssignmentService.php';
            $service = new ShiftingAssignmentService($this->conn, $this->uid);
            $items = [];
            foreach ($service->getOpsTrackSignals() as $signal) {
                $items[] = $this->item(
                    (string)($signal['type'] ?? 'workload'),
                    (string)($signal['priority'] ?? 'medium'),
                    (string)($signal['status'] ?? 'active'),
                    (string)($signal['label'] ?? 'Workforce schedule'),
                    (string)($signal['message'] ?? ''),
                    (string)($signal['created_at'] ?? date('Y-m-d H:i:s')),
                    null,
                    'shifting-' . hash('sha256', (string)($signal['message'] ?? ''))
                );
            }
            return $items;
        } catch (Throwable $e) {
            error_log('TRACS SmartTicker shifting assignment feed failed: ' . $e->getMessage());
            return [];
        }
    }

    private function tickerEventItems(): array {
        if (!$this->tableExists('tracs_ticker_events')) return [];

        $stmt = $this->conn->prepare("
            SELECT id, message, type, module, expires_at, created_at
            FROM tracs_ticker_events
            WHERE user_id=? AND (expires_at IS NULL OR expires_at > NOW())
            ORDER BY created_at DESC
            LIMIT 10
        ");
        if (!$stmt) return [];
        $stmt->bind_param('i', $this->uid);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $items = [];
        foreach ($rows as $r) {
            $prio = match($r['type']) {
                'critical' => 'critical',
                'warning' => 'medium',
                default => 'low'
            };
            $type = $this->normalizeType($r['module'] ?? 'info');
            $items[] = $this->item($type, $prio, 'updated', 'Operational update', $r['message'], $r['created_at'], $r['expires_at'], 'event-'.$r['id']);
        }
        return $items;
    }

    private function customMessageItems(): array {
        if (!$this->tableExists('tracs_ticker_messages')) return [];

        $stmt = $this->conn->prepare("
            SELECT id, text, class, created_at
            FROM tracs_ticker_messages
            WHERE user_id=? AND enabled=1
            ORDER BY created_at DESC
            LIMIT 8
        ");
        if (!$stmt) return [];
        $stmt->bind_param('i', $this->uid);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $items = [];
        foreach ($rows as $r) {
            $prio = match($r['class']) {
                'critical' => 'critical',
                'urgent' => 'high',
                'info' => 'low',
                default => 'info'
            };
            $items[] = $this->item('info', $prio, 'active', 'Manual announcement', $r['text'], $r['created_at'], null, 'custom-'.$r['id']);
        }
        return $items;
    }

    private function groupLowPriority(array $items): array {
        $domain = array_values(array_filter($items, fn($i) => ($i['type'] ?? '') === 'domain' && in_array($i['priority'] ?? '', ['low','info'], true)));
        $finance = array_values(array_filter($items, fn($i) => ($i['type'] ?? '') === 'finance' && in_array($i['priority'] ?? '', ['low','info'], true)));
        $removeIds = [];
        $grouped = [];

        if (count($domain) > 3) {
            foreach ($domain as $i) $removeIds[$i['id']] = true;
            $grouped[] = $this->item('domain', 'info', 'updated', 'Domain updates', count($domain).' low-priority domain updates recorded', date('Y-m-d H:i:s'), null, 'group-domain');
        }
        if (count($finance) > 3) {
            foreach ($finance as $i) $removeIds[$i['id']] = true;
            $grouped[] = $this->item('finance', 'info', 'updated', 'Finance updates', count($finance).' finance updates recorded', date('Y-m-d H:i:s'), null, 'group-finance');
        }

        if (!$removeIds) return $items;
        return array_merge(array_values(array_filter($items, fn($i) => empty($removeIds[$i['id'] ?? '']))), $grouped);
    }

    private function dedupe(array $items): array {
        $seen = [];
        $out = [];
        foreach ($items as $item) {
            $key = strtolower(trim(preg_replace('/\s+/', ' ', $item['message'] ?? '')));
            if ($key === '' || isset($seen[$key])) continue;
            $seen[$key] = true;
            $out[] = $item;
        }
        return $out;
    }

    private function sortWeight(string $priority, string $status): int {
        if ($priority === 'critical' || $status === 'overdue') return 1;
        if ($priority === 'high' || $status === 'due_soon') return 2;
        if (in_array($status, ['pending'], true)) return 3;
        if (in_array($status, ['updated'], true)) return 4;
        if (in_array($status, ['new'], true)) return 5;
        return 9;
    }

    private function tickerClass(string $priority): string {
        return match($priority) {
            'critical' => 'critical',
            'high', 'medium' => 'urgent',
            'low' => 'info',
            default => 'normal'
        };
    }

    private function normalizeType(string $module): string {
        $m = strtolower(str_replace('-', '_', $module));
        return match($m) {
            'cases' => 'case',
            'reminders' => 'reminder',
            'checklist' => 'checklist',
            'domains' => 'domain',
            'finance' => 'finance',
            'mom', 'meeting', 'meetings' => 'meeting',
            'shift_report', 'shift_reports' => 'shift_report',
            'shifting_assignment', 'workload', 'coverage', 'jumpshift', 'overtime', 'holiday' => $m,
            default => 'info'
        };
    }

    private function tableExists(string $table): bool {
        if (array_key_exists($table, $this->tables)) return $this->tables[$table];
        $stmt = $this->conn->prepare("
            SELECT 1
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
            LIMIT 1
        ");
        if (!$stmt) return $this->tables[$table] = false;
        $stmt->bind_param('s', $table);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $this->tables[$table] = $exists;
    }

    private function columnExists(string $table, string $column): bool {
        $key = $table.'.'.$column;
        if (array_key_exists($key, $this->columns)) return $this->columns[$key];
        if (!$this->tableExists($table)) return $this->columns[$key] = false;
        $stmt = $this->conn->prepare("
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
            LIMIT 1
        ");
        if (!$stmt) return $this->columns[$key] = false;
        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $this->columns[$key] = $exists;
    }
}
?>
