<?php
/**
 * TRACS — Cancellation Feedback Dashboard
 */
require_once __DIR__ . '/../core/security/csrf.php';
tracs_start_session();
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/auth/auth_check.php';
require_once __DIR__.'/../modules/cancellation-feedback/controller.php';
require_once __DIR__.'/../modules/alert-ticker/controller.php';
require_once __DIR__.'/includes/page_helpers.php';

$uid = $_SESSION['user_id'] ?? 0;
$controller = new CancellationFeedbackController($conn, $uid);
$ticker = new AlertTickerController($conn, $uid);
$ticker_items = $ticker->formatAlertsForTicker();

// Monthly retention intelligence
$analytics = $controller->getAnalyticsData();
$month_start = date('Y-m-01');
$today = date('Y-m-d');
$monthly_feedbacks = $controller->getFeedbackList(['date_from' => $month_start, 'date_to' => $today], 500, 0);
$retention_intel = $controller->buildRetentionIntelligence($monthly_feedbacks, $analytics);

// Filter handling
$limit = 50;
$offset = 0;
$filters = [
    'q'          => $_GET['q'] ?? '',
    'service'    => $_GET['service'] ?? '',
    'reason'     => $_GET['reason'] ?? '',
    'resolution' => $_GET['resolution'] ?? '',
    'date_from'  => $_GET['df'] ?? '',
    'date_to'    => $_GET['dt'] ?? '',
];

$feedbacks = $controller->getFeedbackList($filters, $limit, $offset);
$total_count = $controller->getTotalCount($filters);

// Dropdown options
$services = [
    'Domain', 'Cloud Hosting cPanel', 'Wordpress Hosting', 'Reseller Hosting cPanel',
    'Website Instant', 'Cloud VPS', 'VPS Pro', 'VPS Rocket', 'VPS AMD Extreme',
    'SSL Comodo', 'Managed VPS WHM', 'Cyberpanel VPS', 'Email & Collaboration (Zimbra)',
    'Dedicated Server', 'Baremetal Server', 'Colocation Server', 'Object Storage',
    'Cloud Storage Drive', 'License', 'Kubernetes', 'Reseller Hosting Plesk', 'Cloud Hosting Plesk'
];

$reasons = [
    'Service No Longer Required', 'Document activation requirements', 'Missing required features',
    'Frequent downtime', 'Slow server performance', 'Network latency / packet loss',
    'Resource limits', 'DDoS / security-related instability', 'Slow Response Time',
    'Issue not resolved', 'Repeated Issue', 'Price Increase', 'Cheaper Competitor Found',
    'Billing/Payment method issue', 'Service Expansion (Upgrade / New Order)', 'Unknown/No Feedback'
];

$resolutions = [
    'End of Billing Periode', 'Refund to Credit Balance', 'Refund to Bank Account / Paypal / CC'
];

$page_title = 'Cancellation Feedback';
$active_page = 'feedback';
include 'includes/header.php';
?>

