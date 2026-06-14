<?php
require_once __DIR__ . '/../core/security/csrf.php';
tracs_start_session();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth/auth_check.php';
require_once __DIR__ . '/../core/access_control.php';
require_once __DIR__ . '/../modules/user-management/controller.php';
require_once __DIR__ . '/../modules/task-management/controller.php';
require_once __DIR__ . '/../modules/alert-ticker/controller.php';
require_once __DIR__ . '/includes/page_helpers.php';

$uid = (int)($_SESSION['user_id'] ?? 0);
$user_email = $_SESSION['user_email'] ?? 'operator@tracs.local';
if (!tracs_user_can($conn, 'users.view') && !tracs_user_can($conn, 'tasks.monitor')) {
    tracs_abort_404();
}

$UM = new UserManagementController($conn, $uid);
$TM = new TaskManagementController($conn, $uid);
$schema_ready = $UM->schemaReady();
$roles = $schema_ready ? $UM->roles() : [];
$intern_role = array_values(array_filter($roles, fn($role) => ($role['slug'] ?? '') === 'intern'))[0] ?? null;
$intern_role_id = (int)($intern_role['id'] ?? 0);
$mentor_options = $schema_ready ? $UM->mentorOptions() : [];
$intern_universities = $schema_ready ? $UM->internUniversities() : [];

$filters = $_GET;
if ($intern_role_id > 0) {
    $filters['role_id'] = $intern_role_id;
}
$interns = $schema_ready ? $UM->users($filters) : [];
$from = trim((string)($_GET['start_from'] ?? ''));
$to = trim((string)($_GET['end_to'] ?? ''));
if ($from !== '') {
    $interns = array_values(array_filter($interns, fn($u) => !empty($u['internship_start_date']) && $u['internship_start_date'] >= $from));
}
if ($to !== '') {
    $interns = array_values(array_filter($interns, fn($u) => !empty($u['internship_end_date']) && $u['internship_end_date'] <= $to));
}

$tm_interns = ($TM->schemaReady() && $TM->canMonitor()) ? $TM->interns() : [];
$tm_by_user = [];
foreach ($tm_interns as $row) {
    $tm_by_user[(int)$row['user_id']] = $row;
}

$selected_id = (int)($_GET['intern_id'] ?? ($interns[0]['id'] ?? 0));
$selected = null;
foreach ($interns as $intern) {
    if ((int)$intern['id'] === $selected_id) {
        $selected = $intern;
        break;
    }
}

$active_count = count(array_filter($interns, fn($u) => in_array((string)($u['internship_status'] ?? ''), ['upcoming','active','ending_soon','extended'], true)));
$ending_soon = count(array_filter($interns, fn($u) => ($u['internship_monitor_state'] ?? '') === 'ending_soon' || ($u['internship_monitor_state'] ?? '') === 'end_passed'));
$need_review = count(array_filter($interns, fn($u) => in_array((string)($u['evaluation_status'] ?? ''), ['not_started','in_review','needs_improvement'], true)));
$without_mentor = count(array_filter($interns, fn($u) => empty($u['mentor_user_id'])));

function im_dt(mixed $value, string $format = 'd-m-Y'): string {
    return ($value && strtotime((string)$value)) ? date($format, strtotime((string)$value)) : '-';
}
function im_status_badge(string $value): string {
    return match ($value) {
        'ending_soon', 'needs_improvement', 'in_review' => 'b-warning',
        'end_passed', 'terminated', 'failed' => 'b-critical',
        'completed', 'passed' => 'b-done',
        'extended', 'upcoming' => 'b-info',
        default => 'b-active',
    };
}

