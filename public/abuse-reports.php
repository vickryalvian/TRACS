<?php
require_once __DIR__ . '/../core/security/csrf.php';
tracs_start_session();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth/auth_check.php';
require_once __DIR__ . '/../core/access_control.php';
require_once __DIR__ . '/../modules/abuse-report/controller.php';
require_once __DIR__ . '/../modules/alert-ticker/controller.php';
require_once __DIR__ . '/../core/notifications.php';
require_once __DIR__ . '/includes/page_helpers.php';

tracs_require_page_permission($conn, 'abuse_reports.view');

$uid = (int)($_SESSION['user_id'] ?? 0);
$AR = new AbuseReportController($conn, $uid);
$TC = new AlertTickerController($conn, $uid);

if (function_exists('tracs_notifications_schedule_abuse_sla')) {
    tracs_notifications_schedule_abuse_sla($conn);
}

$reports = $AR->getReports();
$summary = $AR->dashboardSummary();
$users = $AR->selectableUsers();
$relationships = $AR->relationshipOptions();
$ticker_items = $TC->formatAlertsForTicker();
$critical_count = (int)($summary['critical'] ?? 0);
$can_manage = tracs_user_can($conn, 'abuse_reports.manage', $uid);
$selected_id = tracs_is_positive_int($_GET['id'] ?? null) ? (int)$_GET['id'] : 0;

$status_labels = [
  'incoming' => 'Incoming',
  'investigating' => 'Investigating',
  'waiting_external' => 'Waiting External',
  'action_taken' => 'Action Taken',
  'resolved' => 'Resolved',
  'closed' => 'Closed',
];
$priority_labels = [
  'critical' => 'Critical',
  'high' => 'High',
  'medium' => 'Medium',
  'low' => 'Low',
];
$type_labels = [
  'phishing' => 'Phishing',
  'malware' => 'Malware',
  'spam' => 'Spam',
  'copyright' => 'Copyright',
  'illegal_content' => 'Illegal Content',
  'abuse_complaint' => 'Abuse Complaint',
  'other' => 'Other',
];
$reporters = array_values(array_unique(array_filter(array_map(fn($r) => trim((string)($r['reporter'] ?? '')), $reports))));
sort($reporters);

