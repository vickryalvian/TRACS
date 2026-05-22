<?php
/**
 * MOM (Minutes of Meeting) Controller
 * Handles meeting creation, discussion tracking, decisions, actions, and integrations
 * 
 * Features:
 * - Meeting lifecycle management
 * - Agenda tracking
 * - Discussion notes with rich formatting
 * - Decision documentation
 * - Action item tracking with ownership
 * - Automatic reminder generation
 * - Case linking and cross-referencing
 * - Weekly meeting suggestions from unresolved cases
 * - Operational insights (SLA, escalation monitoring)
 */

class MOMController {
  private $conn;
  private $uid;

  public function __construct($conn, $uid) {
    $this->conn = $conn;
    $this->uid = (int)$uid;
  }

  private function tableExists($table) {
    try {
      $stmt = $this->conn->prepare("
        SELECT COUNT(*) AS total
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
      ");
      if(!$stmt) return false;
      $stmt->bind_param('s', $table);
      $stmt->execute();
      $row = $stmt->get_result()->fetch_assoc();
      return (int)($row['total'] ?? 0) > 0;
    } catch(Throwable $e) {
      return false;
    }
  }

  public function isInstalled() {
    $required = [
      'tracs_moms',
      'tracs_mom_agenda',
      'tracs_mom_notes',
      'tracs_mom_decisions',
      'tracs_mom_actions',
      'tracs_mom_case_links',
      'tracs_mom_screenshots',
      'tracs_mom_audit_log'
    ];
    foreach($required as $table) {
      if(!$this->tableExists($table)) return false;
    }
    return true;
  }

  private function requireInstalled() {
    if(!$this->isInstalled()) {
      throw new RuntimeException('MOM database tables are not installed. Run config/mom_database_schema.sql first.');
    }
  }

  private function hasColumn($table, $column) {
    $stmt = $this->conn->prepare("
      SELECT COUNT(*) AS total
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = ?
        AND COLUMN_NAME = ?
    ");
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int)($row['total'] ?? 0) > 0;
  }

  private function normalizeMeetingAt($meeting_at) {
    $meeting_at = trim((string)$meeting_at);
    if($meeting_at === '') return null;
    $ts = strtotime($meeting_at);
    return $ts ? date('Y-m-d H:i:s', $ts) : null;
  }

  private function normalizeMeetingUrl($meeting_url) {
    $meeting_url = trim((string)$meeting_url);
    if($meeting_url === '') return null;
    if(!preg_match('~^https?://~i', $meeting_url)) {
      $meeting_url = 'https://' . $meeting_url;
    }
    return filter_var($meeting_url, FILTER_VALIDATE_URL) ? substr($meeting_url, 0, 500) : null;
  }

  private function ensureMeetingAtColumn() {
    if($this->hasColumn('tracs_moms', 'meeting_at')) return true;

    try {
      $this->conn->query("ALTER TABLE tracs_moms ADD COLUMN meeting_at DATETIME DEFAULT NULL COMMENT 'Planned meeting date and time' AFTER participants");
      $this->conn->query("ALTER TABLE tracs_moms ADD INDEX idx_meeting_at (meeting_at)");
    } catch(Throwable $e) {
      return $this->hasColumn('tracs_moms', 'meeting_at');
    }

    return true;
  }

  private function ensureMeetingUrlColumn() {
    if($this->hasColumn('tracs_moms', 'meeting_url')) return true;

    try {
      $this->conn->query("ALTER TABLE tracs_moms ADD COLUMN meeting_url VARCHAR(500) DEFAULT NULL COMMENT 'Meeting URL such as Google Meet or Zoom' AFTER meeting_at");
    } catch(Throwable $e) {
      return $this->hasColumn('tracs_moms', 'meeting_url');
    }

    return true;
  }

  private function ensureColumn($column, $definition, $after = null) {
    if($this->hasColumn('tracs_moms', $column)) return true;
    $afterSql = $after ? " AFTER `$after`" : '';
    try {
      $this->conn->query("ALTER TABLE tracs_moms ADD COLUMN `$column` $definition$afterSql");
    } catch(Throwable $e) {
      return $this->hasColumn('tracs_moms', $column);
    }
    return true;
  }

  private function ensureOperationalSchema() {
    if(!$this->isInstalled()) return false;
    $this->ensureMeetingAtColumn();
    $this->ensureMeetingUrlColumn();
    $this->ensureColumn('scheduled_reminder_id', 'INT UNSIGNED DEFAULT NULL COMMENT "Reminder created for scheduled meeting"', 'created_by');
    $this->ensureColumn('created_by_name', 'VARCHAR(150) DEFAULT NULL COMMENT "Creator display snapshot"', 'created_by');
    $this->ensureColumn('ops_status_id', 'INT UNSIGNED DEFAULT NULL COMMENT "Ops window status entry for meeting"', 'scheduled_reminder_id');
    $this->ensureColumn('started_at', 'DATETIME DEFAULT NULL', 'ops_status_id');
    $this->ensureColumn('completed_at', 'DATETIME DEFAULT NULL', 'started_at');
    $this->ensureColumn('cancelled_at', 'DATETIME DEFAULT NULL', 'completed_at');
    $this->ensureColumn('summary', 'LONGTEXT DEFAULT NULL COMMENT "Post-meeting MOM summary"', 'cancelled_at');

    try {
      $this->conn->query("UPDATE tracs_moms SET status='upcoming' WHERE status='active'");
      $this->conn->query("ALTER TABLE tracs_moms MODIFY status ENUM('upcoming','ongoing','completed','cancelled') NOT NULL DEFAULT 'upcoming' COMMENT 'Meeting lifecycle status'");
    } catch(Throwable $e) {
      // Older installs may still use active; formatMOM normalizes it for the UI.
    }

    return true;
  }

  private function tickerEvent($message, $type='info', $ref_id=null) {
    try {
      $path = __DIR__ . '/../../../modules/ticker-events/controller.php';
      if(file_exists($path)) require_once $path;
      if(class_exists('TickerEventController')) {
        (new TickerEventController($this->conn))->create($this->uid, $message, $type, 'mom', $ref_id ? (int)$ref_id : null);
      }
    } catch(Throwable $e) {}
  }

  private function createOpsStatus($message, $severity='info') {
    try {
      $stmt = $this->conn->prepare("INSERT INTO ops_status (message, severity, is_active) VALUES (?, ?, 1)");
      if(!$stmt) return null;
      $stmt->bind_param('ss', $message, $severity);
      return $stmt->execute() ? (int)$stmt->insert_id : null;
    } catch(Throwable $e) {
      return null;
    }
  }

  private function updateOpsStatus($ops_id, $message, $severity='info', $active=1) {
    $ops_id = (int)$ops_id;
    if(!$ops_id) return false;
    try {
      $stmt = $this->conn->prepare("UPDATE ops_status SET message=?, severity=?, is_active=? WHERE id=?");
      if(!$stmt) return false;
      $stmt->bind_param('ssii', $message, $severity, $active, $ops_id);
      return $stmt->execute();
    } catch(Throwable $e) {
      return false;
    }
  }

  private function meetingTimeLabel($meeting_at) {
    return $meeting_at ? date('H:i', strtotime($meeting_at)) : date('H:i');
  }

  private function logMOMActivity($action, $details, $ref = null) {
    try {
      if(!function_exists('logAct')) return;
      $fn = new ReflectionFunction('logAct');
      if($fn->getNumberOfParameters() >= 5) {
        logAct($this->conn, $this->uid, $action, 'MOM', $details, $ref);
      } else {
        logAct($this->conn, $action, $details, $this->uid);
      }
    } catch(Throwable $e) {}
  }

  private function autoStartDueMOMs() {
    if(!$this->hasColumn('tracs_moms', 'meeting_at')) return;
    $this->ensureColumn('started_at', 'DATETIME DEFAULT NULL', 'ops_status_id');

    $stmt = $this->conn->prepare("
      SELECT id, title, type, meeting_at, scheduled_reminder_id, ops_status_id
      FROM tracs_moms
      WHERE created_by=?
        AND status='upcoming'
        AND meeting_at IS NOT NULL
        AND meeting_at <= NOW()
      ORDER BY meeting_at ASC
      LIMIT 50
    ");
    if(!$stmt) return;
    $stmt->bind_param('i', $this->uid);
    $stmt->execute();
    $due = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    if(empty($due)) return;

    $now = date('Y-m-d H:i:s');
    foreach($due as $mom) {
      $mom_id = (int)($mom['id'] ?? 0);
      if(!$mom_id) continue;
      $upd = $this->conn->prepare("
        UPDATE tracs_moms
        SET status='ongoing', started_at=COALESCE(started_at, ?), updated_at=?
        WHERE id=? AND created_by=? AND status='upcoming'
      ");
      if(!$upd) continue;
      $upd->bind_param('ssii', $now, $now, $mom_id, $this->uid);
      if(!$upd->execute() || $upd->affected_rows < 1) continue;

      $msg = "Meeting auto-started: " . ($mom['title'] ?? "MOM #$mom_id");
      if(!empty($mom['ops_status_id'])) {
        $this->updateOpsStatus((int)$mom['ops_status_id'], $msg, 'warning', 1);
      }
      if(!empty($mom['scheduled_reminder_id'])) {
        $rem = $this->conn->prepare("UPDATE tracs_reminders SET is_completed=1, updated_at=NOW() WHERE id=? AND user_id=?");
        if($rem) {
          $rid = (int)$mom['scheduled_reminder_id'];
          $rem->bind_param('ii', $rid, $this->uid);
          $rem->execute();
        }
      }
      $this->tickerEvent($msg, 'warning', $mom_id);
      $this->logMOMActivity('mom_auto_started', $msg, $mom_id);
    }
  }

  private function createMeetingReminder($mom_id, $title, $type, $meeting_at) {
    if(function_exists('tracs_ensure_creator_columns')) tracs_ensure_creator_columns($this->conn, 'tracs_reminders', 'user_id');
    $due = $meeting_at ?: date('Y-m-d H:i:s', strtotime('+1 hour'));
    $priority = $type === 'urgent' ? 'critical' : 'medium';
    $desc = "MOM scheduled in TRACS. Open: mom.php?mom_id=" . (int)$mom_id;
    $creator_name = function_exists('tracs_current_user_display') ? tracs_current_user_display($this->conn) : '';
    $stmt = $this->conn->prepare("
      INSERT INTO tracs_reminders (user_id, title, description, due_date, priority, is_completed, created_by, created_by_name, created_at)
      VALUES (?, ?, ?, ?, ?, 0, ?, ?, NOW())
    ");
    if(!$stmt) return null;
    $remTitle = "MOM: " . $title;
    $stmt->bind_param('issssis', $this->uid, $remTitle, $desc, $due, $priority, $this->uid, $creator_name);
    return $stmt->execute() ? (int)$stmt->insert_id : null;
  }

  // ═══════════════════════════════════════════════════════
  // CORE MOM OPERATIONS
  // ═══════════════════════════════════════════════════════

  public function createMOM($title, $type='weekly', $objective='', $participants='', $meeting_at=null, $meeting_url=null) {
    $this->requireInstalled();
    $this->ensureOperationalSchema();
    $title = trim($title);
    $type = in_array($type, ['weekly', 'training', 'coordination', 'urgent']) ? $type : 'weekly';
    $meeting_at = $this->normalizeMeetingAt($meeting_at);
    $meeting_url = $this->normalizeMeetingUrl($meeting_url);
    $now = date('Y-m-d H:i:s');
    $has_meeting_at = $this->hasColumn('tracs_moms', 'meeting_at');
    $has_meeting_url = $this->hasColumn('tracs_moms', 'meeting_url');
    $has_ops = $this->hasColumn('tracs_moms', 'ops_status_id');
    $has_rem = $this->hasColumn('tracs_moms', 'scheduled_reminder_id');
    $creator_name = function_exists('tracs_current_user_display') ? tracs_current_user_display($this->conn) : '';

    if($has_meeting_at && $has_meeting_url) {
      $stmt = $this->conn->prepare("
        INSERT INTO tracs_moms (title, type, objective, participants, meeting_at, meeting_url, status, created_by, created_by_name, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, 'upcoming', ?, ?, ?, ?)
      ");
      $stmt->bind_param('ssssssisss', $title, $type, $objective, $participants, $meeting_at, $meeting_url, $this->uid, $creator_name, $now, $now);
    } else if($has_meeting_at) {
      $stmt = $this->conn->prepare("
        INSERT INTO tracs_moms (title, type, objective, participants, meeting_at, status, created_by, created_by_name, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, 'upcoming', ?, ?, ?, ?)
      ");
      $stmt->bind_param('sssssisss', $title, $type, $objective, $participants, $meeting_at, $this->uid, $creator_name, $now, $now);
    } else {
      $stmt = $this->conn->prepare("
        INSERT INTO tracs_moms (title, type, objective, participants, status, created_by, created_by_name, created_at, updated_at)
        VALUES (?, ?, ?, ?, 'upcoming', ?, ?, ?, ?)
      ");
      $stmt->bind_param('ssssisss', $title, $type, $objective, $participants, $this->uid, $creator_name, $now, $now);
    }

    if($stmt->execute()) {
      $mom_id = $this->conn->insert_id;
      $rem_id = $this->createMeetingReminder($mom_id, $title, $type, $meeting_at);
      $ops_msg = ucfirst($type) . " meeting scheduled for " . $this->meetingTimeLabel($meeting_at) . ": " . $title;
      $ops_id = $this->createOpsStatus($ops_msg, $type === 'urgent' ? 'critical' : 'info');
      if($has_rem && $has_ops) {
        $upd = $this->conn->prepare("UPDATE tracs_moms SET scheduled_reminder_id=?, ops_status_id=?, updated_at=NOW() WHERE id=? AND created_by=?");
        if($upd) {
          $upd->bind_param('iiii', $rem_id, $ops_id, $mom_id, $this->uid);
          $upd->execute();
        }
      }
      $this->tickerEvent($ops_msg, $type === 'urgent' ? 'critical' : 'info', $mom_id);
      $this->logMOMActivity('mom_scheduled', "Scheduled MOM: $title", $mom_id);
      return $mom_id;
    }
    return false;
  }

  public function getMOM($mom_id) {
    if(!$this->isInstalled()) return null;
    $this->ensureOperationalSchema();
    $this->autoStartDueMOMs();
    $mom_id = (int)$mom_id;
    $stmt = $this->conn->prepare("
      SELECT m.*, COALESCE(NULLIF(m.created_by_name,''), NULLIF(u.name,''), u.email, 'System') AS creator_name
      FROM tracs_moms m
      LEFT JOIN tracs_users u ON m.created_by = u.id
      WHERE m.id=? AND m.created_by=? LIMIT 1
    ");
    $stmt->bind_param('ii', $mom_id, $this->uid);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
  }

  public function getMOMs($status='all', $limit=100) {
    if(!$this->isInstalled()) return [];
    $this->ensureOperationalSchema();
    $this->autoStartDueMOMs();
    $query = "
      SELECT m.*, COALESCE(NULLIF(m.created_by_name,''), NULLIF(u.name,''), u.email, 'System') AS creator_name
      FROM tracs_moms m
      LEFT JOIN tracs_users u ON m.created_by = u.id
      WHERE m.created_by=?
    ";
    $params = [$this->uid];
    $types = 'i';
    
    if($status !== 'all') {
      $query .= " AND m.status=?";
      $params[] = $status;
      $types .= 's';
    }
    
    $order = $this->hasColumn('tracs_moms', 'meeting_at') ? 'COALESCE(m.meeting_at, m.created_at)' : 'm.created_at';
    $query .= " ORDER BY $order DESC LIMIT ?";
    $params[] = $limit;
    $types .= 'i';
    
    $stmt = $this->conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  }

  public function updateMOM($mom_id, $title, $objective, $participants, $type='weekly', $status='upcoming', $meeting_at=null, $meeting_url=null) {
    $this->ensureOperationalSchema();
    $mom_id = (int)$mom_id;
    $status = $this->normalizeStatus($status);
    $meeting_at = $this->normalizeMeetingAt($meeting_at);
    $meeting_url = $this->normalizeMeetingUrl($meeting_url);
    $now = date('Y-m-d H:i:s');
    $has_meeting_at = $this->ensureMeetingAtColumn();
    $has_meeting_url = $this->ensureMeetingUrlColumn();

    if($has_meeting_at && $has_meeting_url) {
      $stmt = $this->conn->prepare("
        UPDATE tracs_moms 
        SET title=?, objective=?, participants=?, type=?, status=?, meeting_at=?, meeting_url=?, updated_at=?
        WHERE id=? AND created_by=?
      ");
      $stmt->bind_param('ssssssssii', $title, $objective, $participants, $type, $status, $meeting_at, $meeting_url, $now, $mom_id, $this->uid);
    } else if($has_meeting_at) {
      $stmt = $this->conn->prepare("
        UPDATE tracs_moms 
        SET title=?, objective=?, participants=?, type=?, status=?, meeting_at=?, updated_at=?
        WHERE id=? AND created_by=?
      ");
      $stmt->bind_param('sssssssii', $title, $objective, $participants, $type, $status, $meeting_at, $now, $mom_id, $this->uid);
    } else {
      $stmt = $this->conn->prepare("
        UPDATE tracs_moms 
        SET title=?, objective=?, participants=?, type=?, status=?, updated_at=?
        WHERE id=? AND created_by=?
      ");
      $stmt->bind_param('ssssssii', $title, $objective, $participants, $type, $status, $now, $mom_id, $this->uid);
    }

    if($stmt->execute()) {
      $this->logMOMActivity('mom_updated', "Updated MOM #$mom_id", $mom_id);
      return true;
    }
    return false;
  }

  public function closeMOM($mom_id) {
    $mom_id = (int)$mom_id;
    $now = date('Y-m-d H:i:s');
    $mom = $this->getMOM($mom_id);
    if(!$mom) return false;
    
    $stmt = $this->conn->prepare("
      UPDATE tracs_moms 
      SET status='completed', completed_at=?, updated_at=?
      WHERE id=? AND created_by=?
    ");
    $stmt->bind_param('ssii', $now, $now, $mom_id, $this->uid);
    if($stmt->execute()) {
      if(!empty($mom['ops_status_id'])) {
        $this->updateOpsStatus((int)$mom['ops_status_id'], "Meeting completed: " . ($mom['title'] ?? "MOM #$mom_id"), 'solved', 0);
      }
      if(!empty($mom['scheduled_reminder_id'])) {
        $rem = $this->conn->prepare("UPDATE tracs_reminders SET is_completed=1, updated_at=NOW() WHERE id=? AND user_id=?");
        if($rem) {
          $rid = (int)$mom['scheduled_reminder_id'];
          $rem->bind_param('ii', $rid, $this->uid);
          $rem->execute();
        }
      }
      $this->tickerEvent("Meeting completed: " . ($mom['title'] ?? "MOM #$mom_id"), 'success', $mom_id);
      $this->logMOMActivity('mom_completed', "Completed MOM #$mom_id", $mom_id);
      return true;
    }
    return false;
  }

  public function deleteMOM($mom_id) {
    $mom_id = (int)$mom_id;
    $mom = $this->getMOM($mom_id);
    if(!$mom) return false;

    $rem = $this->conn->prepare("
      DELETE r FROM tracs_reminders r
      INNER JOIN tracs_mom_actions a ON a.linked_reminder_id=r.id
      INNER JOIN tracs_moms m ON m.id=a.mom_id AND m.created_by=?
      WHERE m.id=? AND r.user_id=?
    ");
    $rem->bind_param('iii', $this->uid, $mom_id, $this->uid);
    $rem->execute();

    $stmt = $this->conn->prepare("DELETE FROM tracs_moms WHERE id=? AND created_by=?");
    $stmt->bind_param('ii', $mom_id, $this->uid);
    $ok = $stmt->execute();
    if($ok && $stmt->affected_rows > 0) {
      if(!empty($mom['scheduled_reminder_id'])) {
        $rid = (int)$mom['scheduled_reminder_id'];
        $scheduled = $this->conn->prepare("UPDATE tracs_reminders SET is_completed=1, updated_at=NOW() WHERE id=? AND user_id=?");
        if($scheduled) {
          $scheduled->bind_param('ii', $rid, $this->uid);
          $scheduled->execute();
        }
      }
      if(!empty($mom['ops_status_id'])) {
        $this->updateOpsStatus((int)$mom['ops_status_id'], "Meeting removed: " . ($mom['title'] ?? "MOM #$mom_id"), 'info', 0);
      }
      $this->logMOMActivity('mom_deleted', "Deleted MOM #$mom_id", $mom_id);
      return true;
    }
    return false;
  }

  public function formatMOM($mom) {
    $status = $this->normalizeStatus($mom['status']??'upcoming');
    return [
      'id' => (int)($mom['id']??0),
      'title' => $mom['title']??'Untitled',
      'type' => $mom['type']??'weekly',
      'status' => $status,
      'objective' => $mom['objective']??'',
      'participants' => $mom['participants']??'',
      'meeting_at' => $mom['meeting_at']??null,
      'meeting_url' => $mom['meeting_url']??null,
      'scheduled_reminder_id' => $mom['scheduled_reminder_id']??null,
      'ops_status_id' => $mom['ops_status_id']??null,
      'started_at' => $mom['started_at']??null,
      'completed_at' => $mom['completed_at']??null,
      'cancelled_at' => $mom['cancelled_at']??null,
      'summary' => $mom['summary']??'',
      'created_at' => $mom['created_at']??null,
      'updated_at' => $mom['updated_at']??null,
      'created_by' => $mom['created_by']??null,
      'created_by_name' => $mom['created_by_name']??null,
      'creator_name' => $mom['creator_name']??null,
    ];
  }

  private function normalizeStatus($status) {
    $status = (string)$status;
    if($status === 'active') return 'upcoming';
    return in_array($status, ['upcoming','ongoing','completed','cancelled'], true) ? $status : 'upcoming';
  }

  public function startMOM($mom_id) {
    $mom_id = (int)$mom_id;
    $mom = $this->getMOM($mom_id);
    if(!$mom) return false;
    $now = date('Y-m-d H:i:s');
    $stmt = $this->conn->prepare("UPDATE tracs_moms SET status='ongoing', started_at=COALESCE(started_at,?), updated_at=? WHERE id=? AND created_by=?");
    $stmt->bind_param('ssii', $now, $now, $mom_id, $this->uid);
    if(!$stmt->execute()) return false;
    if(!empty($mom['scheduled_reminder_id'])) {
      $rid = (int)$mom['scheduled_reminder_id'];
      $rem = $this->conn->prepare("UPDATE tracs_reminders SET is_completed=1, updated_at=NOW() WHERE id=? AND user_id=?");
      if($rem) {
        $rem->bind_param('ii', $rid, $this->uid);
        $rem->execute();
      }
    }
    $msg = "Meeting started: " . ($mom['title'] ?? "MOM #$mom_id");
    if(!empty($mom['ops_status_id'])) $this->updateOpsStatus((int)$mom['ops_status_id'], $msg, 'warning', 1);
    $this->tickerEvent($msg, 'warning', $mom_id);
    $this->logMOMActivity('mom_started', $msg, $mom_id);
    return true;
  }

  public function cancelMOM($mom_id) {
    $mom_id = (int)$mom_id;
    $mom = $this->getMOM($mom_id);
    if(!$mom) return false;
    $now = date('Y-m-d H:i:s');
    $stmt = $this->conn->prepare("UPDATE tracs_moms SET status='cancelled', cancelled_at=?, updated_at=? WHERE id=? AND created_by=?");
    $stmt->bind_param('ssii', $now, $now, $mom_id, $this->uid);
    if(!$stmt->execute()) return false;
    if(!empty($mom['scheduled_reminder_id'])) {
      $rid = (int)$mom['scheduled_reminder_id'];
      $rem = $this->conn->prepare("UPDATE tracs_reminders SET is_completed=1, updated_at=NOW() WHERE id=? AND user_id=?");
      if($rem) {
        $rem->bind_param('ii', $rid, $this->uid);
        $rem->execute();
      }
    }
    if(!empty($mom['ops_status_id'])) $this->updateOpsStatus((int)$mom['ops_status_id'], "Meeting cancelled: " . ($mom['title'] ?? "MOM #$mom_id"), 'info', 0);
    $this->tickerEvent("Meeting cancelled: " . ($mom['title'] ?? "MOM #$mom_id"), 'info', $mom_id);
    $this->logMOMActivity('mom_cancelled', "Cancelled MOM #$mom_id", $mom_id);
    return true;
  }

  public function saveSummary($mom_id, $summary) {
    $mom_id = (int)$mom_id;
    if(!$this->getMOM($mom_id)) return false;
    $stmt = $this->conn->prepare("UPDATE tracs_moms SET summary=?, updated_at=NOW() WHERE id=? AND created_by=?");
    $stmt->bind_param('sii', $summary, $mom_id, $this->uid);
    $ok = $stmt->execute();
    if($ok) $this->logMOMActivity('mom_summary_saved', "Saved MOM summary #$mom_id", $mom_id);
    return $ok;
  }

  // ═══════════════════════════════════════════════════════
  // AGENDA ITEMS
  // ═══════════════════════════════════════════════════════

  public function addAgendaItem($mom_id, $topic, $notes='', $status='pending') {
    $mom_id = (int)$mom_id;
    if(!$this->getMOM($mom_id)) return false;
    $now = date('Y-m-d H:i:s');
    
    $stmt = $this->conn->prepare("
      INSERT INTO tracs_mom_agenda (mom_id, topic, notes, status, created_at)
      VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('issss', $mom_id, $topic, $notes, $status, $now);
    return $stmt->execute() ? $this->conn->insert_id : false;
  }

  public function getAgendaItems($mom_id) {
    $mom_id = (int)$mom_id;
    $stmt = $this->conn->prepare("SELECT * FROM tracs_mom_agenda WHERE mom_id=? ORDER BY created_at ASC");
    $stmt->bind_param('i', $mom_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  }

  public function updateAgendaItem($item_id, $topic, $notes='', $status='pending') {
    $item_id = (int)$item_id;
    $stmt = $this->conn->prepare("
      UPDATE tracs_mom_agenda a
      INNER JOIN tracs_moms m ON m.id=a.mom_id AND m.created_by=?
      SET
        a.topic=CASE WHEN ?='' THEN a.topic ELSE ? END,
        a.notes=CASE WHEN ?='' THEN a.notes ELSE ? END,
        a.status=?
      WHERE a.id=?
    ");
    $stmt->bind_param('isssssi', $this->uid, $topic, $topic, $notes, $notes, $status, $item_id);
    return $stmt->execute() && $stmt->affected_rows > 0;
  }

  public function deleteAgendaItem($item_id) {
    $item_id = (int)$item_id;
    $stmt = $this->conn->prepare("
      DELETE a FROM tracs_mom_agenda a
      INNER JOIN tracs_moms m ON m.id=a.mom_id AND m.created_by=?
      WHERE a.id=?
    ");
    $stmt->bind_param('ii', $this->uid, $item_id);
    return $stmt->execute() && $stmt->affected_rows > 0;
  }

  // ═══════════════════════════════════════════════════════
  // DISCUSSION NOTES
  // ═══════════════════════════════════════════════════════

  public function addDiscussionNote($mom_id, $content, $note_type='discussion') {
    $mom_id = (int)$mom_id;
    if(!$this->getMOM($mom_id)) return false;
    $now = date('Y-m-d H:i:s');
    
    $stmt = $this->conn->prepare("
      INSERT INTO tracs_mom_notes (mom_id, content, note_type, created_by, created_at)
      VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('issis', $mom_id, $content, $note_type, $this->uid, $now);
    return $stmt->execute() ? $this->conn->insert_id : false;
  }

  public function getDiscussionNotes($mom_id) {
    $mom_id = (int)$mom_id;
    $stmt = $this->conn->prepare("
      SELECT n.*, COALESCE(NULLIF(u.name,''), u.email, 'System') AS creator_name
      FROM tracs_mom_notes n
      LEFT JOIN tracs_users u ON n.created_by = u.id
      WHERE n.mom_id=? 
      ORDER BY n.created_at DESC
    ");
    $stmt->bind_param('i', $mom_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  }

  public function deleteNote($note_id) {
    $note_id = (int)$note_id;
    $stmt = $this->conn->prepare("
      DELETE n FROM tracs_mom_notes n
      INNER JOIN tracs_moms m ON m.id=n.mom_id AND m.created_by=?
      WHERE n.id=?
    ");
    $stmt->bind_param('ii', $this->uid, $note_id);
    return $stmt->execute() && $stmt->affected_rows > 0;
  }

  // ═══════════════════════════════════════════════════════
  // DECISIONS
  // ═══════════════════════════════════════════════════════

  public function addDecision($mom_id, $decision, $rationale='', $owner='') {
    $mom_id = (int)$mom_id;
    if(!$this->getMOM($mom_id)) return false;
    $now = date('Y-m-d H:i:s');
    
    $stmt = $this->conn->prepare("
      INSERT INTO tracs_mom_decisions (mom_id, decision, rationale, owner, created_at)
      VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('issss', $mom_id, $decision, $rationale, $owner, $now);
    if($stmt->execute()) {
      $id = $this->conn->insert_id;
      $this->logMOMActivity('mom_decision_recorded', "Decision recorded in MOM #$mom_id", $mom_id);
      return $id;
    }
    return false;
  }

  public function getDecisions($mom_id) {
    $mom_id = (int)$mom_id;
    $stmt = $this->conn->prepare("
      SELECT * FROM tracs_mom_decisions 
      WHERE mom_id=? 
      ORDER BY created_at DESC
    ");
    $stmt->bind_param('i', $mom_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  }

  public function deleteDecision($decision_id) {
    $decision_id = (int)$decision_id;
    $stmt = $this->conn->prepare("
      DELETE d FROM tracs_mom_decisions d
      INNER JOIN tracs_moms m ON m.id=d.mom_id AND m.created_by=?
      WHERE d.id=?
    ");
    $stmt->bind_param('ii', $this->uid, $decision_id);
    return $stmt->execute() && $stmt->affected_rows > 0;
  }

  // ═══════════════════════════════════════════════════════
  // ACTION ITEMS
  // ═══════════════════════════════════════════════════════

  public function addActionItem($mom_id, $title, $description='', $assigned_to='', $priority='medium', $due_date=null) {
    $mom_id = (int)$mom_id;
    if(!$this->getMOM($mom_id)) return false;
    $now = date('Y-m-d H:i:s');
    
    $stmt = $this->conn->prepare("
      INSERT INTO tracs_mom_actions (mom_id, title, description, assigned_to, priority, due_date, status, created_at)
      VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)
    ");
    $stmt->bind_param('issssss', $mom_id, $title, $description, $assigned_to, $priority, $due_date, $now);
    if($stmt->execute()) {
      $id = $this->conn->insert_id;
      $this->logMOMActivity('mom_action_created', "Created MOM action: $title", $mom_id);
      return $id;
    }
    return false;
  }

  public function getActionItems($mom_id) {
    $mom_id = (int)$mom_id;
    $stmt = $this->conn->prepare("
      SELECT * FROM tracs_mom_actions 
      WHERE mom_id=? 
      ORDER BY FIELD(priority,'critical','high','medium','low') ASC, due_date ASC
    ");
    $stmt->bind_param('i', $mom_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  }

  public function getActionItem($action_id) {
    $action_id = (int)$action_id;
    $stmt = $this->conn->prepare("
      SELECT a.*
      FROM tracs_mom_actions a
      INNER JOIN tracs_moms m ON m.id=a.mom_id
      WHERE a.id=? AND m.created_by=?
      LIMIT 1
    ");
    $stmt->bind_param('ii', $action_id, $this->uid);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
  }

  public function updateActionItem($action_id, $title, $description='', $assigned_to='', $priority='medium', $due_date=null) {
    $action_id = (int)$action_id;
    $priority = in_array($priority, ['low','medium','high','critical']) ? $priority : 'medium';
    $now = date('Y-m-d H:i:s');

    $stmt = $this->conn->prepare("
      UPDATE tracs_mom_actions a
      INNER JOIN tracs_moms m ON m.id=a.mom_id AND m.created_by=?
      SET a.title=?, a.description=?, a.assigned_to=?, a.priority=?, a.due_date=?, a.updated_at=?
      WHERE a.id=?
    ");
    $stmt->bind_param('issssssi', $this->uid, $title, $description, $assigned_to, $priority, $due_date, $now, $action_id);
    $ok = $stmt->execute();
    if($ok) {
      $desc = "Action Item from MOM: " . $description;
      $rem = $this->conn->prepare("
        UPDATE tracs_reminders r
        INNER JOIN tracs_mom_actions a ON a.linked_reminder_id=r.id
        INNER JOIN tracs_moms m ON m.id=a.mom_id AND m.created_by=?
        SET r.title=?, r.description=?, r.priority=?, r.due_date=COALESCE(?, r.due_date), r.updated_at=NOW()
        WHERE a.id=? AND r.user_id=?
      ");
      $rem->bind_param('issssii', $this->uid, $title, $desc, $priority, $due_date, $action_id, $this->uid);
      $rem->execute();
    }
    return $ok;
  }

  public function completeAction($action_id, $completed=true) {
    $action_id = (int)$action_id;
    $now = date('Y-m-d H:i:s');
    $status = $completed ? 'completed' : 'pending';

    $stmt = $this->conn->prepare("
      UPDATE tracs_mom_actions a
      INNER JOIN tracs_moms m ON m.id=a.mom_id AND m.created_by=?
      SET a.status=?, a.updated_at=?
      WHERE a.id=?
    ");
    $stmt->bind_param('issi', $this->uid, $status, $now, $action_id);
    $ok = $stmt->execute();
    if($ok) {
      $done = $completed ? 1 : 0;
      $rem = $this->conn->prepare("
        UPDATE tracs_reminders r
        INNER JOIN tracs_mom_actions a ON a.linked_reminder_id=r.id
        INNER JOIN tracs_moms m ON m.id=a.mom_id AND m.created_by=?
        SET r.is_completed=?, r.updated_at=NOW()
        WHERE a.id=? AND r.user_id=?
      ");
      $rem->bind_param('iiii', $this->uid, $done, $action_id, $this->uid);
      $rem->execute();
      $this->logMOMActivity($completed ? 'action_completed' : 'action_reopened', ($completed ? 'Completed' : 'Reopened') . " action #$action_id", $action_id);
    }
    return $ok;
  }

  public function deleteActionItem($action_id) {
    $action_id = (int)$action_id;
    $rem = $this->conn->prepare("
      DELETE r FROM tracs_reminders r
      INNER JOIN tracs_mom_actions a ON a.linked_reminder_id=r.id
      INNER JOIN tracs_moms m ON m.id=a.mom_id AND m.created_by=?
      WHERE a.id=? AND r.user_id=?
    ");
    $rem->bind_param('iii', $this->uid, $action_id, $this->uid);
    $rem->execute();

    $stmt = $this->conn->prepare("
      DELETE a FROM tracs_mom_actions a
      INNER JOIN tracs_moms m ON m.id=a.mom_id AND m.created_by=?
      WHERE a.id=?
    ");
    $stmt->bind_param('ii', $this->uid, $action_id);
    return $stmt->execute() && $stmt->affected_rows > 0;
  }

  // ═══════════════════════════════════════════════════════
  // CASE LINKING
  // ═══════════════════════════════════════════════════════

  public function linkCaseToMOM($mom_id, $case_id) {
    $mom_id = (int)$mom_id;
    $case_id = (int)$case_id;
    $now = date('Y-m-d H:i:s');

    if(!$this->getMOM($mom_id) || !$this->getCaseForUser($case_id)) return false;

    $stmt = $this->conn->prepare("
      INSERT IGNORE INTO tracs_mom_case_links (mom_id, case_id, link_context, linked_at)
      VALUES (?, ?, 'related', ?)
    ");
    $stmt->bind_param('iis', $mom_id, $case_id, $now);
    return $stmt->execute();
  }

  public function unlinkCase($mom_id, $case_id) {
    $mom_id = (int)$mom_id;
    $case_id = (int)$case_id;
    $stmt = $this->conn->prepare("
      DELETE ml FROM tracs_mom_case_links ml
      INNER JOIN tracs_moms m ON m.id=ml.mom_id AND m.created_by=?
      WHERE ml.mom_id=? AND ml.case_id=?
    ");
    $stmt->bind_param('iii', $this->uid, $mom_id, $case_id);
    return $stmt->execute();
  }

  public function getRelatedCases($mom_id) {
    $mom_id = (int)$mom_id;
    $stmt = $this->conn->prepare("
      SELECT c.* 
      FROM tracs_cases c
      INNER JOIN tracs_mom_case_links ml ON c.id=ml.case_id
      INNER JOIN tracs_moms m ON m.id=ml.mom_id
      WHERE ml.mom_id=? AND m.created_by=? AND c.user_id=?
      ORDER BY ml.linked_at DESC
    ");
    $stmt->bind_param('iii', $mom_id, $this->uid, $this->uid);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  }

  public function getCaseForUser($case_id) {
    $case_id = (int)$case_id;
    $stmt = $this->conn->prepare("SELECT * FROM tracs_cases WHERE id=? AND user_id=? LIMIT 1");
    $stmt->bind_param('ii', $case_id, $this->uid);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
  }

  public function createCaseFromAction($action_id) {
    if(function_exists('tracs_ensure_creator_columns')) tracs_ensure_creator_columns($this->conn, 'tracs_cases', 'user_id');
    $action = $this->getActionItem($action_id);
    if(!$action) return false;

    $next = !empty($action['due_date']) ? $action['due_date'] : null;
    $notes = trim("Created from MOM action #{$action['id']}\n\n" . ($action['description'] ?? ''));
    $creator_name = function_exists('tracs_current_user_display') ? tracs_current_user_display($this->conn) : '';
    $stmt = $this->conn->prepare("
      INSERT INTO tracs_cases (user_id, title, status, priority, next_check_at, notes, created_by, created_by_name, created_at, updated_at)
      VALUES (?, ?, 'active', ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    $stmt->bind_param('issssis', $this->uid, $action['title'], $action['priority'], $next, $notes, $this->uid, $creator_name);
    if(!$stmt->execute()) return false;

    $case_id = $stmt->insert_id;
    $this->linkCaseToMOM((int)$action['mom_id'], $case_id);

    $upd = $this->conn->prepare("UPDATE tracs_mom_actions SET linked_case_id=?, updated_at=NOW() WHERE id=?");
    $upd->bind_param('ii', $case_id, $action_id);
    $upd->execute();

    $this->logMOMActivity('mom_case_created', "Created case from MOM action #$action_id", $case_id);
    $this->tickerEvent("Case #$case_id created from MOM action", 'info', $case_id);
    return $case_id;
  }

  // ═══════════════════════════════════════════════════════
  // REMINDER INTEGRATION
  // ═══════════════════════════════════════════════════════

  public function createReminderFromAction($action_id) {
    if(function_exists('tracs_ensure_creator_columns')) tracs_ensure_creator_columns($this->conn, 'tracs_reminders', 'user_id');
    $action = $this->getActionItem($action_id);
    if(!$action) return false;

    if(!empty($action['linked_reminder_id'])) return (int)$action['linked_reminder_id'];

    $due = !empty($action['due_date']) ? date('Y-m-d H:i:s', strtotime($action['due_date'])) : date('Y-m-d H:i:s', strtotime('+1 day'));
    $desc = "Action Item from MOM #{$action['mom_id']}: " . ($action['description'] ?? '');
    $creator_name = function_exists('tracs_current_user_display') ? tracs_current_user_display($this->conn) : '';
    $stmt = $this->conn->prepare("
      INSERT INTO tracs_reminders (user_id, title, description, due_date, priority, is_completed, created_by, created_by_name, created_at)
      VALUES (?, ?, ?, ?, ?, 0, ?, ?, NOW())
    ");
    $stmt->bind_param('issssis', $this->uid, $action['title'], $desc, $due, $action['priority'], $this->uid, $creator_name);
    if(!$stmt->execute()) return false;
    $rem_id = $stmt->insert_id;

    // Link reminder to action
    $upd = $this->conn->prepare("
      UPDATE tracs_mom_actions 
      SET linked_reminder_id=?, updated_at=NOW()
      WHERE id=?
    ");
    $upd->bind_param('ii', $rem_id, $action_id);
    $upd->execute();
    
    $this->logMOMActivity('action_reminder_created', "Created reminder for action #$action_id", $rem_id);
    $this->tickerEvent("Follow-up reminder created from MOM action: " . $action['title'], 'info', $rem_id);
    return $rem_id;
  }

  public function resolveLinkedCaseFromMOM($mom_id, $case_id, $status='completed', $note='') {
    $mom_id = (int)$mom_id;
    $case_id = (int)$case_id;
    $status = in_array($status, ['active','pending','stuck','completed'], true) ? $status : 'completed';
    $mom = $this->getMOM($mom_id);
    $case = $this->getCaseForUser($case_id);
    if(!$mom || !$case) return false;
    if($this->normalizeStatus($mom['status'] ?? 'upcoming') !== 'completed') return false;

    $linked = $this->conn->prepare("SELECT id FROM tracs_mom_case_links WHERE mom_id=? AND case_id=? LIMIT 1");
    $linked->bind_param('ii', $mom_id, $case_id);
    $linked->execute();
    if(!$linked->get_result()->fetch_assoc()) return false;

    $append = trim((string)$note);
    $stamp = date('Y-m-d H:i');
    $notes = trim(($case['notes'] ?? '') . "\n\n[MOM #$mom_id - $stamp]\n" . ($append ?: "Status updated from MOM review."));

    $stmt = $this->conn->prepare("UPDATE tracs_cases SET status=?, notes=?, updated_at=NOW() WHERE id=? AND user_id=?");
    $stmt->bind_param('ssii', $status, $notes, $case_id, $this->uid);
    if(!$stmt->execute()) return false;

    $label = $status === 'completed' ? 'solved' : $status;
    $this->logMOMActivity('mom_case_status_updated', "Case #$case_id marked as $label after MOM review", $case_id);
    $this->tickerEvent("Case #$case_id marked as $label after MOM review", $status === 'completed' ? 'success' : 'info', $case_id);
    return true;
  }

  public function getRelatedReminders($mom_id) {
    $mom_id = (int)$mom_id;
    $has_scheduled = $this->hasColumn('tracs_moms', 'scheduled_reminder_id');
    
    $scheduledSql = $has_scheduled ? 'r.id=m.scheduled_reminder_id OR ' : '';
    $stmt = $this->conn->prepare("
      SELECT DISTINCT r.*
      FROM tracs_reminders r
      INNER JOIN tracs_moms m ON m.id=? AND m.created_by=?
      LEFT JOIN tracs_mom_actions ma ON ma.mom_id=m.id AND ma.linked_reminder_id=r.id
      WHERE r.user_id=?
        AND ($scheduledSql ma.id IS NOT NULL)
      ORDER BY r.due_date ASC
    ");
    $stmt->bind_param('iii', $mom_id, $this->uid, $this->uid);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  }

  // ═══════════════════════════════════════════════════════
  // WEEKLY SUGGESTIONS
  // ═══════════════════════════════════════════════════════

  public function getWeeklySuggestions() {
    // Find unresolved cases from last 7 days that might benefit from discussion
    $stmt = $this->conn->prepare("
      SELECT 
        id as case_id,
        title,
        priority,
        status,
        DATEDIFF(NOW(), created_at) as days_open,
        CASE
          WHEN DATEDIFF(NOW(), next_check_at) > 3 THEN 'overdue_followup'
          WHEN status='stuck' THEN 'stuck_case'
          WHEN priority='critical' AND status!='completed' THEN 'critical_unresolved'
          ELSE 'unresolved'
        END as suggestion_reason
      FROM tracs_cases
      WHERE user_id=?
        AND status != 'completed'
        AND (DATEDIFF(NOW(), created_at) <= 7 OR status='stuck' OR priority='critical')
      ORDER BY FIELD(priority,'critical','high','medium','low') ASC, created_at DESC
      LIMIT 10
    ");
    $stmt->bind_param('i', $this->uid);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  }

  // ═══════════════════════════════════════════════════════
  // SCREENSHOT HANDLING
  // ═══════════════════════════════════════════════════════

  public function attachScreenshot($mom_id, $image_data, $attached_to_type='general', $attached_to_id=null) {
    $mom_id = (int)$mom_id;
    if(!$this->getMOM($mom_id)) return false;
    $now = date('Y-m-d H:i:s');
    $uploadDir = __DIR__ . '/../../uploads/mom';
    if(!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
    
    $declared_mime = '';
    if(preg_match('/^data:([^;,]+);base64,/', (string)$image_data, $matches)) {
      $declared_mime = strtolower($matches[1]);
      $image_data = substr((string)$image_data, strpos((string)$image_data, 'base64,') + 7);
    }

    $bytes = base64_decode($image_data, true);
    if(!$bytes || strlen($bytes) > 5 * 1024 * 1024) {
      return false;
    }

    $info = @getimagesizefromstring($bytes);
    $allowed_mimes = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
    $mime = strtolower((string)($info['mime'] ?? ''));
    if(!isset($allowed_mimes[$mime]) || ($declared_mime !== '' && $declared_mime !== $mime)) {
      return false;
    }

    $filename = 'mom_' . $mom_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowed_mimes[$mime];
    $filepath = $uploadDir . '/' . $filename;

    if(file_put_contents($filepath, $bytes)) {
      @chmod($filepath, 0644);
      $stmt = $this->conn->prepare("
        INSERT INTO tracs_mom_screenshots (mom_id, filename, attached_to_type, attached_to_id, uploaded_at)
        VALUES (?, ?, ?, ?, ?)
      ");
      $attached_to_id = $attached_to_id ? (int)$attached_to_id : null;
      $stmt->bind_param('issis', $mom_id, $filename, $attached_to_type, $attached_to_id, $now);
      if($stmt->execute()) {
        $this->logMOMActivity('mom_screenshot_uploaded', "Uploaded screenshot for MOM #$mom_id", $mom_id);
        return $stmt->insert_id;
      }
    }
    return false;
  }

  public function getScreenshots($mom_id) {
    $mom_id = (int)$mom_id;
    $stmt = $this->conn->prepare("
      SELECT s.* FROM tracs_mom_screenshots s
      INNER JOIN tracs_moms m ON m.id=s.mom_id AND m.created_by=?
      WHERE s.mom_id=?
      ORDER BY s.uploaded_at DESC
    ");
    $stmt->bind_param('ii', $this->uid, $mom_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  }

  public function deleteScreenshot($screenshot_id) {
    $screenshot_id = (int)$screenshot_id;
    $stmt = $this->conn->prepare("
      SELECT s.filename
      FROM tracs_mom_screenshots s
      INNER JOIN tracs_moms m ON m.id=s.mom_id AND m.created_by=?
      WHERE s.id=?
      LIMIT 1
    ");
    $stmt->bind_param('ii', $this->uid, $screenshot_id);
    $stmt->execute();
    $shot = $stmt->get_result()->fetch_assoc();
    if(!$shot) return false;

    $del = $this->conn->prepare("DELETE FROM tracs_mom_screenshots WHERE id=?");
    $del->bind_param('i', $screenshot_id);
    if(!$del->execute()) return false;

    $path = __DIR__ . '/../../uploads/mom/' . basename($shot['filename'] ?? '');
    if(is_file($path)) @unlink($path);
    $this->logMOMActivity('mom_screenshot_deleted', "Deleted MOM screenshot #$screenshot_id", $screenshot_id);
    return true;
  }
}

// Require Reminder Controller for action-to-reminder conversions
if(!class_exists('ReminderController')) {
  $reminderController = __DIR__ . '/../../../modules/reminder/controller.php';
  if(file_exists($reminderController)) {
    require_once $reminderController;
  }
}

if(!function_exists('logAct')) {
  function logAct($conn, $action, $details, $uid) {
    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("
      INSERT INTO tracs_activity_logs (user_id, action, module, description, created_at)
      VALUES (?, ?, 'MOM', ?, ?)
    ");
    $stmt->bind_param('isss', $uid, $action, $details, $now);
    return $stmt->execute();
  }
}
?>
