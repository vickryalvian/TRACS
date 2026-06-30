<?php

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../core/security/csrf.php';
tracs_start_session();

require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/auth/auth_check.php';
require_once __DIR__.'/../core/access_control.php';
tracs_require_page_permission($conn, 'dashboard.view');

require_once __DIR__.'/../modules/case/controller.php';
require_once __DIR__.'/../modules/reminder/controller.php';
require_once __DIR__.'/../modules/checklist/controller.php';
require_once __DIR__.'/../modules/alert-ticker/controller.php';
require_once __DIR__.'/../modules/activity-log/controller.php';
require_once __DIR__.'/../modules/ops-status/controller.php';
require_once __DIR__.'/../modules/shift-reports/controller.php';
require_once __DIR__.'/../modules/mom/controller.php';
require_once __DIR__.'/../modules/task-management/controller.php';
require_once __DIR__.'/../core/notifications.php';

require_once __DIR__.'/includes/page_helpers.php';

// TRACS Operations System: first-deployment dashboard direction by Vickry.
$uid        = (int)($_SESSION['user_id']??0);
$user_email = $_SESSION['user_email']??'operator@tracs.local';
$case_can_manage = tracs_user_can($conn, 'cases.manage');
$case_role = (string)($_SESSION['user_role_slug'] ?? '');
$case_can_delete = tracs_user_can_delete_cases($conn, $uid);
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
$TM = new TaskManagementController($conn,$uid);

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

