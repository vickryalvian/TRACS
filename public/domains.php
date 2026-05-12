<?php
/* ─────────────────────────────────────────────────────────────
   TRACS — domains.php
   Domain Transfer Monitoring & Operational Tracking
   CS / Operations Team · Domain Transfer Log
   ───────────────────────────────────────────────────────────── */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth/auth_check.php';
require_once __DIR__ . '/../modules/alert-ticker/controller.php';
require_once __DIR__ . '/includes/page_helpers.php';

$uid        = $_SESSION['user_id']    ?? 0;
$user_email = $_SESSION['user_email'] ?? 'operator@tracs.local';

$TC           = new AlertTickerController($conn, $uid);
$ticker_items = $TC->formatAlertsForTicker();
$critical_count = 0;

/* ── Auto-create tables (migration-safe) ────────────────────── */
$conn->query("CREATE TABLE IF NOT EXISTS `domain_transfers` (
  `id`                       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `domain_name`              VARCHAR(255) NOT NULL,
  `transfer_status`          ENUM(
    'pending transfer',
    'locked',
    'error epp code',
    'move domain',
    'done',
    'cancelled',
    'retransferred',
    'transferred away',
    'pending verification',
    'renew period'
  ) NOT NULL DEFAULT 'pending transfer',
  `process_start_date`       DATE NULL DEFAULT NULL,
  `process_end_date`         DATE NULL DEFAULT NULL,
  `webnic_reseller_transfer` ENUM('Webnic','Resellercamp') NULL DEFAULT NULL,
  `notes`                    TEXT NULL,
  `created_by`               INT NULL DEFAULT NULL,
  `created_at`               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_domain_name`   (`domain_name`),
  INDEX `idx_transfer_status` (`transfer_status`),
  INDEX `idx_process_start` (`process_start_date`),
  INDEX `idx_process_end`   (`process_end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

/* ── Activity feed table (migration-safe) ───────────────────── */
$conn->query("CREATE TABLE IF NOT EXISTS `activity_feed` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `activity_type`    VARCHAR(50) NOT NULL,
  `activity_message` VARCHAR(255) NOT NULL,
  `related_domain`   VARCHAR(255) NULL DEFAULT NULL,
  `created_by`       INT NULL DEFAULT NULL,
  `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_activity_type` (`activity_type`),
  INDEX `idx_created_at`    (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

/* ── Activity helper ────────────────────────────────────────── */
function log_domain_activity($conn, string $type, string $message, string $domain, int $user_id): void {
    $stmt = $conn->prepare(
        "INSERT INTO activity_feed (activity_type, activity_message, related_domain, created_by)
         VALUES (?, ?, ?, ?)"
    );
    if ($stmt) {
        $stmt->bind_param('sssi', $type, $message, $domain, $user_id);
        $stmt->execute();
        $stmt->close();
    }
    /* Also push into tracs_ticker_messages if the table exists (reuse existing ticker system) */
    $check = $conn->query("SHOW TABLES LIKE 'tracs_ticker_messages'");
    if ($check && $check->num_rows > 0) {
        $cls = match($type) {
            'domain_completed'       => 'info',
            'domain_cancelled'       => 'urgent',
            'domain_error'           => 'critical',
            'domain_transferred_away'=> 'urgent',
            default                  => 'normal',
        };
        $ts = $conn->prepare(
            "INSERT INTO tracs_ticker_messages (user_id, text, class, enabled)
             VALUES (?, ?, ?, 1)"
        );
        if ($ts) {
            $ts->bind_param('iss', $user_id, $message, $cls);
            $ts->execute();
            $ts->close();
        }
    }
}

/* ── Determine ticker message class from status ─────────────── */
function domain_ticker_class(string $status): string {
    return match($status) {
        'done'              => 'info',
        'cancelled'         => 'urgent',
        'error epp code'    => 'critical',
        'transferred away'  => 'urgent',
        'locked'            => 'urgent',
        default             => 'normal',
    };
}

/* ── AJAX / POST handler ────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $raw    = file_get_contents('php://input');
    $data   = json_decode($raw, true) ?? [];
    $action = $data['action'] ?? '';

    /* ── CREATE ── */
    if ($action === 'create') {
        $domain   = trim($data['domain_name']         ?? '');
        $status   = $data['transfer_status']           ?? 'pending transfer';
        $start    = $data['process_start_date']        ?? null;
        $end      = $data['process_end_date']          ?? null;
        $webnic   = trim($data['webnic_reseller_transfer'] ?? '');
        $notes    = trim($data['notes']               ?? '');

        if (!$domain) { echo json_encode(['success'=>false,'message'=>'Domain name is required']); exit; }

        $allowed = ['pending transfer','locked','error epp code','move domain','done',
                    'cancelled','retransferred','transferred away','pending verification','renew period'];
        if (!in_array($status, $allowed, true)) $status = 'pending transfer';

        $start = ($start && preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) ? $start : null;
        $end   = ($end   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end))   ? $end   : null;

        $stmt = $conn->prepare(
            "INSERT INTO domain_transfers
             (domain_name, transfer_status, process_start_date, process_end_date,
              webnic_reseller_transfer, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        if (!$stmt) { echo json_encode(['success'=>false,'message'=>'DB prepare error']); exit; }
        $stmt->bind_param('ssssssi', $domain, $status, $start, $end, $webnic, $notes, $uid);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            $msg = "New domain transfer added: {$domain}";
            log_domain_activity($conn, 'domain_added', $msg, $domain, $uid);
            echo json_encode(['success'=>true,'message'=>'Domain transfer recorded']);
        } else {
            echo json_encode(['success'=>false,'message'=>'Error saving record']);
        }
        exit;
    }

    /* ── UPDATE ── */
    if ($action === 'update') {
        $id     = (int) ($data['id'] ?? 0);
        $domain = trim($data['domain_name']             ?? '');
        $status = $data['transfer_status']               ?? 'pending transfer';
        $start  = $data['process_start_date']            ?? null;
        $end    = $data['process_end_date']              ?? null;
        $webnic = trim($data['webnic_reseller_transfer'] ?? '');
        $notes  = trim($data['notes']                   ?? '');

        if (!$id || !$domain) { echo json_encode(['success'=>false,'message'=>'Invalid data']); exit; }

        $allowed = ['pending transfer','locked','error epp code','move domain','done',
                    'cancelled','retransferred','transferred away','pending verification','renew period'];
        if (!in_array($status, $allowed, true)) $status = 'pending transfer';

        $start = ($start && preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) ? $start : null;
        $end   = ($end   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end))   ? $end   : null;

        /* Fetch old status for activity delta */
        $old_status = '';
        $old_stmt = $conn->prepare("SELECT transfer_status FROM domain_transfers WHERE id = ?");
        if ($old_stmt) {
            $old_stmt->bind_param('i', $id);
            $old_stmt->execute();
            $old_stmt->bind_result($old_status);
            $old_stmt->fetch();
            $old_stmt->close();
        }

        $stmt = $conn->prepare(
            "UPDATE domain_transfers
             SET domain_name = ?, transfer_status = ?, process_start_date = ?,
                 process_end_date = ?, webnic_reseller_transfer = ?, notes = ?
             WHERE id = ?"
        );
        if (!$stmt) { echo json_encode(['success'=>false,'message'=>'DB prepare error']); exit; }
        $stmt->bind_param('ssssssi', $domain, $status, $start, $end, $webnic, $notes, $id);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            /* Auto-activity on meaningful status changes */
            if ($old_status !== $status) {
                $type = match($status) {
                    'done'             => 'domain_completed',
                    'cancelled'        => 'domain_cancelled',
                    'transferred away' => 'domain_transferred_away',
                    'error epp code'   => 'domain_error',
                    'move domain'      => 'domain_moved',
                    default            => 'domain_status_changed',
                };
                $msg_map = [
                    'done'             => "Domain {$domain} transfer completed",
                    'cancelled'        => "Domain {$domain} transfer cancelled",
                    'transferred away' => "Domain {$domain} transferred away",
                    'error epp code'   => "Domain {$domain} EPP code error — action required",
                    'move domain'      => "Domain {$domain} marked for move",
                    'pending transfer' => "Domain {$domain} marked as pending transfer",
                    'locked'           => "Domain {$domain} is locked",
                    'retransferred'    => "Domain {$domain} retransferred",
                    'pending verification' => "Domain {$domain} marked as pending verification",
                    'renew period'     => "Domain {$domain} entered renew period",
                ];
                $msg = $msg_map[$status] ?? "Domain {$domain} status changed to: {$status}";
                log_domain_activity($conn, $type, $msg, $domain, $uid);
            }
            echo json_encode(['success'=>true,'message'=>'Transfer updated']);
        } else {
            echo json_encode(['success'=>false,'message'=>'Error updating record']);
        }
        exit;
    }

    /* ── QUICK STATUS UPDATE (inline row dropdown) ── */
    if ($action === 'quick_status') {
        $id     = (int) ($data['id']     ?? 0);
        $status = trim($data['transfer_status'] ?? '');

        if (!$id || !$status) { echo json_encode(['success'=>false,'message'=>'Invalid data']); exit; }

        $allowed = ['pending transfer','locked','error epp code','move domain','done',
                    'cancelled','retransferred','transferred away','pending verification','renew period'];
        if (!in_array($status, $allowed, true)) { echo json_encode(['success'=>false,'message'=>'Invalid status']); exit; }

        /* Fetch old status for activity delta */
        $old_status = ''; $dn = '';
        $os = $conn->prepare("SELECT transfer_status, domain_name FROM domain_transfers WHERE id = ?");
        if ($os) { $os->bind_param('i',$id); $os->execute(); $os->bind_result($old_status,$dn); $os->fetch(); $os->close(); }

        $stmt = $conn->prepare("UPDATE domain_transfers SET transfer_status = ? WHERE id = ?");
        if (!$stmt) { echo json_encode(['success'=>false,'message'=>'DB error']); exit; }
        $stmt->bind_param('si', $status, $id);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok && $old_status !== $status && $dn) {
            $type = match($status) {
                'done'             => 'domain_completed',
                'cancelled'        => 'domain_cancelled',
                'transferred away' => 'domain_transferred_away',
                'error epp code'   => 'domain_error',
                'move domain'      => 'domain_moved',
                default            => 'domain_status_changed',
            };
            $msg_map = [
                'done'              => "Domain {$dn} transfer completed",
                'cancelled'         => "Domain {$dn} transfer cancelled",
                'transferred away'  => "Domain {$dn} transferred away",
                'error epp code'    => "Domain {$dn} EPP code error — action required",
                'move domain'       => "Domain {$dn} marked for move",
                'pending transfer'  => "Domain {$dn} marked as pending transfer",
                'locked'            => "Domain {$dn} is locked",
                'retransferred'     => "Domain {$dn} retransferred",
                'pending verification' => "Domain {$dn} marked as pending verification",
                'renew period'      => "Domain {$dn} entered renew period",
            ];
            $msg = $msg_map[$status] ?? "Domain {$dn} status changed to: {$status}";
            log_domain_activity($conn, $type, $msg, $dn, $uid);
        }
        echo json_encode(['success' => (bool)$ok, 'message' => $ok ? 'Status updated' : 'Error updating status']);
        exit;
    }

    /* ── QUICK MOVE DOMAIN UPDATE (inline row dropdown) ── */
    if ($action === 'quick_move') {
        $id   = (int) ($data['id']          ?? 0);
        $move = trim($data['move_domain']   ?? '');

        if (!$id) { echo json_encode(['success'=>false,'message'=>'Invalid ID']); exit; }

        $allowed_move = ['', 'Webnic', 'Resellercamp'];
        if (!in_array($move, $allowed_move, true)) { echo json_encode(['success'=>false,'message'=>'Invalid value']); exit; }

        $move_val = $move ?: null;
        $stmt = $conn->prepare("UPDATE domain_transfers SET webnic_reseller_transfer = ? WHERE id = ?");
        if (!$stmt) { echo json_encode(['success'=>false,'message'=>'DB error']); exit; }
        $stmt->bind_param('si', $move_val, $id);
        $ok = $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => (bool)$ok, 'message' => $ok ? 'Updated' : 'Error']);
        exit;
    }

    /* ── QUICK END DATE UPDATE (inline row date input) ── */
    if ($action === 'quick_end_date') {
        $id  = (int) ($data['id']              ?? 0);
        $end = trim($data['process_end_date']  ?? '');

        if (!$id) { echo json_encode(['success'=>false,'message'=>'Invalid ID']); exit; }

        $end_val = ($end && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) ? $end : null;

        $stmt = $conn->prepare("UPDATE domain_transfers SET process_end_date = ? WHERE id = ?");
        if (!$stmt) { echo json_encode(['success'=>false,'message'=>'DB error']); exit; }
        $stmt->bind_param('si', $end_val, $id);
        $ok = $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => (bool)$ok, 'message' => $ok ? 'End date updated' : 'Error updating end date']);
        exit;
    }

    /* ── DELETE ── */
    if ($action === 'delete') {
        $id = (int) ($data['id'] ?? 0);
        if (!$id) { echo json_encode(['success'=>false,'message'=>'Invalid ID']); exit; }

        /* Grab domain name for activity log */
        $domain_name = '';
        $gs = $conn->prepare("SELECT domain_name FROM domain_transfers WHERE id = ?");
        if ($gs) { $gs->bind_param('i',$id); $gs->execute(); $gs->bind_result($domain_name); $gs->fetch(); $gs->close(); }

        $stmt = $conn->prepare("DELETE FROM domain_transfers WHERE id = ?");
        if (!$stmt) { echo json_encode(['success'=>false,'message'=>'DB prepare error']); exit; }
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            if ($domain_name) log_domain_activity($conn,'domain_deleted',"Domain transfer record removed: {$domain_name}",$domain_name,$uid);
            echo json_encode(['success'=>true,'message'=>'Record deleted']);
        } else {
            echo json_encode(['success'=>false,'message'=>'Error deleting record']);
        }
        exit;
    }

    echo json_encode(['success'=>false,'message'=>'Unknown action']);
    exit;
}

