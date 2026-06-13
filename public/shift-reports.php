<?php
/**
 * Shift Reports History Page
 */
require_once __DIR__ . '/../core/security/csrf.php';
tracs_start_session();
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/auth/auth_check.php';
require_once __DIR__.'/../core/access_control.php';
tracs_require_page_permission($conn, 'reports.view');
require_once __DIR__.'/../modules/shift-reports/controller.php';
require_once __DIR__.'/../modules/alert-ticker/controller.php';
require_once __DIR__.'/api/shift-attachment-lib.php';
require_once __DIR__.'/includes/page_helpers.php';

$uid = (int)($_SESSION['user_id']??0);
$user_email = $_SESSION['user_email']??'operator@tracs.local';
tracs_ensure_creator_columns($conn, 'tracs_shift_reports', 'created_by');
$SC = new ShiftReportController($conn, $uid);
$TC = new AlertTickerController($conn, $uid);
$ticker_items = $TC->formatAlertsForTicker();

$filters = [
    'date' => $_GET['date'] ?? null,
    'shift' => $_GET['shift'] ?? null,
    'status' => $_GET['status'] ?? null,
    'priority' => $_GET['priority'] ?? null,
];
$page = (int)($_GET['page'] ?? 1);
$limit = 50;
$offset = ($page - 1) * $limit;

$history = $SC->getHistory($filters, $limit, $offset);
shift_attachment_ensure_table($conn);
foreach ($history as &$report_for_attachments) {
    $report_for_attachments['attachments'] = shift_attachment_list_for_report($conn, (int)($report_for_attachments['id'] ?? 0));
}
unset($report_for_attachments);
$history_groups = [];
foreach ($history as $report) {
    $group_key = (string)($report['active_date'] ?? '') . '|' . (string)($report['shift_name'] ?? '');
    if (!isset($history_groups[$group_key])) {
        $history_groups[$group_key] = [
            'date' => (string)($report['active_date'] ?? ''),
            'shift' => (string)($report['shift_name'] ?? ''),
            'reports' => [],
            'agents' => [],
            'active' => 0,
            'on_hold' => 0,
            'resolved' => 0,
            'critical' => 0,
        ];
    }
    $agent = tracs_creator_label($report);
    $history_groups[$group_key]['agents'][$agent] = true;
    $history_groups[$group_key]['reports'][] = $report;
    if (($report['status'] ?? '') === 'resolved') $history_groups[$group_key]['resolved']++;
    elseif (($report['status'] ?? '') === 'on_hold') $history_groups[$group_key]['on_hold']++;
    else $history_groups[$group_key]['active']++;
    if (($report['status'] ?? '') === 'active' && ($report['priority'] ?? '') === 'critical') $history_groups[$group_key]['critical']++;
}
$stats = $SC->getTodayStats();
$critical_count = (int)($stats['critical'] ?? 0);
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$month_key = date('Y-m');
$today_reports = $SC->getHistory(['date' => $today], 200, 0);
$yesterday_reports = $SC->getHistory(['date' => $yesterday], 200, 0);
$recent_reports = $SC->getHistory([], 500, 0);
$month_reports = array_values(array_filter($recent_reports, fn($r) => str_starts_with((string)($r['active_date'] ?? ''), $month_key)));
$intel = $SC->buildOperationalIntelligence($today_reports, $yesterday_reports, $month_reports, $recent_reports);
$handover_shift = $filters['shift'] ?: $SC->detectCurrentShift();
$handover_date = $filters['date'] ?: date('Y-m-d');
$handover = $SC->getCurrentHandover($handover_shift, $handover_date);
$handover_manual_reports = $SC->getHistory(['date' => $handover_date, 'shift' => $handover_shift], 200, 0);
$handover_needs = array_values(array_filter($handover_manual_reports, fn($r) => ($r['status'] ?? '') === 'active'));
$handover_hold = array_values(array_filter($handover_manual_reports, fn($r) => ($r['status'] ?? '') === 'on_hold'));
$handover_resolved = array_values(array_filter($handover_manual_reports, fn($r) => ($r['status'] ?? '') === 'resolved'));

