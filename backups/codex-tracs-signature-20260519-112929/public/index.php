<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../core/security/csrf.php';
tracs_start_session();

require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/auth/auth_check.php';

require_once __DIR__.'/../modules/case/controller.php';
require_once __DIR__.'/../modules/reminder/controller.php';
require_once __DIR__.'/../modules/checklist/controller.php';
require_once __DIR__.'/../modules/alert-ticker/controller.php';
require_once __DIR__.'/../modules/activity-log/controller.php';
require_once __DIR__.'/../modules/ops-status/controller.php';
require_once __DIR__.'/../modules/shift-reports/controller.php';
require_once __DIR__.'/../modules/mom/controller.php';

require_once __DIR__.'/includes/page_helpers.php';

$uid        = (int)($_SESSION['user_id']??0);
$user_email = $_SESSION['user_email']??'operator@tracs.local';
tracs_ensure_creator_columns($conn, 'tracs_cases', 'user_id');
tracs_ensure_creator_columns($conn, 'tracs_reminders', 'user_id');
tracs_ensure_creator_columns($conn, 'tracs_side_tasks', 'user_id');
tracs_ensure_creator_columns($conn, 'tracs_shift_reports', 'created_by');

$CC = new CaseController($conn,$uid);
$RC = new ReminderController($conn,$uid);
$KC = new ChecklistController($conn,$uid);
$TC = new AlertTickerController($conn,$uid);
$AC = new ActivityLogController($conn,$uid);
$SC = new ShiftReportController($conn,$uid);
$MC = new MOMController($conn,$uid);

$opsStatus = getOpsStatus($conn);
$shift_reports = $SC->getDashboardByShift();
$shift_handover_label = $SC->getDashboardWindowLabel();

$cases      = array_map([$CC,'formatCase'], $CC->getCases()?:[]);
$reminders  = [];
foreach($RC->getReminders()?:[] as $r){try{$reminders[]=$RC->formatReminder($r);}catch(Exception $e){}}
$tasks      = $KC->getTasks()?:[];
$activities = [];
foreach($AC->getRecentActivity(20)?:[] as $a){try{$activities[]=$AC->formatActivity($a);}catch(Exception $e){}}
$ticker_items = $TC->formatAlertsForTicker();
$mom_dashboard = [];
$weekly_suggestions = [];
if($MC->isInstalled()){
  $weekly_suggestions = $MC->getWeeklySuggestions()?:[];
  foreach($MC->getMOMs('all', 8)?:[] as $m){
    try{
      $fm = $MC->formatMOM($m);
      if(in_array($fm['status']??'', ['upcoming','ongoing'], true)) $mom_dashboard[] = $fm;
    }catch(Exception $e){}
  }
}

$total_cases    = count($cases);
$critical_cases = count(array_filter($cases,fn($c)=>($c['priority']??'')==='critical'));
$stuck_cases    = count(array_filter($cases,fn($c)=>($c['status']??'')==='stuck'));
$overdue_rem    = count(array_filter($reminders,fn($r)=>($r['status']??'')==='Overdue'));
$today_rem      = count(array_filter($reminders,fn($r)=>($r['status']??'')==='Today'));
$critical_count = $critical_cases + $overdue_rem;

$total_tasks = count($tasks);
$done_tasks  = count(array_filter($tasks,fn($t)=>!empty($t['is_completed'])));
$pct         = $total_tasks>0?round($done_tasks/$total_tasks*100):0;

function tracs_period_window(string $period, int $offset = 0): array {
  $now = new DateTimeImmutable('now');
  if($period === 'month'){
    $start = $now->modify('first day of this month')->setTime(0, 0, 0)->modify(($offset * 1).' month');
    $end = $offset === 0 ? $now : $start->modify('first day of next month');
    return [$start, $end];
  }
  $end = $offset === 0 ? $now : $now->modify(($offset * 7).' days');
  $start = $end->modify('-7 days');
  return [$start, $end];
}

