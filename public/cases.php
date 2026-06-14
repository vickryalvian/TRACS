<?php
require_once __DIR__ . '/../core/security/csrf.php';
tracs_start_session();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth/auth_check.php';
require_once __DIR__ . '/../core/access_control.php';
tracs_require_page_permission($conn, 'cases.view');
require_once __DIR__ . '/api/case-attachment-lib.php';
require_once __DIR__ . '/../modules/case/controller.php';
require_once __DIR__ . '/../modules/alert-ticker/controller.php';
require_once __DIR__ . '/includes/page_helpers.php';

$uid = (int)($_SESSION['user_id'] ?? 0);
tracs_ensure_creator_columns($conn, 'tracs_cases', 'user_id');
tracs_ensure_case_status_values($conn);
case_attachment_ensure_table($conn);

$CC = new CaseController($conn, $uid);
$TC = new AlertTickerController($conn, $uid);
$ticker_items = $TC->formatAlertsForTicker();
$case_can_manage = tracs_user_can($conn, 'cases.manage');
$case_role = (string)($_SESSION['user_role_slug'] ?? '');
$case_can_delete = in_array($case_role, ['super_admin', 'admin'], true) || tracs_user_can($conn, 'cases.delete');

$all = array_map([$CC, 'formatCase'], $CC->getCases() ?: []);
$total = count($all);
$critical = count(array_filter($all, fn($c) => ($c['priority'] ?? '') === 'critical'));
$stuck = count(array_filter($all, fn($c) => ($c['status'] ?? '') === 'stuck'));
$active = count(array_filter($all, fn($c) => ($c['status'] ?? '') === 'active'));
$in_progress = count(array_filter($all, fn($c) => ($c['status'] ?? '') === 'in_progress'));
$on_hold = count(array_filter($all, fn($c) => ($c['status'] ?? '') === 'on_hold'));
$overdue_count = count(array_filter($all, fn($c) => str_starts_with((string)($c['time_until'] ?? ''), 'Overdue')));
$critical_count = $critical;

$allowed_filters = ['all', 'active', 'in_progress', 'critical', 'stuck', 'on_hold', 'overdue'];
$allowed_sorts = ['operational', 'priority', 'overdue', 'next_check', 'created', 'updated', 'case_number'];
$requested_filter = (string)($_GET['f'] ?? 'all');
$requested_sort = (string)($_GET['sort'] ?? 'operational');
$f = in_array($requested_filter, $allowed_filters, true) ? $requested_filter : 'all';
$q_raw = trim((string)($_GET['q'] ?? ''));
$case_sort = in_array($requested_sort, $allowed_sorts, true) ? $requested_sort : 'operational';

$board_columns = [
  'attention' => ['title' => 'Need Attention', 'icon' => 'circle-alert', 'status' => 'active'],
  'in_progress' => ['title' => 'In Progress', 'icon' => 'loader-circle', 'status' => 'in_progress'],
  'waiting' => ['title' => 'Waiting / Stuck', 'icon' => 'pause-circle', 'status' => 'stuck'],
  'on_hold' => ['title' => 'On Hold', 'icon' => 'archive', 'status' => 'on_hold'],
  'resolved' => ['title' => 'Resolved', 'icon' => 'circle-check', 'status' => 'completed'],
];

$filter_labels = [
  'all' => 'All',
  'active' => 'Active',
  'in_progress' => 'In Progress',
  'critical' => 'Critical',
  'stuck' => 'Stuck',
  'on_hold' => 'On Hold',
  'overdue' => 'Overdue',
];
$sort_labels = [
  'operational' => 'Operational Urgency',
  'priority' => 'Priority',
  'overdue' => 'Overdue',
  'next_check' => 'Next Check',
  'created' => 'Created Date',
  'updated' => 'Last Updated',
  'case_number' => 'Case Number',
];

