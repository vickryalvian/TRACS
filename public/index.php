<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if(session_status()===PHP_SESSION_NONE)session_start();

require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/auth/auth_check.php';

require_once __DIR__.'/../modules/case/controller.php';
require_once __DIR__.'/../modules/reminder/controller.php';
require_once __DIR__.'/../modules/checklist/controller.php';
require_once __DIR__.'/../modules/alert-ticker/controller.php';
require_once __DIR__.'/../modules/activity-log/controller.php';
require_once __DIR__.'/../modules/ops-status/controller.php';
require_once __DIR__.'/../modules/shift-reports/controller.php';

require_once __DIR__.'/includes/page_helpers.php';

$uid        = (int)($_SESSION['user_id']??0);
$user_email = $_SESSION['user_email']??'operator@tracs.local';

$CC = new CaseController($conn,$uid);
$RC = new ReminderController($conn,$uid);
$KC = new ChecklistController($conn,$uid);
$TC = new AlertTickerController($conn,$uid);
$AC = new ActivityLogController($conn,$uid);
$SC = new ShiftReportController($conn,$uid);

$opsStatus = getOpsStatus($conn);
$shift_reports = $SC->getTodayByShift();

$cases      = array_map([$CC,'formatCase'], $CC->getCases()?:[]);
$reminders  = [];
foreach($RC->getReminders()?:[] as $r){try{$reminders[]=$RC->formatReminder($r);}catch(Exception $e){}}
$tasks      = $KC->getTasks()?:[];
$activities = [];
foreach($AC->getRecentActivity(8)?:[] as $a){try{$activities[]=$AC->formatActivity($a);}catch(Exception $e){}}
$ticker_items = $TC->formatAlertsForTicker();

$total_cases    = count($cases);
$critical_cases = count(array_filter($cases,fn($c)=>($c['priority']??'')==='critical'));
$stuck_cases    = count(array_filter($cases,fn($c)=>($c['status']??'')==='stuck'));
$overdue_rem    = count(array_filter($reminders,fn($r)=>($r['status']??'')==='Overdue'));
$today_rem      = count(array_filter($reminders,fn($r)=>($r['status']??'')==='Today'));
$critical_count = $critical_cases + $overdue_rem;

$total_tasks = count($tasks);
$done_tasks  = count(array_filter($tasks,fn($t)=>!empty($t['is_completed'])));
$pct         = $total_tasks>0?round($done_tasks/$total_tasks*100):0;

// Shift Logic
$hour = (int)date('H');
if ($hour >= 0 && $hour < 8) {
    $current_shift = ['num' => 1, 'name' => 'SHIFT 1', 'color' => 'indigo'];
} elseif ($hour >= 8 && $hour < 16) {
    $current_shift = ['num' => 2, 'name' => 'SHIFT 2', 'color' => 'amber'];
} else {
    $current_shift = ['num' => 3, 'name' => 'SHIFT 3', 'color' => 'blue'];
}