function tracs_date_in_window($value, DateTimeImmutable $start, DateTimeImmutable $end): bool {
  if(empty($value)) return false;
  try { $date = new DateTimeImmutable((string)$value); }
  catch(Throwable $e){ return false; }
  return $date >= $start && $date < $end;
}

function tracs_count_window(array $items, callable $filter, string $date_key, DateTimeImmutable $start, DateTimeImmutable $end): int {
  return count(array_filter($items, fn($item) => $filter($item) && tracs_date_in_window($item[$date_key]??null, $start, $end)));
}

function tracs_count_before(array $items, callable $filter, string $date_key, DateTimeImmutable $end): int {
  return count(array_filter($items, function($item) use ($filter, $date_key, $end){
    if(!$filter($item) || empty($item[$date_key])) return false;
    try { $date = new DateTimeImmutable((string)$item[$date_key]); }
    catch(Throwable $e){ return false; }
    return $date < $end;
  }));
}

function tracs_count_on_day(array $items, callable $filter, string $date_key, DateTimeImmutable $day): int {
  $start = $day->setTime(0, 0, 0);
  return tracs_count_window($items, $filter, $date_key, $start, $start->modify('+1 day'));
}

function tracs_rate_before(array $items, callable $filter, string $date_key, DateTimeImmutable $end): int {
  $window_items = array_values(array_filter($items, function($item) use ($date_key, $end){
    if(empty($item[$date_key])) return false;
    try { $date = new DateTimeImmutable((string)$item[$date_key]); }
    catch(Throwable $e){ return false; }
    return $date < $end;
  }));
  $total = count($window_items);
  if($total === 0) return 0;
  $matched = count(array_filter($window_items, $filter));
  return (int)round($matched / $total * 100);
}

function tracs_delta_meta(int $current, int $previous, string $period_label): array {
  $diff = $current - $previous;
  $pct = $previous > 0 ? (int)round(($diff / $previous) * 100) : ($current > 0 ? 100 : 0);
  $state = $diff > 0 ? 'up' : ($diff < 0 ? 'down' : 'flat');
  $prefix = $pct > 0 ? '+' : '';
  return [
    'state' => $state,
    'value' => $prefix.$pct.'%',
    'detail' => $current.' vs '.$previous.' '.$period_label,
  ];
}

[$week_start] = tracs_period_window('week');
[$month_start] = tracs_period_window('month');