$task_assignment_rows = [];
$task_assignment_schema_ready = false;
$task_assignment_can_create = false;
try {
  $task_assignment_schema_ready = $TM->schemaReady();
  if($task_assignment_schema_ready){
    $task_assignment_can_create = $TM->canCreate();
    $TM->refreshOverdueStatuses();
    $stmt = $conn->prepare("
      SELECT t.*, ta.id AS assignment_id, ta.user_id, ta.status AS stored_status,
             CASE
               WHEN ta.status IN ('completed_on_time','completed_late','reviewed','cancelled','reassigned') THEN ta.status
               WHEN t.due_at IS NOT NULL AND t.due_at < NOW() THEN 'overdue'
               ELSE ta.status
             END AS assignment_status,
             ta.progress_note, ta.completion_note, ta.review_note,
             ta.assigned_at, ta.started_at, ta.completed_at, ta.reviewed_at, ta.cancelled_at,
             ta.updated_at AS assignment_updated_at,
             ta.linked_checklist_task_id, ta.linked_reminder_id,
             COALESCE(NULLIF(u.name,''), u.email) AS assignee_name,
             COALESCE(NULLIF(cb.name,''), cb.email) AS created_by_name,
             COALESCE(NULLIF(ab.name,''), ab.email) AS assigned_by_name
      FROM tracs_task_assignments ta
      INNER JOIN tracs_tasks t ON t.id = ta.task_id
      INNER JOIN tracs_users u ON u.id = ta.user_id
      LEFT JOIN tracs_users cb ON cb.id = t.created_by
      LEFT JOIN tracs_users ab ON ab.id = ta.assigned_by
      WHERE ta.user_id = ? OR t.created_by = ?
      ORDER BY
        CASE
          WHEN ta.status NOT IN ('completed_on_time','completed_late','reviewed','cancelled','reassigned') AND t.due_at IS NOT NULL AND t.due_at < NOW() THEN 0
          WHEN ta.status IN ('assigned','not_started') THEN 1
          WHEN ta.status IN ('in_progress','need_review','overdue') THEN 2
          ELSE 3
        END,
        COALESCE(t.due_at, ta.assigned_at, t.created_at) ASC
      LIMIT 80
    ");
    if($stmt){
      $stmt->bind_param('ii', $uid, $uid);
      $stmt->execute();
      $task_assignment_rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
      $stmt->close();
    }
  }
} catch(Throwable $e) {
  $task_assignment_rows = [];
  $task_assignment_schema_ready = false;
  $task_assignment_can_create = false;
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

function tracs_delta_meta(int $current, int $previous, string $period_label, string $polarity = 'positive'): array {
  $diff = $current - $previous;
  $pct = $previous > 0 ? (int)round(($diff / $previous) * 100) : ($current > 0 ? 100 : 0);
  $state = $diff > 0 ? 'up' : ($diff < 0 ? 'down' : 'flat');
  $tone = 'neutral';
  $direction = $state;
  if ($state !== 'flat') {
    if ($polarity === 'negative') {
      $tone = $diff < 0 ? 'good' : 'bad';
      $direction = $diff > 0 ? 'warn' : $state;
    } elseif ($polarity === 'warning') {
      $tone = $diff < 0 ? 'good' : 'warning';
    } elseif ($polarity === 'neutral') {
      $tone = 'neutral';
    } else {
      $tone = $diff > 0 ? 'good' : 'bad';
    }
  }
  $prefix = $pct > 0 ? '+' : '';
  return [
    'state' => $state,
    'tone' => $tone,
    'direction' => $direction,
    'value' => $prefix.$pct.'%',
    'detail' => $current.' vs '.$previous.' '.$period_label,
  ];
}

[$week_start] = tracs_period_window('week');
[$month_start] = tracs_period_window('month');

$case_done_statuses = ['completed','complete','done','resolved','closed'];
$today_key = date('Y-m-d');
$new_cases_today_count = count(array_filter($cases, fn($c)=>!empty($c['created_at']) && date('Y-m-d', strtotime((string)$c['created_at'])) === $today_key));
$overdue_case_count = count(array_filter($cases, fn($c)=>!in_array(strtolower((string)($c['status']??'')), $case_done_statuses, true) && str_starts_with((string)($c['time_until']??''), 'Overdue')));
$due_today_case_count = count(array_filter($cases, fn($c)=>!empty($c['next_check_at']) && date('Y-m-d', strtotime((string)$c['next_check_at'])) === $today_key));

$stat_cards = [
  [
    'color' => 'red',
    'icon' => 'shield-alert',
    'value' => $critical_cases,
    'label' => 'Critical',
    'trend' => tracs_delta_meta(
      $critical_cases,
      tracs_count_before($cases, fn($c)=>strtolower((string)($c['priority']??'')) === 'critical', 'created_at', $week_start),
      'last week',
      'negative'
    ),
  ],
  [
    'color' => 'purple',
    'icon' => 'octagon-alert',
    'value' => $stuck_cases,
    'label' => 'Stuck',
    'trend' => tracs_delta_meta(
      $stuck_cases,
      tracs_count_before($cases, fn($c)=>strtolower((string)($c['status']??'')) === 'stuck', 'created_at', $week_start),
      'last week',
      'negative'
    ),
  ],
  [
    'color' => 'amber',
    'icon' => 'clock-3',
    'value' => $overdue_case_count,
    'label' => 'Overdue',
    'trend' => tracs_delta_meta(
      $overdue_case_count,
      tracs_count_before($cases, fn($c)=>!in_array(strtolower((string)($c['status']??'')), ['completed','complete','done','resolved','closed'], true) && str_starts_with((string)($c['time_until']??''), 'Overdue'), 'created_at', $week_start),
      'last week',
      'negative'
    ),
  ],
  [
    'color' => 'cyan',
    'icon' => 'calendar-clock',
    'value' => $due_today_case_count,
    'label' => 'Due Today',
    'trend' => tracs_delta_meta(
      $due_today_case_count,
      tracs_count_on_day($cases, fn($c)=>true, 'next_check_at', $week_start),
      'last week',
      'warning'
    ),
  ],
  [
    'color' => 'green',
    'icon' => 'list-checks',
    'value' => $pct.'%',
    'label' => 'Tasks Done',
    'trend' => tracs_delta_meta(
      (int)$pct,
      tracs_rate_before($tasks, fn($t)=>!empty($t['is_completed']), 'created_at', $month_start),
      'last month',
      'positive'
    ),
  ],
];

function dashboard_case_status(array $case): string {
  return strtolower((string)($case['status'] ?? 'pending'));
}

function dashboard_case_is_done(array $case): bool {
  return in_array(dashboard_case_status($case), ['completed', 'complete', 'done', 'resolved', 'closed'], true);
}

function dashboard_case_is_overdue(array $case): bool {
  return str_starts_with((string)($case['time_until'] ?? ''), 'Overdue');
}

function dashboard_case_is_visible(array $case): bool {
  if (dashboard_case_status($case) !== 'on_hold') {
    return true;
  }
  $reference = $case['updated_at'] ?? $case['created_at'] ?? null;
  $timestamp = $reference ? strtotime((string)$reference) : false;
  return $timestamp !== false && $timestamp >= strtotime('-24 hours');
}

function dashboard_case_priority_rank(array $case): int {
  return match(strtolower((string)($case['priority'] ?? 'low'))) {
    'critical' => 0,
    'high' => 1,
    'medium' => 2,
    default => 3,
  };
}

function dashboard_case_group_rank(array $case): int {
  if (dashboard_case_is_done($case)) return 40;
  if (dashboard_case_status($case) === 'on_hold') return 30;
  if (dashboard_case_is_overdue($case)) return 0;
  return 10;
}

function dashboard_case_time_rank(array $case): int {
  $timestamp = !empty($case['next_check_at']) ? strtotime((string)$case['next_check_at']) : false;
  if ($timestamp !== false) return $timestamp;
  $updated = !empty($case['updated_at']) ? strtotime((string)$case['updated_at']) : false;
  return $updated !== false ? $updated : PHP_INT_MAX;
}

$unchecked_tasks = array_values(array_filter($tasks, fn($t)=>empty($t['is_completed'])));
$unchecked_reminders = array_values(array_filter($reminders, fn($r)=>empty($r['is_completed'])));
$active_cases = array_values(array_filter($cases, fn($c)=>($c['status']??'') !== 'completed'));
$dashboard_cases = array_values(array_filter($cases, 'dashboard_case_is_visible'));
$dashboard_active_cases = array_values(array_filter($dashboard_cases, fn($c)=>!dashboard_case_is_done($c)));
usort($dashboard_cases, function($a, $b) {
  return dashboard_case_group_rank($a) <=> dashboard_case_group_rank($b)
    ?: dashboard_case_priority_rank($a) <=> dashboard_case_priority_rank($b)
    ?: dashboard_case_time_rank($a) <=> dashboard_case_time_rank($b)
    ?: (int)($b['id'] ?? 0) <=> (int)($a['id'] ?? 0);
});
$dashboard_case_widget_cases = array_slice($dashboard_cases, 0, 8);
$dashboard_case_widget_ids = array_flip(array_map(fn($c)=>(int)($c['id']??0), $dashboard_case_widget_cases));
foreach($dashboard_cases as $case_for_widget){
  $case_id_for_widget = (int)($case_for_widget['id'] ?? 0);
  if(dashboard_case_status($case_for_widget) === 'on_hold' && !isset($dashboard_case_widget_ids[$case_id_for_widget])){
    $dashboard_case_widget_cases[] = $case_for_widget;
    $dashboard_case_widget_ids[$case_id_for_widget] = true;
  }
}
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
$active_case_count = count($dashboard_active_cases);

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
tracs_notifications_schedule_shift_handover($conn);
$notification_center = tracs_notification_recent($conn, $uid, 8);
$notification_unread_count = (int)($notification_center['unread_count'] ?? 0);
$notification_items = array_slice($notification_center['items'] ?? [], 0, 6);
$notification_static_extra = $notif_count;
$notification_groups = [
  [
    'status' => 'cases',
    'label' => 'Cases',
    'count' => $active_case_count,
    'title' => $active_case_count.' unresolved '.($active_case_count===1?'case':'cases'),
    'meta' => $new_cases_today_count.' new today · '.count($attention_cases).' need attention',
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

function dashboard_context_excerpt(mixed $value, int $limit = 52): string {
  $text = trim((string)($value ?? ''));
  $text = trim((string)preg_replace('/\s+/', ' ', strip_tags($text)));
  if($text === '') return 'Untitled';
  if(function_exists('mb_strlen') && function_exists('mb_substr')){
    return mb_strlen($text) > $limit ? rtrim(mb_substr($text, 0, max(0, $limit - 3))).'...' : $text;
  }
  return strlen($text) > $limit ? rtrim(substr($text, 0, max(0, $limit - 3))).'...' : $text;
}

function dashboard_context_when(mixed $value): string {
  if(empty($value)) return '';
  $ts = strtotime((string)$value);
  if(!$ts) return '';
  $today = date('Y-m-d');
  $date = date('Y-m-d', $ts);
  if($date === $today) return date('H:i', $ts);
  if($date === date('Y-m-d', strtotime('+1 day'))) return 'Tomorrow '.date('H:i', $ts);
  return date('M d H:i', $ts);
}

function dashboard_context_chip(string $type, string $icon, string $label, mixed $text, string $meta = '', string $tone = ''): array {
  $meta = trim((string)$meta);
  $icon = preg_replace('/[^a-z0-9_-]/i', '', $icon) ?: '';
  return [
    'type' => preg_replace('/[^a-z0-9_-]/i', '', $type) ?: 'neutral',
    'icon' => $icon,
    'label' => $label,
    'text' => dashboard_context_excerpt($text),
    'meta' => $meta === '' ? '' : dashboard_context_excerpt($meta, 22),
    'tone' => preg_replace('/[^a-z0-9_-]/i', '', $tone) ?: '',
  ];
}

function dashboard_holiday_rows_for_year(int $year): array {
  $rows = [];
  $cacheFile = __DIR__ . '/cache/holidays/indonesia-' . $year . '.json';
  if(is_file($cacheFile)){
    $json = json_decode((string)@file_get_contents($cacheFile), true);
    if(is_array($json) && isset($json['data']) && is_array($json['data'])) $rows = array_merge($rows, $json['data']);
  }
  $fallbackFile = __DIR__ . '/assets/data/indonesia-holidays-fallback.json';
  if(is_file($fallbackFile)){
    $json = json_decode((string)@file_get_contents($fallbackFile), true);
    $fallbackRows = $json['years'][(string)$year] ?? [];
    if(is_array($fallbackRows)) $rows = array_merge($rows, $fallbackRows);
  }
  return $rows;
}

function dashboard_next_holiday_context(): ?array {
  $tz = new DateTimeZone('Asia/Jakarta');
  $today = new DateTimeImmutable('today', $tz);
  $items = [];
  foreach([(int)$today->format('Y'), (int)$today->format('Y') + 1] as $year){
    foreach(dashboard_holiday_rows_for_year($year) as $row){
      if(!is_array($row)) continue;
      $date = trim((string)($row['date'] ?? ''));
      $name = trim((string)($row['name'] ?? ($row['description'] ?? '')));
      if(!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $name === '') continue;
      $items[] = ['date' => $date, 'name' => $name];
    }
  }
  usort($items, fn($a, $b) => strcmp((string)$a['date'], (string)$b['date']));
  $todayKey = $today->format('Y-m-d');
  foreach($items as $item){
    if(($item['date'] ?? '') < $todayKey) continue;
    $holidayDate = new DateTimeImmutable($item['date'].' 00:00:00', $tz);
    $days = (int)$today->diff($holidayDate)->format('%r%a');
    return [
      'name' => $item['name'],
      'days_until' => $days,
      'meta' => $days === 0 ? 'Today' : ($days === 1 ? 'Tomorrow' : 'in '.$days.' days'),
    ];
  }
  return null;
}

function dashboard_holiday_display_name(string $holidayName = ''): string {
  $name = strtolower($holidayName);
  if(str_contains($name, 'cuti bersama')) return 'Joint Leave';
  if(str_contains($name, 'waisak') || str_contains($name, 'vesak') || str_contains($name, 'buddha')) return 'Vesak Day';
  if(str_contains($name, 'idul fitri') || str_contains($name, 'eid al-fitr')) return 'Eid al-Fitr';
  if(str_contains($name, 'idul adha') || str_contains($name, 'eid al-adha')) return 'Eid al-Adha';
  if(str_contains($name, 'maulid')) return "Prophet Muhammad's Birthday";
  if(str_contains($name, 'isra') || str_contains($name, 'miraj')) return "Isra and Mi'raj";
  if(str_contains($name, 'tahun baru islam') || str_contains($name, 'muharram')) return 'Islamic New Year';
  if(str_contains($name, 'nyepi') || str_contains($name, 'saka')) return 'Nyepi';
  if(str_contains($name, 'imlek') || str_contains($name, 'kongzili') || str_contains($name, 'chinese new year') || str_contains($name, 'lunar new year')) return 'Lunar New Year';
  if(str_contains($name, 'christmas') || str_contains($name, 'natal')) return 'Christmas Day';
  if(str_contains($name, 'good friday') || str_contains($name, 'jumat agung') || str_contains($name, 'wafat yesus')) return 'Good Friday';
  if(str_contains($name, 'easter') || str_contains($name, 'paskah')) return 'Easter';
  if(str_contains($name, 'kenaikan') || str_contains($name, 'ascension')) return 'Ascension Day';
  if(str_contains($name, 'kemerdekaan') || str_contains($name, 'republik indonesia') || str_contains($name, 'independence')) return 'Indonesian Independence Day';
  if(str_contains($name, 'pancasila')) return 'Pancasila Day';
  if(str_contains($name, 'buruh') || str_contains($name, 'labour') || str_contains($name, 'labor')) return 'Labour Day';
  if(str_contains($name, 'tahun baru') || str_contains($name, 'new year') || str_contains($name, 'masehi')) return "New Year's Day";
  return trim($holidayName) !== '' ? $holidayName : 'Public Holiday';
}

$shift_context_chips = [];
$context_tasks = array_slice($unchecked_tasks, 0, 3);
foreach($context_tasks as $task){
  $shift_context_chips[] = dashboard_context_chip('task', 'list-checks', 'Task assigned', $task['title'] ?? 'Untitled task', '', 'info');
}

$context_reminders = $unchecked_reminders;
usort($context_reminders, function($a, $b){
  $ad = !empty($a['due_date']) ? strtotime((string)$a['due_date']) : PHP_INT_MAX;
  $bd = !empty($b['due_date']) ? strtotime((string)$b['due_date']) : PHP_INT_MAX;
  return $ad <=> $bd ?: strtotime($b['created_at'] ?? '1970-01-01') <=> strtotime($a['created_at'] ?? '1970-01-01');
});
foreach(array_slice($context_reminders, 0, 2) as $reminder){
  $priority = strtolower((string)($reminder['priority'] ?? ''));
  $tone = $priority === 'critical' ? 'critical' : (in_array($priority, ['high','medium'], true) ? 'warning' : 'info');
  $shift_context_chips[] = dashboard_context_chip('reminder', 'bell', 'Reminder', $reminder['title'] ?? 'Untitled reminder', dashboard_context_when($reminder['due_date'] ?? null), $tone);
}

foreach(array_slice($dashboard_active_cases, 0, 2) as $case){
  $priority = strtolower((string)($case['priority'] ?? ''));
  $status = dashboard_case_status($case);
  $time = (string)($case['time_until'] ?? '');
  $tone = $priority === 'critical' || str_starts_with($time, 'Overdue') ? 'critical' : (in_array($status, ['stuck','on_hold'], true) ? 'warning' : 'info');
  $meta = str_starts_with($time, 'Overdue') ? 'Overdue' : ($priority === 'critical' ? 'Critical' : ucfirst(str_replace('_', ' ', $status)));
  $shift_context_chips[] = dashboard_context_chip('case', 'briefcase-business', 'Active case', $case['title'] ?? 'Untitled case', $meta, $tone);
}

$context_meetings = $scheduled_meetings;
usort($context_meetings, fn($a, $b) => strtotime($a['meeting_at'] ?? '9999-12-31') <=> strtotime($b['meeting_at'] ?? '9999-12-31'));
foreach(array_slice($context_meetings, 0, 1) as $meeting){
  $shift_context_chips[] = dashboard_context_chip('meeting', 'calendar-clock', 'Meeting', $meeting['title'] ?? 'Untitled meeting', dashboard_context_when($meeting['meeting_at'] ?? null), 'info');
}

$context_holiday = dashboard_next_holiday_context();
if($context_holiday){
  $holidayName = (string)($context_holiday['name'] ?? 'Upcoming holiday');
  $holidayDisplayName = dashboard_holiday_display_name($holidayName);
  $holidayDays = (int)($context_holiday['days_until'] ?? 0);
  $holidayText = $holidayDays === 0 ? 'Today is '.$holidayDisplayName : 'Upcoming Holiday: '.$holidayDisplayName;
  $shift_context_chips[] = dashboard_context_chip('holiday', '', 'Holiday', $holidayText, $context_holiday['meta'] ?? '', 'success');
}

// Shift Logic
$shift_change_meta = tracs_next_shift_change();
$current_shift_name = (string)$shift_change_meta['current_shift'];
$current_shift_num = (int)preg_replace('/\D+/', '', $current_shift_name);
$current_shift = [
  'num' => $current_shift_num ?: 1,
  'name' => strtoupper($current_shift_name),
  'color' => match($current_shift_name) {
    'Shift 2' => 'amber',
    'Shift 3' => 'blue',
    default => 'indigo',
  },
];
$previous_shift_meta = match($current_shift_name) {
  'Shift 1' => ['shift' => 'Shift 3', 'date' => date('Y-m-d', strtotime('-1 day'))],
  'Shift 2' => ['shift' => 'Shift 1', 'date' => date('Y-m-d')],
  default => ['shift' => 'Shift 2', 'date' => date('Y-m-d')],
};
$last_shift_reports = $SC->getHistory([
  'date' => $previous_shift_meta['date'],
  'shift' => $previous_shift_meta['shift'],
], 12, 0);
$last_shift_total = count($last_shift_reports);
$last_shift_open = array_values(array_filter($last_shift_reports, fn($r)=>($r['status']??'') === 'active'));
$last_shift_on_hold = array_values(array_filter($last_shift_reports, fn($r)=>($r['status']??'') === 'on_hold'));
$last_shift_resolved = array_values(array_filter($last_shift_reports, fn($r)=>($r['status']??'') === 'resolved'));
$last_shift_priority = array_values(array_filter($last_shift_open, fn($r)=>in_array(($r['priority']??''), ['critical','high'], true)));
$last_shift_focus = $last_shift_priority[0] ?? ($last_shift_open[0] ?? ($last_shift_on_hold[0] ?? ($last_shift_resolved[0] ?? null)));
$last_shift_ref = $previous_shift_meta['shift'].($previous_shift_meta['date'] !== date('Y-m-d') ? ' yesterday' : '');
$shift_summary_status = count(array_filter($last_shift_open, fn($r)=>($r['priority']??'') === 'critical')) > 0 ? 'critical' : (count($last_shift_open) > 0 ? 'active' : 'clear');
$shift_summary_label = $shift_summary_status === 'critical' ? 'Carryover Alert' : ($shift_summary_status === 'active' ? 'Carryover' : 'Clean Handover');
$shift_summary_title = count($last_shift_open) > 0
  ? count($last_shift_open).' open from '.$last_shift_ref
  : (count($last_shift_on_hold) > 0 ? count($last_shift_on_hold).' on hold from '.$last_shift_ref : ($last_shift_total > 0 ? 'Clean Handover' : 'No handover from '.$last_shift_ref));
$shift_summary_detail = count($last_shift_open) > 0
  ? (count($last_shift_priority) > 0 ? count($last_shift_priority).' priority item'.(count($last_shift_priority) === 1 ? '' : 's').' need follow-up' : 'Open items remain for current shift review')
  : (count($last_shift_on_hold) > 0 ? 'Pending monitoring items are visible without urgent handover' : ($last_shift_total > 0 ? 'No active handover required. Resolved items available for visibility.' : 'No recorded issue carried into this shift'));
$shift_summary_icon = $shift_summary_status === 'clear' ? 'check-circle' : 'triangle-alert';
$shift_change_at = $shift_change_meta['change_at'] instanceof DateTimeInterface ? $shift_change_meta['change_at'] : new DateTimeImmutable((string)$shift_change_meta['change_at']);
$shift_minutes_until_change = (int)ceil(((int)$shift_change_meta['seconds_until']) / 60);
$shift_handover_due_soon = (int)$shift_change_meta['seconds_until'] > 0 && (int)$shift_change_meta['seconds_until'] <= 15 * 60;
$shift_handover_time_label = $shift_change_at->format('H:i');
$shift_handover_reminder_label = $shift_handover_due_soon ? 'Handover in '.max(1, $shift_minutes_until_change).' min' : 'Next handover '.$shift_handover_time_label;
$current_user_shift_reports = $SC->getHistory([
  'date' => date('Y-m-d'),
  'shift' => $current_shift_name,
], 100, 0);
$shift_report_submitted = count(array_filter(
  $current_user_shift_reports,
  fn($report) => (int)($report['created_by'] ?? 0) === $uid
)) > 0;
$shift_server_now = (new DateTimeImmutable('now'))->format(DATE_ATOM);
$shift_end_iso = $shift_change_at->format(DATE_ATOM);
if(count($last_shift_resolved) > 0){
  $shift_context_chips[] = dashboard_context_chip('resolved', 'check-check', 'Resolved this shift', (string)count($last_shift_resolved), 'visibility', 'success');
}
$shift_context_chips = array_slice($shift_context_chips, 0, 8);
if(empty($shift_context_chips)){
  $shift_context_chips[] = dashboard_context_chip('neutral', 'activity', 'Shift context', 'No pending shift context. Continue monitoring.', '', 'neutral');
}
$shift_context_sliding = count($shift_context_chips) > 2;

$dashboard_tasks_sorted = $tasks;
usort($dashboard_tasks_sorted, function($a, $b) {
  if (($a['is_completed']??0) !== ($b['is_completed']??0)) return ($a['is_completed']??0) <=> ($b['is_completed']??0);
  return ($b['id']??0) <=> ($a['id']??0);
});

function dashboard_monitor_type_class(string $type): string {
  $class = strtolower((string)preg_replace('/[^a-z0-9]+/i', '-', $type));
  return trim($class, '-') ?: 'reminder';
}

function dashboard_monitor_type_icon(string $type): string {
  return match($type) {
    'Meeting' => 'calendar-clock',
    'Follow-up' => 'corner-down-right',
    'Shift Report' => 'clipboard-check',
    'Case Due' => 'briefcase-business',
    'Assignment' => 'user-check',
    'Schedule' => 'calendar-clock',
    'Holiday' => '',
    default => 'bell',
  };
}

function dashboard_monitor_priority_rank(string $priority): int {
  return match(strtolower($priority)) {
    'critical' => 0,
    'high' => 1,
    'medium' => 2,
    default => 3,
  };
}

function dashboard_monitor_time_label(mixed $value): string {
  if(empty($value) || strtotime((string)$value) === false) return 'No schedule';
  $ts = strtotime((string)$value);
  $day = date('Y-m-d', $ts);
  if($day === date('Y-m-d')) return 'Today '.date('H:i', $ts);
  if($day === date('Y-m-d', strtotime('+1 day'))) return 'Tomorrow '.date('H:i', $ts);
  return date('M d H:i', $ts);
}

function dashboard_monitor_related_from_text(string $text): string {
  if(preg_match('/mom\.php\?mom_id=(\d+)/i', $text, $m) || preg_match('/\bMOM\s*#?(\d+)/i', $text, $m)) return 'MOM #'.$m[1];
  if(preg_match('/\bcase\s*#?(\d+)/i', $text, $m) || preg_match('/\bticket\s*#?(\d+)/i', $text, $m)) return 'Case #'.$m[1];
  if(preg_match('/\bshift\s*([123])\b/i', $text, $m)) return 'Shift '.$m[1];
  return '';
}

function dashboard_monitor_type_from_reminder(array $reminder): string {
  $text = strtolower(trim((string)($reminder['title'] ?? '').' '.(string)($reminder['description'] ?? '')));
  if(str_contains($text, 'holiday') || str_contains($text, 'public holiday') || str_contains($text, 'hari libur') || str_contains($text, 'hari raya') || str_contains($text, 'waisak') || str_contains($text, 'vesak')) return 'Holiday';
  if(str_contains($text, 'meeting') || str_starts_with($text, 'mom:')) return 'Meeting';
  if(str_contains($text, 'mom') || str_contains($text, 'follow-up') || str_contains($text, 'follow up') || str_contains($text, 'action item')) return 'Follow-up';
  if(str_contains($text, 'shift report') || str_contains($text, 'handover')) return 'Shift Report';
  if(str_contains($text, 'case') || str_contains($text, 'ticket')) return 'Case Due';
  return 'Reminder';
}

function dashboard_monitor_make_item(
  string $type,
  mixed $title,
  mixed $due_at,
  string $priority,
  string $status,
  string $related = '',
  string $href = '#',
  bool $is_completed = false,
  string $source = 'reminder',
  int $source_id = 0
): array {
  $due_ts = !empty($due_at) && strtotime((string)$due_at) !== false ? strtotime((string)$due_at) : PHP_INT_MAX;
  $status_key = strtolower((string)preg_replace('/[^a-z0-9]+/i', '-', $status));
  return [
    'type' => $type,
    'type_class' => dashboard_monitor_type_class($type),
    'icon' => $type === 'Holiday' ? '' : dashboard_monitor_type_icon($type),
    'title' => trim((string)($title ?: 'Untitled')),
    'due_at' => $due_at,
    'due_ts' => $due_ts,
    'due_label' => dashboard_monitor_time_label($due_at),
    'related' => $related,
    'priority' => in_array($priority, ['low','medium','high','critical'], true) ? $priority : 'medium',
    'status' => trim($status) !== '' ? $status : 'Scheduled',
    'status_key' => trim($status_key, '-') ?: 'scheduled',
    'href' => $href,
    'is_completed' => $is_completed,
    'source' => $source,
    'source_id' => $source_id,
  ];
}

function dashboard_monitor_sort_items(array &$items): void {
  usort($items, function($a, $b) {
    $rank = function($item): int {
      if(!empty($item['is_completed'])) return 90;
      $status = strtolower((string)($item['status'] ?? ''));
      $type = strtolower((string)($item['type'] ?? ''));
      $dueTs = (int)($item['due_ts'] ?? PHP_INT_MAX);
      if($status === 'overdue' || ($dueTs !== PHP_INT_MAX && $dueTs < time())) return 0;
      if(in_array($status, ['due soon','pending','ongoing','active'], true) || ($dueTs !== PHP_INT_MAX && $dueTs <= strtotime('+4 hours'))) return 10;
      if($type === 'assignment' && in_array($status, ['new','assigned','not started'], true)) return 20;
      if($status === 'today' || ($dueTs !== PHP_INT_MAX && date('Y-m-d', $dueTs) === date('Y-m-d'))) return 30;
      return 40;
    };
    return $rank($a) <=> $rank($b)
      ?: (int)($a['due_ts'] ?? PHP_INT_MAX) <=> (int)($b['due_ts'] ?? PHP_INT_MAX)
      ?: dashboard_monitor_priority_rank((string)($a['priority'] ?? 'low')) <=> dashboard_monitor_priority_rank((string)($b['priority'] ?? 'low'))
      ?: strcmp((string)($a['title'] ?? ''), (string)($b['title'] ?? ''));
  });
}

function dashboard_monitor_item_tone(array $item): string {
  if(!empty($item['is_completed']) || strtolower((string)($item['status'] ?? '')) === 'completed') return 'success';
  $priority = strtolower((string)($item['priority'] ?? ''));
  $status = strtolower((string)($item['status'] ?? ''));
  if($priority === 'critical' || $status === 'overdue') return 'critical';
  if(in_array($priority, ['high','medium'], true) || in_array($status, ['pending','today','ongoing'], true)) return 'warning';
  return 'info';
}

function dashboard_monitor_item_html(array $item): string {
  $tone = dashboard_monitor_item_tone($item);
  $href = trim((string)($item['href'] ?? ''));
  $tag = ($href !== '' && $href !== '#') ? 'a' : 'div';
  $type = (string)($item['type'] ?? 'Reminder');
  $icon = trim((string)($item['icon'] ?? 'bell'));
  $priority = strtolower((string)($item['priority'] ?? 'low'));
  $source = (string)($item['source'] ?? '');
  $source_id = (int)($item['source_id'] ?? 0);
  $source_attr = $source === 'reminder' && $source_id > 0
    ? ' data-monitor-reminder-id="'.$source_id.'" data-monitor-reminder-completed="'.(!empty($item['is_completed']) ? '1' : '0').'" data-monitor-reminder-status="'.esc($item['status'] ?? 'Scheduled').'"'
    : '';
  ob_start();
  ?>
  <<?=$tag?> class="tm-feed-item is-<?=esc($tone)?> type-<?=esc((string)($item['type_class'] ?? 'reminder'))?>" <?=$tag === 'a' ? 'href="'.esc($href).'"' : ''?><?=$source_attr?>>
    <?php if($icon !== ''): ?><span class="tm-feed-icon"><i data-lucide="<?=esc($icon)?>" class="icon-sm"></i></span><?php endif; ?>
    <span class="tm-feed-main">
      <span class="tm-feed-line">
        <span class="tm-type-badge"><?=esc($type)?></span>
        <span class="tm-feed-title"><?=esc($item['title'] ?? 'Untitled')?></span>
      </span>
      <span class="tm-feed-meta">
        <span><i data-lucide="clock" class="icon-xs"></i><?=esc($item['due_label'] ?? 'No schedule')?></span>
        <?php if(!empty($item['related'])): ?><span><?=esc($item['related'])?></span><?php endif; ?>
        <span class="tm-feed-status"><?=esc($item['status'] ?? 'Scheduled')?></span>
      </span>
    </span>
    <span class="tm-priority-dot is-<?=esc(in_array($priority, ['low','medium','high','critical'], true) ? $priority : 'low')?>" title="<?=esc(ucfirst($priority))?> priority"></span>
  </<?=$tag?>>
  <?php
  return trim((string)ob_get_clean());
}

function dashboard_monitor_reminder_status(array $item): array {
  if(!empty($item['is_completed']) || strtolower((string)($item['status'] ?? '')) === 'completed') {
    return ['label' => 'Done', 'key' => 'done'];
  }
  $due_ts = (int)($item['due_ts'] ?? PHP_INT_MAX);
  $status = strtolower((string)($item['status'] ?? ''));
  if($status === 'overdue' || ($due_ts !== PHP_INT_MAX && $due_ts < time())) return ['label' => 'Overdue', 'key' => 'overdue'];
  if(in_array($status, ['today','pending','ongoing','active'], true) || ($due_ts !== PHP_INT_MAX && $due_ts <= strtotime('+4 hours'))) {
    return ['label' => 'Due Soon', 'key' => 'due-soon'];
  }
  if(strtolower((string)($item['type'] ?? '')) === 'assignment' && in_array($status, ['new','assigned','not started'], true)) {
    return ['label' => 'New', 'key' => 'new'];
  }
  return ['label' => 'Upcoming', 'key' => 'upcoming'];
}

function dashboard_monitor_reminder_item_html(array $item): string {
  $status = dashboard_monitor_reminder_status($item);
  $priority = strtolower((string)($item['priority'] ?? 'low'));
  $icon = trim((string)($item['icon'] ?? 'bell'));
  $typeClass = dashboard_monitor_type_class((string)($item['type'] ?? 'Reminder'));
  $href = trim((string)($item['href'] ?? ''));
  $tag = ($href !== '' && $href !== '#') ? 'a' : 'div';
  $source = (string)($item['source'] ?? '');
  $source_id = (int)($item['source_id'] ?? 0);
  $source_attr = $source === 'reminder' && $source_id > 0
    ? ' data-monitor-reminder-id="'.$source_id.'" data-monitor-reminder-completed="'.(!empty($item['is_completed']) ? '1' : '0').'" data-monitor-reminder-status="'.esc($item['status'] ?? 'Scheduled').'"'
    : '';
  ob_start();
  ?>
  <<?=$tag?> class="tm-reminder-list-item is-<?=esc($status['key'])?> type-<?=esc($typeClass)?>" <?=$tag === 'a' ? 'href="'.esc($href).'"' : ''?><?=$source_attr?>>
    <?php if($icon !== ''): ?><span class="tm-reminder-list-icon"><i data-lucide="<?=esc($icon)?>" class="icon-sm"></i></span><?php endif; ?>
    <span class="tm-reminder-list-main">
      <span class="tm-reminder-list-line"><span class="tm-type-badge"><?=esc($item['type'] ?? 'Reminder')?></span><span class="tm-reminder-list-title"><?=esc($item['title'] ?? 'Untitled reminder')?></span></span>
      <span class="tm-reminder-list-meta"><?=esc($item['due_label'] ?? 'No schedule')?></span>
    </span>
    <span class="tm-reminder-list-side">
      <span class="tm-reminder-status-pill is-<?=esc($status['key'])?>"><?=esc($status['label'])?></span>
      <span class="tm-priority-dot is-<?=esc(in_array($priority, ['low','medium','high','critical'], true) ? $priority : 'low')?>" title="<?=esc(ucfirst($priority))?> priority"></span>
    </span>
  </<?=$tag?>>
  <?php
  return trim((string)ob_get_clean());
}

function dashboard_monitor_reminder_list_html(array $items, string $empty_text = 'No active reminders', string $empty_help = 'Nothing is scheduled for this task lane right now.', int $limit = 7): string {
  $visible_items = array_slice(array_values($items), 0, $limit);
  ob_start();
  if(empty($visible_items)): ?>
    <div class="tm-reminder-empty">
      <i data-lucide="bell-off" class="icon-sm"></i>
      <span><?=esc($empty_text)?></span>
      <small><?=esc($empty_help)?></small>
    </div>
  <?php else: ?>
    <div class="tm-scroll tm-reminder-list">
      <?php foreach($visible_items as $item): ?><?=dashboard_monitor_reminder_item_html($item)?><?php endforeach; ?>
    </div>
  <?php endif;
  return trim((string)ob_get_clean());
}

function dashboard_assignment_status_meta(array $assignment): array {
  $raw = strtolower((string)($assignment['assignment_status'] ?? $assignment['stored_status'] ?? 'assigned'));
  $dueTs = !empty($assignment['due_at']) && strtotime((string)$assignment['due_at']) !== false ? strtotime((string)$assignment['due_at']) : null;
  if(in_array($raw, ['completed_on_time','completed_late','reviewed','cancelled','reassigned'], true)) {
    return ['label' => in_array($raw, ['completed_on_time','completed_late','reviewed'], true) ? 'Done' : ucwords(str_replace('_',' ', $raw)), 'key' => 'done'];
  }
  if($raw === 'overdue' || ($dueTs && $dueTs < time())) return ['label' => 'Overdue', 'key' => 'overdue'];
  if($dueTs && $dueTs <= strtotime('+4 hours')) return ['label' => 'Due Soon', 'key' => 'due-soon'];
  if(in_array($raw, ['assigned','not_started'], true)) return ['label' => 'New', 'key' => 'new'];
  if($raw === 'in_progress') return ['label' => 'In Progress', 'key' => 'in-progress'];
  if($raw === 'need_review') return ['label' => 'Review', 'key' => 'due-soon'];
  return ['label' => ucwords(str_replace('_',' ', $raw ?: 'Assigned')), 'key' => 'new'];
}

function dashboard_assignment_priority(array $assignment): string {
  return match(strtolower((string)($assignment['priority'] ?? 'normal'))) {
    'urgent' => 'critical',
    'high' => 'high',
    'low' => 'low',
    default => 'medium',
  };
}

function dashboard_assignment_active(array $assignment): bool {
  $status = dashboard_assignment_status_meta($assignment);
  return $status['key'] !== 'done';
}

function dashboard_assignment_row_html(array $assignment): string {
  $status = dashboard_assignment_status_meta($assignment);
  $priority = dashboard_assignment_priority($assignment);
  $aid = (int)($assignment['assignment_id'] ?? 0);
  $href = $aid > 0 ? 'monitoring.php?assignment_id='.$aid : 'monitoring.php';
  ob_start();
  ?>
  <a class="tm-assignment-row is-<?=esc($status['key'])?>" href="<?=esc($href)?>">
    <span class="tm-feed-icon"><i data-lucide="user-check" class="icon-sm"></i></span>
    <span class="tm-assignment-main">
      <span class="tm-assignment-title"><?=esc($assignment['title'] ?? 'Untitled assignment')?></span>
      <span class="tm-assignment-meta">
        <span>Assignee: <?=esc($assignment['assignee_name'] ?? 'Unassigned')?></span>
        <span>By <?=esc($assignment['created_by_name'] ?? $assignment['assigned_by_name'] ?? 'System')?></span>
        <span><?=esc(dashboard_monitor_time_label($assignment['due_at'] ?? null))?></span>
      </span>
      <?php if(!empty($assignment['description'])): ?><span class="tm-assignment-note"><?=esc($assignment['description'])?></span><?php endif; ?>
    </span>
    <span class="tm-assignment-side">
      <span class="tm-reminder-status-pill is-<?=esc($status['key'])?>"><?=esc($status['label'])?></span>
      <span class="tm-priority-dot is-<?=esc($priority)?>" title="<?=esc(ucfirst($priority))?> priority"></span>
    </span>
  </a>
  <?php
  return trim((string)ob_get_clean());
}

$mom_scheduled_reminder_ids = [];
foreach($mom_dashboard as $m){
  $scheduled_id = (int)($m['scheduled_reminder_id'] ?? 0);
  if($scheduled_id > 0) $mom_scheduled_reminder_ids[$scheduled_id] = true;
}

$task_monitor_regular_reminders = array_values(array_filter($dashboard_reminders, fn($r)=>!isset($mom_scheduled_reminder_ids[(int)($r['id'] ?? 0)])));
$task_monitor_active_reminder_count = count(array_filter($task_monitor_regular_reminders, fn($r)=>empty($r['is_completed'])));

$task_monitor_reminder_items = [];
foreach($task_monitor_regular_reminders as $r){
  $type = dashboard_monitor_type_from_reminder($r);
  if($type === 'Meeting') $type = 'Follow-up';
  $raw_text = trim((string)($r['title'] ?? '').' '.(string)($r['description'] ?? ''));
  $related = dashboard_monitor_related_from_text($raw_text);
  $task_monitor_reminder_items[] = dashboard_monitor_make_item(
    $type,
    $r['title'] ?? 'Untitled',
    $r['due_date'] ?? null,
    strtolower((string)($r['priority'] ?? 'medium')),
    !empty($r['is_completed']) ? 'Completed' : (string)($r['status'] ?? 'Scheduled'),
    $related,
    'reminders.php',
    !empty($r['is_completed']),
    'reminder',
    (int)($r['id'] ?? 0)
  );
}

$task_monitor_assignment_alert_items = [];
$task_monitor_assignment_awareness = [];
foreach($task_assignment_rows as $assignment){
  if(!dashboard_assignment_active($assignment)) continue;
  $status = dashboard_assignment_status_meta($assignment);
  $aid = (int)($assignment['assignment_id'] ?? 0);
  $task_monitor_assignment_alert_items[] = dashboard_monitor_make_item(
    'Assignment',
    $assignment['title'] ?? 'Untitled assignment',
    $assignment['due_at'] ?? ($assignment['assigned_at'] ?? null),
    dashboard_assignment_priority($assignment),
    $status['label'],
    !empty($assignment['assignee_name']) ? 'For '.$assignment['assignee_name'] : '',
    $aid > 0 ? 'monitoring.php?assignment_id='.$aid : 'monitoring.php',
    false,
    'assignment',
    $aid
  );
  if((int)($assignment['user_id'] ?? 0) === $uid && in_array($status['key'], ['new','due-soon','overdue'], true)){
    $task_monitor_assignment_awareness[] = $assignment;
  }
}
$task_monitor_assignment_awareness = array_slice($task_monitor_assignment_awareness, 0, 3);
$task_monitor_active_assignment_count = count(array_filter($task_assignment_rows, 'dashboard_assignment_active'));

$task_monitor_meeting_items = [];
foreach($mom_dashboard as $m){
  $mid = (int)($m['id'] ?? 0);
  $mtype = strtolower((string)($m['type'] ?? 'weekly'));
  $mstatus = strtolower((string)($m['status'] ?? 'upcoming'));
  $task_monitor_meeting_items[] = dashboard_monitor_make_item(
    'Meeting',
    $m['title'] ?? 'Untitled meeting',
    $m['meeting_at'] ?? ($m['created_at'] ?? null),
    $mtype === 'urgent' ? 'critical' : ($mstatus === 'ongoing' ? 'high' : 'medium'),
    $mstatus === 'ongoing' ? 'Ongoing' : 'Scheduled',
    $mid > 0 ? 'MOM #'.$mid : '',
    $mid > 0 ? 'mom.php?mom_id='.$mid : 'mom.php',
    false,
    'mom',
    $mid
  );
}
dashboard_monitor_sort_items($task_monitor_meeting_items);
$task_monitor_today_meetings = count(array_filter($task_monitor_meeting_items, fn($i)=>!empty($i['due_at']) && strtotime((string)$i['due_at']) !== false && date('Y-m-d', strtotime((string)$i['due_at'])) === date('Y-m-d')));

$task_monitor_case_due_items = [];
foreach($dashboard_active_cases as $c){
  $case_ts = !empty($c['next_check_at']) ? strtotime((string)$c['next_check_at']) : false;
  if(!$case_ts) continue;
  $priority = strtolower((string)($c['priority'] ?? 'low'));
  if($case_ts > strtotime('+48 hours') && $priority !== 'critical') continue;
  $cid = (int)($c['id'] ?? 0);
  $status = $case_ts < time() ? 'Overdue' : (date('Y-m-d', $case_ts) === date('Y-m-d') ? 'Today' : 'Upcoming');
  $task_monitor_case_due_items[] = dashboard_monitor_make_item(
    'Case Due',
    $c['title'] ?? 'Untitled case',
    $c['next_check_at'] ?? null,
    in_array($priority, ['low','medium','high','critical'], true) ? $priority : 'medium',
    $status,
    $cid > 0 ? 'Case #'.$cid : '',
    'cases.php',
    false,
    'case',
    $cid
  );
}
dashboard_monitor_sort_items($task_monitor_case_due_items);
$task_monitor_case_due_items = array_slice($task_monitor_case_due_items, 0, 5);

$task_monitor_shift_items = [];
$shift_due_value = $shift_change_at->format('Y-m-d H:i:s');
$task_monitor_shift_items[] = dashboard_monitor_make_item(
  'Shift Report',
  $shift_handover_due_soon ? 'Complete current shift report' : 'Next shift handover',
  $shift_due_value,
  $shift_handover_due_soon ? 'high' : 'low',
  $shift_handover_due_soon ? 'Pending' : 'Scheduled',
  $current_shift['name'],
  'shift-reports.php',
  false,
  'shift',
  0
);
if(count($last_shift_open) > 0){
  $task_monitor_shift_items[] = dashboard_monitor_make_item(
    'Follow-up',
    count($last_shift_open).' open from '.$last_shift_ref,
    $shift_due_value,
    count($last_shift_priority) > 0 ? 'critical' : 'high',
    'Active',
    $last_shift_ref,
    'shift-reports.php',
    false,
    'shift',
    0
  );
}

$task_monitor_followup_items = array_values(array_filter($task_monitor_reminder_items, fn($i)=>in_array($i['type'], ['Follow-up','Case Due','Shift Report'], true)));
$task_monitor_schedule_items = array_merge($task_monitor_meeting_items, $task_monitor_shift_items);
$task_monitor_operational_alert_items = array_merge($task_monitor_followup_items, $task_monitor_case_due_items, $task_monitor_shift_items);
$task_monitor_reminder_list_items = array_merge(
  array_values(array_filter($task_monitor_reminder_items, fn($i)=>empty($i['is_completed']))),
  $task_monitor_meeting_items,
  $task_monitor_shift_items,
  $task_monitor_assignment_alert_items
);
$task_monitor_upcoming_items = array_merge(
  array_values(array_filter($task_monitor_reminder_items, fn($i)=>empty($i['is_completed']))),
  $task_monitor_meeting_items,
  $task_monitor_case_due_items,
  $task_monitor_shift_items
);
dashboard_monitor_sort_items($task_monitor_followup_items);
dashboard_monitor_sort_items($task_monitor_schedule_items);
dashboard_monitor_sort_items($task_monitor_operational_alert_items);
dashboard_monitor_sort_items($task_monitor_reminder_list_items);
dashboard_monitor_sort_items($task_monitor_upcoming_items);
$task_monitor_upcoming_items = array_slice($task_monitor_upcoming_items, 0, 8);
$task_monitor_active_reminder_count = count(array_filter($task_monitor_reminder_list_items, fn($i)=>empty($i['is_completed'])));

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
        <div id="notif-badge-container" data-badge-mode="alerts" data-static-extra="<?=$notification_static_extra?>" data-unchecked-checklist="<?=$unchecked_task_count?>" data-unread-notifications="<?=$notification_unread_count?>">
          <?php if(($notif_count + $notification_unread_count) > 0): ?>
          <span class="bell-badge"><?= min($notif_count + $notification_unread_count,99) ?></span>
          <?php endif; ?>
        </div>

        <!-- Notif Dropdown -->
        <div class="notif-dropdown">
          <div class="notif-drop-head">
            <span class="notif-drop-title">Attention Center</span>
            <a href="activity.php" class="notif-drop-link">Activity Log</a>
          </div>
          <div class="notif-drop-body">
            <div class="notif-permission-callout hidden" data-tracs-notification-permission>
              <div>
                <strong>Browser alerts</strong>
                <span>Get short operational notifications while TRACS is open.</span>
              </div>
              <div class="notif-permission-actions">
                <button type="button" class="btn btn-primary btn-sm" data-tracs-notification-enable>Enable</button>
                <button type="button" class="btn btn-ghost btn-sm" data-tracs-notification-dismiss>Later</button>
              </div>
            </div>
            <div class="notif-dynamic-section" data-tracs-notification-list>
              <div class="notif-section-label">TRACS Notifications</div>
              <?php if(!empty($notification_items)): ?>
              <?php foreach($notification_items as $n): ?>
              <a class="notif-drop-item notif-system-item <?=$n['is_read']?'is-read':'is-unread'?>" href="<?=esc($n['related_url'] ?: '#')?>">
                <div class="notif-drop-status status-<?=esc($n['related_module'] ?: 'pending')?>"></div>
                <div class="notif-drop-info">
                  <div class="notif-drop-label"><?=esc(str_replace('_', ' ', $n['notification_type']))?></div>
                  <div class="notif-drop-text"><?=esc($n['title'])?></div>
                  <div class="notif-drop-meta"><?=esc($n['message'])?></div>
                </div>
              </a>
              <?php endforeach; ?>
              <?php else: ?>
              <div class="notif-drop-empty is-compact">No TRACS notifications yet</div>
              <?php endif; ?>
            </div>
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

  <div class="dashboard-content">
    <!-- ── STAT STRIP ── -->
    <div class="stat-strip dashboard-stat-strip" aria-label="Dashboard statistics">
      <?php foreach($stat_cards as $card): ?><?=tracs_render_stat_card($card)?><?php endforeach; ?>
    </div><!-- /stat-strip -->

    <!-- ── 3-COLUMN DASHBOARD GRID ── -->
    <div class="dash-grid">

    <!-- ════════════════════════════
         LEFT COL — Core Operations
    ════════════════════════════ -->
    <div class="col-left">

      <!-- OPERATIONS SUMMARY WIDGETS -->
      <div class="dashboard-widget-slider" aria-label="Operations summary widgets">
        <div class="dashboard-widget-track">
          <div class="dashboard-widget-slide">
            <a class="panel infra-dashboard-widget" href="infrastructure-pulse.php" data-infra-dashboard-widget>
              <div class="infra-dashboard-widget__head">
                <div>
                  <span>Infrastructure Pulse</span>
                  <strong>Loading</strong>
                </div>
                <i data-lucide="radar" class="dashboard-widget-main-icon"></i>
              </div>
            </a>
          </div>
          <div class="dashboard-widget-slide">
            <a class="panel shift-dashboard-widget is-<?=esc($shift_summary_status)?>" href="shift-reports.php"
              data-shift-report-reminder
              data-shift-end-time="<?=esc($shift_end_iso)?>"
              data-server-now="<?=esc($shift_server_now)?>"
              data-report-submitted="<?=$shift_report_submitted?'1':'0'?>">
              <div class="shift-dashboard-widget__head">
                <div class="shift-dashboard-widget__title">
                  <span>Shift Summary</span>
                  <strong><?=esc($shift_summary_label)?></strong>
                </div>
                <i data-lucide="<?=esc($shift_summary_icon)?>" class="dashboard-widget-main-icon"></i>
              </div>
              <div class="shift-dashboard-widget__body">
                <div class="shift-dashboard-widget__smart">
                  <span><?=esc($shift_summary_title)?></span>
                  <strong><?=esc($last_shift_focus['title'] ?? 'Current shift can continue monitoring')?></strong>
                  <p><?=esc($shift_summary_detail)?></p>
                </div>
                <div class="shift-dashboard-widget__side">
                  <div class="shift-dashboard-widget__latest">
                    <span>Last shift</span>
                    <strong><?=esc($last_shift_ref)?></strong>
                  </div>
                  <div class="shift-dashboard-widget__reminder" data-shift-reminder-state="normal" aria-live="polite">
                    <i data-lucide="bell" class="icon-xs" data-shift-reminder-icon></i>
                    <span data-shift-reminder-message><?=esc($shift_handover_reminder_label)?></span>
                    <strong data-shift-reminder-countdown>Reminder set</strong>
                  </div>
                </div>
              </div>
              <div class="shift-dashboard-widget__context" aria-label="Shift context">
                <div class="shift-context-strip__viewport">
                  <div class="shift-context-strip__track <?=$shift_context_sliding?'is-sliding':''?>">
                    <?php for($contextSet = 0; $contextSet < ($shift_context_sliding ? 2 : 1); $contextSet++): ?>
                    <div class="shift-context-strip__set" <?=$contextSet > 0 ? 'aria-hidden="true"' : ''?>>
                      <?php foreach($shift_context_chips as $chip): ?>
                      <span class="shift-context-chip is-<?=esc($chip['type'])?> <?=$chip['tone']!==''?'has-'.esc($chip['tone']):''?>">
                        <?php if(!empty($chip['icon'])): ?><i data-lucide="<?=esc($chip['icon'])?>" class="icon-xs"></i><?php endif; ?>
                        <span><b><?=esc($chip['label'])?>:</b> <?=esc($chip['text'])?></span>
                        <?php if($chip['meta'] !== ''): ?><em><?=esc($chip['meta'])?></em><?php endif; ?>
                      </span>
                      <?php endforeach; ?>
                    </div>
                    <?php endfor; ?>
                  </div>
                </div>
              </div>
            </a>
          </div>
        </div>
        <button type="button" class="dashboard-widget-next" data-dashboard-widget-next aria-label="Show next summary widget">›</button>
      </div>

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

        <?php if(empty($dashboard_case_widget_cases)): ?>
        <div class="empty">
          <div class="empty-ic"><i data-lucide="briefcase"></i></div>
          <div class="empty-t">No dashboard cases</div>
          <div class="empty-s">Click Add to create your first case</div>
        </div>
        <?php else: ?>
        <div class="dashboard-case-list scroll-y">
        <?php foreach($dashboard_case_widget_cases as $c):
          $cid  = intval($c['id']??0);
          $title= esc($c['title']??'Untitled');
          $st   = strtolower($c['status']??'pending');
          $pr   = strtolower($c['priority']??'low');
          $time = esc($c['time_until']??'—');
          $over = str_starts_with($time,'Overdue');
          [$sb,$sl] = status_badge($st);
          $pb   = prio_badge($pr);
          $day  = safe_dt($c['next_check_at']??null,'M d');
          $hr   = safe_dt($c['next_check_at']??null,'H:i');
          $ndt  = safe_dt_local($c['next_check_at']??null);
          $updated = safe_dt($c['updated_at']??null, 'M d H:i');
        ?>
        <div class="case-row <?=dashboard_case_is_done($c)?'is-done':''?>"
          data-cid="<?=$cid?>"
          data-title="<?=esc($c['title']??'')?>"
          data-status="<?=esc($st)?>"
          data-priority="<?=esc($pr)?>"
          data-next="<?=$ndt?>"
          data-notes="<?=esc($c['notes']??'')?>"
          role="button"
          tabindex="0"
          onclick="openCaseTicket(<?=$cid?>)"
          onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();openCaseTicket(<?=$cid?>)}">

          <div class="case-body">
            <div class="case-top-row">
              <span class="case-id">#<?=$cid?></span>
              <span class="case-title"><?=$title?></span>
            </div>
            <?=tracs_creator_meta($c, $c['created_at'] ?? null, false)?>
            <div class="case-date-meta"><span>Updated <?=$updated?></span></div>
          </div>

          <div class="case-status-stack">
              <span class="badge <?=$sb?>"><span class="badge-dot"></span><?=$sl?></span>
              <span class="badge <?=$pb?>"><?=ucfirst($pr)?></span>
              <?php if($over): ?><span class="badge b-critical">Overdue</span><?php else: ?><span class="case-time"><?=$time?></span><?php endif; ?>
          </div>
          <div class="case-right">
            <div class="case-when">
              <span class="case-day"><?=$day?></span>
              <span class="case-hr"><?=$hr?></span>
            </div>
            <details class="row-action-menu case-row-menu" onclick="event.stopPropagation()" onkeydown="event.stopPropagation()">
              <summary class="btn btn-ghost btn-icon" title="Case actions" aria-label="Actions for case #<?=$cid?>">
                <i data-lucide="more-vertical" class="icon-sm"></i>
              </summary>
              <div class="row-action-popover">
                <button class="btn btn-ghost btn-sm" type="button" onclick="openCaseTicket(<?=$cid?>)">Preview</button>
                <?php if($case_can_manage): ?>
                <button class="btn btn-ghost btn-sm" type="button" onclick="openEditCase(<?=$cid?>)">Edit</button>
                <?php endif; ?>
                <?php if($case_can_delete): ?>
                <button class="btn btn-danger btn-sm" type="button" onclick="deleteCase(<?=$cid?>,this)">Delete</button>
                <?php endif; ?>
              </div>
            </details>
          </div>

        </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if($total_cases>count($dashboard_case_widget_cases)): ?>
        <div class="case-more-link">
          <a href="cases.php" class="btn btn-ghost btn-sm">View all <?=$total_cases?> cases →</a>
        </div>
        <?php endif; ?>
      </div><!-- /cases panel -->

    </div><!-- /col-left -->

    <div class="dashboard-workspace">
    <!-- ════════════════════════════
         PRODUCTIVITY - Task Monitoring
    ════════════════════════════ -->
    <div class="col-productivity">
      <section class="panel task-monitoring-panel" data-task-monitoring>
        <div class="panel-head task-monitoring-head">
          <div class="task-monitoring-title">
            <span class="panel-title">Task Monitoring</span>
            <div class="task-monitoring-counters" aria-label="Task monitoring counters">
              <span title="Checklist progress"><i data-lucide="list-checks" class="icon-xs"></i><b data-task-monitor-progress><?=$done_tasks?>/<?=$total_tasks?></b></span>
              <span title="Active reminders"><i data-lucide="bell" class="icon-xs"></i><b data-task-monitor-active-reminders><?=$task_monitor_active_reminder_count?></b></span>
              <span title="Meetings today"><i data-lucide="calendar-clock" class="icon-xs"></i><b><?=$task_monitor_today_meetings?></b></span>
            </div>
          </div>
          <div class="panel-right task-monitoring-actions">
            <a href="checklist.php" class="btn btn-ghost btn-sm" data-task-monitor-all>All</a>
          </div>
        </div>

        <div class="task-monitoring-tabs" role="tablist" aria-label="Task Monitoring">
          <button type="button" class="task-monitoring-tab active" role="tab" aria-selected="true" aria-controls="tm-pane-checklist" data-task-monitor-tab="checklist" data-all-href="checklist.php"><i data-lucide="list-checks" class="icon-xs"></i>Checklist and Reminder</button>
          <button type="button" class="task-monitoring-tab" role="tab" aria-selected="false" aria-controls="tm-pane-assignments" data-task-monitor-tab="assignments" data-all-href="monitoring.php"><i data-lucide="user-check" class="icon-xs"></i>Assignments<?php if($task_monitor_active_assignment_count > 0): ?><span class="tm-tab-count"><?=esc($task_monitor_active_assignment_count)?></span><?php endif; ?></button>
          <button type="button" class="task-monitoring-tab" role="tab" aria-selected="false" aria-controls="tm-pane-activity" data-task-monitor-tab="activity" data-all-href="activity.php"><i data-lucide="activity" class="icon-xs"></i>Activity</button>
        </div>

        <div class="task-monitoring-viewport">
          <section class="task-monitoring-pane is-active" id="tm-pane-checklist" role="tabpanel" data-task-monitor-pane="checklist">
            <div class="task-monitoring-grid tm-checklist-grid">
              <div class="tm-column tm-primary">
                <div class="tm-column-head">
                  <div>
                    <span>Operational Checklist</span>
                    <strong id="prog-lbl"><?=$done_tasks?> / <?=$total_tasks?></strong>
                  </div>
                  <div class="tm-column-actions">
                    <span class="panel-counter <?=dashboard_counter_class($unchecked_task_count)?>" data-task-monitor-unchecked title="<?=$unchecked_task_count?> unchecked checklist items"><?=$unchecked_task_count?></span>
                    <button type="button" class="btn btn-primary btn-sm btn-add-reveal tm-column-add" onclick="openNewTask()" title="Add checklist item" aria-label="Add checklist item">
                      <i data-lucide="plus" class="icon-sm"></i><span class="btn-add-label">Add</span>
                    </button>
                  </div>
                </div>
                <div class="prog-wrap tm-progress">
                  <div class="prog-track"><div class="prog-fill" id="prog-fill" style="width:<?=$pct?>%"></div></div>
                  <div class="prog-info"><span>Progress</span><span id="prog-pct"><?=$pct?>%</span></div>
                </div>
                <?php if(!empty($task_monitor_assignment_awareness)): ?>
                <div class="tm-assignment-awareness">
                  <?php foreach($task_monitor_assignment_awareness as $assignment):
                    $status = dashboard_assignment_status_meta($assignment);
                    $aid = (int)($assignment['assignment_id'] ?? 0);
                  ?>
                  <button type="button" class="tm-assignment-awareness-item is-<?=esc($status['key'])?>" data-task-monitor-switch="assignments" data-assignment-href="<?=esc($aid > 0 ? 'monitoring.php?assignment_id='.$aid : 'monitoring.php')?>">
                    <span class="tm-type-badge">Assigned</span>
                    <span>New assigned task: <?=esc($assignment['title'] ?? 'Untitled assignment')?></span>
                    <i data-lucide="arrow-right" class="icon-xs"></i>
                  </button>
                  <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php if(empty($dashboard_tasks_sorted)): ?>
                <div class="tm-empty"><i data-lucide="list-checks" class="icon-sm"></i><span>No active checklist items</span></div>
                <?php else: ?>
                <div class="dashboard-checklist-scroll tm-scroll scroll-y">
                  <?php foreach($dashboard_tasks_sorted as $t):
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
                    <input type="checkbox" class="rem-check task-chk" <?=$tdone?'checked':''?> onchange="toggleTask(<?=$tid?>,this)">
                    <div class="flex1">
                      <div class="task-title <?=$tdone?'done':''?>"><?=$ttit?></div>
                      <?php if($tdesc): ?><div class="task-sub"><?=$tdesc?></div><?php endif; ?>
                      <?=tracs_creator_meta($t, $t['created_at'] ?? null, false)?>
                    </div>
                    <div class="task-acts">
                      <button class="btn btn-ghost btn-icon" onclick="openEditTask(<?=$tid?>)" title="Edit" aria-label="Edit checklist item"><i data-lucide="pencil" class="icon-sm"></i></button>
                      <button class="btn btn-danger btn-icon" onclick="deleteTask(<?=$tid?>,this)" title="Delete" aria-label="Delete checklist item"><i data-lucide="trash-2" class="icon-sm"></i></button>
                    </div>
                  </div>
                  <?php endforeach; ?>
                </div>
                <?php endif; ?>
              </div>

              <div class="tm-column tm-reminder-column">
                <div class="tm-column-head">
                  <div><span>Reminder List</span><strong><?=count($task_monitor_reminder_list_items)?> active</strong></div>
                  <div class="tm-column-actions">
                    <button type="button" class="btn btn-primary btn-sm btn-add-reveal tm-column-add" onclick="openNewReminder()" title="Add reminder" aria-label="Add reminder">
                      <i data-lucide="plus" class="icon-sm"></i><span class="btn-add-label">Add</span>
                    </button>
                  </div>
                </div>
                <?=dashboard_monitor_reminder_list_html($task_monitor_reminder_list_items, 'No active reminders', 'Checklist work has no linked reminders right now.')?>
              </div>
            </div>
          </section>

          <section class="task-monitoring-pane" id="tm-pane-assignments" role="tabpanel" data-task-monitor-pane="assignments" hidden>
            <div class="task-monitoring-grid">
              <div class="tm-column tm-primary">
                <div class="tm-column-head">
                  <div><span>Task Assignments</span><strong><?=$task_monitor_active_assignment_count?> active</strong></div>
                  <div class="tm-column-actions">
                    <a href="monitoring.php" class="btn btn-ghost btn-sm">All</a>
                    <?php if($task_assignment_can_create): ?>
                    <a href="monitoring.php?tab=assigned&amp;add=1" class="btn btn-primary btn-sm btn-add-reveal tm-column-add" title="Add task assignment" aria-label="Add task assignment">
                      <i data-lucide="plus" class="icon-sm"></i><span class="btn-add-label">Add</span>
                    </a>
                    <?php endif; ?>
                  </div>
                </div>
                <?php if(!$task_assignment_schema_ready): ?>
                <div class="tm-empty"><i data-lucide="user-check" class="icon-sm"></i><span>Task assignment module is not installed</span></div>
                <?php elseif(empty($task_assignment_rows)): ?>
                <div class="tm-empty"><i data-lucide="user-check" class="icon-sm"></i><span>No task assignments</span></div>
                <?php else: ?>
                <div class="tm-scroll tm-assignment-list">
                  <?php foreach($task_assignment_rows as $assignment): ?><?=dashboard_assignment_row_html($assignment)?><?php endforeach; ?>
                </div>
                <?php endif; ?>
              </div>

              <div class="tm-column">
                <div class="tm-column-head">
                  <div><span>Assignment Alerts</span><strong><?=count($task_monitor_assignment_alert_items)?> active</strong></div>
                </div>
                <?=dashboard_monitor_reminder_list_html($task_monitor_assignment_alert_items, 'No active assignments', 'New and due task assignments will appear here.')?>
              </div>
            </div>
          </section>

          <section class="task-monitoring-pane" id="tm-pane-activity" role="tabpanel" data-task-monitor-pane="activity" hidden>
            <div class="task-monitoring-grid">
              <div class="tm-column tm-primary">
                <div class="tm-column-head">
                  <div><span>Recent Activity</span><strong><?=count($activities)?> events</strong></div>
                </div>
                <?php if(empty($activities)): ?>
                <div class="tm-empty"><i data-lucide="activity" class="icon-sm"></i><span>No activity yet</span></div>
                <?php else: ?>
                <div class="dashboard-activity-scroll tm-scroll">
                  <?php foreach(array_slice($activities,0,10) as $a): ?>
                  <div class="act-row">
                    <div class="act-ic"><i data-lucide="<?=esc($a['icon']??'file-text')?>" class="icon-sm"></i></div>
                    <div class="flex1 min0">
                      <div class="act-text"><strong><?=esc(ucfirst($a['action']??''))?></strong><span>· <?=esc($a['module']??'')?></span></div>
                      <div class="act-desc"><?=esc($a['description']??'')?></div>
                      <div class="act-time"><?=esc($a['time_ago']??'')?> · <?=tracs_creator_meta($a, $a['created_at'] ?? null, false)?></div>
                    </div>
                  </div>
                  <?php endforeach; ?>
                </div>
                <?php endif; ?>
              </div>

              <div class="tm-column">
                <div class="tm-column-head">
                  <div><span>Reminder List</span><strong><?=count($task_monitor_reminder_list_items)?> active</strong></div>
                  <div class="tm-column-actions">
                    <button type="button" class="btn btn-primary btn-sm btn-add-reveal tm-column-add" onclick="openNewReminder()" title="Add reminder" aria-label="Add reminder">
                      <i data-lucide="plus" class="icon-sm"></i><span class="btn-add-label">Add</span>
                    </button>
                  </div>
                </div>
                <?=dashboard_monitor_reminder_list_html($task_monitor_reminder_list_items, 'No active reminders', 'Recent activity has no open task reminders.')?>
              </div>
            </div>
          </section>
        </div>
      </section>
    </div><!-- /col-productivity -->

    <!-- ════════════════════════════
         CENTER COL — Workstream
    ════════════════════════════ -->
    <div class="col-center">

      <!-- SHIFT HANDOVER PANEL -->
      <div class="panel shift-handover-panel">
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
        <div class="empty shift-handover-empty">
          <div class="empty-ic"><i data-lucide="refresh-cw"></i></div>
          <div class="empty-t">No reports today</div>
        </div>
        <?php else: ?>
        <div class="scroll-y dashboard-shift-scroll">
          <?php foreach($shift_reports as $sname => $items): ?>
          <div class="shift-group">
            <div class="shift-group-title"><?=esc($sname)?></div>
            <?php
              $shiftStatusGroups = [
                'active' => ['label' => 'Needs Handover', 'items' => array_values(array_filter($items, fn($sr) => ($sr['status'] ?? 'active') === 'active'))],
                'on_hold' => ['label' => 'On Hold / Monitoring', 'items' => array_values(array_filter($items, fn($sr) => ($sr['status'] ?? '') === 'on_hold'))],
                'resolved' => ['label' => 'Resolved This Shift', 'items' => array_values(array_filter($items, fn($sr) => ($sr['status'] ?? '') === 'resolved'))],
              ];
            ?>
            <?php foreach($shiftStatusGroups as $statusKey => $statusGroup): if(empty($statusGroup['items'])) continue; ?>
            <div class="shift-status-lane is-<?=esc($statusKey)?>">
              <div class="shift-status-lane-title"><?=esc($statusGroup['label'])?></div>
              <?php foreach($statusGroup['items'] as $sr):
              $srid=intval($sr['id']);
              $srtit=esc($sr['title']);
              $srprio=strtolower($sr['priority']);
              $srstatus=$sr['status'];
              $pclass=prio_bar($srprio);
              $statusBadge = $srstatus === 'resolved' ? 'b-resolved' : ($srstatus === 'on_hold' ? 'b-hold' : 'b-active');
              $statusText = $srstatus === 'active' ? 'Need Handover' : ucwords(str_replace('_', ' ', $srstatus));
            ?>
            <div class="shift-item <?=$srstatus==='resolved'?'resolved':''?> <?=$srstatus==='on_hold'?'on-hold':''?>"
              data-id="<?=$srid?>"
              data-title="<?=$srtit?>"
              data-shift="<?=esc($sr['shift_name'] ?? $sname)?>"
              data-prio="<?=esc($srprio)?>"
              data-status="<?=esc($srstatus)?>"
              data-details="<?=esc($sr['details'] ?? '')?>"
              data-date="<?=esc($sr['active_date'] ?? '')?>"
              data-resolution-note="<?=esc($sr['resolution_note'] ?? '')?>"
              data-resolved-at="<?=esc($sr['resolved_at'] ?? '')?>"
              onclick="openEditShiftReport(<?=$srid?>)">
              <div class="shift-priority <?=$pclass?>"></div>
              <div class="shift-text"><?=$srtit?><?=tracs_creator_meta($sr, $sr['created_at'] ?? null, false)?></div>
              <span class="badge <?=$statusBadge?>" style="transform:scale(0.8)"><?=esc($statusText)?></span>
            </div>
              <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div><!-- /shift handover -->

    </div><!-- /col-center -->

    <!-- ════════════════════════════
         RIGHT COL — Utilities
    ════════════════════════════ -->
    <div class="col-right">

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

    </div><!-- /col-right -->
    </div><!-- /dashboard-workspace -->

    </div><!-- /dash-grid -->
  </div><!-- /dashboard-content -->
</div><!-- /main-inner -->
</main>

<?php include __DIR__.'/../modules/ops-status/modal.php'; ?>
<?php $_infra_data_v = @filemtime(__DIR__.'/assets/infrastructure-pulse-data.js') ?: time(); ?>
<?php $_infra_js_v = @filemtime(__DIR__.'/assets/infrastructure-pulse.js') ?: time(); ?>
<script src="assets/infrastructure-pulse-data.js?v=<?=$_infra_data_v?>"></script>
<script src="assets/infrastructure-pulse.js?v=<?=$_infra_js_v?>"></script>
<?php include 'includes/footer.php'; ?>