$page_title='Dashboard'; $active_page='dashboard';
include 'includes/header.php';
?>
<main class="main">
<div class="main-inner">

  <!-- ── TOPBAR ── -->
  <div class="topbar">

    <div class="topbar-left">
    <div class="page-title page-logo-title">
      <img
        src="assets/img/logo.svg"
        alt="TRACS"
        class="page-title-logo"
      >
    </div>
      <div class="page-sub" id="tracs-clock">—</div>
    </div>

    <!-- OPS STATUS MARQUEE -->
    <div class="ops-marquee">
      <button class="ops-arrow" id="opsPrev">‹</button>
      <div class="ops-window">
        <div class="ops-track" id="opsTrack">
          <?php foreach($opsStatus as $item): ?>
          <div class="ops-item ops-<?= htmlspecialchars($item['severity']) ?>">
            <span class="ops-badge"><?= strtoupper($item['severity']) ?></span>
            <span class="ops-text"><?= htmlspecialchars($item['message']) ?></span>
            <button class="ops-edit-btn" onclick='openOpsModal(<?= $item["id"] ?>,<?= json_encode($item["message"]) ?>,<?= json_encode($item["severity"]) ?>)'>
              <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5"/>
                <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
              </svg>
            </button>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <button class="ops-arrow" id="opsNext">›</button>
    </div>

    <div class="topbar-right">
      <div class="shift-indicator">
        <div class="shift-dot <?= $current_shift['color'] ?>"></div>
        <div class="shift-info">
          <div class="shift-slider" id="shift-slider">
            <div class="shift-slide active"><?= $current_shift['name'] ?></div>
            <div class="shift-slide greeting-slide" id="shift-greeting"></div>
          </div>
        </div>
      </div>

      <div class="topbar-divider"></div>

      <div class="notif-bell-btn" title="View Reminders">
        <i data-lucide="bell" class="icon-md"></i>
        <div id="notif-badge-container">
          <?php 
          $pending_tasks = $total_tasks - $done_tasks;
          if($pending_tasks > 0): ?>
          <span class="bell-badge"><?= $pending_tasks ?></span>
          <?php endif; ?>
        </div>

        <!-- Notif Dropdown -->
        <div class="notif-dropdown">
          <div class="notif-drop-head">
            <span class="notif-drop-title">Notification Reminder</span>
            <a href="reminders.php" class="notif-drop-link">View All</a>
          </div>
          <div class="notif-drop-body">
            <?php 
            $active_rems = array_filter($reminders, fn($r) => in_array($r['status'], ['Overdue', 'Today', 'Upcoming', 'Tomorrow']) || strpos($r['status'], 'in ') === 0);
            $active_rems = array_slice($active_rems, 0, 6); // Show top 6
            if(empty($active_rems)): ?>
            <div class="notif-drop-empty">No active reminders</div>
            <?php else: foreach($active_rems as $r): 
              $r_status = $r['status'];
              if (strpos($r_status, 'in ') === 0 || $r_status === 'Tomorrow') $r_status = 'Upcoming';
            ?>
            <div class="notif-drop-item">
              <div class="notif-drop-status status-<?= strtolower($r_status) ?>"></div>
              <div class="notif-drop-info">
                <div class="notif-drop-text"><?= esc($r['title']) ?></div>
                <div class="notif-drop-meta"><?= esc($r['status']) ?> · <?= date('d M, H:i', strtotime($r['due_date'] ?? 'now')) ?></div>
              </div>
            </div>
            <?php endforeach; endif; ?>
          </div>
        </div>
      </div>
    </div>

  </div><!-- /topbar -->

  <!-- ── STAT STRIP ── -->
  <div class="stat-strip">
    <div class="stat-card red">
      <div class="stat-glow"></div>
      <div class="stat-num"><?=$critical_cases?></div>
      <div class="stat-label">Critical</div>
    </div>
    <div class="stat-card purple">
      <div class="stat-glow"></div>
      <div class="stat-num"><?=$stuck_cases?></div>
      <div class="stat-label">Stuck</div>
    </div>
    <div class="stat-card amber">
      <div class="stat-glow"></div>
      <div class="stat-num"><?=$overdue_rem?></div>
      <div class="stat-label">Overdue</div>
    </div>
    <div class="stat-card cyan">
      <div class="stat-glow"></div>
      <div class="stat-num"><?=$today_rem?></div>
      <div class="stat-label">Due Today</div>
    </div>
    <div class="stat-card blue">
      <div class="stat-glow"></div>
      <div class="stat-num"><?=$total_cases?></div>
      <div class="stat-label">Total Cases</div>
    </div>
    <div class="stat-card green">
      <div class="stat-glow"></div>
      <div class="stat-num"><?=$pct?>%</div>
      <div class="stat-label">Tasks Done</div>
    </div>
  </div><!-- /stat-strip -->

  <!-- ── 3-COLUMN DASHBOARD GRID ── -->
  <div class="dash-grid">

    <!-- ════════════════════════════
         LEFT COL — Core Operations
    ════════════════════════════ -->
    <div class="col-left">

      <!-- CASES PANEL -->
      <div class="panel">
        <div class="panel-head">
          <span class="panel-title">Cases</span>
          <div class="panel-right">
            <span class="panel-meta"><?=$total_cases?> total</span>
            <a href="cases.php" class="btn btn-ghost btn-sm">All →</a>
            <button class="btn btn-primary btn-sm" onclick="openNewCase()">
              <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>Add
            </button>
          </div>
        </div>

        <?php if(empty($cases)): ?>
        <div class="empty">
          <div class="empty-ic"><i data-lucide="briefcase"></i></div>
          <div class="empty-t">No cases yet</div>
          <div class="empty-s">Click Add to create your first case</div>
        </div>
        <?php else: foreach(array_slice($cases,0,8) as $c):
          $cid  = intval($c['id']??0);
          $title= esc($c['title']??'Untitled');
          $st   = strtolower($c['status']??'pending');
          $pr   = strtolower($c['priority']??'low');
          $time = esc($c['time_until']??'—');
          $over = str_starts_with($time,'Overdue');
          [$sb,$sl] = status_badge($st);
          $pb   = prio_badge($pr);
          $bar  = prio_bar($pr);
          $day  = safe_dt($c['next_check_at']??null,'M d');
          $hr   = safe_dt($c['next_check_at']??null,'H:i');
          $ndt  = safe_dt_local($c['next_check_at']??null);
        ?>
        <div class="case-row"
          data-cid="<?=$cid?>"
          data-title="<?=esc($c['title']??'')?>"
          data-status="<?=esc($st)?>"
          data-priority="<?=esc($pr)?>"
          data-next="<?=$ndt?>"
          data-notes="<?=esc($c['notes']??'')?>">

          <!-- Priority bar -->
          <div class="case-bar <?=$bar?>"></div>

          <!-- Main body: title + inline meta -->
          <div class="case-body">
            <div class="case-top-row">
              <span class="case-id">#<?=$cid?></span>
              <span class="case-title"><?=$title?></span>
            </div>
            <div class="case-meta">
              <span class="badge <?=$sb?>"><span class="badge-dot"></span><?=$sl?></span>
              <span class="badge <?=$pb?>"><?=ucfirst($pr)?></span>
              <span class="case-time <?=$over?'ov':''?>"><?=$time?></span>
            </div>
          </div>

          <!-- Right: date + actions -->
          <div class="case-right">
            <div class="case-when">
              <span class="case-day"><?=$day?></span>
              <span class="case-hr"><?=$hr?></span>
            </div>
            <div class="case-acts">
              <button class="btn btn-ghost btn-icon" onclick="openEditCase(<?=$cid?>)" title="Edit">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
              </button>
              <button class="btn btn-danger btn-icon" onclick="deleteCase(<?=$cid?>)" title="Delete">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/></svg>
              </button>
            </div>
          </div>

        </div>
        <?php endforeach; endif; ?>

        <?php if($total_cases>8): ?>
        <div style="padding:7px 12px;border-top:1px solid var(--bd1);text-align:center">
          <a href="cases.php" class="btn btn-ghost btn-sm">View all <?=$total_cases?> cases →</a>
        </div>
        <?php endif; ?>
      </div><!-- /cases panel -->

    </div><!-- /col-left -->

    <!-- ════════════════════════════
         CENTER COL — Workstream
    ════════════════════════════ -->
    <div class="col-center">

      <!-- CHECKLIST PANEL -->
      <div class="panel">
        <div class="panel-head">
          <span class="panel-title">Checklist</span>
          <div class="panel-right">
            <span class="panel-meta" id="prog-lbl"><?=$done_tasks?>/<?=$total_tasks?></span>
            <a href="checklist.php" class="btn btn-ghost btn-sm">All →</a>
            <button class="btn btn-primary btn-sm" onclick="openNewTask()">
              <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>Add
            </button>
          </div>
        </div>

        <div class="prog-wrap">
          <div class="prog-track">
            <div class="prog-fill" id="prog-fill" style="width:<?=$pct?>%"></div>
          </div>
          <div class="prog-info">
            <span>Progress</span>
            <span id="prog-pct"><?=$pct?>%</span>
          </div>
        </div>

        <?php if(empty($tasks)): ?>
        <div class="empty" style="padding:14px">
          <div class="empty-ic"><i data-lucide="list-checks"></i></div>
          <div class="empty-t">No tasks yet</div>
        </div>
        <?php else: ?>
        <div class="scroll-y" style="max-height:240px">
          <?php 
            // Sort tasks: Incomplete first, then by ID (Newest first)
            usort($tasks, function($a, $b) {
              if (($a['is_completed']??0) !== ($b['is_completed']??0)) return ($a['is_completed']??0) <=> ($b['is_completed']??0);
              return ($b['id']??0) <=> ($a['id']??0);
            });
            foreach(array_slice($tasks,0,12) as $t):
            $tid   = intval($t['id']??0);
            $ttit  = esc($t['title']??'Untitled');
            $tdesc = esc($t['description']??'');
            $tdone = !empty($t['is_completed']);
          ?>
          <div class="task-row"
            data-tid="<?=$tid?>"
            data-title="<?=esc($t['title']??'')?>"
            data-desc="<?=esc($t['description']??'')?>">

            <input type="checkbox" class="rem-check task-chk" <?=$tdone?'checked':''?> onchange="toggleTask(<?=$tid?>,this.checked)">
            <div class="flex1">
              <div class="task-title <?=$tdone?'done':''?>"><?=$ttit?></div>
              <?php if($tdesc): ?><div class="task-sub"><?=$tdesc?></div><?php endif; ?>
            </div>
            <div class="task-acts">
              <button class="btn btn-ghost btn-icon" onclick="openEditTask(<?=$tid?>)" title="Edit">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
              </button>
              <button class="btn btn-danger btn-icon" onclick="deleteTask(<?=$tid?>)" title="Delete">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
              </button>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div><!-- /checklist -->

      <!-- SHIFT HANDOVER PANEL -->
      <div class="panel">
        <div class="panel-head">
          <span class="panel-title">Shift Handover</span>
          <div class="panel-right">
            <span class="panel-meta">Today</span>
            <a href="shift-reports.php" class="btn btn-ghost btn-sm">History →</a>
            <button class="btn btn-primary btn-sm" onclick="openNewShiftReport()">
              <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>Add
            </button>
          </div>
        </div>
        
        <?php if(empty($shift_reports)): ?>
        <div class="empty" style="padding:14px">
          <div class="empty-ic"><i data-lucide="refresh-cw"></i></div>
          <div class="empty-t">No reports today</div>
        </div>
        <?php else: ?>
        <div class="scroll-y" style="max-height:260px;padding:8px 0">
          <?php foreach($shift_reports as $sname => $items): ?>
          <div class="shift-group">
            <div class="shift-group-title"><?=esc($sname)?></div>
            <?php foreach($items as $sr): 
              $srid=intval($sr['id']);
              $srtit=esc($sr['title']);
              $srprio=strtolower($sr['priority']);
              $srstatus=$sr['status'];
              $pclass=prio_bar($srprio);
            ?>
            <div class="shift-item <?=$srstatus==='resolved'?'resolved':''?>" onclick="openEditShiftReport(<?=$srid?>)">
              <div class="shift-priority <?=$pclass?>"></div>
              <div class="shift-text"><?=$srtit?></div>
              <?php if($srstatus==='resolved'):?><span class="badge b-done" style="transform:scale(0.8)">Done</span><?php endif;?>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div><!-- /shift handover -->

      <!-- CURRENCY CONVERTER PANEL -->
      <div class="panel">
        <div class="panel-head">
          <span class="panel-title">Currency Converter</span>
        </div>
        <div class="currency-body">
          <div class="currency-row">
            <select id="currency-from" class="form-select">
              <option value="IDR">IDR</option>
              <option value="USD">USD</option>
              <option value="SGD">SGD</option>
            </select>
            <button type="button" class="btn btn-ghost btn-icon" id="swap-currency" style="width:30px;height:30px;"><i data-lucide="arrow-right-left" class="icon-sm"></i></button>
            <select id="currency-to" class="form-select">
              <option value="USD">USD</option>
              <option value="IDR">IDR</option>
              <option value="SGD">SGD</option>
            </select>
          </div>
          <input type="number" id="currency-amount" class="form-input" placeholder="Enter amount" value="1000000">
          <button type="button" class="btn btn-primary" id="convert-btn" style="width:100%">Convert</button>
          <div class="currency-result">
            <div id="currency-result">—</div>
            <small id="currency-rate"></small>
          </div>
          <div class="currency-updated">Updated: <span id="currency-time">—</span></div>
        </div>
      </div><!-- /currency -->

    </div><!-- /col-center -->

    <!-- ════════════════════════════
         RIGHT COL — Utilities
    ════════════════════════════ -->
    <div class="col-right">

      <!-- REMINDERS PANEL -->
      <div class="panel">
        <div class="panel-head">
          <span class="panel-title">Reminders</span>
          <div class="panel-right">
            <a href="reminders.php" class="btn btn-ghost btn-sm">All →</a>
            <button class="btn btn-primary btn-sm" onclick="openNewReminder()">
              <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>Add
            </button>
          </div>
        </div>

        <?php if(empty($reminders)): ?>
        <div class="empty">
          <div class="empty-ic"><i data-lucide="bell"></i></div>
          <div class="empty-t">No reminders</div>
        </div>
        <?php else: foreach(array_slice($reminders,0,7) as $r):
          $rid   = intval($r['id']??0);
          $rtit  = esc($r['title']??'Untitled');
          $rstat = $r['status']??'—';
          $rprio = strtolower($r['priority']??'low');
          $rdone = !empty($r['is_completed']);
          $rdue  = safe_dt_local($r['due_date']??null);
          $scls  = rem_status_class($rstat);
          $pb    = prio_badge($rprio);
        ?>
        <div class="rem-row"
          data-rid="<?=$rid?>"
          data-title="<?=esc($r['title']??'')?>"
          data-priority="<?=esc($rprio)?>"
          data-due="<?=$rdue?>"
          data-desc="<?=esc($r['description']??'')?>">

          <input type="checkbox" class="rem-check" <?=$rdone?'checked':''?> onchange="toggleReminder(<?=$rid?>,this.checked)">
          <div class="flex1">
            <div class="rem-title <?=$rdone?'done':''?>"><?=$rtit?></div>
            <div class="rem-meta">
              <span class="badge <?=$pb?>" style="font-size:8px;padding:1px 5px"><?=ucfirst($rprio)?></span>
              <span class="<?=$scls?>"><?=esc($rstat)?></span>
            </div>
          </div>
          <div class="rem-acts">
            <button class="btn btn-ghost btn-icon" onclick="openEditReminder(<?=$rid?>)" title="Edit">
              <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            </button>
            <button class="btn btn-danger btn-icon" onclick="deleteReminder(<?=$rid?>)" title="Delete">
              <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
            </button>
          </div>
        </div>
        <?php endforeach; endif; ?>
      </div><!-- /reminders -->

      <!-- ACTIVITY LOG PANEL -->
      <div class="panel">
        <div class="panel-head">
          <span class="panel-title">Activity Log</span>
          <a href="activity.php" class="btn btn-ghost btn-sm">All →</a>
        </div>

        <?php if(empty($activities)): ?>
        <div class="empty">
          <div class="empty-ic"><i data-lucide="activity"></i></div>
          <div class="empty-t">No activity yet</div>
        </div>
        <?php else: foreach($activities as $a): ?>
        <div class="act-row">
          <div class="act-ic" style="color:var(--tx3);display:flex;align-items:center"><i data-lucide="<?=esc($a['icon']??'file-text')?>" class="icon-sm"></i></div>
          <div class="flex1">
            <div class="act-text">
              <strong><?=esc(ucfirst($a['action']??''))?></strong>
              <span>· <?=esc($a['module']??'')?></span>
            </div>
            <div class="act-desc"><?=esc($a['description']??'')?></div>
            <div class="act-time"><?=esc($a['time_ago']??'')?></div>
          </div>
        </div>
        <?php endforeach; endif; ?>
      </div><!-- /activity -->

    </div><!-- /col-right -->

  </div><!-- /dash-grid -->
</div><!-- /main-inner -->
</main>

<?php include __DIR__.'/../modules/ops-status/modal.php'; ?>
<?php include 'includes/footer.php'; ?>
