<?php
/**
 * TRACS — Cancellation Feedback Dashboard
 */
require_once __DIR__ . '/../core/security/csrf.php';
tracs_start_session();
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/auth/auth_check.php';
require_once __DIR__.'/../core/access_control.php';
tracs_require_page_permission($conn, 'cancellation_feedback.view');
require_once __DIR__.'/../modules/cancellation-feedback/controller.php';
require_once __DIR__.'/../modules/alert-ticker/controller.php';
require_once __DIR__.'/includes/page_helpers.php';

$uid = $_SESSION['user_id'] ?? 0;
tracs_ensure_creator_columns($conn, 'tracs_cancellation_feedback', null);
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
$services = cf_allowed_services();
$reasons = cf_allowed_reasons();
$resolutions = cf_allowed_resolutions();
$critical_reasons = ['Frequent downtime', 'DDoS / security-related instability', 'Slow server performance', 'Repeated Issue', 'Issue not resolved'];
$feedback_detail_records = [];

function cf_render_value_chips(array $values, string $class = ''): string {
    if (empty($values)) {
        return '<span class="text-muted">—</span>';
    }
    $html = '<div class="cf-chip-row">';
    foreach ($values as $value) {
        $html .= '<span class="cf-chip ' . esc($class) . '">' . esc($value) . '</span>';
    }
    return $html . '</div>';
}

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
  <div class="feedback-toolbar-row">
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

        <input type="hidden" name="q" value="<?=esc($filters['q'])?>">

        <?php if(array_filter($filters)): ?>
        <a href="cancellation_feedback.php" class="btn btn-ghost btn-reset">
          <i data-lucide="rotate-ccw" class="icon-xs" style="margin-right:4px"></i> Reset
        </a>
        <?php endif; ?>

        <button type="submit" class="btn btn-primary feedback-filter-btn">
          <i data-lucide="filter" class="icon-xs"></i> Filter
        </button>
        </form>
      </div>
    </div>

    <form method="get" class="shift-toolbar-panel search-form-wrap feedback-search-wrap feedback-search-panel">
      <input type="hidden" name="service" value="<?=esc($filters['service'])?>">
      <input type="hidden" name="reason" value="<?=esc($filters['reason'])?>">
      <input type="hidden" name="resolution" value="<?=esc($filters['resolution'])?>">
      <input type="hidden" name="df" value="<?=esc($filters['date_from'])?>">
      <input type="hidden" name="dt" value="<?=esc($filters['date_to'])?>">
      <i data-lucide="search" class="search-ic icon-sm"></i>
      <input type="text" name="q" class="search-input" placeholder="Search customer email, domain, service reference, or notes" value="<?=esc($filters['q'])?>" onchange="this.form.submit()">
    </form>
  </div>

  <!-- ── Main Table ─────────────────────────────────────────────── -->
  <div class="panel">
    <div class="panel-head">
      <span class="panel-title">Feedback Records</span>
      <div class="panel-right">
        <span class="panel-meta"><?= $total_count ?> records found</span>
        <details class="report-export-menu">
          <summary class="btn btn-ghost btn-icon report-export-trigger" title="More actions" aria-label="More actions" data-tooltip="More actions"><i data-lucide="more-vertical" class="icon-sm"></i></summary>
          <form method="get" action="/api/export-feedback.php" class="report-export-popover">
            <input type="hidden" name="service" value="<?=esc($filters['service'])?>">
            <input type="hidden" name="reason" value="<?=esc($filters['reason'])?>">
            <input type="hidden" name="resolution" value="<?=esc($filters['resolution'])?>">
            <input type="hidden" name="q" value="<?=esc($filters['q'])?>">
            <div class="report-export-title">
              <i data-lucide="download" class="icon-xs"></i>
              Export CSV
            </div>
            <label>From Date<input type="date" name="from" class="form-input" value="<?=esc($filters['date_from'])?>"></label>
            <label>To Date<input type="date" name="to" class="form-input" value="<?=esc($filters['date_to'])?>"></label>
            <button type="submit" class="btn btn-primary"><i data-lucide="download" class="icon-sm"></i>Download CSV</button>
          </form>
        </details>
      </div>
    </div>

    <!-- ── Inline Quick-Entry Form ────────────────────────────── -->
    <div class="fb-inline-form">
      <div class="fb-inline-label">
        <i data-lucide="plus" style="width:11px;height:11px"></i>
        Add Cancellation Feedback
      </div>
      <div class="fb-inline-grid">
        <div class="dt-inline-group fb-field-reference">
          <label class="fb-inline-lbl">Reference</label>
          <input type="text" class="form-input fb-inline-input" id="inRef" placeholder="Domain, invoice, or service reference, e.g. exampledomain.com" autocomplete="off">
        </div>
        <div class="dt-inline-group fb-field-email">
          <label class="fb-inline-lbl">Customer Email</label>
          <input type="text" class="form-input fb-inline-input" id="inEmail" placeholder="Customer email, e.g. client@domain.com" autocomplete="off">
        </div>
        <div class="dt-inline-group fb-field-service">
          <label class="fb-inline-lbl">Service <span class="req-star">*</span></label>
          <div class="cf-choice-box" id="inService" data-multi-choice>
            <?php foreach($services as $s): ?>
            <label class="cf-choice-option">
              <input type="checkbox" value="<?=esc($s)?>">
              <span class="cf-choice-mark"></span>
              <span class="cf-choice-text"><?=esc($s)?></span>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="dt-inline-group fb-field-reason">
          <label class="fb-inline-lbl">Reason <span class="req-star">*</span></label>
          <div class="cf-choice-box" id="inReason" data-multi-choice>
            <?php foreach($reasons as $r): ?>
            <label class="cf-choice-option">
              <input type="checkbox" value="<?=esc($r)?>">
              <span class="cf-choice-mark"></span>
              <span class="cf-choice-text"><?=esc($r)?></span>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="dt-inline-group fb-field-resolution">
          <label class="fb-inline-lbl">Resolution</label>
          <select class="form-select fb-inline-input" id="inResolution">
            <option value="">— Select —</option>
            <?php foreach($resolutions as $res): ?><option value="<?=esc($res)?>"><?=esc($res)?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="dt-inline-group fb-field-details">
          <label class="fb-inline-lbl">Additional Details / Context</label>
          <textarea class="form-textarea fb-inline-input fb-details-input" id="inDetails" placeholder="Add cancellation context, retention effort, or follow-up action"></textarea>
        </div>
        <div class="fb-inline-action">
          <button class="btn btn-primary fb-save-btn" onclick="quickSaveFeedback()">
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
            <th class="feedback-actions-head">Actions</th>
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
            $services_selected = cf_decode_multi_value($f['cancelled_service'] ?? '');
            $reasons_selected = cf_decode_multi_value($f['cancellation_reason'] ?? '');
            $service_display = implode(', ', $services_selected);
            $reason_display = implode(', ', $reasons_selected);
            $submitter_display = $f['submitter_display'] ?? $f['creator_name'] ?? $f['submitter_name'] ?? 'System';
            $initials = strtoupper(substr($submitter_display, 0, 1) . substr(explode(' ', $submitter_display)[1] ?? '', 0, 1));
            $is_critical = (bool)array_intersect($reasons_selected, $critical_reasons);
            $feedback_detail_records[(int)$f['id']] = [
              'id' => (int)$f['id'],
              'submitter_name' => $submitter_display,
              'cancelled_service' => $f['cancelled_service'] ?? '',
              'cancelled_services' => $services_selected,
              'cancelled_service_display' => $service_display,
              'cancellation_reason' => $f['cancellation_reason'] ?? '',
              'cancellation_reasons' => $reasons_selected,
              'cancellation_reason_display' => $reason_display,
              'additional_details' => $f['additional_details'] ?? '',
              'whmcs_reference' => $f['whmcs_reference'] ?? '',
              'email_address' => $f['email_address'] ?? '',
              'payment_resolution' => $f['payment_resolution'] ?? '',
              'created_at' => $f['created_at'] ?? '',
              'updated_at' => $f['updated_at'] ?? '',
              'creator_name' => $f['creator_name'] ?? '',
              'created_by_name' => $f['created_by_name'] ?? '',
            ];
          ?>
          <tr data-feedback-id="<?= $f['id'] ?>" class="<?= $is_critical ? 'row-critical' : '' ?>">
            <td>
              <div class="user-cell">
                <div class="avatar"><?= $initials ?></div>
                <div class="user-info">
                  <div class="user-name"><?= esc($submitter_display) ?></div>
                  <?=tracs_creator_meta($f)?>
                </div>
              </div>
            </td>
            <td><?= cf_render_value_chips($services_selected, 'cf-chip-service') ?></td>
            <td>
              <?= cf_render_value_chips($reasons_selected, $is_critical ? 'cf-chip-critical' : '') ?>
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
            <td class="feedback-actions-cell">
              <div class="row-action-group cf-row-actions">
                <button class="btn btn-ghost btn-icon" type="button" onclick="viewFeedback(<?= $f['id'] ?>)" title="View report" aria-label="View cancellation feedback report">
                  <i data-lucide="eye" class="icon-sm"></i>
                </button>
                <button class="btn btn-ghost btn-icon cf-delete-action" type="button" onclick="deleteFeedback(<?= $f['id'] ?>)" title="Delete feedback" aria-label="Delete cancellation feedback">
                  <i data-lucide="trash-2" class="icon-sm"></i>
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
</main>