$case_dataset = array_map(function(array $case): array {
  $status = strtolower((string)($case['status'] ?? 'pending'));
  $nextCheck = (string)($case['next_check_at'] ?? '');
  $nextTimestamp = $nextCheck !== '' ? strtotime($nextCheck) : false;
  $isOverdue = $status !== 'completed' && (
    str_starts_with((string)($case['time_until'] ?? ''), 'Overdue')
    || ($nextTimestamp !== false && $nextTimestamp < time())
  );

  return [
    'id' => (int)($case['id'] ?? 0),
    'title' => (string)($case['title'] ?? ''),
    'status' => $status,
    'priority' => strtolower((string)($case['priority'] ?? 'low')),
    'notes' => (string)($case['notes'] ?? ''),
    'description' => (string)($case['description'] ?? $case['notes'] ?? ''),
    'next_check_at' => $nextCheck,
    'next_check_local' => safe_dt_local($nextCheck),
    'next_check_display' => safe_dt($nextCheck, 'd M Y, H:i'),
    'time_until' => (string)($case['time_until'] ?? '—'),
    'created_at' => (string)($case['created_at'] ?? ''),
    'created_display' => safe_dt($case['created_at'] ?? null, 'd M Y'),
    'updated_at' => (string)($case['updated_at'] ?? ''),
    'creator_name' => (string)($case['creator_name'] ?? $case['created_by_name'] ?? 'System'),
    'created_by_name' => (string)($case['created_by_name'] ?? ''),
    'assigned_agent' => (string)($case['assigned_agent'] ?? $case['assigned_user'] ?? $case['pic'] ?? ''),
    'client' => (string)($case['client'] ?? $case['client_name'] ?? ''),
    'domain' => (string)($case['domain'] ?? $case['domain_name'] ?? ''),
    'service' => (string)($case['service'] ?? $case['service_name'] ?? ''),
    'attachment_count' => (int)($case['attachment_count'] ?? 0),
    'overdue' => $isOverdue,
  ];
}, $all);

$page_title = 'Cases';
$active_page = 'cases';
include 'includes/header.php';
?>
<main class="main case-main">
<div class="main-inner case-page" id="caseWorkspace"
  data-case-filter="<?=esc($f)?>"
  data-case-query="<?=esc($q_raw)?>"
  data-case-sort="<?=esc($case_sort)?>"
  data-total="<?=$total?>"
  data-critical="<?=$critical?>"
  data-stuck="<?=$stuck?>"
  data-in-progress="<?=$in_progress?>"
  data-active="<?=$active?>"
  data-on-hold="<?=$on_hold?>"
  data-overdue="<?=$overdue_count?>">

<div class="topbar case-page-head">
  <div>
    <div class="page-title">Cases</div>
    <div class="page-sub" id="casePageSummary"><?=$total?> total · <?=$critical?> critical · <?=$in_progress?> in progress · <?=$on_hold?> on hold</div>
  </div>
  <div class="case-queue-health"><span class="<?=$overdue_count > 0 ? 'is-alert' : ''?>"></span><strong id="caseQueueHealth"><?=$overdue_count?> overdue · <?=$stuck?> waiting</strong></div>
</div>

<div class="case-toolbar">
  <div class="filter-bar" aria-label="Case filters">
    <?php foreach ($filter_labels as $key => $label): ?>
    <button type="button" class="filter-tab <?=$f === $key ? 'active' : ''?>" data-case-filter-option="<?=$key?>" aria-pressed="<?=$f === $key ? 'true' : 'false'?>"><?=$label?></button>
    <?php endforeach; ?>
  </div>
  <div class="case-toolbar-actions">
    <form class="search-form-wrap case-search-form" id="caseSearchForm">
      <i data-lucide="search" class="search-ic icon-sm"></i>
      <input type="search" name="q" id="caseSearchInput" class="search-input" placeholder="Search case #, title, notes, creator, status, or service" value="<?=esc($q_raw)?>" aria-label="Search all cases" autocomplete="off">
    </form>
    <div class="case-sort-form">
      <label for="caseSort">Sort</label>
      <select class="form-select compact-select" id="caseSort" name="sort">
        <?php foreach ($sort_labels as $key => $label): ?>
        <option value="<?=$key?>" <?=$case_sort === $key ? 'selected' : ''?>><?=$label?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="case-view-toggle" role="group" aria-label="Case view">
      <button type="button" class="is-active" data-case-view="board" aria-pressed="true"><i data-lucide="columns-3" class="icon-sm"></i>Board</button>
      <button type="button" data-case-view="table" aria-pressed="false"><i data-lucide="list" class="icon-sm"></i>Table</button>
    </div>
    <?php if ($case_can_manage): ?>
    <button class="btn btn-primary toolbar-add-btn" type="button" onclick="openNewCase()"><i data-lucide="plus-circle" class="icon-sm"></i>Add New Case</button>
    <?php endif; ?>
  </div>
  <div class="case-active-state" id="caseActiveState" hidden>
    <span id="caseActiveStateText"></span>
    <button type="button" class="case-clear-filters" id="caseClearFilters">Clear filters</button>
  </div>