<main class="main">
<div class="main-inner">

  <!-- ── Topbar ─────────────────────────────────────────────────── -->
  <div class="topbar">
    <div class="topbar-left">
      <div class="page-title">Cancellation Feedback</div>
      <div class="page-sub">Monthly Cancellation Intelligence · Retention Insights</div>
    </div>
  </div>

  <!-- ── Operational Intelligence ──────────────────────────────── -->
  <div class="shift-intel-panel feedback-summary-panel">
    <div class="shift-intel-head">
      <div>
        <div class="shift-intel-kicker">
          <i data-lucide="brain-circuit" class="icon-sm"></i>
          AI Retention Summary
        </div>
        <div class="shift-intel-title">Rule-based summary from monthly cancellation feedback</div>
      </div>
      <?php if(($retention_intel['critical_count'] ?? 0) > 0): ?>
      <span class="shift-intel-badge">
        <i data-lucide="alert-triangle" class="icon-xs"></i>
        <?=$retention_intel['critical_count']?> risk item<?=($retention_intel['critical_count'] ?? 0) === 1 ? '' : 's'?>
      </span>
      <?php endif; ?>
    </div>
    <div class="shift-intel-summary"><?=tracs_highlight_lines($retention_intel['summary'], $retention_intel['summary_highlight'] ?? '')?></div>
    <div class="shift-intel-lists">
      <div class="shift-intel-list">
        <div class="shift-intel-list-title">
          <i data-lucide="sparkles" class="icon-xs"></i>
          Highlighted Insights
        </div>
        <ul>
          <?php foreach($retention_intel['key_insights'] as $insight): ?>
          <li><?=esc($insight)?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div class="shift-intel-list">
        <div class="shift-intel-list-title">
          <i data-lucide="list-checks" class="icon-xs"></i>
          Recommended Follow-up
        </div>
        <ul>
          <?php foreach($retention_intel['followups'] as $followup): ?>
          <li><?=esc($followup)?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>

  <!-- ── Filter & Search ────────────────────────────────────────── -->
  <div class="shift-toolbar-panel feedback-toolbar-panel">
    <div class="filter-search-row">
      <form method="get" class="filter-group-wrap">
        <div class="month-select-wrap">
          <label>Service</label>
          <select name="service" class="form-select compact-select" onchange="this.form.submit()">
            <option value="">All Services</option>
            <?php foreach($services as $s): ?>
            <option value="<?=esc($s)?>" <?= $filters['service']===$s ? 'selected':'' ?>><?=esc($s)?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="month-select-wrap">
          <label>Reason</label>
          <select name="reason" class="form-select compact-select" onchange="this.form.submit()">
            <option value="">All Reasons</option>
            <?php foreach($reasons as $r): ?>
            <option value="<?=esc($r)?>" <?= $filters['reason']===$r ? 'selected':'' ?>><?=esc($r)?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="month-select-wrap">
          <label>Resolution</label>
          <select name="resolution" class="form-select compact-select" onchange="this.form.submit()">
            <option value="">All Resolutions</option>
            <?php foreach($resolutions as $res): ?>
            <option value="<?=esc($res)?>" <?= $filters['resolution']===$res ? 'selected':'' ?>><?=esc($res)?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="month-select-wrap">
          <label>From</label>
          <input type="date" name="df" class="form-input compact-select" value="<?=esc($filters['date_from'])?>" onchange="this.form.submit()" style="width:125px">
        </div>

        <div class="month-select-wrap">
          <label>To</label>
          <input type="date" name="dt" class="form-input compact-select" value="<?=esc($filters['date_to'])?>" onchange="this.form.submit()" style="width:125px">
        </div>

        <?php if(array_filter($filters)): ?>
        <a href="cancellation_feedback.php" class="btn btn-ghost btn-reset">
          <i data-lucide="rotate-ccw" class="icon-xs" style="margin-right:4px"></i> Reset
        </a>
        <?php endif; ?>
      </form>

      <form method="get" class="search-form-wrap feedback-search-wrap">
        <input type="hidden" name="service" value="<?=esc($filters['service'])?>">
        <input type="hidden" name="reason" value="<?=esc($filters['reason'])?>">
        <input type="hidden" name="resolution" value="<?=esc($filters['resolution'])?>">
        <input type="hidden" name="df" value="<?=esc($filters['date_from'])?>">
        <input type="hidden" name="dt" value="<?=esc($filters['date_to'])?>">
        <i data-lucide="search" class="search-ic icon-sm"></i>
        <input type="text" name="q" class="search-input" placeholder="Search email, domain, reference..." value="<?=esc($filters['q'])?>" onchange="this.form.submit()">
      </form>
    </div>
  </div>

  <!-- ── Main Table ─────────────────────────────────────────────── -->
  <div class="panel">
    <div class="panel-head">
      <span class="panel-title">Feedback Records</span>
      <span class="panel-meta"><?= $total_count ?> records found</span>
    </div>

    <!-- ── Inline Quick-Entry Form ────────────────────────────── -->
    <div class="fb-inline-form">
      <div class="fb-inline-label">
        <i data-lucide="plus" style="width:11px;height:11px"></i>
        Add Cancellation Feedback
      </div>
      <div class="fb-inline-grid">
        <div class="dt-inline-group">
          <label class="fb-inline-lbl">Submitter <span class="req-star">*</span></label>
          <input type="text" class="form-input fb-inline-input" id="inSubmitter" placeholder="Name" autocomplete="off">
        </div>
        <div class="dt-inline-group">
          <label class="fb-inline-lbl">Service <span class="req-star">*</span></label>
          <select class="form-select fb-inline-input" id="inService">
            <option value="">— Select —</option>
            <?php foreach($services as $s): ?><option value="<?=esc($s)?>"><?=esc($s)?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="dt-inline-group">
          <label class="fb-inline-lbl">Reason <span class="req-star">*</span></label>
          <select class="form-select fb-inline-input" id="inReason">
            <option value="">— Select —</option>
            <?php foreach($reasons as $r): ?><option value="<?=esc($r)?>"><?=esc($r)?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="dt-inline-group">
          <label class="fb-inline-lbl">Reference</label>
          <input type="text" class="form-input fb-inline-input" id="inRef" placeholder="e.g. example.com" autocomplete="off">
        </div>
        <div class="dt-inline-group">
          <label class="fb-inline-lbl">Email</label>
          <input type="text" class="form-input fb-inline-input" id="inEmail" placeholder="customer@email.com" autocomplete="off">
        </div>
        <div class="dt-inline-group">
          <label class="fb-inline-lbl">Resolution</label>
          <select class="form-select fb-inline-input" id="inResolution">
            <option value="">— Select —</option>
            <?php foreach($resolutions as $res): ?><option value="<?=esc($res)?>"><?=esc($res)?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="fb-inline-grid-row-2">
        <div class="dt-inline-group">
          <label class="fb-inline-lbl">Additional Details / Context</label>
          <input type="text" class="form-input fb-inline-input" id="inDetails" placeholder="Customer comments, context, retention efforts..." autocomplete="off">
        </div>
        <div class="fb-inline-action">
          <button class="btn btn-primary" onclick="quickSaveFeedback()" style="height:30px; width:100%; font-size:11px; padding:0 20px">
            <i data-lucide="check" class="icon-xs"></i> Save Feedback
          </button>
        </div>
      </div>
    </div>

    <div class="table-wrap sticky-head">
      <table class="tracs-table">
        <thead>
          <tr>
            <th>Submitter</th>
            <th>Service</th>
            <th>Reason</th>
            <th>Details</th>
            <th>Reference</th>
            <th>Email</th>
            <th>Resolution</th>
            <th>Created At</th>
            <th style="width:120px">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if(empty($feedbacks)): ?>
          <tr>
            <td colspan="9">
              <div class="empty">
                <div class="empty-ic"><i data-lucide="clipboard-x"></i></div>
                <div class="empty-t">No feedback found</div>
                <div class="empty-s">Try adjusting your filters or search terms</div>
              </div>
            </td>
          </tr>
          <?php else: foreach($feedbacks as $f): 
            $initials = strtoupper(substr($f['submitter_name'], 0, 1) . substr(explode(' ', $f['submitter_name'])[1] ?? '', 0, 1));
            $is_critical = in_array($f['cancellation_reason'], ['Frequent downtime', 'DDoS / security-related instability', 'Slow server performance', 'Repeated Issue']);
          ?>
          <tr data-feedback-id="<?= $f['id'] ?>" class="<?= $is_critical ? 'row-critical' : '' ?>">
            <td>
              <div class="user-cell">
                <div class="avatar"><?= $initials ?></div>
                <div class="user-info">
                  <div class="user-name"><?= esc($f['submitter_name']) ?></div>
                </div>
              </div>
            </td>
            <td><span class="badge badge-service"><?= esc($f['cancelled_service']) ?></span></td>
            <td>
              <span class="reason-text <?= $is_critical ? 'text-critical' : '' ?>">
                <?= esc($f['cancellation_reason']) ?>
              </span>
            </td>
            <td class="details-cell" title="<?= esc($f['additional_details']) ?>">
              <div class="truncate-details"><?= esc($f['additional_details']) ?></div>
            </td>
            <td class="mono">
              <div class="ref-wrap">
                <span class="ref-text"><?= esc($f['whmcs_reference']) ?></span>
                <button class="btn-copy" onclick="copyToClipboard('<?= esc($f['whmcs_reference']) ?>')"><i data-lucide="copy"></i></button>
              </div>
            </td>
            <td><a href="mailto:<?= esc($f['email_address']) ?>" class="email-link"><?= esc($f['email_address']) ?></a></td>
            <td><span class="resolution-text"><?= esc($f['payment_resolution']) ?></span></td>
            <td class="mono text-muted"><?= date('d M Y, H:i', strtotime($f['created_at'])) ?></td>
            <td class="tracs-acts">
              <button class="btn btn-ghost btn-icon" onclick="viewFeedback(<?= $f['id'] ?>)" title="View"><i data-lucide="eye"></i></button>
              <button class="btn btn-ghost btn-icon" onclick="openEditFeedback(<?= htmlspecialchars(json_encode($f)) ?>)" title="Edit"><i data-lucide="edit-2"></i></button>
              <button class="btn btn-danger btn-icon" onclick="deleteFeedback(<?= $f['id'] ?>)" title="Delete"><i data-lucide="trash-2"></i></button>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
</main>

<?php include 'includes/footer.php'; ?>