<div class="modal-overlay hidden" id="feedbackViewModal">
<div class="modal modal-lg cf-detail-modal">
  <div class="modal-head">
    <div>
      <div class="modal-title">Cancellation Feedback Report</div>
      <div class="modal-sub" id="feedbackViewSub">Detailed report view</div>
    </div>
    <div class="modal-head-actions">
      <button class="modal-icon-action" type="button" id="feedbackViewEdit" title="Edit feedback" aria-label="Edit cancellation feedback">
        <i data-lucide="edit-2"></i>
      </button>
      <button class="modal-close" type="button" onclick="closeModal('feedbackView')" aria-label="Close report"><i data-lucide="x"></i></button>
    </div>
  </div>
  <div class="modal-body">
    <div class="cf-detail-grid">
      <section class="cf-detail-section">
        <div class="cf-detail-label">Submitter</div>
        <div class="cf-detail-value" id="fvSubmitter">—</div>
      </section>
      <section class="cf-detail-section">
        <div class="cf-detail-label">Email Address</div>
        <div class="cf-detail-value" id="fvEmail">—</div>
      </section>
      <section class="cf-detail-section cf-detail-wide">
        <div class="cf-detail-label">Cancelled Service</div>
        <div class="cf-detail-value" id="fvServices">—</div>
      </section>
      <section class="cf-detail-section cf-detail-wide">
        <div class="cf-detail-label">Reason for Cancellation</div>
        <div class="cf-detail-value" id="fvReasons">—</div>
      </section>
      <section class="cf-detail-section cf-detail-wide">
        <div class="cf-detail-label">WHMCS Service URL / Invoice / Domain / Hostname</div>
        <div class="cf-detail-value cf-detail-mono" id="fvReference">—</div>
      </section>
      <section class="cf-detail-section">
        <div class="cf-detail-label">Payment Resolution</div>
        <div class="cf-detail-value" id="fvResolution">—</div>
      </section>
      <section class="cf-detail-section">
        <div class="cf-detail-label">Submitted Date</div>
        <div class="cf-detail-value cf-detail-mono" id="fvCreated">—</div>
      </section>
      <section class="cf-detail-section">
        <div class="cf-detail-label">Updated Date</div>
        <div class="cf-detail-value cf-detail-mono" id="fvUpdated">—</div>
      </section>
      <section class="cf-detail-section">
        <div class="cf-detail-label">Created By</div>
        <div class="cf-detail-value" id="fvCreator">—</div>
      </section>
      <section class="cf-detail-section cf-detail-wide">
        <div class="cf-detail-label">Additional Cancellation Details / Notes</div>
        <div class="cf-detail-value cf-detail-notes" id="fvDetails">—</div>
      </section>
    </div>
  </div>
  <div class="modal-foot">
    <button class="btn btn-ghost" type="button" onclick="closeModal('feedbackView')">Close</button>
  </div>
</div>
</div>

<script>
window.feedbackRecords = <?= json_encode($feedback_detail_records, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
</script>

<?php include 'includes/footer.php'; ?>