$page_title = 'Abuse Reports';
$active_page = 'abuse-reports';
include 'includes/header.php';
?>
<main class="main abuse-main">
<div class="main-inner abuse-page" id="abuseWorkspace" data-selected-id="<?=$selected_id?>" data-can-manage="<?=$can_manage ? '1' : '0'?>">

  <div class="topbar abuse-page-head">
    <div>
      <div class="page-title">Abuse Reports</div>
      <div class="page-sub" id="abusePageSummary"><?=count($reports)?> total · <?=esc((string)$summary['open'])?> open · <?=esc((string)$summary['critical'])?> critical · <?=esc((string)$summary['over_sla'])?> over SLA</div>
    </div>
    <div class="abuse-health"><span class="<?=((int)$summary['over_sla'] > 0 || (int)$summary['critical'] > 0) ? 'is-alert' : ''?>"></span><strong id="abuseQueueHealth"><?=esc((string)$summary['over_sla'])?> over SLA · <?=esc((string)$summary['resolved_today'])?> resolved today</strong></div>
  </div>

  <section class="abuse-toolbar panel">
    <form class="search-form-wrap abuse-search" id="abuseSearchForm">
      <i data-lucide="search" class="search-ic icon-sm"></i>
      <input type="search" class="search-input" id="abuseSearchInput" placeholder="Search ID, domain, IP, reporter, customer, title, or tags" autocomplete="off" aria-label="Search abuse reports">
    </form>
    <select class="form-select compact-select" id="abuseStatusFilter" aria-label="Status filter">
      <option value="">All Status</option>
      <?php foreach($status_labels as $key => $label): ?><option value="<?=esc($key)?>"><?=esc($label)?></option><?php endforeach; ?>
    </select>
    <select class="form-select compact-select" id="abusePriorityFilter" aria-label="Priority filter">
      <option value="">All Priority</option>
      <?php foreach($priority_labels as $key => $label): ?><option value="<?=esc($key)?>"><?=esc($label)?></option><?php endforeach; ?>
    </select>
    <select class="form-select compact-select" id="abuseReporterFilter" aria-label="Reporter filter">
      <option value="">All Reporters</option>
      <?php foreach($reporters as $reporter): ?><option value="<?=esc($reporter)?>"><?=esc($reporter)?></option><?php endforeach; ?>
    </select>
    <select class="form-select compact-select" id="abuseAssignedFilter" aria-label="Assigned staff filter">
      <option value="">All Staff</option>
      <?php foreach($users as $user): ?><option value="<?=esc((string)$user['id'])?>"><?=esc($user['label'] ?? '')?></option><?php endforeach; ?>
    </select>
    <?=tracs_date_range_picker([
      'id' => 'abuseDateRange',
      'start_id' => 'abuseDateStart',
      'end_id' => 'abuseDateEnd',
      'label' => 'Date range',
      'placeholder' => 'Date Range',
      'class' => 'abuse-date-range',
    ])?>
    <label class="abuse-check"><input type="checkbox" id="abuseHasAttachmentFilter"> Evidence</label>
    <label class="abuse-check"><input type="checkbox" id="abuseOverSlaFilter"> Over SLA</label>
    <div class="abuse-toolbar-stats">
      <span><b id="abuseOpenStat"><?=esc((string)$summary['open'])?></b> Open</span>
      <span><b id="abuseCriticalStat"><?=esc((string)$summary['critical'])?></b> Critical</span>
      <span><b id="abuseSlaStat"><?=esc((string)$summary['over_sla'])?></b> SLA</span>
    </div>
    <?php if($can_manage): ?>
    <button class="btn btn-primary abuse-new-btn" type="button" id="abuseNewBtn"><i data-lucide="plus-circle" class="icon-sm"></i>New Abuse Report</button>
    <?php endif; ?>
  </section>

  <div class="abuse-workspace-grid">
    <section class="abuse-board-wrap" aria-label="Abuse report workflow board">
      <div class="abuse-board" id="abuseBoard">
        <?php foreach($status_labels as $status => $label): ?>
        <section class="abuse-column" data-abuse-column="<?=esc($status)?>">
          <header class="abuse-column-head">
            <div>
              <strong><?=esc($label)?></strong>
              <small data-column-summary>0 reports</small>
            </div>
            <span data-column-count>0</span>
          </header>
          <div class="abuse-column-list" data-abuse-dropzone="<?=esc($status)?>"></div>
        </section>
        <?php endforeach; ?>
      </div>
    </section>

    <aside class="abuse-detail-panel" id="abuseDetailPanel" aria-live="polite">
      <div class="abuse-detail-empty" id="abuseDetailEmpty">
        <div class="empty-ic"><i data-lucide="shield-alert"></i></div>
        <div class="empty-t">Select an abuse report</div>
        <div class="empty-s">Details, timeline, evidence, notes, and actions stay here while you move through the board.</div>
      </div>

      <div class="abuse-detail-content" id="abuseDetailContent" hidden>
        <header class="abuse-detail-head">
          <div>
            <span class="abuse-detail-ref" id="abuseDetailRef">TRACS-AR</span>
            <h2 id="abuseDetailTitle">Abuse report</h2>
          </div>
          <button class="btn btn-ghost btn-icon" type="button" id="abuseClosePanel" aria-label="Close detail panel"><i data-lucide="x" class="icon-sm"></i></button>
        </header>

        <div class="abuse-detail-tabs" role="tablist" aria-label="Abuse report detail tabs">
          <button type="button" class="is-active" data-abuse-tab="overview">Overview</button>
          <button type="button" data-abuse-tab="timeline">Timeline</button>
          <button type="button" data-abuse-tab="evidence">Evidence</button>
          <button type="button" data-abuse-tab="notes">Notes</button>
          <button type="button" data-abuse-tab="relationships">Relationships</button>
          <button type="button" data-abuse-tab="actions">Actions</button>
        </div>

        <form class="abuse-detail-pane is-active" id="abuseOverviewPane" data-abuse-pane="overview">
          <input type="hidden" id="abuseReportId">
          <div class="abuse-form-grid">
            <div class="form-group is-wide"><label class="form-label">Title *</label><input class="form-input" id="abuseTitle" maxlength="220" <?=$can_manage ? '' : 'disabled'?>></div>
            <div class="form-group"><label class="form-label">Type</label><select class="form-select" id="abuseType" <?=$can_manage ? '' : 'disabled'?>><?php foreach($type_labels as $key => $label): ?><option value="<?=esc($key)?>"><?=esc($label)?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label class="form-label">Status</label><select class="form-select" id="abuseStatus" <?=$can_manage ? '' : 'disabled'?>><?php foreach($status_labels as $key => $label): ?><option value="<?=esc($key)?>"><?=esc($label)?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label class="form-label">Priority</label><select class="form-select" id="abusePriority" <?=$can_manage ? '' : 'disabled'?>><?php foreach($priority_labels as $key => $label): ?><option value="<?=esc($key)?>"><?=esc($label)?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label class="form-label">Affected Domain</label><input class="form-input" id="abuseDomain" maxlength="255" <?=$can_manage ? '' : 'disabled'?>></div>
            <div class="form-group"><label class="form-label">Affected IP</label><input class="form-input" id="abuseIp" maxlength="64" <?=$can_manage ? '' : 'disabled'?>></div>
            <div class="form-group"><label class="form-label">Reporter</label><input class="form-input" id="abuseReporter" maxlength="160" <?=$can_manage ? '' : 'disabled'?>></div>
            <div class="form-group"><label class="form-label">Reporter Contact</label><input class="form-input" id="abuseReporterContact" maxlength="190" <?=$can_manage ? '' : 'disabled'?>></div>
            <div class="form-group"><label class="form-label">Assigned Staff</label><select class="form-select" id="abuseAssignedUser" <?=$can_manage ? '' : 'disabled'?>> <option value="">Unassigned</option><?php foreach($users as $user): ?><option value="<?=esc((string)$user['id'])?>"><?=esc($user['label'] ?? '')?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label class="form-label">SLA Due</label><input type="datetime-local" class="form-input" id="abuseSlaDue" <?=$can_manage ? '' : 'disabled'?>></div>
            <div class="form-group"><label class="form-label">Customer</label><input class="form-input" id="abuseCustomer" maxlength="190" <?=$can_manage ? '' : 'disabled'?>></div>
            <div class="form-group"><label class="form-label">Customer Ref</label><input class="form-input" id="abuseCustomerRef" maxlength="190" <?=$can_manage ? '' : 'disabled'?>></div>
            <div class="form-group is-wide"><label class="form-label">Tags</label><input class="form-input" id="abuseTags" maxlength="500" placeholder="phishing, domain, urgent" <?=$can_manage ? '' : 'disabled'?>></div>
            <div class="form-group is-wide"><label class="form-label">Description</label><textarea class="form-textarea" id="abuseDescription" <?=$can_manage ? '' : 'disabled'?>></textarea></div>
          </div>
          <?php if($can_manage): ?>
          <div class="abuse-detail-actions">
            <button type="button" class="btn btn-ghost" id="abuseResetBtn">Reset</button>
            <button type="submit" class="btn btn-primary" id="abuseSaveBtn"><i data-lucide="check" class="icon-sm"></i>Save Report</button>
          </div>
          <?php endif; ?>
        </form>

        <section class="abuse-detail-pane" data-abuse-pane="timeline" hidden>
          <div class="abuse-timeline" id="abuseTimeline"></div>
        </section>

        <section class="abuse-detail-pane" data-abuse-pane="evidence" hidden>
          <?php if($can_manage): ?>
          <form class="abuse-evidence-form" id="abuseEvidenceForm">
            <select class="form-select" id="abuseEvidenceType" aria-label="Evidence type">
              <option value="">Auto Type</option>
              <option value="screenshot">Screenshot</option>
              <option value="email">Email</option>
              <option value="header">Header</option>
              <option value="log">Log</option>
              <option value="document">Document</option>
              <option value="image">Image</option>
              <option value="other">Other</option>
            </select>
            <input class="form-input" type="file" id="abuseEvidenceInput" multiple>
            <button type="submit" class="btn btn-primary"><i data-lucide="upload" class="icon-sm"></i>Upload</button>
          </form>
          <?php endif; ?>
          <div class="abuse-evidence-grid" id="abuseEvidenceGrid"></div>
        </section>

        <section class="abuse-detail-pane" data-abuse-pane="notes" hidden>
          <?php if($can_manage): ?>
          <form class="abuse-note-form" id="abuseNoteForm">
            <textarea class="form-textarea" id="abuseNoteBody" placeholder="Add internal investigation note"></textarea>
            <button type="submit" class="btn btn-primary"><i data-lucide="notebook-pen" class="icon-sm"></i>Add Note</button>
          </form>
          <?php endif; ?>
          <div class="abuse-notes" id="abuseNotes"></div>
        </section>

        <section class="abuse-detail-pane" data-abuse-pane="relationships" hidden>
          <div class="abuse-form-grid">
            <div class="form-group"><label class="form-label">Related Domain</label><select class="form-select" id="abuseRelatedDomain" <?=$can_manage ? '' : 'disabled'?>> <option value="">None</option><?php foreach($relationships['domains'] ?? [] as $item): ?><option value="<?=esc((string)$item['id'])?>"><?=esc($item['label'] ?? '')?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label class="form-label">Related Server</label><select class="form-select" id="abuseRelatedServer" <?=$can_manage ? '' : 'disabled'?>> <option value="">None</option><?php foreach($relationships['servers'] ?? [] as $item): ?><option value="<?=esc((string)$item['id'])?>"><?=esc($item['label'] ?? '')?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label class="form-label">Related Case</label><select class="form-select" id="abuseRelatedCase" <?=$can_manage ? '' : 'disabled'?>> <option value="">None</option><?php foreach($relationships['cases'] ?? [] as $item): ?><option value="<?=esc((string)$item['id'])?>"><?=esc($item['label'] ?? '')?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label class="form-label">Shift Report ID</label><input class="form-input" id="abuseRelatedShift" type="number" min="1" <?=$can_manage ? '' : 'disabled'?>></div>
          </div>
          <?php if($can_manage): ?><button type="button" class="btn btn-primary" id="abuseSaveRelationships"><i data-lucide="save" class="icon-sm"></i>Save Relationships</button><?php endif; ?>
        </section>

        <section class="abuse-detail-pane" data-abuse-pane="actions" hidden>
          <div class="abuse-quick-actions" id="abuseQuickActions">
            <?php foreach($status_labels as $status => $label): ?>
            <button type="button" class="btn btn-ghost" data-abuse-move="<?=esc($status)?>"><?=esc($label)?></button>
            <?php endforeach; ?>
            <button type="button" class="btn btn-ghost" data-abuse-placeholder="notify_customer"><i data-lucide="mail" class="icon-sm"></i>Notify Customer</button>
            <button type="button" class="btn btn-ghost" data-abuse-placeholder="notify_reporter"><i data-lucide="send" class="icon-sm"></i>Notify Reporter</button>
            <button type="button" class="btn btn-ghost" data-abuse-placeholder="suspend"><i data-lucide="ban" class="icon-sm"></i>Suspend Service</button>
            <button type="button" class="btn btn-ghost" data-abuse-placeholder="unsuspend"><i data-lucide="rotate-ccw" class="icon-sm"></i>Unsuspend Service</button>
          </div>
          <div class="abuse-activity-log" id="abuseActivityLog"></div>
        </section>
      </div>
    </aside>
  </div>

  <script type="application/json" id="abuseDataset"><?=json_encode($reports, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)?></script>
  <script>window.TRACS_ABUSE_CAPS = <?=json_encode(['canManage' => $can_manage], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)?>;</script>
</div>
</main>
<?php include 'includes/footer.php'; ?>
