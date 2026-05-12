<?php
/**
 * Shift Reports History Page
 */
if(session_status()===PHP_SESSION_NONE)session_start();
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/auth/auth_check.php';
require_once __DIR__.'/../modules/shift-reports/controller.php';
require_once __DIR__.'/includes/page_helpers.php';

$uid = (int)($_SESSION['user_id']??0);
$SC = new ShiftReportController($conn, $uid);

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

  <!-- STAT STRIP -->
  <div class="stat-strip">
    <div class="stat-card blue">
      <div class="stat-glow"></div>
      <div class="stat-num"><?=$stats['total']?></div>
      <div class="stat-label">Reports Today</div>
    </div>
    <div class="stat-card amber">
      <div class="stat-glow"></div>
      <div class="stat-num"><?=$stats['active']?></div>
      <div class="stat-label">Active Handover</div>
    </div>
    <div class="stat-card green">
      <div class="stat-glow"></div>
      <div class="stat-num"><?=$stats['resolved']?></div>
      <div class="stat-label">Resolved Today</div>
    </div>
    <div class="stat-card red">
      <div class="stat-glow"></div>
      <div class="stat-num"><?=$stats['critical']?></div>
      <div class="stat-label">Critical Active</div>
    </div>
  </div>

  <!-- ── Filter & Search Rows (Domains Hierarchy) ──────────────── -->
  <div class="filter-search-row">
    <div class="filter-bar">
      <?php foreach(['all'=>'All','active'=>'Active','resolved'=>'Resolved'] as $k=>$l): ?>
      <a href="?status=<?=$k==='all'?'':$k?>&date=<?=urlencode($filters['date'])?>&shift=<?=urlencode($filters['shift'])?>&priority=<?=urlencode($filters['priority'])?>" 
         class="filter-tab <?=($filters['status']??'')===( $k==='all'?'':$k ) ? 'active' : ''?>">
         <?=$l?>
      </a>
      <?php endforeach;?>
    </div>
  </div>

  <div class="filter-search-row filter-search-row-mt">
    <!-- Filter Group -->
    <form method="get" class="filter-group-wrap" style="display:flex;gap:10px;align-items:center">
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
      <i data-lucide="search" class="search-ic icon-sm"></i>
      <input type="text" id="liveSearchInput" class="search-input" placeholder="Search reports..." oninput="liveSearch(this,'.shift-tr','.search-text')">
    </form>

    <!-- Action -->
    <button class="btn btn-primary" onclick="openNewShiftReport()" style="flex-shrink:0; height:32px; font-size:11.5px">
      <i data-lucide="plus-circle" class="icon-sm"></i>
      New Report
    </button>
  </div>

  <!-- MAIN PANEL -->
  <div class="panel">
    <div class="panel-head">
      <span class="panel-title">Report History</span>
      <span class="panel-meta"><?=count($history)?> shown</span>
    </div>

    <?php if(empty($history)):?>
    <div class="empty"><div class="empty-ic"><i data-lucide="clipboard-list"></i></div><div class="empty-t">No reports found</div><div class="empty-s">Try a different filter or create a new report</div></div>
    <?php else: ?>
    <div class="table-wrap">
      <table class="tracs-table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Shift</th>
            <th>Title</th>
            <th>Priority</th>
            <th>Status</th>
            <th style="width:80px"></th>
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
          
          $pb=prio_badge($prio);
          $sb=($status==='resolved')?'b-done':'b-active';
          $dt_disp=safe_dt($r['active_date']??null, 'd M Y');
        ?>
        <tr class="shift-tr" data-id="<?=$id?>" data-title="<?=$title?>" data-shift="<?=$shift?>" data-prio="<?=$prio?>" data-status="<?=$status?>" data-details="<?=$details?>" data-date="<?=$r['active_date']??''?>">
          <td style="white-space:nowrap"><?=$dt_disp?></td>
          <td><span class="badge b-cyan"><?=$shift?></span></td>
          <td style="max-width:300px">
            <div class="search-text" style="font-weight:500;color:var(--tx1);<?=$status==='resolved'?'text-decoration:line-through;color:var(--tx3)':''?>"><?=$title?></div>
            <?php if($details):?><div style="font-size:10px;color:var(--tx3);white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="<?=$details?>"><?=$details?></div><?php endif;?>
          </td>
          <td><span class="badge <?=$pb?>"><?=ucfirst($prio)?></span></td>
          <td><span class="badge <?=$sb?>"><?=ucfirst($status)?></span></td>
          <td class="tracs-acts">
            <?php if($status==='active'):?>
            <button class="btn btn-ghost btn-icon" onclick="resolveShiftReport(<?=$id?>)" title="Resolve"><i data-lucide="check-circle" class="icon-sm"></i></button>
            <?php endif;?>
            <button class="btn btn-ghost btn-icon" onclick="openEditShiftReport(<?=$id?>)" title="Edit"><i data-lucide="edit-2" class="icon-sm"></i></button>
            <button class="btn btn-danger btn-icon" onclick="deleteShiftReport(<?=$id?>)" title="Delete"><i data-lucide="trash-2" class="icon-sm"></i></button>
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