</div>

<section class="case-board-view" data-case-view-panel="board" aria-label="Case workflow board">
  <div class="case-board-head">
    <div class="case-board-title-block">
      <div class="case-board-title-line"><strong>Workflow Board</strong><span id="caseBoardCount"><?=$total?> cases shown</span></div>
    </div>
    <details class="report-export-menu">
      <summary class="btn btn-ghost btn-icon report-export-trigger" title="Export cases" aria-label="Export cases"><i data-lucide="download" class="icon-sm"></i></summary>
      <form method="get" action="/api/export-cases.php" class="report-export-popover">
        <input type="hidden" name="f" id="caseExportFilter" value="<?=esc($f)?>">
        <input type="hidden" name="q" id="caseExportQuery" value="<?=esc($q_raw)?>">
        <div class="report-export-title"><i data-lucide="download" class="icon-xs"></i>Export CSV</div>
        <?=tracs_date_range_picker([
            'id' => 'caseExportRange',
            'start_name' => 'from',
            'end_name' => 'to',
            'label' => 'Export date range',
        ])?>
        <button type="submit" class="btn btn-primary"><i data-lucide="download" class="icon-sm"></i>Download CSV</button>
      </form>
    </details>
  </div>
  <div class="case-kanban">
    <?php foreach ($board_columns as $column_key => $column): ?>
    <section class="case-kanban-column column-<?=$column_key?>" data-case-column="<?=$column_key?>" data-target-status="<?=$column['status']?>">
      <header class="case-column-head">
        <div class="case-column-heading">
          <span class="case-column-icon"><i data-lucide="<?=$column['icon']?>" class="icon-sm"></i></span>
          <div><strong><?=$column['title']?></strong><small data-column-summary>0 cases</small></div>
        </div>
        <div class="case-column-tools">
          <span class="case-column-count" data-column-count>0</span>
          <?php if ($case_can_manage): ?>
          <button type="button" class="case-column-add" onclick="openNewCase('<?=$column['status']?>')" title="Add case to <?=$column['title']?>" aria-label="Add case to <?=$column['title']?>"><i data-lucide="plus" class="icon-sm"></i></button>
          <?php endif; ?>
        </div>
      </header>
      <div class="case-column-list" data-case-dropzone>
        <div class="case-column-empty" hidden data-column-empty>
          <i data-lucide="inbox" class="icon-sm"></i><span data-column-empty-label>No cases in this stage</span>
        </div>
      </div>
    </section>
    <?php endforeach; ?>
  </div>
</section>

<section class="panel case-table-view" data-case-view-panel="table" hidden>
  <div class="panel-head">
    <span class="panel-title">Case List</span>
    <div class="panel-right"><span class="panel-meta" id="caseTableCount"><?=$total?> shown</span></div>
  </div>
  <div class="table-wrap">
    <table class="tracs-table">
      <thead><tr><th>#</th><th>Title</th><th>Status</th><th>Priority</th><th>Next Check</th><th>Time Until</th><th>Actions</th></tr></thead>
      <tbody id="caseTableBody"></tbody>
    </table>
  </div>
  <div class="empty case-table-empty" id="caseTableEmpty" hidden>
    <div class="empty-ic"><i data-lucide="briefcase"></i></div>
    <div class="empty-t">No cases match your search/filter</div>
    <div class="empty-s">Clear filters to return to the complete case list.</div>
    <button type="button" class="btn btn-ghost" data-case-clear-filters>Clear filters</button>
  </div>
</section>

<script type="application/json" id="caseDataset"><?=json_encode(
  $case_dataset,
  JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
)?></script>

</div>
</main>
<?php include 'includes/footer.php'; ?>