$TC = new AlertTickerController($conn, $uid);
$ticker_items = $TC->formatAlertsForTicker();
$critical_count = $ending_soon;
$page_title = 'Intern Management';
$active_page = 'intern-management';
include __DIR__ . '/includes/header.php';
?>
<main class="main"><div class="main-inner im-page">
  <div class="topbar im-topbar">
    <div>
      <div class="page-title">Intern Management</div>
      <div class="page-sub">Internship timeline, mentor notes, review status, and task performance.</div>
    </div>
    <a class="btn btn-ghost" href="/user-management.php?tab=users"><i data-lucide="users-round" class="icon-sm"></i>User Management</a>
  </div>

  <?php if(!$schema_ready): ?>
    <div class="panel"><div class="um-empty-state"><div class="empty-ic"><i data-lucide="database"></i></div><div class="empty-t">User Management schema is required</div></div></div>
  <?php else: ?>
  <section class="panel im-overview">
    <div class="im-overview-row">
      <span>Active interns</span><strong><?=esc($active_count)?></strong>
      <span>Ending soon</span><strong><?=esc($ending_soon)?></strong>
      <span>Need review</span><strong><?=esc($need_review)?></strong>
      <span>Without mentor</span><strong><?=esc($without_mentor)?></strong>
    </div>
  </section>

  <form method="get" class="panel im-filter">
    <div class="search-form-wrap"><i data-lucide="search" class="search-ic icon-sm"></i><input class="search-input" name="q" placeholder="Search name, email, university, notes" value="<?=esc($_GET['q'] ?? '')?>"></div>
    <select class="form-select compact-select" name="mentor_user_id"><option value="">Any Mentor</option><option value="-1" <?=($_GET['mentor_user_id'] ?? '')==='-1'?'selected':''?>>Without Mentor</option><?php foreach($mentor_options as $mentor): ?><option value="<?=$mentor['id']?>" <?=((string)($_GET['mentor_user_id'] ?? '')===(string)$mentor['id'])?'selected':''?>><?=esc($mentor['display_name'])?></option><?php endforeach; ?></select>
    <select class="form-select compact-select" name="internship_status"><option value="">Any Internship Status</option><?php foreach(['upcoming'=>'Upcoming','active'=>'Active','ending_soon'=>'Ending Soon','completed'=>'Completed','extended'=>'Extended','terminated'=>'Terminated'] as $v=>$l): ?><option value="<?=$v?>" <?=($_GET['internship_status'] ?? '')===$v?'selected':''?>><?=$l?></option><?php endforeach; ?></select>
    <select class="form-select compact-select" name="evaluation_status"><option value="">Any Review Status</option><?php foreach(['not_started'=>'Not Started','in_review'=>'In Review','passed'=>'Passed','needs_improvement'=>'Needs Improvement','failed'=>'Failed'] as $v=>$l): ?><option value="<?=$v?>" <?=($_GET['evaluation_status'] ?? '')===$v?'selected':''?>><?=$l?></option><?php endforeach; ?></select>
    <select class="form-select compact-select" name="university"><option value="">Any University</option><?php foreach($intern_universities as $university): ?><option value="<?=esc($university)?>" <?=($_GET['university'] ?? '')===$university?'selected':''?>><?=esc($university)?></option><?php endforeach; ?></select>
    <?=tracs_date_range_picker([
        'id' => 'internMonitoringRange',
        'start' => $_GET['start_from'] ?? '',
        'end' => $_GET['end_to'] ?? '',
        'start_name' => 'start_from',
        'end_name' => 'end_to',
        'label' => 'Internship date range',
    ])?>
    <select class="form-select compact-select" name="intern_monitor"><option value="">Any Signal</option><option value="ending_soon" <?=($_GET['intern_monitor'] ?? '')==='ending_soon'?'selected':''?>>Ending Soon</option><option value="without_mentor" <?=($_GET['intern_monitor'] ?? '')==='without_mentor'?'selected':''?>>Without Mentor</option><option value="pending_evaluation" <?=($_GET['intern_monitor'] ?? '')==='pending_evaluation'?'selected':''?>>Need Review</option></select>
    <button class="btn btn-primary" type="submit"><i data-lucide="filter" class="icon-sm"></i>Apply</button>
  </form>

  <div class="im-split">
    <section class="panel im-list-panel">
      <div class="panel-head"><span class="panel-title">Intern Directory</span><span class="panel-meta"><?=count($interns)?> shown</span></div>
      <?php if(!$interns): ?>
        <div class="um-empty-state"><div class="empty-ic"><i data-lucide="graduation-cap"></i></div><div class="empty-t">No matching interns</div></div>
      <?php else: ?>
      <div class="im-list">
        <?php foreach($interns as $intern):
          $task = $tm_by_user[(int)$intern['id']] ?? [];
          $active = (int)($task['assigned_tasks'] ?? 0);
          $done = (int)($task['completed_tasks'] ?? 0);
          $progress = $active > 0 ? round($done / $active * 100) : 0;
          $selected_class = ((int)$intern['id'] === $selected_id) ? 'is-selected' : '';
        ?>
        <a class="im-row <?=$selected_class?>" href="?<?=http_build_query(array_merge($_GET, ['intern_id' => (int)$intern['id']]))?>">
          <div>
            <strong><?=esc($intern['display_name'])?></strong>
            <span><?=esc($intern['email'])?> · <?=esc($intern['university_name'] ?: 'University not set')?></span>
          </div>
          <span class="badge <?=im_status_badge((string)($intern['internship_monitor_state'] ?: $intern['internship_status']))?>"><?=esc(ucwords(str_replace('_',' ', (string)($intern['internship_status'] ?: 'active'))))?></span>
          <div class="im-row-meta">
            <span><?=im_dt($intern['internship_start_date'])?> - <?=im_dt($intern['internship_end_date'])?></span>
            <span>Mentor: <?=esc($intern['mentor_name'] ?: 'Unassigned')?></span>
            <span><?=$progress?>% task progress</span>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </section>

    <aside class="panel im-detail-panel">
      <div class="panel-head"><span class="panel-title">Intern Profile</span></div>
      <?php if(!$selected): ?>
        <div class="um-empty-state um-empty-compact"><div class="empty-t">Select an intern to inspect timeline, notes, and task progress.</div></div>
      <?php else:
        $task = $tm_by_user[(int)$selected['id']] ?? [];
        $assigned = (int)($task['assigned_tasks'] ?? 0);
        $completed = (int)($task['completed_tasks'] ?? 0);
        $overdue = (int)($task['overdue_tasks'] ?? 0);
      ?>
      <div class="im-profile">
        <div class="im-profile-head">
          <div class="um-avatar"><?=tracs_user_initials($selected['display_name'] ?? '', $selected['email'] ?? 'I')?></div>
          <div><strong><?=esc($selected['display_name'])?></strong><span><?=esc($selected['university_name'] ?: 'University not set')?></span></div>
        </div>
        <div class="im-detail-grid">
          <div><span>Period</span><strong><?=im_dt($selected['internship_start_date'])?> - <?=im_dt($selected['internship_end_date'])?></strong></div>
          <div><span>Mentor</span><strong><?=esc($selected['mentor_name'] ?: 'Unassigned')?></strong></div>
          <div><span>Skill / capacity</span><strong><?=esc(ucwords(str_replace('_',' ', (string)($selected['skill_level'] ?: 'Not set'))))?></strong></div>
          <div><span>Review</span><strong><?=esc(ucwords(str_replace('_',' ', (string)($selected['evaluation_status'] ?: 'not_started'))))?></strong></div>
          <div><span>Assigned tasks</span><strong><?=$assigned?></strong></div>
          <div><span>Completed</span><strong><?=$completed?></strong></div>
          <div><span>Overdue</span><strong><?=$overdue?></strong></div>
          <div><span>Pending review</span><strong><?=esc($task['review_tasks'] ?? 0)?></strong></div>
        </div>
        <div class="im-notes"><span>Special notes</span><strong><?=esc($selected['special_notes'] ?: 'No special notes recorded.')?></strong></div>
        <a class="btn btn-ghost" href="/monitoring.php?tab=interns&user_id=<?=$selected['id']?>"><i data-lucide="kanban-square" class="icon-sm"></i>Open Task Monitoring</a>
      </div>
      <?php endif; ?>
    </aside>
  </div>
  <?php endif; ?>
</div></main>
<?php include __DIR__ . '/includes/footer.php'; ?>
