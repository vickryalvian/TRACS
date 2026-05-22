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
  : ((($filters['status'] ?? '') === 'active') ? 'active' : ((($filters['status'] ?? '') === 'resolved') ? 'resolved' : 'all'));

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
    <div class="table-wrap">
      <table class="tracs-table shift-report-table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Submitter</th>
            <th>Shift</th>
            <th>Title</th>
            <th>Priority</th>
            <th>Status</th>
            
          </tr>
        </thead>
        <tbody>
        <?php foreach($history as $r):
          $id=intval($r['id']??0);
          $title=esc($r['title']??'');
          $shift=esc($r['shift_name']??'');
          $status=$r['status']??'active';
          $prio=strtolower($r['priority']??'medium');
          $details=esc($r['details']??'');
          $submitter=tracs_creator_label($r);
          
          $pb=prio_badge($prio);
          $sb=($status==='resolved')?'b-done':'b-active';
          $dt_disp=safe_dt($r['active_date']??null, 'd M Y');
        ?>
        <tr class="shift-tr" data-id="<?=$id?>" data-title="<?=$title?>" data-shift="<?=$shift?>" data-prio="<?=$prio?>" data-status="<?=$status?>" data-details="<?=$details?>" data-date="<?=$r['active_date']??''?>">
          <td style="white-space:nowrap"><?=$dt_disp?></td>
          <td>
            <div class="user-cell">
              <div class="avatar"><?=esc(strtoupper(substr($submitter, 0, 1) . substr(explode(' ', $submitter)[1] ?? '', 0, 1)))?></div>
              <div class="user-info">
                <div class="user-name"><?=esc($submitter)?></div>
              </div>
            </div>
          </td>
          <td><span class="badge b-info"><?=$shift?></span></td>
          <td style="max-width:300px">
            <div class="search-text" style="font-weight:500;color:var(--tx1);<?=$status==='resolved'?'text-decoration:line-through;color:var(--tx3)':''?>"><?=$title?></div>
            <?php if($details):?><div style="font-size:10px;color:var(--tx3);white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="<?=$details?>"><?=$details?></div><?php endif;?>
            <?=tracs_creator_meta($r)?>
          </td>
          <td><span class="badge <?=$pb?>"><?=ucfirst($prio)?></span></td>
          <td class="shift-actions-cell">
            <span class="badge <?=$sb?>"><?=ucfirst($status)?></span>
            <div class="shift-row-controls">
              <?php if($status==='active'):?>
              <button class="btn btn-ghost btn-icon shift-resolve-btn" onclick="resolveShiftReport(<?=$id?>)" title="Resolve"><i data-lucide="check-circle" class="icon-sm"></i></button>
              <?php endif;?>
              <details class="row-action-menu">
                <summary class="btn btn-ghost btn-icon" title="Actions" aria-label="Row actions"><i data-lucide="more-vertical" class="icon-sm"></i></summary>
                <div class="row-action-popover">
                  <button class="btn btn-ghost btn-sm" type="button" onclick="openEditShiftReport(<?=$id?>)">Edit</button>
                  <button class="btn btn-danger btn-sm" type="button" onclick="deleteShiftReport(<?=$id?>)">Delete</button>
                </div>
              </details>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
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