/* ── Filters & Search ───────────────────────────────────────── */
$filter_status = $_GET['s']  ?? 'all';
$q             = trim($_GET['q'] ?? '');
$month         = $_GET['m']  ?? '';
$page          = max(1, intval($_GET['p'] ?? 1));
$per_page      = 25;
$offset        = ($page - 1) * $per_page;

/* ── Build WHERE clause ─────────────────────────────────────── */
$allowed_statuses = ['pending transfer','locked','error epp code','move domain','done',
                     'cancelled','retransferred','transferred away','pending verification','renew period'];

$conditions = [];
$bind_types = '';
$bind_vals  = [];

if (in_array($filter_status, $allowed_statuses, true)) {
    $conditions[] = "transfer_status = ?";
    $bind_types  .= 's';
    $bind_vals[]  = $filter_status;
}

if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
    $conditions[] = "DATE_FORMAT(process_start_date, '%Y-%m') = ?";
    $bind_types  .= 's';
    $bind_vals[]  = $month;
}

if ($q !== '') {
    $like = '%' . $q . '%';
    $conditions[] = "(domain_name LIKE ? OR webnic_reseller_transfer LIKE ? OR notes LIKE ?)";
    $bind_types  .= 'sss';
    $bind_vals[]  = $like;
    $bind_vals[]  = $like;
    $bind_vals[]  = $like;
}

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