$tab_base = [
  'date' => $filters['date'],
  'shift' => $filters['shift'],
];
$filter_tabs = [
  [
    'key' => 'all',
    'label' => 'All',
    'count' => null,
    'params' => $tab_base
  ],
  [
    'key' => 'active',
    'label' => 'Active',
    'count' => $stats['active'],
    'params' => $tab_base + ['status' => 'active']
  ],
  [
    'key' => 'on_hold',
    'label' => 'On Hold',
    'count' => $stats['on_hold'] ?? 0,
    'params' => $tab_base + ['status' => 'on_hold']
  ],
  [
    'key' => 'resolved',
    'label' => 'Resolved',
    'count' => $stats['resolved'],
    'params' => $tab_base + ['status' => 'resolved']
  ],
  [
    'key' => 'critical',
    'label' => 'Critical',
    'count' => $stats['critical'],
    'params' => $tab_base + ['status' => 'active', 'priority' => 'critical']
  ],
];
$active_tab = (($filters['status'] ?? '') === 'active' && ($filters['priority'] ?? '') === 'critical')
  ? 'critical'
  : ((($filters['status'] ?? '') === 'active') ? 'active' : ((($filters['status'] ?? '') === 'on_hold') ? 'on_hold' : ((($filters['status'] ?? '') === 'resolved') ? 'resolved' : 'all')));

