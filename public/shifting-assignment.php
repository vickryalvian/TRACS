<?php

require_once __DIR__ . '/../core/security/csrf.php';
tracs_start_session();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth/auth_check.php';
require_once __DIR__ . '/../core/access_control.php';
require_once __DIR__ . '/../modules/shifting-assignment/ShiftingAssignmentService.php';
require_once __DIR__ . '/../modules/alert-ticker/controller.php';
require_once __DIR__ . '/includes/page_helpers.php';

tracs_require_page_permission($conn, 'shifts.view');
$uid = (int)($_SESSION['user_id'] ?? 0);
$user_email = $_SESSION['user_email'] ?? 'operator@tracs.local';
$service = new ShiftingAssignmentService($conn, $uid);
$canManageMonthlyTemplates = $service->canManageMonthlyTemplates();
$canManageSettings = $service->canManageSettings();
$defaultConfigTab = $canManageSettings ? 'patterns' : 'monthly';
$today = new DateTimeImmutable('today', new DateTimeZone('Asia/Jakarta'));
$rangeStart = $_GET['start'] ?? $today->modify('monday this week')->format('Y-m-d');
$rangeEnd = $_GET['end'] ?? $today->modify('sunday this week')->format('Y-m-d');
$initialData = $service->getPageData([
    'start' => $rangeStart,
    'end' => $rangeEnd,
    'division_id' => $_GET['division_id'] ?? null,
    'user_id' => $_GET['user_id'] ?? null,
]);
$ticker_items = (new AlertTickerController($conn, $uid))->formatAlertsForTicker();
$critical_count = (int)$initialData['summary']['conflicts'] + (int)$initialData['summary']['coverage_gaps'];
$page_title = 'Shifting Assignment';
$active_page = 'shifting-assignment';
include __DIR__ . '/includes/header.php';
?>
<main class="main shifting-page" id="shiftingAssignmentApp">
  <div class="main-inner">
    <div class="topbar shifting-topbar">
      <div class="topbar-left">
        <div class="page-title">Shifting Assignment</div>
        <div class="page-sub">Workforce schedule, workload, holiday coverage, lembur, and rest-risk monitoring</div>
      </div>
      <div class="topbar-actions">
        <?php if($service->canManage()): ?>
        <button class="btn btn-primary" type="button" data-shift-open="assignment">
          <i data-lucide="plus" class="icon-sm"></i>Add Assignment
        </button>
        <?php endif; ?>
      </div>
    </div>

    <!-- Summary values rendered in bottom Schedule Insights -->
    <div id="shiftSummaryData" hidden>
      <?php
      $cards = [
          ['today_assigned', 'Today Assigned', 'users', 'blue'],
          ['active_now', 'Active Now', 'radio', 'green'],
          ['under_target', 'Under Target', 'trending-down', 'amber'],
          ['overtime_risk', 'Overtime Risk', 'timer-off', 'red'],
          ['jumpshift_risk', 'Jumpshift Risk', 'clock-alert', 'amber'],
          ['coverage_gaps', 'Coverage Gaps', 'shield-alert', 'red'],
          ['upcoming_holiday', 'Upcoming Holiday', 'calendar-days', 'cyan'],
      ];
      foreach($cards as [$key, $label, $icon, $tone]):
      ?>
      <span data-summary-value="<?=$key?>">0</span>
      <span data-summary-note="<?=$key?>"></span>
      <?php endforeach; ?>
    </div>

    <section class="panel shift-toolbar-panel">
      <div class="shift-toolbar">
        <div class="shift-filter-row shift-filter-row-range">
          <span class="shift-filter-label">Date Range</span>
          <div class="shift-range-control">
            <button class="btn btn-ghost btn-icon" type="button" data-shift-range="-1" aria-label="Previous period"><i data-lucide="chevron-left" class="icon-sm"></i></button>
            <label><span>From</span><input class="form-input" type="date" id="shiftFilterStart" value="<?=htmlspecialchars($initialData['range']['start'])?>"></label>
            <label><span>To</span><input class="form-input" type="date" id="shiftFilterEnd" value="<?=htmlspecialchars($initialData['range']['end'])?>"></label>
            <button class="btn btn-ghost btn-icon" type="button" data-shift-range="1" aria-label="Next period"><i data-lucide="chevron-right" class="icon-sm"></i></button>
          </div>
          <button class="btn btn-ghost" type="button" id="shiftTodayBtn"><i data-lucide="calendar-clock" class="icon-xs"></i>Today</button>
        </div>
        <div class="shift-filter-row shift-filter-row-scope">
          <span class="shift-filter-label">Scope Filters</span>
          <div class="shift-scope-controls">
            <select class="form-select" id="shiftFilterAgent" aria-label="Filter by agent"><option value="">All agents</option></select>
            <select class="form-select" id="shiftFilterDivision" aria-label="Filter by division"><option value="">All divisions</option></select>
            <select class="form-select" id="shiftFilterType" aria-label="Filter by shift type"><option value="">All shift types</option></select>
            <select class="form-select" id="shiftFilterStatus" aria-label="Filter by status">
              <option value="">All statuses</option>
              <option value="assigned">Assigned</option><option value="confirmed">Confirmed</option>
              <option value="active">Active</option><option value="completed">Completed</option>
              <option value="cancelled">Cancelled</option><option value="no_show">No Show</option><option value="replaced">Replaced</option>
            </select>
          </div>
          <details class="shift-risk-menu">
            <summary><i data-lucide="shield-alert" class="icon-xs"></i>Risk Filters<span id="shiftRiskCount"></span></summary>
            <div class="shift-risk-popover">
              <label><input type="checkbox" data-recap-filter="under">Under target</label>
              <label><input type="checkbox" data-recap-filter="over">Over target</label>
              <label><input type="checkbox" data-recap-filter="jumpshift">Jumpshift</label>
              <label><input type="checkbox" data-recap-filter="conflict">Conflict</label>
              <label><input type="checkbox" id="shiftHolidayOnly">Holiday / lembur</label>
            </div>
          </details>
        </div>
        <div class="shift-filter-row shift-filter-row-actions">
          <span class="shift-filter-label">Search + Actions</span>
          <input class="form-input" id="shiftFilterSearch" type="search" placeholder="Search agent, shift, type, or notes">
          <div class="shift-filter-actions">
            <button class="btn btn-primary" id="shiftApplyFilters" type="button"><i data-lucide="filter" class="icon-sm"></i>Apply</button>
            <button class="btn btn-ghost" id="shiftResetFilters" type="button"><i data-lucide="rotate-ccw" class="icon-xs"></i>Reset</button>
          </div>
        </div>
      </div>
    </section>

    <nav class="shift-workspace-tabs" role="tablist" aria-label="Shifting Assignment modules">
      <button class="active" type="button" role="tab" aria-selected="true" data-workspace-tab="timeline"><i data-lucide="gantt-chart-square" class="icon-xs"></i>Schedule Timeline</button>
      <button type="button" role="tab" aria-selected="false" data-workspace-tab="workload"><i data-lucide="chart-no-axes-column-increasing" class="icon-xs"></i>Workload Recap</button>
      <button type="button" role="tab" aria-selected="false" data-workspace-tab="warnings"><i data-lucide="shield-alert" class="icon-xs"></i>Smart Warnings<span class="shift-tab-count" id="shiftWarningCount">0</span></button>
      <button type="button" role="tab" aria-selected="false" data-workspace-tab="audit"><i data-lucide="list-collapse" class="icon-xs"></i>Assignment Audit</button>
      <?php if($service->canManageSettings() || $canManageMonthlyTemplates): ?>
      <button type="button" role="tab" aria-selected="false" data-workspace-tab="configuration"><i data-lucide="settings-2" class="icon-xs"></i>Configuration</button>
      <?php endif; ?>
    </nav>

    <div class="shift-dashboard-workspace shift-workspace-fullwidth">
      <div class="shift-workspace-main">
        <section class="shift-workspace-pane active" data-workspace-pane="timeline">
          <div class="panel shift-timeline-panel">
            <div class="shift-pane-toolbar">
              <div>
                <span class="panel-title"><i data-lucide="gantt-chart-square" class="icon-sm"></i>Schedule Timeline</span>
                <span class="panel-meta" id="shiftTimelineMeta"></span>
              </div>
            </div>
            <div class="shift-timeline-toolbar">
              <div class="shift-quick-actions">
                <?php if($service->canManage()): ?>
                <button class="btn btn-ghost" type="button" id="shiftCopyLastWeek"><i data-lucide="copy" class="icon-xs"></i>Copy Last Week</button>
                <button class="btn btn-ghost" type="button" data-shift-open="assignment" data-shift-type="lembur"><i data-lucide="clock-plus" class="icon-xs"></i>Assign Lembur</button>
                <button class="btn btn-ghost" type="button" data-shift-open="assignment" data-shift-type="holiday_coverage"><i data-lucide="calendar-check" class="icon-xs"></i>Holiday Coverage</button>
                <button class="btn btn-ghost" type="button" data-shift-open="replace"><i data-lucide="user-round-cog" class="icon-xs"></i>Replace Agent</button>
                <?php endif; ?>
                <span class="shift-timeline-legend"><i></i>15-minute drag / resize snap</span>
              </div>
              <div class="shift-view-switch" role="group" aria-label="Timeline view">
                <button type="button" data-shift-view="daily">Daily</button>
                <button type="button" data-shift-view="weekly" class="active">Weekly</button>
                <button type="button" data-shift-view="monthly">Monthly</button>
              </div>
            </div>
            <div class="shift-timeline-scroll" id="shiftTimeline" aria-live="polite"></div>
            <div class="shift-resize-tooltip" id="shiftResizeTooltip" hidden></div>
          </div>
        </section>

        <section class="shift-workspace-pane" data-workspace-pane="workload" hidden>
          <div class="panel">
            <div class="shift-pane-toolbar">
              <div><span class="panel-title"><i data-lucide="chart-no-axes-column-increasing" class="icon-sm"></i>Workload Recap</span><span class="panel-meta" id="shiftRecapMeta"></span></div>
              <div class="shift-chip-filter" role="group" aria-label="Workload status filter">
                <button class="active" type="button" data-workload-filter="all">All</button>
                <button type="button" data-workload-filter="under">Under Target</button>
                <button type="button" data-workload-filter="over">Over Target</button>
                <button type="button" data-workload-filter="overtime">Overtime</button>
                <button type="button" data-workload-filter="jumpshift">Jumpshift</button>
                <button type="button" data-workload-filter="none">No Schedule</button>
              </div>
            </div>
            <div class="shift-recap-mini" id="shiftRecapMini"></div>
            <div class="table-wrap">
              <table class="data-table shift-recap-table">
                <thead><tr>
                  <th>Agent</th><th>Division</th><th>Days</th><th>Total</th><th>Regular</th><th>OT</th>
                  <th>Holiday</th><th>Standby</th><th>Target</th><th>Difference</th><th>Rest</th><th>Status</th>
                </tr></thead>
                <tbody id="shiftRecapBody"></tbody>
              </table>
            </div>
          </div>
        </section>

        <section class="shift-workspace-pane" data-workspace-pane="warnings" hidden>
          <div class="panel">
            <div class="shift-pane-toolbar">
              <div><span class="panel-title"><i data-lucide="shield-alert" class="icon-sm"></i>Smart Warnings</span><span class="panel-meta">Grouped by operational risk</span></div>
              <div class="shift-warning-controls">
                <select class="form-select" id="shiftWarningType"><option value="all">All warning types</option></select>
                <label class="shift-check-label"><input type="checkbox" id="shiftWarningsUnresolved" checked>Unresolved only</label>
              </div>
            </div>
            <div class="shift-warning-list" id="shiftWarningList"></div>
          </div>
        </section>

        <section class="shift-workspace-pane" data-workspace-pane="audit" hidden>
          <div class="panel">
            <div class="shift-pane-toolbar">
              <div><span class="panel-title"><i data-lucide="list-collapse" class="icon-sm"></i>Assignment Audit</span><span class="panel-meta" id="shiftAssignmentMeta"></span></div>
              <?php if($service->canExport()): ?>
              <a class="btn btn-ghost btn-sm" id="shiftExportBtn" href="shifting-assignment-export.php"><i data-lucide="download" class="icon-xs"></i>Export CSV</a>
              <?php endif; ?>
            </div>
            <div class="table-wrap">
              <table class="data-table shift-assignment-table">
                <thead><tr>
                  <th>Date</th><th>Agent</th><th>Division</th><th>Shift</th><th>Time</th><th>Duration</th><th>Type</th>
                  <th>Status</th><th>Approval</th><th>Warnings</th><th>Source</th><th>Last Modified</th><th>Actions</th>
                </tr></thead>
                <tbody id="shiftAssignmentBody"></tbody>
              </table>
            </div>
          </div>
        </section>

        <?php if($service->canManageSettings() || $canManageMonthlyTemplates): ?>
        <section class="shift-workspace-pane" data-workspace-pane="configuration" hidden>
          <div class="panel shift-management-panel">
            <div class="shift-pane-toolbar shift-config-toolbar">
              <div><span class="panel-title"><i data-lucide="settings-2" class="icon-sm"></i>Schedule Configuration</span><span class="panel-meta">Templates, holidays, coverage, and workload rules</span></div>
              <div class="shift-config-tabs">
                <?php if($canManageSettings): ?>
                <button type="button" class="<?=$defaultConfigTab === 'patterns' ? 'active' : ''?>" data-config-tab="patterns">Shift Patterns</button>
                <?php endif; ?>
                <?php if($canManageMonthlyTemplates): ?>
                <button type="button" class="<?=$defaultConfigTab === 'monthly' ? 'active' : ''?>" data-config-tab="monthly">Monthly Templates</button>
                <?php endif; ?>
                <?php if($canManageSettings): ?>
                <button type="button" data-config-tab="holidays">Holidays</button>
                <button type="button" data-config-tab="coverage">Coverage Rules</button>
                <button type="button" data-config-tab="settings">Workload Settings</button>
                <?php endif; ?>
              </div>
            </div>
            <?php if($canManageSettings): ?>
            <div class="shift-config-pane <?=$defaultConfigTab === 'patterns' ? 'active' : ''?>" data-config-pane="patterns">
              <section class="shift-config-section">
                <div class="shift-config-head">
                  <div><h3>Shift Patterns</h3><p>Reusable shift time, break, type, and color definitions.</p></div>
                  <button class="btn btn-primary" type="button" data-shift-open="template"><i data-lucide="plus" class="icon-sm"></i>Add Shift Pattern</button>
                </div>
                <div class="shift-config-filter"><input class="form-input" id="shiftTemplateSearch" type="search" placeholder="Search shift patterns"><select class="form-select" id="shiftTemplateStatus"><option value="all">All statuses</option><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
                <div class="shift-config-cards" id="shiftTemplateList"></div>
              </section>
            </div>
            <?php endif; ?>
            <?php if($canManageMonthlyTemplates): ?>
            <div class="shift-config-pane <?=$defaultConfigTab === 'monthly' ? 'active' : ''?>" data-config-pane="monthly">
              <section class="shift-config-section">
                <div class="shift-config-head">
                  <div>
                    <h3>Monthly Shift Templates</h3>
                    <p>Plan, preview, and apply one-month schedules without replacing protected assignments.</p>
                  </div>
                  <button class="btn btn-primary" type="button" data-shift-open="monthlyTemplate"><i data-lucide="plus" class="icon-sm"></i>Create Monthly Template</button>
                </div>
                <div class="shift-config-filter shift-monthly-filter">
                  <input class="form-input" id="shiftMonthlyTemplateSearch" type="search" placeholder="Search monthly templates">
                  <select class="form-select" id="shiftMonthlyTemplateStatus"><option value="all">All statuses</option><option value="draft">Draft</option><option value="previewed">Previewed</option><option value="applied">Applied</option><option value="archived">Archived</option></select>
                </div>
                <div class="shift-monthly-template-list" id="shiftMonthlyTemplateList"></div>
              </section>
            </div>
            <?php endif; ?>
            <?php if($canManageSettings): ?>
            <div class="shift-config-pane" data-config-pane="holidays">
              <div class="shift-config-head"><div><h3>Holidays</h3><p>Database holidays override the Indonesia fallback calendar.</p></div><button class="btn btn-primary" type="button" data-shift-open="holiday"><i data-lucide="plus" class="icon-sm"></i>Add Holiday</button></div>
              <div class="shift-config-cards" id="shiftHolidayList"></div>
            </div>
            <div class="shift-config-pane" data-config-pane="coverage">
              <div class="shift-config-head"><div><h3>Coverage Rules</h3><p>Minimum staffing windows evaluated against actual shift overlap.</p></div><button class="btn btn-primary" type="button" data-shift-open="coverage"><i data-lucide="plus" class="icon-sm"></i>Add Rule</button></div>
              <div class="shift-config-cards" id="shiftCoverageList"></div>
            </div>
            <div class="shift-config-pane" data-config-pane="settings">
              <div class="shift-config-head"><div><h3>Workload Settings</h3><p>Targets, overtime thresholds, rest requirements, and timeline behavior.</p></div></div>
              <form id="shiftSettingsForm" class="shift-settings-form">
                <label>Division<select class="form-select" name="division_id" id="shiftSettingsDivision"><option value="">Global default</option></select></label>
                <label>Weekly target (hours)<input class="form-input" name="weekly_target_hours" type="number" min="1" max="168" step=".25"></label>
                <label>Daily target (hours)<input class="form-input" name="daily_target_hours" type="number" min="1" max="24" step=".25"></label>
                <label>Overtime risk (hours)<input class="form-input" name="overtime_threshold_hours" type="number" min="1" max="168" step=".25"></label>
                <label>Maximum weekly (hours)<input class="form-input" name="max_weekly_hours" type="number" min="1" max="168" step=".25"></label>
                <label>Maximum daily (hours)<input class="form-input" name="max_daily_hours" type="number" min="1" max="24" step=".25"></label>
                <label>Minimum rest (hours)<input class="form-input" name="minimum_rest_hours" type="number" min="0" max="24" step=".25"></label>
                <label>Timeline snap (minutes)<input class="form-input" name="timeline_snap_minutes" type="number" min="5" max="60" step="5"></label>
                <label>Minimum shift (hours)<input class="form-input" name="minimum_shift_hours" type="number" min=".25" max="12" step=".25"></label>
                <label>Normal work days<input class="form-input" name="normal_working_days_per_week" type="number" min="1" max="7"></label>
                <label>Holiday minimum agents<input class="form-input" name="holiday_minimum_agents" type="number" min="1" max="100"></label>
                <label class="shift-check-label"><input name="count_standby_as_work_hour" type="checkbox">Count standby as work hour</label>
                <button class="btn btn-primary" type="submit"><i data-lucide="save" class="icon-sm"></i>Save Settings</button>
              </form>
            </div>
            <?php endif; ?>
          </div>
        </section>
        <?php endif; ?>
    </div>

    <section class="shift-schedule-insights is-collapsed" id="shiftScheduleInsights" aria-label="Schedule Insights">
      <div class="shift-insights-head">
        <h3 class="shift-insights-title"><i data-lucide="bar-chart-3" class="icon-xs"></i>Schedule Insights</h3>
        <div class="shift-insights-summary" id="shiftInsightsSummary"></div>
        <button class="btn btn-ghost btn-sm" type="button" id="shiftInsightsToggle" aria-expanded="false" aria-controls="shiftInsightsGrid">
          <span>Expand</span><i data-lucide="chevron-down" class="icon-xs"></i>
        </button>
      </div>
      <div class="shift-insights-grid" id="shiftInsightsGrid">
        <div class="shift-insight-compact shift-health-compact" id="shiftBottomHealth">
          <div class="shift-insight-compact-head"><i data-lucide="activity" class="icon-xs"></i>Shift Health</div>
          <div class="shift-insight-compact-body">
            <strong id="shiftHealthScore">100</strong>
            <div class="shift-health-track"><i id="shiftHealthBar"></i></div>
            <span id="shiftHealthStatus">Healthy</span>
          </div>
        </div>
        <div class="shift-insight-compact" id="shiftBottomCoverage">
          <div class="shift-insight-compact-head"><i data-lucide="users-round" class="icon-xs"></i>Today's Coverage</div>
          <div class="shift-insight-compact-body" id="shiftTodayCoverage"></div>
        </div>
        <div class="shift-insight-compact" id="shiftBottomHoliday">
          <div class="shift-insight-compact-head"><i data-lucide="calendar-days" class="icon-xs"></i>Upcoming Holiday</div>
          <div class="shift-insight-compact-body" id="shiftInsightHoliday"></div>
        </div>
        <div class="shift-insight-compact" id="shiftBottomWarning">
          <div class="shift-insight-compact-head"><i data-lucide="triangle-alert" class="icon-xs"></i>Top Warning</div>
          <div class="shift-insight-compact-body" id="shiftInsightWarning"></div>
        </div>
      </div>
    </section>
  </div>

  <?php if($service->canManage()): ?>
  <div class="modal-overlay hidden shift-modal-overlay" id="shiftAssignmentModal" data-shift-modal aria-hidden="true">
    <form class="modal shift-modal sa-modal" id="shiftAssignmentForm" data-shift-singleton-form novalidate role="dialog" aria-modal="true" aria-labelledby="shiftAssignmentModalTitle">
      <div class="modal-head"><div><div class="modal-title" id="shiftAssignmentModalTitle">Shift Assignment</div><div class="modal-sub">Flexible times, cross-day support, and automatic workload checks</div></div><button class="modal-close" type="button" data-shift-close aria-label="Close assignment modal"><i data-lucide="x"></i></button></div>
      <div class="modal-body" data-shift-canonical-body>
        <input type="hidden" name="id">
        <div class="shift-form-alert" id="shiftAssignmentFormAlert" role="alert" hidden></div>
        <div class="sa-modal-grid">
          <label class="form-group"><span class="form-label">Agent *</span><select class="form-select" name="user_id" required aria-describedby="shiftAssignmentUserError"></select><small class="shift-field-error" id="shiftAssignmentUserError"></small></label>
          <label class="form-group"><span class="form-label">Division</span><input class="form-input" name="division_name" readonly></label>
          <label class="form-group"><span class="form-label">Assignment Date *</span><input class="form-input" name="assignment_date" type="date" required aria-describedby="shiftAssignmentDateError"><small class="shift-field-error" id="shiftAssignmentDateError"></small></label>
          <label class="form-group"><span class="form-label">Shift Template</span><select class="form-select" name="shift_template_id"><option value="">Custom shift</option></select></label>
          <label class="form-group"><span class="form-label">Start Time *</span><input class="form-input" name="start_time" type="time" step="900" required aria-describedby="shiftAssignmentStartError"><small class="shift-field-error" id="shiftAssignmentStartError"></small></label>
          <label class="form-group"><span class="form-label">End Time *</span><input class="form-input" name="end_time" type="time" step="900" required aria-describedby="shiftAssignmentEndError"><small class="shift-field-error" id="shiftAssignmentEndError"></small></label>
          <label class="form-group"><span class="form-label">Break Minutes</span><input class="form-input" name="break_minutes" type="number" min="0" max="720" step="5" value="0" aria-describedby="shiftAssignmentBreakError"><small class="shift-field-error" id="shiftAssignmentBreakError"></small></label>
          <div class="shift-duration-preview" role="status" aria-live="polite"><span id="shiftDurationPreview">Duration: 0h</span><small id="shiftCrossDayNote" hidden>This shift ends the next day.</small></div>
          <label class="form-group"><span class="form-label">Assignment Type</span><select class="form-select" name="assignment_type"></select></label>
          <label class="form-group"><span class="form-label">Status</span><select class="form-select" name="status"><option value="assigned">Assigned</option><option value="confirmed">Confirmed</option><option value="cancelled">Cancelled</option><option value="no_show">No Show</option><option value="replaced">Replaced</option></select></label>
        </div>
        <label class="form-group"><span class="form-label">Notes</span><textarea class="form-textarea" name="notes" placeholder="Coverage context, approval detail, or handover note"></textarea></label>
        <input type="hidden" name="is_manual_duration_override" value="0">
      </div>
      <div class="modal-foot"><button class="btn btn-ghost" type="button" data-shift-close>Cancel</button><button class="btn btn-primary" type="submit"><i data-lucide="check" class="icon-sm"></i>Save Assignment</button></div>
    </form>
  </div>

  <div class="modal-overlay hidden shift-modal-overlay" id="shiftReplaceModal" data-shift-modal aria-hidden="true">
    <form class="modal modal-sm sa-modal" id="shiftReplaceForm" role="dialog" aria-modal="true" aria-labelledby="shiftReplaceModalTitle">
      <div class="modal-head"><div><div class="modal-title" id="shiftReplaceModalTitle">Replace Agent</div><div class="modal-sub">The existing assignment will be marked replaced</div></div><button class="modal-close" type="button" data-shift-close aria-label="Close replace agent modal"><i data-lucide="x"></i></button></div>
      <div class="modal-body">
        <label class="form-group"><span class="form-label">Assignment *</span><select class="form-select" name="assignment_id" required></select></label>
        <label class="form-group"><span class="form-label">Replacement Agent *</span><select class="form-select" name="new_user_id" required></select></label>
        <label class="form-group"><span class="form-label">Notes</span><textarea class="form-textarea" name="notes"></textarea></label>
      </div>
      <div class="modal-foot"><button class="btn btn-ghost" type="button" data-shift-close>Cancel</button><button class="btn btn-primary" type="submit">Create Replacement</button></div>
    </form>
  </div>
  <?php endif; ?>

  <div class="modal-overlay hidden shift-modal-overlay" id="shiftHistoryModal" data-shift-modal aria-hidden="true">
    <div class="modal modal-sm sa-modal" role="dialog" aria-modal="true" aria-labelledby="shiftHistoryModalTitle">
      <div class="modal-head"><div><div class="modal-title" id="shiftHistoryModalTitle">Assignment History</div><div class="modal-sub">Recorded changes for this schedule assignment</div></div><button class="modal-close" type="button" data-shift-close aria-label="Close assignment history"><i data-lucide="x"></i></button></div>
      <div class="modal-body" id="shiftHistoryContent"></div>
      <div class="modal-foot"><button class="btn btn-ghost" type="button" data-shift-close>Close</button></div>
    </div>
  </div>

  <?php if($canManageMonthlyTemplates): ?>
  <div class="modal-overlay hidden shift-modal-overlay" id="shiftMonthlyTemplateModal" data-shift-modal aria-hidden="true">
    <form class="modal sa-modal shift-monthly-modal" id="shiftMonthlyTemplateForm" data-shift-singleton-form novalidate role="dialog" aria-modal="true" aria-labelledby="shiftMonthlyTemplateModalTitle">
      <div class="modal-head"><div><div class="modal-title" id="shiftMonthlyTemplateModalTitle">Monthly Shift Template</div><div class="modal-sub">Generate a reusable one-month schedule pattern before applying it live</div></div><button class="modal-close" type="button" data-shift-close aria-label="Close monthly template modal"><i data-lucide="x"></i></button></div>
      <div class="modal-body" data-shift-canonical-body>
        <input type="hidden" name="id">
        <div class="shift-form-alert" id="shiftMonthlyTemplateFormAlert" role="alert" hidden></div>
        <div class="shift-modal-section-title">Definition</div>
        <div class="sa-modal-grid">
          <label class="form-group"><span class="form-label">Template Name *</span><input class="form-input" name="name" required placeholder="CS Monthly Refreshment - July 2026"></label>
          <label class="form-group"><span class="form-label">Target Month *</span><input class="form-input" name="target_month" type="month" required></label>
          <label class="form-group"><span class="form-label">Division *</span><select class="form-select" name="division_id" required><option value="">Select division</option></select></label>
          <label class="form-group"><span class="form-label">Base Shift Pattern *</span><select class="form-select" name="shift_template_id" required><option value="">Select shift pattern</option></select></label>
        </div>
        <label class="form-group"><span class="form-label">Agents Included *</span><select class="form-select shift-agent-multiselect" name="agent_ids" multiple required size="6"></select><small class="form-hint">Use Ctrl/Cmd or Shift to select multiple agents.</small></label>
        <div class="shift-modal-section-title">Rules</div>
        <fieldset class="shift-monthly-fieldset">
          <legend>Rest Day Pattern</legend>
          <div class="shift-day-options">
            <?php foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $index => $day): ?>
            <label><input type="checkbox" name="rest_days" value="<?=$index + 1?>" <?=$day === 'Sun' ? 'checked' : ''?>><?=$day?></label>
            <?php endforeach; ?>
          </div>
        </fieldset>
        <div class="sa-modal-grid">
          <label class="form-group"><span class="form-label">Weekend Handling</span><select class="form-select" name="weekend_handling"><option value="exclude">Exclude weekends</option><option value="regular">Use regular shift</option><option value="standby">Use standby shift</option></select></label>
          <label class="form-group"><span class="form-label">Save Status</span><select class="form-select" name="status"><option value="draft">Draft</option></select></label>
        </div>
        <div class="shift-monthly-options">
          <label class="shift-check-label"><input type="checkbox" name="repeat_weekly_pattern" checked>Repeat weekly pattern</label>
          <label class="shift-check-label"><input type="checkbox" name="rotate_agents_weekly">Rotate agent rest days weekly</label>
          <label class="shift-check-label"><input type="checkbox" name="exclude_public_holidays">Exclude public holidays</label>
          <label class="shift-check-label"><input type="checkbox" name="include_holiday_coverage">Include holiday coverage shift</label>
          <label class="shift-check-label"><input type="checkbox" name="include_lembur_template">Generate as lembur</label>
          <label class="shift-check-label"><input type="checkbox" name="prevent_workload_over_target">Prevent workload over target</label>
          <label class="shift-check-label"><input type="checkbox" name="warn_coverage_gap" checked>Warn if coverage gap exists</label>
        </div>
        <label class="form-group"><span class="form-label">Notes</span><textarea class="form-textarea" name="notes" placeholder="Refreshment cycle, coverage assumptions, or handover notes"></textarea></label>
      </div>
      <div class="modal-foot shift-monthly-modal-foot">
        <button class="btn btn-ghost" type="button" data-shift-close>Cancel</button>
        <button class="btn btn-ghost" type="button" id="shiftMonthlyPreviewDraft"><i data-lucide="eye" class="icon-xs"></i>Preview</button>
        <button class="btn btn-primary" type="submit"><i data-lucide="save" class="icon-sm"></i>Save as Draft</button>
      </div>
    </form>
  </div>

  <div class="modal-overlay hidden shift-modal-overlay" id="shiftMonthlyPreviewModal" data-shift-modal aria-hidden="true">
    <div class="modal sa-modal shift-monthly-preview-modal" role="dialog" aria-modal="true" aria-labelledby="shiftMonthlyPreviewModalTitle">
      <div class="modal-head"><div><div class="modal-title" id="shiftMonthlyPreviewModalTitle">Monthly Template Preview</div><div class="modal-sub">Generated schedule, workload checks, coverage warnings, and live conflicts</div></div><button class="modal-close" type="button" data-shift-close aria-label="Close monthly template preview"><i data-lucide="x"></i></button></div>
      <div class="modal-body" id="shiftMonthlyPreviewContent"></div>
      <div class="modal-foot" id="shiftMonthlyPreviewActions"><button class="btn btn-ghost" type="button" data-shift-close>Close</button></div>
    </div>
  </div>

  <div class="modal-overlay hidden shift-modal-overlay" id="shiftMonthlyDuplicateModal" data-shift-modal aria-hidden="true">
    <form class="modal modal-sm sa-modal" id="shiftMonthlyDuplicateForm" role="dialog" aria-modal="true" aria-labelledby="shiftMonthlyDuplicateModalTitle">
      <div class="modal-head"><div><div class="modal-title" id="shiftMonthlyDuplicateModalTitle">Duplicate Monthly Template</div><div class="modal-sub">Weekday structure is regenerated for the new target month</div></div><button class="modal-close" type="button" data-shift-close aria-label="Close duplicate monthly template"><i data-lucide="x"></i></button></div>
      <div class="modal-body">
        <input type="hidden" name="id">
        <label class="form-group"><span class="form-label">Template Name *</span><input class="form-input" name="name" required></label>
        <label class="form-group"><span class="form-label">Target Month *</span><input class="form-input" name="target_month" type="month" required></label>
      </div>
      <div class="modal-foot"><button class="btn btn-ghost" type="button" data-shift-close>Cancel</button><button class="btn btn-primary" type="submit">Duplicate Template</button></div>
    </form>
  </div>
  <?php endif; ?>

  <?php if($service->canManageSettings()): ?>
  <div class="modal-overlay hidden shift-modal-overlay" id="shiftTemplateModal" data-shift-modal aria-hidden="true"><form class="modal modal-sm sa-modal" id="shiftTemplateForm" role="dialog" aria-modal="true" aria-labelledby="shiftTemplateModalTitle">
    <div class="modal-head"><div><div class="modal-title" id="shiftTemplateModalTitle">Shift Template</div><div class="modal-sub">Reusable helper with manual override support</div></div><button class="modal-close" type="button" data-shift-close aria-label="Close shift template modal"><i data-lucide="x"></i></button></div>
    <div class="modal-body">
      <input type="hidden" name="id">
      <label class="form-group"><span class="form-label">Template Name *</span><input class="form-input" name="shift_name" required></label>
      <div class="form-row"><label class="form-group"><span class="form-label">Start</span><input class="form-input" name="start_time" type="time" required></label><label class="form-group"><span class="form-label">End</span><input class="form-input" name="end_time" type="time" required></label></div>
      <div class="form-row"><label class="form-group"><span class="form-label">Break Minutes</span><input class="form-input" name="default_break_minutes" type="number" min="0" value="0"></label><label class="form-group"><span class="form-label">Color</span><input class="form-input" name="color_label" type="color" value="#4f46e5"></label></div>
      <label class="form-group"><span class="form-label">Default Type</span><select class="form-select" name="default_assignment_type"></select></label>
      <label class="shift-check-label"><input type="checkbox" name="count_as_work_hour" checked>Count as work hour</label>
      <label class="shift-check-label"><input type="checkbox" name="is_active" checked>Active</label>
      <label class="form-group"><span class="form-label">Notes</span><textarea class="form-textarea" name="notes"></textarea></label>
    </div>
    <div class="modal-foot"><button class="btn btn-ghost" type="button" data-shift-close>Cancel</button><button class="btn btn-primary" type="submit">Save Template</button></div>
  </form></div>

  <div class="modal-overlay hidden shift-modal-overlay" id="shiftHolidayModal" data-shift-modal aria-hidden="true"><form class="modal modal-sm sa-modal" id="shiftHolidayForm" role="dialog" aria-modal="true" aria-labelledby="shiftHolidayModalTitle">
    <div class="modal-head"><div><div class="modal-title" id="shiftHolidayModalTitle">Public Holiday</div><div class="modal-sub">Database dates override fallback holiday data</div></div><button class="modal-close" type="button" data-shift-close aria-label="Close public holiday modal"><i data-lucide="x"></i></button></div>
    <div class="modal-body">
      <input type="hidden" name="id">
      <label class="form-group"><span class="form-label">Date *</span><input class="form-input" name="holiday_date" type="date" required></label>
      <label class="form-group"><span class="form-label">Holiday Name *</span><input class="form-input" name="holiday_name" required></label>
      <label class="form-group"><span class="form-label">Type</span><select class="form-select" name="holiday_type"><option value="national_holiday">National Holiday</option><option value="collective_leave">Collective Leave</option><option value="company_holiday">Company Holiday</option><option value="custom">Custom</option></select></label>
      <label class="shift-check-label"><input type="checkbox" name="is_active" checked>Active</label>
      <label class="form-group"><span class="form-label">Notes</span><textarea class="form-textarea" name="notes"></textarea></label>
    </div>
    <div class="modal-foot"><button class="btn btn-ghost" type="button" data-shift-close>Cancel</button><button class="btn btn-primary" type="submit">Save Holiday</button></div>
  </form></div>

  <div class="modal-overlay hidden shift-modal-overlay" id="shiftCoverageModal" data-shift-modal aria-hidden="true"><form class="modal modal-sm sa-modal" id="shiftCoverageForm" role="dialog" aria-modal="true" aria-labelledby="shiftCoverageModalTitle">
    <div class="modal-head"><div><div class="modal-title" id="shiftCoverageModalTitle">Coverage Rule</div><div class="modal-sub">Minimum staffing for a local Jakarta time window</div></div><button class="modal-close" type="button" data-shift-close aria-label="Close coverage rule modal"><i data-lucide="x"></i></button></div>
    <div class="modal-body">
      <input type="hidden" name="id">
      <label class="form-group"><span class="form-label">Division</span><select class="form-select" name="division_id"><option value="">All divisions</option></select></label>
      <label class="form-group"><span class="form-label">Day Type</span><select class="form-select" name="day_type"><option value="weekday">Weekday</option><option value="weekend">Weekend</option><option value="public_holiday">Public Holiday</option><option value="custom">Custom Date</option></select></label>
      <label class="form-group shift-custom-date" hidden><span class="form-label">Custom Date</span><input class="form-input" name="custom_date" type="date"></label>
      <div class="form-row"><label class="form-group"><span class="form-label">Start</span><input class="form-input" name="start_time" type="time" required></label><label class="form-group"><span class="form-label">End</span><input class="form-input" name="end_time" type="time" required></label></div>
      <label class="form-group"><span class="form-label">Minimum Agents</span><input class="form-input" name="minimum_agents" type="number" min="1" value="1" required></label>
      <label class="form-group"><span class="form-label">Role Required</span><input class="form-input" name="role_required" placeholder="Optional role slug"></label>
      <label class="shift-check-label"><input type="checkbox" name="is_active" checked>Active</label>
      <label class="form-group"><span class="form-label">Notes</span><textarea class="form-textarea" name="notes"></textarea></label>
    </div>
    <div class="modal-foot"><button class="btn btn-ghost" type="button" data-shift-close>Cancel</button><button class="btn btn-primary" type="submit">Save Rule</button></div>
  </form></div>
  <?php endif; ?>
</main>
<script>
window.TRACS_SHIFTING_INITIAL = <?=json_encode($initialData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)?>;
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