$stat_cards = [
  [
    'color' => 'red',
    'value' => $critical_cases,
    'label' => 'Critical',
    'trend' => tracs_delta_meta(
      $critical_cases,
      tracs_count_before($cases, fn($c)=>($c['priority']??'')==='critical', 'created_at', $week_start),
      'last week'
    ),
  ],
  [
    'color' => 'purple',
    'value' => $stuck_cases,
    'label' => 'Stuck',
    'trend' => tracs_delta_meta(
      $stuck_cases,
      tracs_count_before($cases, fn($c)=>($c['status']??'')==='stuck', 'created_at', $week_start),
      'last week'
    ),
  ],
  [
    'color' => 'amber',
    'value' => $overdue_rem,
    'label' => 'Overdue',
    'trend' => tracs_delta_meta(
      $overdue_rem,
      tracs_count_before($reminders, fn($r)=>empty($r['is_completed']), 'due_date', $week_start),
      'last week'
    ),
  ],
  [
    'color' => 'cyan',
    'value' => $today_rem,
    'label' => 'Due Today',
    'trend' => tracs_delta_meta(
      $today_rem,
      tracs_count_on_day($reminders, fn($r)=>empty($r['is_completed']), 'due_date', $week_start),
      'last week'
    ),
  ],
  [
    'color' => 'blue',
    'value' => $total_cases,
    'label' => 'Total Cases',
    'trend' => tracs_delta_meta(
      $total_cases,
      tracs_count_before($cases, fn($c)=>true, 'created_at', $week_start),
      'last week'
    ),
  ],
  [
    'color' => 'green',
    'value' => $pct.'%',
    'label' => 'Tasks Done',
    'trend' => tracs_delta_meta(
      $pct,
      tracs_rate_before($tasks, fn($t)=>!empty($t['is_completed']), 'created_at', $month_start),
      'last month'
    ),
  ],
];
$unchecked_tasks = array_values(array_filter($tasks, fn($t)=>empty($t['is_completed'])));
$unchecked_reminders = array_values(array_filter($reminders, fn($r)=>empty($r['is_completed'])));
$active_cases = array_values(array_filter($cases, fn($c)=>($c['status']??'') !== 'completed'));
$attention_cases = array_values(array_filter($active_cases, function($c){
  $time = (string)($c['time_until']??'');
  return ($c['priority']??'') === 'critical' || ($c['status']??'') === 'stuck' || str_starts_with($time, 'Overdue');
}));
$new_cases = array_values(array_filter($cases, fn($c)=>!empty($c['created_at']) && strtotime((string)$c['created_at']) >= strtotime('-24 hours')));
$recent_checked_reminders = array_values(array_filter($reminders, fn($r)=>!empty($r['is_completed']) && reminder_visible_in_checklist($r)));
usort($unchecked_reminders, fn($a, $b) => strtotime($b['created_at'] ?? '1970-01-01') <=> strtotime($a['created_at'] ?? '1970-01-01'));
usort($recent_checked_reminders, fn($a, $b) => strtotime(reminder_completed_at($b) ?? '1970-01-01') <=> strtotime(reminder_completed_at($a) ?? '1970-01-01'));
$dashboard_reminders = array_merge($unchecked_reminders, $recent_checked_reminders);
$scheduled_meetings = array_values(array_filter($mom_dashboard, fn($m)=>($m['status']??'') === 'upcoming'));
$active_mom_count = count(array_filter($mom_dashboard, fn($m)=>in_array($m['status']??'', ['upcoming','ongoing'], true)));
$active_reminder_count = count($unchecked_reminders);
$unchecked_task_count = count($unchecked_tasks);
$active_case_count = count($active_cases);

$notification_alerts = [];
$now_ts = time();
foreach($unchecked_reminders as $r){
  if(empty($r['due_date'])) continue;
  $due_ts = strtotime((string)$r['due_date']);
  if(!$due_ts) continue;
  $mins = (int)floor(($due_ts - $now_ts) / 60);
  if($mins <= 5){
    $notification_alerts[] = [
      'status' => $mins < 0 ? 'overdue' : ($mins === 0 ? 'now' : 'soon'),
      'label' => 'Reminder',
      'title' => $mins < 0 ? 'Reminder overdue: '.($r['title']??'Untitled') : 'Reminder due '.($mins <= 0 ? 'now' : 'in '.$mins.' min').': '.($r['title']??'Untitled'),
      'meta' => safe_dt($r['due_date'], 'd M, H:i'),
      'href' => 'reminders.php',
      'ref' => 'reminder-'.(int)($r['id']??0),
      'sort_key' => $due_ts,
    ];
  }
}
foreach($mom_dashboard as $m){
  $mstatus = $m['status']??'';
  $meeting_ts = !empty($m['meeting_at']) ? strtotime((string)$m['meeting_at']) : null;
  $mins = $meeting_ts ? (int)floor(($meeting_ts - $now_ts) / 60) : null;
  if($mstatus === 'ongoing' || ($mstatus === 'upcoming' && $mins !== null && $mins >= 0 && $mins <= 5)){
    $notification_alerts[] = [
      'status' => $mstatus === 'ongoing' ? 'meeting-live' : 'meeting-soon',
      'label' => 'MoM',
      'title' => $mstatus === 'ongoing' ? 'Meeting is ongoing: '.($m['title']??'Untitled') : 'Meeting starts in '.$mins.' min: '.($m['title']??'Untitled'),
      'meta' => safe_dt($m['meeting_at']??($m['created_at']??null), 'd M, H:i'),
      'href' => 'mom.php?mom_id='.(int)($m['id']??0),
      'ref' => 'mom-'.(int)($m['id']??0),
      'sort_key' => $meeting_ts ?: $now_ts,
    ];
  }
}
usort($notification_alerts, fn($a,$b)=>(int)($a['sort_key']??0) <=> (int)($b['sort_key']??0));
$notification_alerts = array_slice($notification_alerts, 0, 6);
$notif_count = count($notification_alerts);
$notification_static_extra = $notif_count;
$notification_groups = [
  [
    'status' => 'cases',
    'label' => 'Cases',
    'count' => $active_case_count,
    'title' => $active_case_count.' unresolved '.($active_case_count===1?'case':'cases'),
    'meta' => count($new_cases).' new today · '.count($attention_cases).' need attention',
    'href' => 'cases.php',
  ],
  [
    'status' => 'checklist',
    'label' => 'Checklist',
    'count' => $unchecked_task_count,
    'title' => $unchecked_task_count.' unchecked '.($unchecked_task_count===1?'task':'tasks'),
    'meta' => $done_tasks.'/'.$total_tasks.' completed',
    'href' => 'checklist.php',
  ],
  [
    'status' => 'reminders',
    'label' => 'Reminders',
    'count' => $active_reminder_count,
    'title' => $active_reminder_count.' active '.($active_reminder_count===1?'reminder':'reminders'),
    'meta' => $overdue_rem.' overdue · '.$today_rem.' due today',
    'href' => 'reminders.php',
  ],
  [
    'status' => 'meeting',
    'label' => 'MoM',
    'count' => $active_mom_count,
    'title' => $active_mom_count.' active '.($active_mom_count===1?'meeting':'meetings'),
    'meta' => count($scheduled_meetings).' scheduled · '.count(array_filter($mom_dashboard, fn($m)=>($m['status']??'') === 'ongoing')).' ongoing',
    'href' => 'mom.php',
  ],
];