$page_title='Shift Reports'; $active_page='shift-reports';
include 'includes/header.php';
?>
<main class="main">
<div class="main-inner">

  <!-- TOPBAR -->
  <div class="topbar">
    <div class="topbar-left">
      <div class="page-title">Shift Handover Reports</div>
      <div class="page-sub">Operational transition logs and active incidents</div>
    </div>
    <!-- Button moved to filter row for streamlined UX -->
  </div>

  <?php $handover_focus = array_merge($handover['updates'], $handover['attention']); ?>

  <!-- OPERATIONAL INTELLIGENCE + SHIFT SUMMARY -->
  <div class="shift-intel-panel">
    <div class="shift-intel-main">
      <div class="shift-intel-head">
        <div>
          <div class="shift-intel-kicker">
            <i data-lucide="brain-circuit" class="icon-sm"></i>
            AI Operational Summary
          </div>
          <div class="shift-intel-title">Rule-based summary from latest handover data</div>
        </div>
        <?php if(($intel['critical_active'] ?? 0) > 0): ?>
        <span class="shift-intel-badge">
          <i data-lucide="alert-triangle" class="icon-xs"></i>
          <?=$intel['critical_active']?> critical active
        </span>
        <?php endif; ?>
      </div>
      <p class="shift-intel-summary"><?=tracs_highlight_summary($intel['summary'], $intel['summary_highlight'] ?? '')?></p>
      <div class="shift-intel-lists">
        <div class="shift-intel-list">
          <div class="shift-intel-list-title">
            <i data-lucide="sparkles" class="icon-xs"></i>
            Highlighted Insights
          </div>
          <ul>
            <?php foreach($intel['key_insights'] as $insight): ?>
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
            <?php foreach($intel['followups'] as $followup): ?>
            <li><?=esc($followup)?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    </div>
    <aside class="shift-summary-side">
      <div class="shift-intel-kicker">
        <i data-lucide="clipboard-check" class="icon-sm"></i>
        Shift Summary
      </div>
      <div class="shift-summary-side-meta"><?=esc($handover['shift_name'])?> · <?=esc(safe_dt($handover['date'], 'd M Y'))?></div>
      <div class="shift-summary-text"><?=esc($handover['summary'])?></div>
      <div class="shift-window-note">
        <?=esc(safe_dt($handover['window']['start'], 'H:i'))?> - <?=esc(safe_dt($handover['window']['end'], 'H:i'))?>
      </div>
    </aside>
  </div>

  <!-- SHIFT SUMMARY ITEMS -->
  <div class="shift-summary-status-grid">
    <?php
      $summaryGroups = [
        ['title' => 'Needs Handover', 'icon' => 'radio-tower', 'items' => $handover_needs, 'badge' => 'b-active', 'empty' => 'No active handover items for this shift.'],
        ['title' => 'On Hold / Monitoring', 'icon' => 'pause-circle', 'items' => $handover_hold, 'badge' => 'b-hold', 'empty' => 'No monitoring items on hold.'],
        ['title' => 'Resolved This Shift', 'icon' => 'check-check', 'items' => $handover_resolved, 'badge' => 'b-resolved', 'empty' => 'No resolved informational items recorded.'],
      ];
    ?>
    <?php foreach($summaryGroups as $summaryGroup): ?>
    <section class="panel shift-summary-status-card <?= $summaryGroup['title'] === 'Resolved This Shift' ? 'is-muted' : '' ?>">
      <div class="panel-head">
        <span class="panel-title">
          <i data-lucide="<?=esc($summaryGroup['icon'])?>" class="icon-sm"></i>
          <?=esc($summaryGroup['title'])?>
        </span>
        <span class="panel-meta"><?=count($summaryGroup['items'])?></span>
      </div>
      <?php if(empty($summaryGroup['items'])): ?>
        <div class="shift-empty-line"><?=esc($summaryGroup['empty'])?></div>
      <?php else: ?>
        <div class="shift-summary-status-list">
          <?php foreach($summaryGroup['items'] as $item): ?>
          <div class="shift-summary-status-row">
            <span class="badge <?=$summaryGroup['badge']?>"><?=esc(ucwords(str_replace('_', ' ', $item['status'] ?? 'active')))?></span>
            <div>
              <div class="shift-activity-title"><?=esc($item['title'] ?? 'Untitled')?></div>
              <?php
                $resolution = trim((string)($item['resolution_note'] ?? ''));
                $resolvedAt = !empty($item['resolved_at']) ? 'resolved at '.safe_dt($item['resolved_at'], 'H:i') : '';
                $detailLine = trim($resolution . ($resolution && $resolvedAt ? ' - ' : '') . $resolvedAt);
              ?>
              <?php if($detailLine !== ''): ?><div class="shift-activity-desc"><?=esc($detailLine)?></div><?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
    <?php endforeach; ?>
  </div>

  <!-- CURRENT SHIFT HANDOVER -->
  <div class="shift-handover-grid">
    <section class="panel shift-handover-card">
      <div class="panel-head">
        <span class="panel-title">
          <i data-lucide="check-check" class="icon-sm"></i>
          Completed During This Shift
        </span>
        <span class="panel-meta"><?=count($handover['completed'])?></span>
      </div>
      <?php if(empty($handover['completed'])): ?>
        <div class="shift-empty-line">No completed checklist or reminder items recorded in this shift window.</div>
      <?php else: ?>
        <div class="shift-activity-list">
          <?php foreach($handover['completed'] as $item): ?>
          <div class="shift-activity-row">
            <span class="badge b-done"><?=esc($item['activity_type'])?></span>
            <div>
              <div class="shift-activity-title"><?=esc($item['title'])?></div>
              <?php if(!empty($item['description'])): ?><div class="shift-activity-desc"><?=esc($item['description'])?></div><?php endif; ?>
              <?=tracs_creator_meta($item, $item['created_at'] ?? null, false)?>
            </div>
            <time><?=esc(safe_dt($item['created_at'], 'H:i'))?></time>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="panel shift-handover-card shift-focus-card">
      <div class="panel-head">
        <span class="panel-title">
          <i data-lucide="radio-tower" class="icon-sm"></i>
          Updates & Next Shift Attention
        </span>
        <span class="panel-meta"><?=count($handover_focus)?></span>
      </div>
      <?php if(empty($handover_focus)): ?>
        <div class="shift-empty-line">No important updates or pending handover items detected.</div>
      <?php else: ?>
        <div class="shift-activity-list">
          <?php foreach($handover_focus as $item):
            $status = $item['status'] ?? '';
            $badge = $status === 'critical' ? 'b-critical' : (($status === 'attention') ? 'b-high' : (($status === 'pending') ? 'b-active' : 'b-info'));
          ?>
          <div class="shift-activity-row">
            <span class="badge <?=$badge?>"><?=esc($item['activity_type'])?></span>
            <div>
              <div class="shift-activity-title"><?=esc($item['title'])?></div>
              <?php if(!empty($item['description'])): ?><div class="shift-activity-desc"><?=esc($item['description'])?></div><?php endif; ?>
              <?=tracs_creator_meta($item, $item['created_at'] ?? null, false)?>
            </div>
            <time><?=esc(safe_dt($item['created_at'], 'H:i'))?></time>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </div>

  <!-- ── Filter & Search Rows (Domains Hierarchy) ──────────────── -->
  <div class="shift-toolbar-panel">
    <div class="filter-search-row">
      <div class="filter-bar">
        <?php foreach($filter_tabs as $tab): ?>
        <a href="?<?=http_build_query(array_filter($tab['params'], fn($v) => $v !== null && $v !== ''))?>"
           class="filter-tab <?= $active_tab === $tab['key'] ? 'active' : '' ?>">
          <span><?=$tab['label']?></span>
          <?php if($tab['count'] !== null): ?>
          <span class="filter-tab-count"><?=$tab['count']?></span>
          <?php endif; ?>
        </a>
        <?php endforeach;?>
      </div>

      <!-- Filter Group -->
      <form method="get" class="filter-group-wrap">
        <input type="hidden" name="status" value="<?=esc($filters['status'])?>">

        <div class="month-select-wrap">
          <label>Date</label>
          <input type="date" name="date" class="form-input compact-select" value="<?=esc($filters['date'])?>" onchange="this.form.submit()" style="width:130px">
        </div>

        <div class="month-select-wrap">
          <label>Shift</label>
          <select name="shift" class="form-select compact-select" onchange="this.form.submit()">
            <option value="">All Shifts</option>
            <option value="Shift 1" <?=$filters['shift']==='Shift 1'?'selected':''?>>Shift 1</option>
            <option value="Shift 2" <?=$filters['shift']==='Shift 2'?'selected':''?>>Shift 2</option>
            <option value="Shift 3" <?=$filters['shift']==='Shift 3'?'selected':''?>>Shift 3</option>
          </select>
        </div>

        <div class="month-select-wrap">
          <label>Priority</label>
          <select name="priority" class="form-select compact-select" onchange="this.form.submit()">
            <option value="">All</option>
            <option value="critical" <?=$filters['priority']==='critical'?'selected':''?>>Critical</option>
            <option value="high" <?=$filters['priority']==='high'?'selected':''?>>High</option>
            <option value="medium" <?=$filters['priority']==='medium'?'selected':''?>>Medium</option>
            <option value="low" <?=$filters['priority']==='low'?'selected':''?>>Low</option>
          </select>
        </div>

        <?php if(array_filter($filters)):?><a href="shift-reports.php" class="btn btn-ghost btn-reset btn-sm">Reset</a><?php endif;?>
      </form>

      <!-- Search -->
      <form method="get" class="search-form-wrap">
        <input type="hidden" name="date" value="<?=esc($filters['date'])?>">
        <input type="hidden" name="shift" value="<?=esc($filters['shift'])?>">
        <input type="hidden" name="status" value="<?=esc($filters['status'])?>">
        <input type="hidden" name="priority" value="<?=esc($filters['priority'])?>">
        <i data-lucide="search" class="search-ic icon-sm"></i>
        <input type="text" id="liveSearchInput" name="q" class="search-input" placeholder="Search shift title, handover details, status, or priority" oninput="liveSearch(this,'.shift-tr','.search-text');document.getElementById('shiftExportQ').value=this.value">
      </form>

      <!-- Action -->
      <button class="btn btn-primary toolbar-add-btn" onclick="openNewShiftReport()">
        <i data-lucide="plus-circle" class="icon-sm"></i>
        New Report
      </button>
    </div>
  </div>

  <!-- MAIN PANEL -->
  <div class="panel">
    <div class="panel-head">
      <span class="panel-title">Report History</span>
      <div class="panel-right">
        <span class="panel-meta"><?=count($history)?> shown</span>
        <details class="report-export-menu">
          <summary class="btn btn-ghost btn-icon report-export-trigger" title="More actions" aria-label="More actions" data-tooltip="More actions"><i data-lucide="more-vertical" class="icon-sm"></i></summary>
          <form method="get" action="/api/export-shift-reports.php" class="report-export-popover">
            <input type="hidden" name="date" value="<?=esc($filters['date'])?>">
            <input type="hidden" name="shift" value="<?=esc($filters['shift'])?>">
            <input type="hidden" name="status" value="<?=esc($filters['status'])?>">
            <input type="hidden" name="priority" value="<?=esc($filters['priority'])?>">
            <input type="hidden" name="q" id="shiftExportQ" value="">
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

    <?php if(empty($history)):?>
    <div class="empty"><div class="empty-ic"><i data-lucide="clipboard-list"></i></div><div class="empty-t">No reports found</div><div class="empty-s">Try a different filter or create a new report</div></div>
    <?php else: ?>
    <div class="shift-group-list">
      <?php foreach($history_groups as $group):
        $agents = array_keys($group['agents']);
        $group_status = $group['critical'] > 0 ? 'critical' : ($group['active'] > 0 ? 'active' : (($group['on_hold'] ?? 0) > 0 ? 'on_hold' : 'resolved'));
        $group_badge = $group_status === 'critical' ? 'b-critical' : ($group_status === 'active' ? 'b-active' : ($group_status === 'on_hold' ? 'b-hold' : 'b-resolved'));
      ?>
      <section class="shift-report-group is-<?=esc($group_status)?>">
        <div class="shift-report-group-head">
          <div>
            <div class="shift-report-group-title"><?=esc($group['shift'])?> · <?=esc(safe_dt($group['date'], 'd M Y'))?></div>
            <div class="shift-report-group-agents"><?=esc(implode(', ', $agents))?></div>
          </div>
          <div class="shift-report-group-meta">
            <span class="badge <?=$group_badge?>"><?=esc($group['active'])?> active</span>
            <span class="badge b-hold"><?=esc($group['on_hold'] ?? 0)?> on hold</span>
            <span class="badge b-resolved"><?=esc($group['resolved'])?> resolved</span>
          </div>
        </div>
        <div class="shift-report-items">
          <?php foreach($group['reports'] as $r):
            $id=intval($r['id']??0);
            $title=esc($r['title']??'');
            $shift=esc($r['shift_name']??'');
            $status=$r['status']??'active';
            $prio=strtolower($r['priority']??'medium');
            $details=esc($r['details']??'');
            $submitter=tracs_creator_label($r);
            $pb=prio_badge($prio);
            $sb=($status==='resolved')?'b-resolved':(($status==='on_hold')?'b-hold':'b-active');
            $statusLabel = $status === 'active' ? 'Need Handover' : ucwords(str_replace('_', ' ', $status));
          ?>
          <article class="shift-report-item shift-tr" data-id="<?=$id?>" data-title="<?=$title?>" data-shift="<?=$shift?>" data-prio="<?=$prio?>" data-status="<?=$status?>" data-details="<?=$details?>" data-date="<?=$r['active_date']??''?>" data-resolution-note="<?=esc($r['resolution_note']??'')?>" data-resolved-at="<?=esc($r['resolved_at']??'')?>">
            <div class="shift-report-item-main">
              <div class="shift-report-item-title search-text"><?=$title?></div>
              <?php if($details):?><p title="<?=$details?>"><?=$details?></p><?php endif;?>
              <?php if($status === 'resolved' && (!empty($r['resolution_note']) || !empty($r['resolved_at']))): ?>
              <p class="shift-resolution-line"><?=esc(trim((string)($r['resolution_note'] ?? '') . (!empty($r['resolved_at']) ? ' - resolved at ' . safe_dt($r['resolved_at'], 'H:i') : '')))?></p>
              <?php endif; ?>
              <div class="shift-report-item-agent"><?=tracs_creator_meta($r)?></div>
              <?php if(!empty($r['attachments'])): ?>
              <div class="shift-photo-grid">
                <?php foreach($r['attachments'] as $attachment): ?>
                <a href="<?=esc($attachment['image_url'])?>" target="_blank" rel="noopener noreferrer" class="shift-photo-thumb" title="<?=esc($attachment['original_filename'])?>">
                  <img src="<?=esc($attachment['thumbnail_url'])?>" alt="<?=esc($attachment['original_filename'])?>" loading="lazy">
                </a>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
            </div>
            <div class="shift-report-item-status">
              <span class="badge <?=$pb?>"><?=ucfirst($prio)?></span>
              <span class="badge <?=$sb?>"><?=esc($statusLabel)?></span>
              <span class="shift-report-submit"><?=esc($submitter)?></span>
            </div>
            <div class="shift-row-controls">
              <?php if($status!=='resolved'):?>
              <button class="btn btn-ghost btn-icon shift-resolve-btn" onclick="resolveShiftReport(<?=$id?>,this)" title="Resolve"><i data-lucide="check-circle" class="icon-sm"></i></button>
              <?php endif;?>
              <details class="row-action-menu">
                <summary class="btn btn-ghost btn-icon" title="Actions" aria-label="Row actions"><i data-lucide="more-vertical" class="icon-sm"></i></summary>
                <div class="row-action-popover">
                  <button class="btn btn-ghost btn-sm" type="button" onclick="openEditShiftReport(<?=$id?>)">Edit</button>
                  <button class="btn btn-danger btn-sm" type="button" onclick="deleteShiftReport(<?=$id?>,this)">Delete</button>
                </div>
              </details>
            </div>
          </article>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endforeach; ?>
    </div>
    <?php if(count($history) === $limit): ?>
    <div class="tracs-pagination">
      <div class="tp-info">Page <?=$page?></div>
      <div class="tp-btns">
        <?php if($page>1): ?>
        <a href="?page=<?=$page-1?>&<?=http_build_query(array_filter($filters))?>" class="btn btn-ghost btn-sm">‹ Prev</a>
        <?php endif; ?>
        <a href="?page=<?=$page+1?>&<?=http_build_query(array_filter($filters))?>" class="btn btn-ghost btn-sm">Next ›</a>
      </div>
    </div>
    <?php endif; ?>
    <?php endif;?>

  </div>
</div></main>
<?php include 'includes/footer.php';?>
