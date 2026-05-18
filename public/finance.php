<?php
/* ─────────────────────────────────────────────────────────────
   TRACS — finance.php
   Account Balance Transfer Logging System
   CS / Operations Team · Balance Transfer Log
   ───────────────────────────────────────────────────────────── */
require_once __DIR__ . '/../core/security/csrf.php';
tracs_start_session();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth/auth_check.php';
require_once __DIR__ . '/../modules/alert-ticker/controller.php';
require_once __DIR__ . '/includes/page_helpers.php';

$uid        = $_SESSION['user_id']    ?? 0;
$user_email = $_SESSION['user_email'] ?? 'operator@tracs.local';

$TC           = new AlertTickerController($conn, $uid);
$ticker_items = $TC->formatAlertsForTicker();
$critical_count = 0;

/* ── Auto-create table (migration-safe) ─────────────────────── */
$conn->query("CREATE TABLE IF NOT EXISTS `balance_transfers` (
  `id`               INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `transfer_date`    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sender_email`     VARCHAR(254)     NOT NULL DEFAULT '',
  `sender_user_id`   VARCHAR(100)     NOT NULL DEFAULT '',
  `sender_type`      ENUM('client_area','billing_console','billing_awan') NOT NULL DEFAULT 'client_area',
  `receiver_email`   VARCHAR(254)     NOT NULL DEFAULT '',
  `receiver_user_id` VARCHAR(100)     NOT NULL DEFAULT '',
  `receiver_type`    ENUM('client_area','billing_console','billing_awan') NOT NULL DEFAULT 'client_area',
  `amount`           DECIMAL(15,2)    NOT NULL DEFAULT '0.00',
  `status`           ENUM('done','pending') NOT NULL DEFAULT 'pending',
  `admin_name`       VARCHAR(150)     NOT NULL DEFAULT '',
  `ticket_id`        VARCHAR(100)     NULL DEFAULT NULL,
  `created_at`       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_admin`          (`admin_name`),
  INDEX `idx_ticket`         (`ticket_id`),
  INDEX `idx_transfer_date`  (`transfer_date`),
  INDEX `idx_sender_email`   (`sender_email`(64)),
  INDEX `idx_receiver_email` (`receiver_email`(64)),
  INDEX `idx_status`         (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
tracs_ensure_creator_columns($conn, 'balance_transfers', null);

/* ── Filters & Search ───────────────────────────────────────── */
$filter_status = $_GET['s']    ?? 'all';   // all | done | pending
$q             = trim($_GET['q'] ?? '');   // search query
$month         = $_GET['m']    ?? '';      // YYYY-MM
$page          = max(1, intval($_GET['p'] ?? 1));
$per_page      = 25;
$offset        = ($page - 1) * $per_page;

/* ── Build WHERE clause ─────────────────────────────────────── */
$conditions = [];
$bind_types = '';
$bind_vals  = [];

if ($filter_status === 'done')    { $conditions[] = "status = 'done'"; }
if ($filter_status === 'pending') { $conditions[] = "status = 'pending'"; }

if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
    $conditions[] = "DATE_FORMAT(transfer_date, '%Y-%m') = ?";
    $bind_types  .= 's';
    $bind_vals[]  = $month;
}

if ($q !== '') {
    $like = '%' . $q . '%';
    $conditions[] = "(sender_email LIKE ? OR receiver_email LIKE ?
                    OR sender_user_id LIKE ? OR receiver_user_id LIKE ?
                    OR ticket_id LIKE ? OR admin_name LIKE ?
                    OR bt.created_by_name LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
    $bind_types  .= 'sssssssss';
    $bind_vals[]  = $like;
    $bind_vals[]  = $like;
    $bind_vals[]  = $like;
    $bind_vals[]  = $like;
    $bind_vals[]  = $like;
    $bind_vals[]  = $like;
    $bind_vals[]  = $like;
    $bind_vals[]  = $like;
    $bind_vals[]  = $like;
}

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
$from_sql = "balance_transfers bt LEFT JOIN tracs_users u ON bt.created_by = u.id";

/* ── Total count for pagination ─────────────────────────────── */
$count_sql  = "SELECT COUNT(*) FROM $from_sql $where";
$count_stmt = $conn->prepare($count_sql);
if ($bind_types) $count_stmt->bind_param($bind_types, ...$bind_vals);
$count_stmt->execute();
$count_stmt->bind_result($total_rows);
$count_stmt->fetch();
$count_stmt->close();
$total_pages = max(1, (int) ceil($total_rows / $per_page));

/* ── Fetch current page ─────────────────────────────────────── */
$data_sql  = "SELECT bt.*, COALESCE(NULLIF(bt.created_by_name,''), NULLIF(u.name,''), u.email, NULLIF(bt.admin_name,''), 'System') AS creator_name FROM $from_sql $where ORDER BY bt.transfer_date DESC LIMIT ? OFFSET ?";
$data_stmt = $conn->prepare($data_sql);
$full_types = $bind_types . 'ii';
$full_vals  = array_merge($bind_vals, [$per_page, $offset]);
$data_stmt->bind_param($full_types, ...$full_vals);
$data_stmt->execute();
$result     = $data_stmt->get_result();
$transfers  = [];
while ($row = $result->fetch_assoc()) $transfers[] = $row;
$data_stmt->close();

/* ── Stats: all-time ────────────────────────────────────────── */
$stats = $conn->query("
  SELECT
    COUNT(*)                                        AS total,
    SUM(amount)                                     AS total_amount,
    SUM(CASE WHEN status='done'    THEN amount ELSE 0 END) AS done_amount,
    SUM(CASE WHEN status='pending' THEN amount ELSE 0 END) AS pending_amount,
    SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END)      AS pending_count
  FROM balance_transfers
")->fetch_assoc();

$stat_total          = (int)   ($stats['total']         ?? 0);
$stat_total_amount   = (float) ($stats['total_amount']  ?? 0);
$stat_done_amount    = (float) ($stats['done_amount']   ?? 0);
$stat_pending_amount = (float) ($stats['pending_amount']?? 0);
$stat_pending_count  = (int)   ($stats['pending_count'] ?? 0);

/* ── Stats: current month ───────────────────────────────────── */
$month_stats = $conn->query("
  SELECT
    COUNT(*)   AS month_count,
    SUM(amount) AS month_amount
  FROM balance_transfers
  WHERE DATE_FORMAT(transfer_date,'%Y-%m') = DATE_FORMAT(NOW(),'%Y-%m')
")->fetch_assoc();
$stat_month_count  = (int)   ($month_stats['month_count']  ?? 0);
$stat_month_amount = (float) ($month_stats['month_amount'] ?? 0);

/* ── Month dropdown options ─────────────────────────────────── */
$months_res = $conn->query("
  SELECT DISTINCT DATE_FORMAT(transfer_date,'%Y-%m') AS ym,
                  DATE_FORMAT(transfer_date,'%M %Y') AS label
  FROM balance_transfers
  ORDER BY ym DESC
  LIMIT 24
");
$month_options = [];
while ($mr = $months_res->fetch_assoc()) $month_options[] = $mr;

/* ── Helper: type label ─────────────────────────────────────── */
function type_label(string $t): string {
    return match($t) {
        'client_area'     => 'Client Area',
        'billing_console' => 'Billing Console',
        'billing_awan'    => 'Billing Awan',
        default           => esc($t),
    };
}
function type_class(string $t): string {
    return match($t) {
        'client_area'     => 'type-ca',
        'billing_console' => 'type-bc',
        'billing_awan'    => 'type-ba',
        default           => '',
    };
}

/* ── Page bootstrap ─────────────────────────────────────────── */
$page_title  = 'Finance';
$active_page = 'finance';
include 'includes/header.php';
?>

<main class="main"><div class="main-inner">

<!-- ── Topbar ─────────────────────────────────────────────────── -->
<div class="topbar">
  <div>
    <div class="page-title">Balance Transfer Log</div>
    <div class="page-sub">Account balance transfers · CS/Ops team · <?= $stat_total ?> total records</div>
  </div>
</div>

<!-- ── Stat strip ─────────────────────────────────────────────── -->
<div class="stat-strip">
  <div class="stat-card finance-stat-card blue">
    <div class="stat-glow"></div>
    <div class="stat-num rp">Rp <?= number_format($stat_total_amount, 0, ',', '.') ?></div>
    <div class="stat-label">Total Transferred</div>
  </div>
  <div class="stat-card finance-stat-card green">
    <div class="stat-glow"></div>
    <div class="stat-num rp">Rp <?= number_format($stat_done_amount, 0, ',', '.') ?></div>
    <div class="stat-label">Completed</div>
  </div>
  <div class="stat-card finance-stat-card amber">
    <div class="stat-glow"></div>
    <div class="stat-num"><?= $stat_pending_count ?></div>
    <div class="stat-label">Pending Transfers</div>
  </div>
  <div class="stat-card finance-stat-card cyan">
    <div class="stat-glow"></div>
    <div class="stat-num rp">Rp <?= number_format($stat_month_amount, 0, ',', '.') ?></div>
    <div class="stat-label">This Month</div>
  </div>
</div>

<!-- ── Filter & Search bar ───────────────────────────────────── -->
<div class="filter-search-row">

  <!-- Status filter -->
  <div class="filter-bar">
    <?php foreach (['all' => 'All', 'done' => 'Done', 'pending' => 'Pending'] as $k => $l): ?>
    <a href="?s=<?= $k ?>&q=<?= urlencode($q) ?>&m=<?= urlencode($month) ?>"
       class="filter-tab <?= $filter_status === $k ? 'active' : '' ?>"><?= $l ?></a>
    <?php endforeach; ?>
  </div>

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
           placeholder="Search customer email, user ID, ticket, or operator"
           value="<?= esc($q) ?>">
  </form>

</div><!-- /filter row -->

<!-- ── Transfer table ───────────────────────────────────────── -->
<div class="panel">
  <div class="panel-head">
    <span class="panel-title">Transfer Records</span>
    <div class="panel-right">
      <span class="panel-meta">
        <?= $total_rows ?> record<?= $total_rows !== 1 ? 's' : '' ?>
        <?= $q ? ' · "' . esc($q) . '"' : '' ?>
        <?= $month ? ' · ' . esc($month) : '' ?>
      </span>
      <details class="report-export-menu">
        <summary class="btn btn-ghost btn-icon report-export-trigger" title="More actions" aria-label="More actions" data-tooltip="More actions"><i data-lucide="more-vertical" class="icon-sm"></i></summary>
        <form method="get" action="/api/export-finance.php" class="report-export-popover">
          <input type="hidden" name="s" value="<?= esc($filter_status) ?>">
          <input type="hidden" name="m" value="<?= esc($month) ?>">
          <input type="hidden" name="q" value="<?= esc($q) ?>">
          <div class="report-export-title">
            <i data-lucide="download" class="icon-xs"></i>
            Export CSV
          </div>
          <label>From Date<input type="date" name="from" class="form-input"></label>
          <label>To Date<input type="date" name="to" class="form-input"></label>
          <button type="submit" class="btn btn-primary"><i data-lucide="download" class="icon-sm"></i>Download CSV</button>
        </form>
      </details>
    </div>
  </div>

  <!-- ── Inline Quick-Entry Form ────────────────────────────── -->
  <div class="bt-inline-form" id="btInlineForm">
    <div class="bt-inline-label">
      <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:11px;height:11px;flex-shrink:0"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      New Transfer
    </div>

    <div class="bt-inline-grid">

      <!-- Row 1: sender group -->
      <div class="bt-inline-group bt-field-email">
        <label class="bt-inline-lbl">Sender Email</label>
        <input type="email" class="form-input bt-inline-input" id="nSenderEmail" placeholder="Sender email, e.g. client@domain.com" autocomplete="off">
      </div>
      <div class="bt-inline-group bt-field-uid">
        <label class="bt-inline-lbl">Sender UID</label>
        <input type="text" class="form-input bt-inline-input" id="nSenderUid" placeholder="Sender user ID, e.g. CID-10042" autocomplete="off">
      </div>
      <div class="bt-inline-group bt-field-type">
        <label class="bt-inline-lbl">Sender Type <span class="req-star">*</span></label>
        <select class="form-select bt-inline-input" id="nSenderType">
          <option value="client_area">Client Area</option>
          <option value="billing_console">Billing Console</option>
          <option value="billing_awan">Billing Awan</option>
        </select>
      </div>


      <!-- Row 2: receiver group -->
      <div class="bt-inline-group bt-field-email">
        <label class="bt-inline-lbl">Receiver Email</label>
        <input type="email" class="form-input bt-inline-input" id="nReceiverEmail" placeholder="Receiver email, e.g. billing@domain.com" autocomplete="off">
      </div>
      <div class="bt-inline-group bt-field-uid">
        <label class="bt-inline-lbl">Receiver UID</label>
        <input type="text" class="form-input bt-inline-input" id="nReceiverUid" placeholder="Receiver user ID, e.g. CID-10088" autocomplete="off">
      </div>
      <div class="bt-inline-group bt-field-type">
        <label class="bt-inline-lbl">Receiver Type <span class="req-star">*</span></label>
        <select class="form-select bt-inline-input" id="nReceiverType">
          <option value="client_area">Client Area</option>
          <option value="billing_console">Billing Console</option>
          <option value="billing_awan">Billing Awan</option>
        </select>
      </div>


      <!-- Row 3: amount, status, ticket, date, save -->
      <div class="bt-inline-group bt-field-amount">
        <label class="bt-inline-lbl">Amount (Rp) <span class="req-star">*</span></label>
        <input type="number" class="form-input bt-inline-input" id="nAmount" placeholder="Transfer amount" min="0" step="0.01"
               onkeydown="if(event.key==='Enter')quickSaveBt()">
      </div>
      <div class="bt-inline-group bt-field-status">
        <label class="bt-inline-lbl">Status</label>
        <select class="form-select bt-inline-input" id="nStatus">
          <option value="pending">Pending</option>
          <option value="done">Done</option>
        </select>
      </div>
      <div class="bt-inline-group bt-field-ticket">
        <label class="bt-inline-lbl">Ticket ID</label>
        <input type="text" class="form-input bt-inline-input" id="nTicket" placeholder="Ticket ID, e.g. WHMCS-2026-001" autocomplete="off"
               onkeydown="if(event.key==='Enter')quickSaveBt()">
      </div>
      <div class="bt-inline-group bt-field-datetime">
        <label class="bt-inline-lbl">Transfer Date & Time</label>
        <div class="bt-datetime-pair">
          <input type="date" class="form-input split-date bt-inline-input" id="nDateVal" data-sync="nDate">
          <input type="time" class="form-input split-time bt-inline-input" id="nTimeVal" data-sync="nDate">
        </div>
        <input type="hidden" id="nDate" class="quick-datetime">
      </div>
      <div class="bt-inline-group bt-inline-action">
        <label class="bt-inline-lbl">&nbsp;</label>
        <button class="btn btn-primary bt-save-btn" onclick="quickSaveBt()">
          <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><polyline points="20 6 9 17 4 12"/></svg>
          Save
        </button>
      </div>

    </div><!-- /bt-inline-grid -->
  </div><!-- /bt-inline-form -->

  <?php if (empty($transfers)): ?>
  <div class="bt-empty">
    <div class="bt-empty-ic"><i data-lucide="credit-card" class="icon-xl"></i></div>
    <div class="bt-empty-t">No transfers found</div>
    <div class="bt-empty-s">
      <?= $q || $month || $filter_status !== 'all'
          ? 'Try adjusting filters or search terms'
          : 'Log a balance transfer to get started' ?>
    </div>
  </div>

  <?php else: ?>
  <div class="bt-table-wrap">
  <table class="bt-table">
    <thead>
      <tr>
        <th style="width:38px">No</th>
        <th>Transfer Date</th>
        <th>Sender</th>
        <th>Type</th>
        <th style="width:20px"></th>
        <th>Receiver</th>
        <th>Type</th>
        <th style="text-align:right">Amount</th>
        <th>Status</th>
        <th>Ticket ID</th>
      </tr>
    </thead>
    <tbody>
    <?php
    $row_num = $offset + 1;
    foreach ($transfers as $tr):
      $tid     = (int) $tr['id'];
      $dt_main = date('d M Y', strtotime($tr['transfer_date']));
      $dt_time = date('H:i',   strtotime($tr['transfer_date']));
      $amt     = number_format((float) $tr['amount'], 2, ',', '.');
      $stype   = type_label($tr['sender_type']);
      $rtype   = type_label($tr['receiver_type']);
      $scls    = type_class($tr['sender_type']);
      $rcls    = type_class($tr['receiver_type']);
      $status  = $tr['status'];
      $ticket  = $tr['ticket_id'] ?? '';

      // JSON-safe data for edit modal
      $row_json = htmlspecialchars(json_encode([
        'id'               => $tid,
        'transfer_date'    => date('Y-m-d\TH:i', strtotime($tr['transfer_date'])),
        'sender_email'     => $tr['sender_email'],
        'sender_user_id'   => $tr['sender_user_id'],
        'sender_type'      => $tr['sender_type'],
        'receiver_email'   => $tr['receiver_email'],
        'receiver_user_id' => $tr['receiver_user_id'],
        'receiver_type'    => $tr['receiver_type'],
        'amount'           => (float) $tr['amount'],
        'status'           => $status,
        'ticket_id'        => $ticket,
      ]), ENT_QUOTES, 'UTF-8');
    ?>
    <tr data-bt-id="<?= $tid ?>">
      <td><span class="bt-rownum"><?= $row_num++ ?></span></td>

      <td>
        <div class="bt-date-main"><?= $dt_main ?></div>
        <div class="bt-date-time"><?= $dt_time ?></div>
        <?=tracs_creator_meta($tr, $tr['created_at'] ?? null, false)?>
      </td>

      <td>
        <div class="bt-acct-email" title="<?= esc($tr['sender_email']) ?>"><?= esc($tr['sender_email']) ?></div>
        <div class="bt-acct-uid"><?= esc($tr['sender_user_id']) ?></div>
      </td>

      <td><span class="bt-type <?= $scls ?>"><?= $stype ?></span></td>

      <td>
        <div class="bt-dir-arrow">
          <i data-lucide="chevron-right" class="icon-sm"></i>
        </div>
      </td>

      <td>
        <div class="bt-acct-email" title="<?= esc($tr['receiver_email']) ?>"><?= esc($tr['receiver_email']) ?></div>
        <div class="bt-acct-uid"><?= esc($tr['receiver_user_id']) ?></div>
      </td>

      <td><span class="bt-type <?= $rcls ?>"><?= $rtype ?></span></td>

      <td style="text-align:right">
        <div class="bt-amount">
          <span class="bt-amount-cur">Rp</span><?= $amt ?>
        </div>
      </td>

      <td><span class="bt-status <?= $status ?>"><?= ucfirst($status) ?></span></td>

      <td>
        <?php if ($ticket): ?>
          <span class="bt-ticket"><?= esc($ticket) ?></span>
        <?php else: ?>
          <span class="bt-ticket-none">—</span>
        <?php endif; ?>
        <details class="row-action-menu">
          <summary class="btn btn-ghost btn-icon" title="Actions" aria-label="Row actions"><i data-lucide="more-vertical" class="icon-sm"></i></summary>
          <div class="row-action-popover">
            <button class="btn btn-ghost btn-sm" type="button" onclick="openEditBt(<?= $row_json ?>)">Edit</button>
            <button class="btn btn-danger btn-sm" type="button" onclick="deleteBt(<?= $tid ?>)">Delete</button>
          </div>
        </details>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div><!-- /bt-table-wrap -->

  <!-- ── Pagination ──────────────────────────────────────────── -->
  <?php if ($total_pages > 1): ?>
  <div class="bt-pagination">
    <span>
      <?= number_format(($offset + 1)) ?>–<?= number_format(min($offset + $per_page, $total_rows)) ?>
      of <?= number_format($total_rows) ?> records
    </span>
    <div class="bt-pages">

      <!-- Prev -->
      <a href="?s=<?= esc($filter_status) ?>&q=<?= urlencode($q) ?>&m=<?= urlencode($month) ?>&p=<?= max(1, $page - 1) ?>"
         class="bt-page-btn <?= $page <= 1 ? 'disabled' : '' ?>">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:10px;height:10px;stroke-width:3"><polyline points="15 18 9 12 15 6"/></svg>
      </a>

      <?php
      // Show up to 7 page buttons with ellipsis
      $start_pg = max(1, min($page - 3, $total_pages - 6));
      $end_pg   = min($total_pages, $start_pg + 6);
      for ($pg = $start_pg; $pg <= $end_pg; $pg++):
      ?>
      <a href="?s=<?= esc($filter_status) ?>&q=<?= urlencode($q) ?>&m=<?= urlencode($month) ?>&p=<?= $pg ?>"
         class="bt-page-btn <?= $pg === $page ? 'active' : '' ?>"><?= $pg ?></a>
      <?php endfor; ?>

      <!-- Next -->
      <a href="?s=<?= esc($filter_status) ?>&q=<?= urlencode($q) ?>&m=<?= urlencode($month) ?>&p=<?= min($total_pages, $page + 1) ?>"
         class="bt-page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:10px;height:10px;stroke-width:3"><polyline points="9 18 15 12 9 6"/></svg>
      </a>

    </div>
  </div>
  <?php endif; ?>

  <?php endif; ?><!-- /empty check -->
</div><!-- /panel -->

</div></main><!-- /main-inner /main -->

<!-- ══════════════════════════════════════════════
     EDIT TRANSFER MODAL (edit only — new entries use inline form)
══════════════════════════════════════════════ -->
<div class="modal-overlay hidden" id="btModal">
<div class="modal" style="max-width:580px">
  <div class="modal-head">
    <div>
      <div class="modal-title">Edit Transfer</div>
      <div class="modal-sub">Update transfer record</div>
    </div>
    <button class="modal-close" onclick="closeModal('bt')">
      <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
  </div>
  <div class="modal-body">
    <input type="hidden" id="btId">

    <div class="form-group">
      <label class="form-label">Transfer Date & Time</label>
      <div class="split-input-group">
        <input type="date" class="form-input split-date" id="btDateVal" data-sync="btDate" style="flex: 1.5">
        <input type="time" class="form-input split-time" id="btTimeVal" data-sync="btDate" style="flex: 1">
      </div>
      <input type="hidden" id="btDate" class="quick-datetime">
    </div>

    <!-- Sender -->
    <div style="border:1px solid var(--bd1);border-radius:var(--r2);padding:10px 12px;margin-bottom:10px">
      <div style="font-size:9.5px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--tx3);font-family:var(--mono);margin-bottom:8px">Sender Account</div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Email</label>
          <input type="email" class="form-input" id="btSenderEmail" placeholder="Sender email, e.g. client@domain.com" autocomplete="off">
        </div>
        <div class="form-group">
          <label class="form-label">User ID</label>
          <input type="text" class="form-input" id="btSenderUid" placeholder="Sender user ID, e.g. CID-10042" autocomplete="off">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Account Type *</label>
        <select class="form-select" id="btSenderType">
          <option value="client_area">Client Area</option>
          <option value="billing_console">Billing Console</option>
          <option value="billing_awan">Billing Awan</option>
        </select>
      </div>
    </div>

    <!-- Receiver -->
    <div style="border:1px solid var(--bd1);border-radius:var(--r2);padding:10px 12px;margin-bottom:10px">
      <div style="font-size:9.5px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--tx3);font-family:var(--mono);margin-bottom:8px">Receiver Account</div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Email</label>
          <input type="email" class="form-input" id="btReceiverEmail" placeholder="Receiver email, e.g. billing@domain.com" autocomplete="off">
        </div>
        <div class="form-group">
          <label class="form-label">User ID</label>
          <input type="text" class="form-input" id="btReceiverUid" placeholder="Receiver user ID, e.g. CID-10088" autocomplete="off">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Account Type *</label>
        <select class="form-select" id="btReceiverType">
          <option value="client_area">Client Area</option>
          <option value="billing_console">Billing Console</option>
          <option value="billing_awan">Billing Awan</option>
        </select>
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Amount (Rp) *</label>
        <input type="number" class="form-input" id="btAmount" placeholder="Transfer amount" min="0" step="0.01">
      </div>
      <div class="form-group">
        <label class="form-label">Status</label>
        <select class="form-select" id="btStatus">
          <option value="pending">Pending</option>
          <option value="done">Done</option>
        </select>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Ticket ID <span style="color:var(--tx4)">(optional)</span></label>
        <input type="text" class="form-input" id="btTicket" placeholder="Ticket ID, e.g. WHMCS-2026-001" autocomplete="off">
      </div>
    </div>
  </div>
  <div class="modal-foot">
    <button class="btn btn-ghost" onclick="closeModal('bt')">Cancel</button>
    <button class="btn btn-primary" onclick="saveEditBt()">
      <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><polyline points="20 6 9 17 4 12"/></svg>
      Update Transfer
    </button>
  </div>
</div>
</div>

<?php include 'includes/footer.php'; ?>
