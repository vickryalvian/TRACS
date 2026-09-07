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
$can_delete = tracs_user_can_delete_abuse_reports($conn, $uid);
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
<div class="main-inner abuse-page" id="abuseWorkspace" data-selected-id="<?=$selected_id?>" data-can-manage="<?=$can_manage ? '1' : '0'?>" data-can-delete="<?=$can_delete ? '1' : '0'?>">

  <div class="topbar abuse-page-head">
    <div>
      <div class="abuse-title-line">
        <div class="page-title">Abuse Reports</div>
        <div class="page-sub" id="abusePageSummary" data-resolved-today="<?=esc((string)$summary['resolved_today'])?>"><?=count($reports)?> shown · <?=esc((string)$summary['open'])?> open · <?=esc((string)$summary['critical'])?> critical · <?=esc((string)($summary['action_required'] ?? $summary['over_sla']))?> action required · <?=esc((string)$summary['resolved_today'])?> resolved today</div>
      </div>
    </div>
    <div class="abuse-page-actions">
      <details class="report-export-menu">
        <summary class="btn btn-ghost btn-icon report-export-trigger" title="More actions" aria-label="More actions" data-tooltip="More actions"><i data-lucide="more-vertical" class="icon-sm"></i></summary>
        <form method="get" action="/api/export-abuse-reports.php" class="report-export-popover" id="abuseExportForm">
          <input type="hidden" name="q" id="abuseExportQ" value="">
          <input type="hidden" name="status" id="abuseExportStatus" value="">
          <input type="hidden" name="priority" id="abuseExportPriority" value="">
          <input type="hidden" name="reporter" id="abuseExportReporter" value="">
          <input type="hidden" name="assigned" id="abuseExportAssigned" value="">
          <input type="hidden" name="evidence" id="abuseExportEvidence" value="">
          <input type="hidden" name="action_required" id="abuseExportActionRequired" value="">
          <div class="report-export-title"><i data-lucide="download" class="icon-xs"></i>Export CSV</div>
          <?=tracs_date_range_picker([
              'id' => 'abuseExportRange',
              'start_name' => 'from',
              'end_name' => 'to',
              'label' => 'Export date range',
          ])?>
          <button type="submit" class="btn btn-primary"><i data-lucide="download" class="icon-sm"></i>Download CSV</button>
        </form>
      </details>
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
            <strong><?=esc($column['label'])?></strong>
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
            <th><button type="button" class="table-sort-button" data-abuse-sort="priority" aria-label="Sort by priority"><span>Priority</span><i data-lucide="chevrons-up-down" class="icon-xs table-sort-icon" aria-hidden="true"></i></button></th>
            <th><button type="button" class="table-sort-button" data-abuse-sort="report" aria-label="Sort by report"><span>Report</span><i data-lucide="chevrons-up-down" class="icon-xs table-sort-icon" aria-hidden="true"></i></button></th>
            <th><button type="button" class="table-sort-button" data-abuse-sort="status" aria-label="Sort by status"><span>Status</span><i data-lucide="chevrons-up-down" class="icon-xs table-sort-icon" aria-hidden="true"></i></button></th>
            <th><button type="button" class="table-sort-button" data-abuse-sort="age" aria-label="Sort by age"><span>Age</span><i data-lucide="chevrons-up-down" class="icon-xs table-sort-icon" aria-hidden="true"></i></button></th>
            <th><button type="button" class="table-sort-button" data-abuse-sort="assignee" aria-label="Sort by assignee"><span>Assignee</span><i data-lucide="chevrons-up-down" class="icon-xs table-sort-icon" aria-hidden="true"></i></button></th>
            <th><button type="button" class="table-sort-button" data-abuse-sort="reporter" aria-label="Sort by reporter"><span>Reporter</span><i data-lucide="chevrons-up-down" class="icon-xs table-sort-icon" aria-hidden="true"></i></button></th>
            <th><button type="button" class="table-sort-button" data-abuse-sort="evidence" aria-label="Sort by evidence"><span>Evidence</span><i data-lucide="chevrons-up-down" class="icon-xs table-sort-icon" aria-hidden="true"></i></button></th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="abuseListBody"></tbody>
      </table>
    </div>
  </section>

  <div class="modal-overlay hidden abuse-preview-modal" id="abusePreviewModal" aria-hidden="true">
    <section class="modal abuse-preview" role="dialog" aria-modal="true" aria-labelledby="abusePreviewTitle">
      <header class="abuse-preview-head">
        <div>
          <span class="abuse-detail-ref" id="abusePreviewRef">TRACS-AR</span>
          <h2 id="abusePreviewTitle">Abuse report</h2>
        </div>
        <button class="modal-close" type="button" id="abuseClosePreview" aria-label="Close preview" title="Close"><i data-lucide="x"></i></button>
      </header>
      <dl class="abuse-preview-facts">
        <div><dt>Type</dt><dd id="abusePreviewType">-</dd></div>
        <div><dt>Priority</dt><dd id="abusePreviewPriority">-</dd></div>
        <div><dt>Status</dt><dd id="abusePreviewStatus">-</dd></div>
        <div><dt>Affected domain</dt><dd id="abusePreviewDomain">-</dd></div>
        <div><dt>Last activity</dt><dd id="abusePreviewActivity">-</dd></div>
        <div><dt>SLA due</dt><dd id="abusePreviewSla">-</dd></div>
      </dl>
      <footer class="abuse-preview-actions">
        <button class="btn btn-ghost" type="button" id="abusePreviewDismiss">Close</button>
        <button class="btn btn-primary" type="button" id="abuseOpenRecord"><i data-lucide="external-link" class="icon-sm"></i>Open record</button>
      </footer>
    </section>
  </div>

  <div class="modal-overlay hidden abuse-detail-modal" id="abuseDetailModal" data-unsaved-no-auto-save aria-hidden="true">
    <div class="modal modal-ticket abuse-modal" id="abuseDetailPanel" aria-live="polite">
      <div class="abuse-detail-content" id="abuseDetailContent">
        <header class="modal-head abuse-detail-head">
          <div class="abuse-detail-identity">
            <div class="modal-title" id="abuseDetailTitle">Abuse report</div>
            <div class="abuse-detail-meta">
              <span class="abuse-detail-ref" id="abuseDetailRef">TRACS-AR</span>
              <span class="modal-sub" id="abuseDetailSub">Operational workflow detail</span>
              <span class="abuse-status-chip" id="abuseDetailStatusBadge">Incoming</span>
              <span class="abuse-pill" id="abuseDetailPriorityBadge">medium</span>
            </div>
          </div>
          <div class="abuse-detail-head-actions">
            <?php if($can_delete): ?><button class="modal-close abuse-delete-record" type="button" id="abuseDeleteRecord" aria-label="Delete report" title="Delete report"><i data-lucide="trash-2"></i></button><?php endif; ?>
            <button class="modal-close" type="button" id="abuseClosePanel" aria-label="Close detail" title="Close"><i data-lucide="x"></i></button>
          </div>
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
            <div class="form-group abuse-optional-field" data-abuse-optional="affected_ip"><label class="form-label">Affected IP</label><input class="form-input" id="abuseIp" maxlength="64" <?=$can_manage ? '' : 'disabled'?>></div>
            <div class="form-group is-wide"><label class="form-label">Notes</label><textarea class="form-textarea abuse-intake-notes" id="abuseDescription" <?=$can_manage ? '' : 'disabled'?>></textarea></div>
          </div>
          <?php if($can_manage): ?><div class="abuse-add-fields" id="abuseAddFields" aria-label="Add optional field"></div><?php endif; ?>
          <details class="abuse-advanced-fields" id="abuseAdvancedFields">
            <summary><i data-lucide="sliders-horizontal" class="icon-sm"></i><span>Advanced</span><i data-lucide="chevron-down" class="icon-sm"></i></summary>
            <div class="abuse-form-grid">
              <div class="form-group abuse-inline-field"><label class="form-label">Status</label><select class="form-select" id="abuseStatus" <?=$can_manage ? '' : 'disabled'?>><?php foreach($status_labels as $key => $label): ?><option value="<?=esc($key)?>"><?=esc($label)?></option><?php endforeach; ?></select></div>
              <div class="form-group abuse-optional-field" data-abuse-optional="reporter_contact"><label class="form-label">Reporter Contact</label><input class="form-input" id="abuseReporterContact" maxlength="190" <?=$can_manage ? '' : 'disabled'?>></div>
              <div class="form-group abuse-inline-field"><label class="form-label">Assigned Staff</label><select class="form-select" id="abuseAssignedUser" <?=$can_manage ? '' : 'disabled'?>> <option value="">Unassigned</option><?php foreach($users as $user): ?><option value="<?=esc((string)$user['id'])?>"><?=esc($user['label'] ?? '')?></option><?php endforeach; ?></select></div>
              <div class="form-group abuse-existing-only abuse-inline-field"><label class="form-label">SLA Due</label><input type="datetime-local" class="form-input" id="abuseSlaDue" <?=$can_manage ? '' : 'disabled'?>></div>
              <div class="form-group abuse-optional-field" data-abuse-optional="customer_name"><label class="form-label">Customer</label><input class="form-input" id="abuseCustomer" maxlength="190" <?=$can_manage ? '' : 'disabled'?>></div>
              <div class="form-group abuse-optional-field" data-abuse-optional="customer_reference"><label class="form-label">Customer Ref</label><input class="form-input" id="abuseCustomerRef" maxlength="190" <?=$can_manage ? '' : 'disabled'?>></div>
              <div class="form-group abuse-inline-field"><label class="form-label">Ticket</label><select class="form-select" id="abuseTicketStatus" <?=$can_manage ? '' : 'disabled'?>> <option value="not_sent">Not Sent</option><option value="sent">Sent</option></select></div>
              <div class="form-group abuse-optional-field" data-abuse-optional="ticket_reference"><label class="form-label">Ticket Reference</label><input class="form-input" id="abuseTicketRef" maxlength="190" <?=$can_manage ? '' : 'disabled'?>></div>
              <div class="form-group abuse-optional-field" data-abuse-optional="ticket_url"><label class="form-label">Ticket Link</label><input class="form-input" id="abuseTicketUrl" maxlength="255" <?=$can_manage ? '' : 'disabled'?>></div>
              <div class="form-group abuse-optional-field" data-abuse-optional="ticket_sent_at"><label class="form-label">Ticket Sent</label><input type="datetime-local" class="form-input" id="abuseTicketSentAt" <?=$can_manage ? '' : 'disabled'?>></div>
              <div class="form-group abuse-waiting-field"><label class="form-label">Waiting Period</label><select class="form-select" id="abuseWaitingHours" <?=$can_manage ? '' : 'disabled'?>> <option value="24">24 hours</option><option value="48">48 hours</option></select></div>
              <div class="form-group abuse-waiting-field"><label class="form-label">Waiting Started</label><input type="datetime-local" class="form-input" id="abuseWaitingStartedAt" <?=$can_manage ? '' : 'disabled'?>></div>
              <div class="form-group abuse-waiting-field"><label class="form-label">Waiting Until</label><input type="datetime-local" class="form-input" id="abuseWaitingUntil" <?=$can_manage ? '' : 'disabled'?>></div>
              <div class="form-group abuse-waiting-field"><label class="form-label">Waiting Status</label><div class="abuse-readonly-metric" id="abuseWaitingStatus">-</div></div>
              <div class="form-group is-wide abuse-optional-field" data-abuse-optional="tags"><label class="form-label">Tags</label><input class="form-input" id="abuseTags" maxlength="500" placeholder="phishing, domain, urgent" <?=$can_manage ? '' : 'disabled'?>></div>
              <div class="form-group is-wide abuse-optional-field" data-abuse-optional="nameserver_snapshot"><label class="form-label">Nameserver Snapshot</label><textarea class="form-textarea" id="abuseNameservers" placeholder="ns1.example.com&#10;ns2.example.com" <?=$can_manage ? '' : 'disabled'?>></textarea></div>
              <div class="form-group abuse-optional-field" data-abuse-optional="nameserver_snapshot_at"><label class="form-label">Snapshot Taken</label><input type="datetime-local" class="form-input" id="abuseNameserverAt" <?=$can_manage ? '' : 'disabled'?>></div>
            </div>
          </details>
          <?php if($can_manage): ?>
          <div class="abuse-detail-actions">
            <button type="button" class="btn btn-ghost" id="abuseResetBtn">Reset</button>
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
  <script>window.TRACS_ABUSE_CAPS = <?=json_encode(['canManage' => $can_manage, 'canDelete' => $can_delete], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)?>;</script>
</div>
</main>
<?php include 'includes/footer.php'; ?>