/* ── Total count ────────────────────────────────────────────── */
$count_sql  = "SELECT COUNT(*) FROM domain_transfers $where";
$count_stmt = $conn->prepare($count_sql);
if ($bind_types) $count_stmt->bind_param($bind_types, ...$bind_vals);
$count_stmt->execute();
$count_stmt->bind_result($total_rows);
$count_stmt->fetch();
$count_stmt->close();
$total_pages = max(1, (int) ceil($total_rows / $per_page));

/* ── Fetch current page ─────────────────────────────────────── */
$data_sql  = "SELECT * FROM domain_transfers $where ORDER BY created_at DESC LIMIT ? OFFSET ?";
$data_stmt = $conn->prepare($data_sql);
$full_types = $bind_types . 'ii';
$full_vals  = array_merge($bind_vals, [$per_page, $offset]);
$data_stmt->bind_param($full_types, ...$full_vals);
$data_stmt->execute();
$result          = $data_stmt->get_result();
$domain_records  = [];
while ($row = $result->fetch_assoc()) $domain_records[] = $row;
$data_stmt->close();

/* ── Stats: all-time ────────────────────────────────────────── */
$stats = $conn->query("
  SELECT
    COUNT(*)                                                        AS total,
    SUM(CASE WHEN transfer_status = 'pending transfer' THEN 1 ELSE 0 END) AS pending_count,
    SUM(CASE WHEN transfer_status = 'done'             THEN 1 ELSE 0 END) AS done_count,
    SUM(CASE WHEN transfer_status IN ('error epp code','locked')    THEN 1 ELSE 0 END) AS error_count,
    SUM(CASE WHEN transfer_status = 'cancelled'        THEN 1 ELSE 0 END) AS cancelled_count
  FROM domain_transfers
")->fetch_assoc();

$stat_total     = (int) ($stats['total']          ?? 0);
$stat_pending   = (int) ($stats['pending_count']  ?? 0);
$stat_done      = (int) ($stats['done_count']     ?? 0);
$stat_error     = (int) ($stats['error_count']    ?? 0);
$stat_cancelled = (int) ($stats['cancelled_count']?? 0);

/* ── Month dropdown ─────────────────────────────────────────── */
$months_res = $conn->query("
  SELECT DISTINCT DATE_FORMAT(process_start_date,'%Y-%m') AS ym,
                  DATE_FORMAT(process_start_date,'%M %Y') AS label
  FROM domain_transfers
  WHERE process_start_date IS NOT NULL
  ORDER BY ym DESC
  LIMIT 24
");
$month_options = [];
if ($months_res) while ($mr = $months_res->fetch_assoc()) $month_options[] = $mr;

/* ── Status helpers ─────────────────────────────────────────── */
function dt_status_class(string $s): string {
    return match($s) {
        'pending transfer'    => 'dt-status-pending',
        'locked'              => 'dt-status-locked',
        'error epp code'      => 'dt-status-error',
        'move domain'         => 'dt-status-move',
        'done'                => 'dt-status-done',
        'cancelled'           => 'dt-status-cancelled',
        'retransferred'       => 'dt-status-retransferred',
        'transferred away'    => 'dt-status-away',
        'pending verification'=> 'dt-status-verify',
        'renew period'        => 'dt-status-renew',
        default               => '',
    };
}
function dt_status_label(string $s): string {
    return match($s) {
        'pending transfer'    => 'Pending Transfer',
        'locked'              => 'Locked',
        'error epp code'      => 'Error EPP Code',
        'move domain'         => 'Move Domain',
        'done'                => 'Done',
        'cancelled'           => 'Cancelled',
        'retransferred'       => 'Retransferred',
        'transferred away'    => 'Transferred Away',
        'pending verification'=> 'Pending Verification',
        'renew period'        => 'Renew Period',
        default               => htmlspecialchars($s, ENT_QUOTES, 'UTF-8'),
    };
}

/* ── Page bootstrap ─────────────────────────────────────────── */
$page_title  = 'Domain Transfers';
$active_page = 'domains';
include 'includes/header.php';
?>

<main class="main"><div class="main-inner">

<!-- ── Topbar ─────────────────────────────────────────────────── -->
<div class="topbar">
  <div>
    <div class="page-title">Domain Transfer Log</div>
    <div class="page-sub">Domain transfer monitoring · CS/Ops team · <?= $stat_total ?> total records</div>
  </div>
</div>

<!-- ── Stat strip ─────────────────────────────────────────────── -->
<div class="stat-strip">
  <div class="stat-card blue">
    <div class="stat-glow"></div>
    <div class="stat-num"><?= $stat_total ?></div>
    <div class="stat-label">Total Transfers</div>
  </div>
  <div class="stat-card amber">
    <div class="stat-glow"></div>
    <div class="stat-num"><?= $stat_pending ?></div>
    <div class="stat-label">Pending Transfer</div>
  </div>
  <div class="stat-card green">
    <div class="stat-glow"></div>
    <div class="stat-num"><?= $stat_done ?></div>
    <div class="stat-label">Completed</div>
  </div>
  <div class="stat-card red">
    <div class="stat-glow"></div>
    <div class="stat-num"><?= $stat_error ?></div>
    <div class="stat-label">Error / Problem</div>
  </div>
  <div class="stat-card" style="--card-accent:var(--tx3)">
    <div class="stat-glow"></div>
    <div class="stat-num"><?= $stat_cancelled ?></div>
    <div class="stat-label">Cancelled</div>
  </div>
</div>

<!-- ── Filter & Search bar ───────────────────────────────────── -->
<div class="filter-search-row">

  <!-- Status filter tabs -->
  <div class="filter-bar">
    <a href="?s=all&q=<?= urlencode($q) ?>&m=<?= urlencode($month) ?>"
       class="filter-tab <?= $filter_status === 'all' ? 'active' : '' ?>">All</a>
    <?php
    $tab_statuses = [
        'pending transfer'    => 'Pending',
        'locked'              => 'Locked',
        'error epp code'      => 'Error EPP',
        'move domain'         => 'Move',
        'done'                => 'Done',
        'cancelled'           => 'Cancelled',
        'retransferred'       => 'Retransferred',
        'transferred away'    => 'Transferred Away',
        'pending verification'=> 'Verification',
        'renew period'        => 'Renew',
    ];
    foreach ($tab_statuses as $k => $l): ?>
    <a href="?s=<?= urlencode($k) ?>&q=<?= urlencode($q) ?>&m=<?= urlencode($month) ?>"
       class="filter-tab <?= $filter_status === $k ? 'active' : '' ?>"><?= esc($l) ?></a>
    <?php endforeach; ?>
  </div>

</div>

<!-- ── Second filter row: month + search + reset ─────────────── -->
<div class="filter-search-row filter-search-row-mt">

  <!-- Month picker -->
  <?php if ($month_options): ?>
  <div class="month-select-wrap">
    <label>Month</label>
    <select class="form-select compact-select"
            onchange="location.href='?s=<?= esc($filter_status) ?>&q=<?= urlencode($q) ?>&m='+this.value">
      <option value="">All months</option>
      <?php foreach ($month_options as $mo): ?>
      <option value="<?= esc($mo['ym']) ?>" <?= $month === $mo['ym'] ? 'selected' : '' ?>>
        <?= esc($mo['label']) ?>
      </option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php endif; ?>

  <!-- Search -->
  <form method="get" class="search-form-wrap">
    <input type="hidden" name="s" value="<?= esc($filter_status) ?>">
    <input type="hidden" name="m" value="<?= esc($month) ?>">
    <i data-lucide="search" class="search-ic icon-sm"></i>
    <input type="text" name="q" class="search-input"
           placeholder="Search domain, reseller, notes…"
           value="<?= esc($q) ?>">
    <?php if ($q || $month || $filter_status !== 'all'): ?>
    <a href="?" class="btn btn-ghost btn-reset btn-sm">Reset</a>
    <?php endif; ?>
  </form>

</div><!-- /filter row -->

<!-- ── Domain Transfer Table ────────────────────────────────── -->
<div class="panel panel-mt">
  <div class="panel-head">
    <span class="panel-title">Transfer Records</span>
    <span class="panel-meta">
      <?= $total_rows ?> record<?= $total_rows !== 1 ? 's' : '' ?>
      <?= $q ? ' · "' . esc($q) . '"' : '' ?>
      <?= $month ? ' · ' . esc($month) : '' ?>
    </span>
  </div>

  <!-- ── Inline Quick-Entry Form ────────────────────────────── -->
  <div class="dt-inline-form">
    <div class="dt-inline-label">
      <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:11px;height:11px;flex-shrink:0"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Add Domain Transfer
    </div>
    <div class="dt-inline-grid">

      <div class="dt-inline-group">
        <label class="dt-inline-lbl">Domain Name <span class="req-star">*</span></label>
        <input type="text" class="form-input dt-inline-input" id="nDomain"
               placeholder="example.com" autocomplete="off"
               onkeydown="if(event.key==='Enter')quickSaveDt()">
      </div>

      <div class="dt-inline-group">
        <label class="dt-inline-lbl">Status <span class="req-star">*</span></label>
        <select class="form-select dt-inline-input" id="nStatus">
          <option value="pending transfer">Pending Transfer</option>
          <option value="locked">Locked</option>
          <option value="error epp code">Error EPP Code</option>
          <option value="move domain">Move Domain</option>
          <option value="done">Done</option>
          <option value="cancelled">Cancelled</option>
          <option value="retransferred">Retransferred</option>
          <option value="transferred away">Transferred Away</option>
          <option value="pending verification">Pending Verification</option>
          <option value="renew period">Renew Period</option>
        </select>
      </div>

      <div class="dt-inline-group">
        <label class="dt-inline-lbl">Start Date</label>
        <input type="date" class="form-input dt-inline-input" id="nStartDate">
      </div>

      <div class="dt-inline-group">
        <label class="dt-inline-lbl">End Date</label>
        <input type="date" class="form-input dt-inline-input" id="nEndDate">
      </div>

      <div class="dt-inline-group">
        <label class="dt-inline-lbl">Move Domain</label>
        <select class="form-select dt-inline-input" id="nWebnic">
          <option value="">—</option>
          <option value="Webnic">Webnic</option>
          <option value="Resellercamp">Resellercamp</option>
        </select>
      </div>

      <div class="dt-inline-group dt-inline-action">
        <label class="dt-inline-lbl">&nbsp;</label>
        <button class="btn btn-primary dt-save-btn" onclick="quickSaveDt()">
          <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><polyline points="20 6 9 17 4 12"/></svg>
          Save
        </button>
      </div>

    </div><!-- /dt-inline-grid -->
  </div><!-- /dt-inline-form -->

  <?php if (empty($domain_records)): ?>
  <div class="dt-empty">
    <div class="dt-empty-ic"><i data-lucide="globe" class="icon-xl"></i></div>
    <div class="dt-empty-t">No transfer records found</div>
    <div class="dt-empty-s">
      <?= ($q || $month || $filter_status !== 'all')
          ? 'Try adjusting filters or search terms'
          : 'Add a domain transfer above to get started' ?>
    </div>
  </div>

  <?php else: ?>
  <div class="dt-table-wrap">
  <table class="dt-table">
    <thead>
      <tr>
        <th style="width:38px">No</th>
        <th>Domain</th>
        <th>Status</th>
        <th>Start Date</th>
        <th>End Date</th>
        <th>Move Domain</th>
        <th>Notes</th>
        <th style="width:60px"></th>
      </tr>
    </thead>
    <tbody>
    <?php
    $row_num = $offset + 1;
    foreach ($domain_records as $dr):
      $did      = (int) $dr['id'];
      $status   = $dr['transfer_status'];
      $scls     = dt_status_class($status);
      $slabel   = dt_status_label($status);

      $start_fmt = $dr['process_start_date'] ? date('d M Y', strtotime($dr['process_start_date'])) : null;
      $end_fmt   = $dr['process_end_date']   ? date('d M Y', strtotime($dr['process_end_date']))   : null;

      $move_val = $dr['webnic_reseller_transfer'] ?? '';

      $row_json = htmlspecialchars(json_encode([
        'id'                       => $did,
        'domain_name'              => $dr['domain_name'],
        'transfer_status'          => $status,
        'process_start_date'       => $dr['process_start_date'] ?? '',
        'process_end_date'         => $dr['process_end_date']   ?? '',
        'webnic_reseller_transfer' => $move_val,
        'notes'                    => $dr['notes'] ?? '',
      ]), ENT_QUOTES, 'UTF-8');
    ?>
    <tr data-dt-id="<?= $did ?>">
      <td><span class="dt-rownum"><?= $row_num++ ?></span></td>

      <td>
        <div class="dt-domain-name" title="<?= esc($dr['domain_name']) ?>"><?= esc($dr['domain_name']) ?></div>
        <div class="dt-domain-sub">#<?= $did ?></div>
      </td>

      <!-- ── Inline-editable status cell ── -->
      <td>
        <div class="dt-status-wrap" title="Click to change status">
          <span class="dt-status <?= $scls ?>" id="dt-status-badge-<?= $did ?>"><?= $slabel ?></span>
          <select class="dt-status-select" onchange="quickStatusUpdate(<?= $did ?>, this)"
                  aria-label="Change status for <?= esc($dr['domain_name']) ?>">
            <option value="pending transfer"    <?= $status==='pending transfer'    ?'selected':'' ?>>Pending Transfer</option>
            <option value="locked"              <?= $status==='locked'              ?'selected':'' ?>>Locked</option>
            <option value="error epp code"      <?= $status==='error epp code'      ?'selected':'' ?>>Error EPP Code</option>
            <option value="move domain"         <?= $status==='move domain'         ?'selected':'' ?>>Move Domain</option>
            <option value="done"                <?= $status==='done'                ?'selected':'' ?>>Done</option>
            <option value="cancelled"           <?= $status==='cancelled'           ?'selected':'' ?>>Cancelled</option>
            <option value="retransferred"       <?= $status==='retransferred'       ?'selected':'' ?>>Retransferred</option>
            <option value="transferred away"    <?= $status==='transferred away'    ?'selected':'' ?>>Transferred Away</option>
            <option value="pending verification"<?= $status==='pending verification'?'selected':'' ?>>Pending Verification</option>
            <option value="renew period"        <?= $status==='renew period'        ?'selected':'' ?>>Renew Period</option>
          </select>
        </div>
      </td>

      <td>
        <?php if ($start_fmt): ?>
          <span class="dt-date"><?= esc($start_fmt) ?></span>
        <?php else: ?>
          <span class="dt-date-none">—</span>
        <?php endif; ?>
      </td>

      <!-- ── Inline-editable end date ── -->
      <td>
        <input type="date"
               class="dt-date-input <?= $dr['process_end_date'] ? 'has-value' : '' ?>"
               id="dt-end-<?= $did ?>"
               value="<?= esc($dr['process_end_date'] ?? '') ?>"
               onchange="quickEndDateUpdate(<?= $did ?>, this)"
               title="Click to set end date">
      </td>

      <!-- ── Inline move domain dropdown ── -->
      <td>
        <select class="dt-move-select <?= $move_val ? 'has-value' : '' ?>"
                id="dt-move-<?= $did ?>"
                onchange="quickMoveUpdate(<?= $did ?>, this)"
                aria-label="Move domain for <?= esc($dr['domain_name']) ?>">
          <option value=""            <?= !$move_val            ?'selected':'' ?>>—</option>
          <option value="Webnic"      <?= $move_val==='Webnic'      ?'selected':'' ?>>Webnic</option>
          <option value="Resellercamp"<?= $move_val==='Resellercamp'?'selected':'' ?>>Resellercamp</option>
        </select>
      </td>

      <td>
        <?php if ($dr['notes']): ?>
          <span class="dt-notes" title="<?= esc($dr['notes']) ?>"><?= esc($dr['notes']) ?></span>
        <?php else: ?>
          <span class="dt-notes-none">—</span>
        <?php endif; ?>
      </td>

      <td>
        <div class="dt-acts">
          <button class="btn btn-ghost btn-icon"
                  onclick="openEditDt(<?= $row_json ?>)"
                  title="Edit">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
          </button>
          <button class="btn btn-danger btn-icon"
                  onclick="deleteDt(<?= $did ?>)"
                  title="Delete">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
          </button>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div><!-- /dt-table-wrap -->

  <!-- ── Pagination ──────────────────────────────────────────── -->
  <?php if ($total_pages > 1): ?>
  <div class="dt-pagination">
    <span>
      <?= number_format($offset + 1) ?>–<?= number_format(min($offset + $per_page, $total_rows)) ?>
      of <?= number_format($total_rows) ?> records
    </span>
    <div class="dt-pages">

      <a href="?s=<?= esc($filter_status) ?>&q=<?= urlencode($q) ?>&m=<?= urlencode($month) ?>&p=<?= max(1, $page - 1) ?>"
         class="dt-page-btn <?= $page <= 1 ? 'disabled' : '' ?>">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:10px;height:10px;stroke-width:3"><polyline points="15 18 9 12 15 6"/></svg>
      </a>

      <?php
      $start_pg = max(1, min($page - 3, $total_pages - 6));
      $end_pg   = min($total_pages, $start_pg + 6);
      for ($pg = $start_pg; $pg <= $end_pg; $pg++):
      ?>
      <a href="?s=<?= esc($filter_status) ?>&q=<?= urlencode($q) ?>&m=<?= urlencode($month) ?>&p=<?= $pg ?>"
         class="dt-page-btn <?= $pg === $page ? 'active' : '' ?>"><?= $pg ?></a>
      <?php endfor; ?>

      <a href="?s=<?= esc($filter_status) ?>&q=<?= urlencode($q) ?>&m=<?= urlencode($month) ?>&p=<?= min($total_pages, $page + 1) ?>"
         class="dt-page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:10px;height:10px;stroke-width:3"><polyline points="9 18 15 12 9 6"/></svg>
      </a>

    </div>
  </div>
  <?php endif; ?>

  <?php endif; ?><!-- /empty check -->
</div><!-- /panel -->

</div></main><!-- /main-inner /main -->

<!-- ══════════════════════════════════════════════
     EDIT DOMAIN TRANSFER MODAL
══════════════════════════════════════════════ -->
<div class="modal-overlay hidden" id="dtModal">
<div class="modal" style="max-width:520px">
  <div class="modal-head">
    <div>
      <div class="modal-title">Edit Domain Transfer</div>
      <div class="modal-sub">Update transfer record</div>
    </div>
    <button class="modal-close" onclick="closeModal('dt')">
      <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
  </div>
  <div class="modal-body">
    <input type="hidden" id="dtId">

    <div class="form-group">
      <label class="form-label">Domain Name <span class="req-star">*</span></label>
      <input type="text" class="form-input" id="dtDomain" placeholder="example.com" autocomplete="off">
    </div>

    <div class="form-group">
      <label class="form-label">Transfer Status <span class="req-star">*</span></label>
      <select class="form-select" id="dtStatus">
        <option value="pending transfer">Pending Transfer</option>
        <option value="locked">Locked</option>
        <option value="error epp code">Error EPP Code</option>
        <option value="move domain">Move Domain</option>
        <option value="done">Done</option>
        <option value="cancelled">Cancelled</option>
        <option value="retransferred">Retransferred</option>
        <option value="transferred away">Transferred Away</option>
        <option value="pending verification">Pending Verification</option>
        <option value="renew period">Renew Period</option>
      </select>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Process Start Date</label>
        <input type="date" class="form-input" id="dtStartDate">
      </div>
      <div class="form-group">
        <label class="form-label">Process End Date</label>
        <input type="date" class="form-input" id="dtEndDate">
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">Move Domain</label>
      <select class="form-select" id="dtWebnic">
        <option value="">—</option>
        <option value="Webnic">Webnic</option>
        <option value="Resellercamp">Resellercamp</option>
      </select>
    </div>

    <div class="form-group">
      <label class="form-label">Notes <span style="color:var(--tx4)">(optional)</span></label>
      <textarea class="form-input" id="dtNotes" rows="3" placeholder="Additional notes…" style="resize:vertical;min-height:60px"></textarea>
    </div>
  </div>
  <div class="modal-foot">
    <button class="btn btn-ghost" onclick="closeModal('dt')">Cancel</button>
    <button class="btn btn-primary" onclick="saveEditDt()">
      <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><polyline points="20 6 9 17 4 12"/></svg>
      Update Transfer
    </button>
  </div>
</div>
</div>

<?php include 'includes/footer.php'; ?>
