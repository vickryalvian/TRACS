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
$critical_count += (int)($summary['action_required'] ?? $summary['over_sla'] ?? 0);
$can_manage = tracs_user_can($conn, 'abuse_reports.manage', $uid);
$selected_id = tracs_is_positive_int($_GET['id'] ?? null) ? (int)$_GET['id'] : 0;

$status_labels = [
  'incoming' => 'Incoming',
  'investigating' => 'Investigating',
  'waiting_external' => 'Waiting External',
  'action_taken' => 'Action Required',
  'resolved' => 'Resolved',
  'closed' => 'Closed',
];
$board_columns = [
  'incoming' => ['label' => 'Incoming', 'status' => 'incoming'],
  'investigating' => ['label' => 'Investigating', 'status' => 'investigating'],
  'waiting_external' => ['label' => 'Waiting External', 'status' => 'waiting_external'],
  'action_required' => ['label' => 'Action Required', 'status' => 'action_taken'],
  'resolved' => ['label' => 'Resolved', 'status' => 'resolved'],
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
      <div class="abuse-title-line">
        <div class="page-title">Abuse Reports</div>
        <div class="page-sub" id="abusePageSummary" data-resolved-today="<?=esc((string)$summary['resolved_today'])?>"><?=count($reports)?> shown · <?=esc((string)$summary['open'])?> open · <?=esc((string)$summary['critical'])?> critical · <?=esc((string)($summary['action_required'] ?? $summary['over_sla']))?> action required · <?=esc((string)$summary['resolved_today'])?> resolved today</div>
      </div>
    </div>
    <div class="abuse-page-actions">
      <?php if($can_manage): ?>
      <button class="btn btn-primary abuse-new-btn" type="button" id="abuseNewBtn"><i data-lucide="plus-circle" class="icon-sm"></i>Add Abuse Report</button>
      <?php endif; ?>
    </div>
  </div>

  <section class="abuse-toolbar panel">
    <form class="search-form-wrap abuse-search" id="abuseSearchForm">
      <i data-lucide="search" class="search-ic icon-sm"></i>
      <input type="search" class="search-input" id="abuseSearchInput" placeholder="Search ID, domain, IP, reporter, customer, title, or tags" autocomplete="off" aria-label="Search abuse reports">
    </form>
    <div class="abuse-filter-control">
      <i data-lucide="columns-3" class="icon-sm"></i>
      <select class="form-select compact-select" id="abuseStatusFilter" aria-label="Status filter">
        <option value="">Status</option>
        <?php foreach($board_columns as $key => $column): ?><option value="<?=esc($key)?>"><?=esc($column['label'])?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="abuse-filter-control">
      <i data-lucide="flag" class="icon-sm"></i>
      <select class="form-select compact-select" id="abusePriorityFilter" aria-label="Priority filter">
        <option value="">Priority</option>
        <?php foreach($priority_labels as $key => $label): ?><option value="<?=esc($key)?>"><?=esc($label)?></option><?php endforeach; ?>
      </select>
    </div>
    <details class="abuse-more-filters" id="abuseMoreFilters">
      <summary id="abuseMoreFiltersSummary"><i data-lucide="sliders-horizontal" class="icon-sm"></i><span>More filters</span><b id="abuseMoreFilterCount" hidden>0</b><i data-lucide="chevron-down" class="icon-sm"></i></summary>
      <div class="abuse-more-filters-popover">
        <label class="form-group">
          <span class="form-label">Reporter</span>
          <select class="form-select" id="abuseReporterFilter" aria-label="Reporter filter">
            <option value="">All Reporters</option>
            <?php foreach($reporters as $reporter): ?><option value="<?=esc($reporter)?>"><?=esc($reporter)?></option><?php endforeach; ?>
          </select>
        </label>
        <label class="form-group">
          <span class="form-label">Staff</span>
          <select class="form-select" id="abuseAssignedFilter" aria-label="Assigned staff filter">
            <option value="">All Staff</option>
            <?php foreach($users as $user): ?><option value="<?=esc((string)$user['id'])?>"><?=esc($user['label'] ?? '')?></option><?php endforeach; ?>
          </select>
        </label>
      </div>
    </details>
    <?=tracs_date_range_picker([
      'id' => 'abuseDateRange',
      'start_id' => 'abuseDateStart',
      'end_id' => 'abuseDateEnd',
      'label' => 'Date range',
      'placeholder' => 'Date Range',
      'class' => 'abuse-date-range',
    ])?>
    <label class="abuse-toggle-chip"><input type="checkbox" id="abuseHasAttachmentFilter"><span><i data-lucide="paperclip" class="icon-sm"></i>Evidence</span></label>
    <label class="abuse-toggle-chip"><input type="checkbox" id="abuseActionRequiredFilter"><span><i data-lucide="clock-alert" class="icon-sm"></i>Action</span></label>
    <div class="abuse-view-toggle" role="group" aria-label="View mode">
      <button type="button" class="is-active" data-abuse-view="board" aria-pressed="true" title="Board view"><i data-lucide="kanban-square" class="icon-sm"></i><span>Board</span></button>
      <button type="button" data-abuse-view="list" aria-pressed="false" title="List view"><i data-lucide="list" class="icon-sm"></i><span>List</span></button>
    </div>
  </section>

  <section class="abuse-board-wrap" id="abuseBoardWrap" aria-label="Abuse report workflow board">
    <div class="abuse-board" id="abuseBoard">
      <?php foreach($board_columns as $stage => $column): ?>
        <section class="abuse-column <?=$stage === 'action_required' ? 'is-action-required' : ''?>" data-abuse-column="<?=esc($stage)?>" data-abuse-status="<?=esc($column['status'])?>">
          <header class="abuse-column-head">
            <div>
              <strong><?=esc($column['label'])?></strong>
              <small data-column-summary>0 reports</small>
            </div>
            <span class="panel-counter" data-column-count>0</span>
          </header>
          <div class="abuse-column-list" data-abuse-dropzone="<?=esc($stage)?>"></div>
        </section>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="abuse-list-wrap" id="abuseListWrap" aria-label="Abuse report triage list" hidden>
    <div class="abuse-list-table-shell">
      <table class="abuse-list-table">
        <thead>
          <tr>
            <th><button type="button" data-abuse-sort="priority">Priority</button></th>
            <th><button type="button" data-abuse-sort="report">Report</button></th>
            <th><button type="button" data-abuse-sort="status">Status</button></th>
            <th><button type="button" data-abuse-sort="age">Age</button></th>
            <th><button type="button" data-abuse-sort="assignee">Assignee</button></th>
            <th><button type="button" data-abuse-sort="reporter">Reporter</button></th>
            <th>Evidence</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="abuseListBody"></tbody>
      </table>
    </div>
  </section>

  <div class="modal-overlay hidden abuse-detail-modal" id="abuseDetailModal" data-unsaved-no-auto-save aria-hidden="true">
    <div class="modal modal-ticket abuse-modal" id="abuseDetailPanel" aria-live="polite">
      <div class="abuse-detail-content" id="abuseDetailContent">
        <header class="modal-head abuse-detail-head">
          <div>
            <span class="abuse-detail-ref" id="abuseDetailRef">TRACS-AR</span>
            <div class="modal-title" id="abuseDetailTitle">Abuse report</div>
            <div class="modal-sub" id="abuseDetailSub">Operational workflow detail</div>
          </div>
          <button class="modal-close" type="button" id="abuseClosePanel" aria-label="Close detail"><i data-lucide="x"></i></button>
        </header>

        <div class="abuse-detail-tabs" role="tablist" aria-label="Abuse report detail tabs">
          <button type="button" class="is-active" data-abuse-tab="overview">Overview</button>
          <button type="button" data-abuse-tab="timeline">Timeline</button>
          <button type="button" data-abuse-tab="evidence">Evidence</button>
          <button type="button" data-abuse-tab="notes">Notes</button>
          <button type="button" data-abuse-tab="relationships">Relationships</button>
          <button type="button" data-abuse-tab="actions">Actions</button>
        </div>

        <?php if($can_manage): ?>
        <div class="abuse-create-mode" id="abuseCreateMode" role="group" aria-label="Create mode" hidden>
          <button type="button" class="is-active" data-abuse-create-mode="single" aria-pressed="true"><i data-lucide="file-plus-2" class="icon-sm"></i>Single</button>
          <button type="button" data-abuse-create-mode="bulk" aria-pressed="false"><i data-lucide="table-2" class="icon-sm"></i>Bulk</button>
        </div>
        <?php endif; ?>

        <form class="abuse-detail-pane abuse-single-pane is-active" id="abuseOverviewPane" data-abuse-pane="overview">
          <input type="hidden" id="abuseReportId">
          <input type="hidden" id="abuseReporter">
          <div class="abuse-form-grid abuse-intake-grid">
            <div class="form-group is-wide"><label class="form-label">Title *</label><input class="form-input" id="abuseTitle" maxlength="220" required <?=$can_manage ? '' : 'disabled'?>></div>
            <div class="form-group"><label class="form-label">Type</label><select class="form-select" id="abuseType" <?=$can_manage ? '' : 'disabled'?>><?php foreach($type_labels as $key => $label): ?><option value="<?=esc($key)?>"><?=esc($label)?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label class="form-label">Priority</label><select class="form-select" id="abusePriority" <?=$can_manage ? '' : 'disabled'?>><?php foreach($priority_labels as $key => $label): ?><option value="<?=esc($key)?>"><?=esc($label)?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label class="form-label">Affected Domain</label><input class="form-input" id="abuseDomain" maxlength="255" <?=$can_manage ? '' : 'disabled'?>></div>
            <div class="form-group"><label class="form-label">Affected IP</label><input class="form-input" id="abuseIp" maxlength="64" <?=$can_manage ? '' : 'disabled'?>></div>
            <div class="form-group is-wide"><label class="form-label">Notes</label><textarea class="form-textarea abuse-intake-notes" id="abuseDescription" <?=$can_manage ? '' : 'disabled'?>></textarea></div>
          </div>
          <details class="abuse-advanced-fields" id="abuseAdvancedFields">
            <summary><i data-lucide="sliders-horizontal" class="icon-sm"></i><span>Advanced</span><i data-lucide="chevron-down" class="icon-sm"></i></summary>
            <div class="abuse-form-grid">
              <div class="form-group"><label class="form-label">Status</label><select class="form-select" id="abuseStatus" <?=$can_manage ? '' : 'disabled'?>><?php foreach($status_labels as $key => $label): ?><option value="<?=esc($key)?>"><?=esc($label)?></option><?php endforeach; ?></select></div>
              <div class="form-group"><label class="form-label">Reporter Contact</label><input class="form-input" id="abuseReporterContact" maxlength="190" <?=$can_manage ? '' : 'disabled'?>></div>
              <div class="form-group"><label class="form-label">Assigned Staff</label><select class="form-select" id="abuseAssignedUser" <?=$can_manage ? '' : 'disabled'?>> <option value="">Unassigned</option><?php foreach($users as $user): ?><option value="<?=esc((string)$user['id'])?>"><?=esc($user['label'] ?? '')?></option><?php endforeach; ?></select></div>
              <div class="form-group abuse-existing-only"><label class="form-label">SLA Due</label><input type="datetime-local" class="form-input" id="abuseSlaDue" <?=$can_manage ? '' : 'disabled'?>></div>
              <div class="form-group"><label class="form-label">Customer</label><input class="form-input" id="abuseCustomer" maxlength="190" <?=$can_manage ? '' : 'disabled'?>></div>
              <div class="form-group"><label class="form-label">Customer Ref</label><input class="form-input" id="abuseCustomerRef" maxlength="190" <?=$can_manage ? '' : 'disabled'?>></div>
              <div class="form-group"><label class="form-label">Ticket</label><select class="form-select" id="abuseTicketStatus" <?=$can_manage ? '' : 'disabled'?>> <option value="not_sent">Not Sent</option><option value="sent">Sent</option></select></div>
              <div class="form-group"><label class="form-label">Ticket Reference</label><input class="form-input" id="abuseTicketRef" maxlength="190" <?=$can_manage ? '' : 'disabled'?>></div>
              <div class="form-group"><label class="form-label">Ticket Link</label><input class="form-input" id="abuseTicketUrl" maxlength="255" <?=$can_manage ? '' : 'disabled'?>></div>
              <div class="form-group"><label class="form-label">Ticket Sent</label><input type="datetime-local" class="form-input" id="abuseTicketSentAt" <?=$can_manage ? '' : 'disabled'?>></div>
              <div class="form-group abuse-waiting-field"><label class="form-label">Waiting Period</label><select class="form-select" id="abuseWaitingHours" <?=$can_manage ? '' : 'disabled'?>> <option value="24">24 hours</option><option value="48">48 hours</option></select></div>
              <div class="form-group abuse-waiting-field"><label class="form-label">Waiting Started</label><input type="datetime-local" class="form-input" id="abuseWaitingStartedAt" <?=$can_manage ? '' : 'disabled'?>></div>
              <div class="form-group abuse-waiting-field"><label class="form-label">Waiting Until</label><input type="datetime-local" class="form-input" id="abuseWaitingUntil" <?=$can_manage ? '' : 'disabled'?>></div>
              <div class="form-group abuse-waiting-field"><label class="form-label">Waiting Status</label><div class="abuse-readonly-metric" id="abuseWaitingStatus">-</div></div>
              <div class="form-group is-wide"><label class="form-label">Tags</label><input class="form-input" id="abuseTags" maxlength="500" placeholder="phishing, domain, urgent" <?=$can_manage ? '' : 'disabled'?>></div>
              <div class="form-group is-wide"><label class="form-label">Nameserver Snapshot</label><textarea class="form-textarea" id="abuseNameservers" placeholder="ns1.example.com&#10;ns2.example.com" <?=$can_manage ? '' : 'disabled'?>></textarea></div>
              <div class="form-group"><label class="form-label">Snapshot Taken</label><input type="datetime-local" class="form-input" id="abuseNameserverAt" <?=$can_manage ? '' : 'disabled'?>></div>
            </div>
          </details>
          <?php if($can_manage): ?>
          <div class="abuse-detail-actions">
            <button type="button" class="btn btn-ghost" id="abuseResetBtn">Reset</button>
            <button type="button" class="btn btn-ghost abuse-create-only" id="abuseSaveAddAnotherBtn"><i data-lucide="copy-plus" class="icon-sm"></i>Save and Add Another</button>
            <button type="submit" class="btn btn-primary" id="abuseSaveBtn"><i data-lucide="check" class="icon-sm"></i>Save Report</button>
          </div>
          <?php endif; ?>
        </form>

        <?php if($can_manage): ?>
        <form class="abuse-detail-pane abuse-bulk-pane" id="abuseBulkPane" hidden>
          <div class="abuse-bulk-table-shell">
            <table class="abuse-bulk-table" aria-label="Bulk abuse report entry">
              <thead>
                <tr>
                  <th>Domain</th>
                  <th>Type</th>
                  <th>Priority</th>
                  <th>Notes</th>
                  <th></th>
                </tr>
              </thead>
              <tbody id="abuseBulkRows"></tbody>
            </table>
          </div>
          <div class="abuse-detail-actions abuse-bulk-actions">
            <button type="button" class="btn btn-ghost" id="abuseBulkAddRow"><i data-lucide="plus" class="icon-sm"></i>Add Row</button>
            <button type="submit" class="btn btn-primary" id="abuseBulkSaveBtn"><i data-lucide="check" class="icon-sm"></i>Save Reports</button>
          </div>
        </form>
        <?php endif; ?>

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
            <button type="button" class="btn btn-ghost" data-abuse-ticket-sent><i data-lucide="send" class="icon-sm"></i>Mark Ticket Sent</button>
            <button type="button" class="btn btn-ghost" data-abuse-snapshot-now><i data-lucide="server" class="icon-sm"></i>Stamp Nameserver Snapshot</button>
            <button type="button" class="btn btn-ghost" data-abuse-placeholder="suspend"><i data-lucide="ban" class="icon-sm"></i>Suspend Service</button>
            <button type="button" class="btn btn-ghost" data-abuse-placeholder="unsuspend"><i data-lucide="rotate-ccw" class="icon-sm"></i>Unsuspend Service</button>
          </div>
          <div class="abuse-activity-log" id="abuseActivityLog"></div>
        </section>
      </div>
    </div>
  </div>

  <script type="application/json" id="abuseDataset"><?=json_encode($reports, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)?></script>
  <script>window.TRACS_ABUSE_CAPS = <?=json_encode(['canManage' => $can_manage], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)?>;</script>
</div>
</main>
<?php include 'includes/footer.php'; ?>