function dashboard_counter_class(int $count): string {
  if($count <= 0) return 'is-zero';
  if($count < 5) return 'is-low';
  if($count < 10) return 'is-warning';
  return 'is-critical';
}

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
    <div class="ops-marquee" id="ops-window">
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

      <div class="notif-bell-btn" title="Open notification center" role="button" tabindex="0" aria-label="Open notification center" aria-haspopup="true" aria-expanded="false">
        <i data-lucide="bell" class="icon-md"></i>
        <div id="notif-badge-container" data-badge-mode="alerts" data-static-extra="<?=$notification_static_extra?>" data-unchecked-checklist="<?=$unchecked_task_count?>">
          <?php if($notif_count > 0): ?>
          <span class="bell-badge"><?= min($notif_count,99) ?></span>
          <?php endif; ?>
        </div>

        <!-- Notif Dropdown -->
        <div class="notif-dropdown">
          <div class="notif-drop-head">
            <span class="notif-drop-title">Attention Center</span>
            <a href="activity.php" class="notif-drop-link">Activity Log</a>
          </div>
          <div class="notif-drop-body">
            <?php if(!empty($notification_alerts)): ?>
            <div class="notif-section-label">Now / Next 5 min</div>
            <?php foreach($notification_alerts as $n): ?>
            <a class="notif-drop-item notif-alert-item" href="<?=esc($n['href'])?>">
              <div class="notif-drop-status status-<?= esc($n['status']) ?>"></div>
              <div class="notif-drop-info">
                <div class="notif-drop-label"><?= esc($n['label']) ?></div>
                <div class="notif-drop-text"><?= esc($n['title']) ?></div>
                <div class="notif-drop-meta"><?= esc($n['meta']) ?></div>
              </div>
            </a>
            <?php endforeach; ?>
            <?php else: ?>
            <div class="notif-drop-empty">No time-sensitive alerts</div>
            <?php endif; ?>
            <div class="notif-section-label">Workload Summary</div>
            <?php foreach($notification_groups as $n): ?>
            <a class="notif-drop-item" href="<?=esc($n['href'])?>">
              <div class="notif-drop-status status-<?= esc($n['status']) ?>"></div>
              <div class="notif-drop-info">
                <div class="notif-drop-label"><?= esc($n['label']) ?></div>
                <div class="notif-drop-text"><?= esc($n['title']) ?></div>
                <div class="notif-drop-meta"><?= esc($n['meta']) ?></div>
              </div>
              <span class="panel-counter <?=dashboard_counter_class((int)$n['count'])?>"><?=min((int)$n['count'],99)?></span>
            </a>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

  </div><!-- /topbar -->

  <!-- ── STAT STRIP ── -->
  <div class="stat-strip">
    <?php foreach($stat_cards as $card): ?>
    <div class="stat-card <?=esc($card['color'])?>">
      <div class="stat-glow"></div>
      <div class="stat-label"><?=esc($card['label'])?></div>
      <div class="stat-main">
        <div class="stat-num"><?=esc((string)$card['value'])?></div>
        <div class="stat-trend <?=esc($card['trend']['state'])?>" title="<?=esc($card['trend']['detail'])?>">
          <span class="stat-trend-arrow"></span>
          <span><?=esc($card['trend']['value'])?></span>
        </div>
      </div>
      <div class="stat-compare"><?=esc($card['trend']['detail'])?></div>
    </div>
    <?php endforeach; ?>
  </div><!-- /stat-strip -->

  <!-- ── 3-COLUMN DASHBOARD GRID ── -->
  <div class="dash-grid">

    <!-- ════════════════════════════
         LEFT COL — Core Operations
    ════════════════════════════ -->
    <div class="col-left">

      <!-- INFRASTRUCTURE PULSE SUMMARY -->
      <a class="panel infra-dashboard-widget" href="infrastructure-pulse.php" data-infra-dashboard-widget>
        <div class="infra-dashboard-widget__head">
          <div>
            <span>Infrastructure Pulse</span>
            <strong>Loading</strong>
          </div>
          <i data-lucide="radar"></i>
        </div>
      </a>

      <!-- CASES PANEL -->
      <div class="panel dashboard-case-panel">
        <div class="panel-head">
          <span class="panel-title">Cases</span>
          <div class="panel-right">
            <span class="panel-meta"><?=$total_cases?> total</span>
            <span class="panel-counter <?=dashboard_counter_class($active_case_count)?>" title="<?=$active_case_count?> unresolved cases"><?=$active_case_count?></span>
            <a href="cases.php" class="btn btn-ghost btn-sm">All →</a>
            <button class="btn btn-primary btn-sm btn-add-reveal" onclick="openNewCase()">
              <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg><span class="btn-add-label">Add</span>
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
            <?=tracs_creator_meta($c, $c['created_at'] ?? null, false)?>
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
      <div class="panel checklist-panel">
        <div class="panel-head">
          <span class="panel-title checklist-panel-title"><i data-lucide="list-checks" class="icon-xs"></i>Checklist</span>
          <div class="panel-right">
            <span class="panel-meta" id="prog-lbl"><?=$done_tasks?>/<?=$total_tasks?></span>
            <span class="panel-counter <?=dashboard_counter_class($unchecked_task_count)?>" title="<?=$unchecked_task_count?> unchecked checklist items"><?=$unchecked_task_count?></span>
            <a href="checklist.php" class="btn btn-ghost btn-sm">All →</a>
            <button class="btn btn-primary btn-sm btn-add-reveal" onclick="openNewTask()">
              <i data-lucide="plus" class="icon-sm"></i><span class="btn-add-label">Add</span>
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
        <div class="dashboard-checklist-scroll scroll-y">
          <?php 
            // Sort tasks: Incomplete first, then by ID (Newest first)
            usort($tasks, function($a, $b) {
              if (($a['is_completed']??0) !== ($b['is_completed']??0)) return ($a['is_completed']??0) <=> ($b['is_completed']??0);
              return ($b['id']??0) <=> ($a['id']??0);
            });
            foreach($tasks as $t):
            $tid   = intval($t['id']??0);
            $ttit  = esc($t['title']??'Untitled');
            $tdesc = esc($t['description']??'');
            $tdone = !empty($t['is_completed']);
          ?>
          <div class="task-row checkable-row <?=$tdone?'is-completed':''?>"
            data-tid="<?=$tid?>"
            data-completed="<?=$tdone?'1':'0'?>"
            data-title="<?=esc($t['title']??'')?>"
            data-desc="<?=esc($t['description']??'')?>">

            <input type="checkbox" class="rem-check task-chk" <?=$tdone?'checked':''?> onchange="toggleTask(<?=$tid?>,this.checked)">
            <div class="flex1">
              <div class="task-title <?=$tdone?'done':''?>"><?=$ttit?></div>
              <?php if($tdesc): ?><div class="task-sub"><?=$tdesc?></div><?php endif; ?>
              <?=tracs_creator_meta($t, $t['created_at'] ?? null, false)?>
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
            <span class="panel-meta"><?=esc($shift_handover_label)?></span>
            <a href="shift-reports.php" class="btn btn-ghost btn-sm">History →</a>
            <button class="btn btn-primary btn-sm btn-add-reveal" onclick="openNewShiftReport()">
              <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg><span class="btn-add-label">Add</span>
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
              <div class="shift-text"><?=$srtit?><?=tracs_creator_meta($sr, $sr['created_at'] ?? null, false)?></div>
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
          <input type="number" id="currency-amount" class="form-input" placeholder="Transfer amount" value="1000000">
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
            <span class="panel-counter <?=dashboard_counter_class($active_reminder_count)?>" title="<?=$active_reminder_count?> active reminders"><?=$active_reminder_count?></span>
            <a href="reminders.php" class="btn btn-ghost btn-sm">All →</a>
            <button class="btn btn-primary btn-sm btn-add-reveal" onclick="openNewReminder()">
              <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg><span class="btn-add-label">Add</span>
            </button>
          </div>
        </div>

        <?php if(empty($dashboard_reminders)): ?>
        <div class="empty">
          <div class="empty-ic"><i data-lucide="bell"></i></div>
          <div class="empty-t">No undone reminders</div>
        </div>
        <?php else: ?>
        <div class="dashboard-reminder-scroll">
        <?php foreach($dashboard_reminders as $r):
          $rid   = intval($r['id']??0);
          $rtit  = esc($r['title']??'Untitled');
          $rstat = $r['status']??'—';
          $rprio = strtolower($r['priority']??'low');
          $rdone = !empty($r['is_completed']);
          $rdue  = safe_dt_local($r['due_date']??null);
          $scls  = rem_status_class($rstat);
          $pb    = prio_badge($rprio);
        ?>
        <div class="rem-row checkable-row <?=$rdone?'is-completed':''?>"
          data-rid="<?=$rid?>"
          data-completed="<?=$rdone?'1':'0'?>"
          data-notif-alert="<?=(!$rdone && !empty($r['due_date']) && strtotime((string)$r['due_date']) !== false && strtotime((string)$r['due_date']) <= time() + 300)?'1':'0'?>"
          data-title="<?=esc($r['title']??'')?>"
          data-priority="<?=esc($rprio)?>"
          data-due="<?=$rdue?>"
          data-desc="<?=esc($r['description']??'')?>">

          <div class="flex1">
            <div class="rem-title <?=$rdone?'done':''?>"><?=$rtit?></div>
            <div class="rem-meta">
              <span class="badge <?=$pb?>" style="font-size:8px;padding:1px 5px"><?=ucfirst($rprio)?></span>
              <span class="<?=$scls?>"><?=esc($rstat)?></span>
            </div>
            <?=tracs_creator_meta($r, $r['created_at'] ?? null, false)?>
          </div>
          <div class="rem-acts">
            <?php if($rdone): ?>
            <button class="btn btn-ghost btn-icon rem-primary-action" data-compact-action="1" onclick="toggleReminder(<?=$rid?>,false)" title="Reopen reminder" aria-label="Reopen reminder">
              <i data-lucide="rotate-ccw" class="icon-sm"></i>
            </button>
            <?php else: ?>
            <button class="btn btn-ghost btn-icon rem-done-btn rem-primary-action" data-compact-action="1" onclick="completeReminder(<?=$rid?>)" title="Mark reminder done" aria-label="Mark reminder done">
              <i data-lucide="check-square" class="icon-sm"></i>
            </button>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div><!-- /reminders -->

      <!-- MOM SCHEDULE PANEL -->
      <div class="panel">
        <div class="panel-head">
          <span class="panel-title">MOM Schedule</span>
          <div class="panel-right">
            <span class="panel-meta"><?=count($mom_dashboard)?> active</span>
            <a href="mom.php" class="btn btn-ghost btn-sm">All →</a>
            <button class="btn btn-primary btn-sm btn-add-reveal" onclick="openNewMOM()"><i data-lucide="plus" class="icon-sm"></i><span class="btn-add-label">Add</span></button>
          </div>
        </div>

        <?php if(empty($mom_dashboard)): ?>
        <div class="empty">
          <div class="empty-ic"><i data-lucide="clipboard-list"></i></div>
          <div class="empty-t">No scheduled meetings</div>
        </div>
        <?php else: foreach(array_slice($mom_dashboard,0,5) as $m):
          $mid = intval($m['id']??0);
          $mtitle = esc($m['title']??'Untitled');
          $mtype = strtolower($m['type']??'weekly');
          $mstatus = strtolower($m['status']??'upcoming');
          $mstatus_badge = $mstatus === 'ongoing' ? 'b-high' : 'b-pending';
          $mtype_badge = $mtype === 'urgent' ? 'b-critical' : ($mtype === 'training' ? 'b-info' : 'b-active');
          $mwhen = safe_dt($m['meeting_at']??($m['created_at']??null), 'M d H:i');
        ?>
        <a class="rem-row" href="mom.php?mom_id=<?=$mid?>" style="text-decoration:none">
          <div class="act-ic" style="color:var(--tx3);display:flex;align-items:center"><i data-lucide="calendar-clock" class="icon-sm"></i></div>
          <div class="flex1">
            <div class="rem-title"><?=$mtitle?></div>
            <div class="rem-meta">
              <span class="badge <?=$mtype_badge?>" style="font-size:8px;padding:1px 5px"><?=ucfirst($mtype)?></span>
              <span class="badge <?=$mstatus_badge?>" style="font-size:8px;padding:1px 5px"><?=ucfirst($mstatus)?></span>
            </div>
            <?=tracs_creator_meta($m, $m['created_at'] ?? null, false)?>
          </div>
          <div class="rem-meta" style="font-family:var(--mono);color:var(--tx3)"><?=$mwhen?></div>
        </a>
        <?php endforeach; endif; ?>
      </div><!-- /mom schedule -->

      <!-- ACTIVITY LOG PANEL -->
      <div class="panel dashboard-activity-panel">
        <div class="panel-head">
          <span class="panel-title">Activity Log</span>
          <a href="activity.php" class="btn btn-ghost btn-sm">All →</a>
        </div>

        <div class="dashboard-activity-scroll">
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
            <div class="act-time"><?=esc($a['time_ago']??'')?> · <?=tracs_creator_meta($a, $a['created_at'] ?? null, false)?></div>
          </div>
        </div>
        <?php endforeach; endif; ?>
        </div>
      </div><!-- /activity -->

    </div><!-- /col-right -->

  </div><!-- /dash-grid -->
</div><!-- /main-inner -->
</main>

<?php include __DIR__.'/../modules/ops-status/modal.php'; ?>
<?php $_infra_data_v = @filemtime(__DIR__.'/assets/infrastructure-pulse-data.js') ?: time(); ?>
<?php $_infra_js_v = @filemtime(__DIR__.'/assets/infrastructure-pulse.js') ?: time(); ?>
<script src="assets/infrastructure-pulse-data.js?v=<?=$_infra_data_v?>"></script>
<script src="assets/infrastructure-pulse.js?v=<?=$_infra_js_v?>"></script>
<?php include 'includes/footer.php'; ?>
