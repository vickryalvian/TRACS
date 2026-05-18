<?php
require_once __DIR__ . '/../core/security/csrf.php';
tracs_start_session();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth/auth_check.php';
require_once __DIR__ . '/../modules/task-management/controller.php';
require_once __DIR__ . '/../modules/alert-ticker/controller.php';
require_once __DIR__ . '/includes/page_helpers.php';

$uid = (int)($_SESSION['user_id'] ?? 0);
$user_email = $_SESSION['user_email'] ?? 'operator@tracs.local';
$TM = new TaskManagementController($conn, $uid);
$schema_ready = $TM->schemaReady();
$can_monitor = $schema_ready && $TM->canMonitor();
$can_create = $schema_ready && $TM->canCreate();
$is_monitoring_route = str_ends_with((string)($_SERVER['SCRIPT_NAME'] ?? ''), '/monitoring.php');

if (!$schema_ready) {
    $actor = tracs_get_user_by_id($conn, $uid);
    if (($actor['role_slug'] ?? '') !== 'super_admin' && !tracs_user_can($conn, 'tasks.monitor')) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
} elseif (($is_monitoring_route && !$can_monitor) || (!tracs_user_can($conn, 'tasks.view_own') && !$can_monitor)) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

function tm_flash(string $type, string $message): void {
    $_SESSION['tracs_flash'] = ['type' => $type, 'message' => $message];
}
function tm_redirect(string $tab = 'my'): never {
    $base = str_ends_with((string)($_SERVER['SCRIPT_NAME'] ?? ''), '/tasks.php') ? '/tasks.php' : '/monitoring.php';
    header('Location: ' . $base . '?tab=' . urlencode($tab));
    exit;
}
function tm_badge_class(string $value, string $kind = 'status'): string {
    if ($kind === 'priority') {
        return match ($value) {
            'urgent' => 'b-critical',
            'high' => 'b-high',
            'normal' => 'b-medium',
            default => 'b-low',
        };
    }
    return match ($value) {
        'completed', 'completed_on_time', 'reviewed' => 'b-active',
        'completed_late' => 'b-warning',
        'overdue', 'cancelled' => 'b-critical',
        'need_review' => 'b-warning',
        'in_progress' => 'b-info',
        default => 'b-low',
    };
}
function tm_label(string $value): string {
    return ucwords(str_replace('_', ' ', $value));
}
function tm_duration(?int $seconds): string {
    if ($seconds === null) return '-';
    if ($seconds <= 0) return '-';
    $seconds = abs($seconds);
    $days = intdiv($seconds, 86400);
    $hours = intdiv($seconds % 86400, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    if ($days > 0) return $days . 'd' . ($hours ? ' ' . $hours . 'h' : '');
    if ($hours > 0) return $hours . 'h' . ($minutes ? ' ' . $minutes . 'm' : '');
    return max(1, $minutes) . 'm';
}
function tm_time_delta(?string $dueAt, string $status): array {
    if (!$dueAt || !strtotime($dueAt)) return ['label' => '-', 'class' => ''];
    if (in_array($status, ['completed_on_time','completed_late','reviewed','cancelled'], true)) return ['label' => '-', 'class' => ''];
    $delta = strtotime($dueAt) - time();
    return $delta < 0
        ? ['label' => 'Overdue ' . tm_duration($delta), 'class' => 'tm-danger']
        : ['label' => tm_duration($delta) . ' left', 'class' => 'tm-ok'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!$schema_ready) {
        tm_flash('error', 'Run config/migrations/2026_05_18_task_management.sql before saving tasks.');
        tm_redirect('my');
    }
    try {
        $action = (string)($_POST['action'] ?? '');
        $result = match ($action) {
            'create_task' => $TM->create($_POST, tracs_current_user_display($conn)),
            'update_assignment' => $TM->updateAssignment($_POST),
            default => throw new InvalidArgumentException('Unknown task action.'),
        };
        tm_flash('success', $result['message'] ?? 'Saved.');
        tm_redirect((string)($_POST['return_tab'] ?? 'my'));
    } catch (Throwable $e) {
        tm_flash('error', $e->getMessage());
        tm_redirect((string)($_POST['return_tab'] ?? 'my'));
    }
}

$flash = $_SESSION['tracs_flash'] ?? null;
unset($_SESSION['tracs_flash']);

$allowed_tabs = $can_monitor ? ['my','assigned','monitoring','interns','review'] : ['my'];
$requested_tab = (string)($_GET['tab'] ?? 'my');
$tab = in_array($requested_tab, $allowed_tabs, true) ? $requested_tab : 'my';

$summary = $schema_ready ? $TM->summary() : [];
$users = $schema_ready ? $TM->users() : [];
$roles = $schema_ready ? $TM->roles() : [];
$divisions = $schema_ready ? $TM->divisions() : [];
$filters = [
    'status' => $_GET['status'] ?? '',
    'priority' => $_GET['priority'] ?? '',
    'category' => $_GET['category'] ?? '',
    'user_id' => $_GET['user_id'] ?? '',
    'division_id' => $_GET['division_id'] ?? '',
    'role_id' => $_GET['role_id'] ?? '',
    'due_date' => $_GET['due_date'] ?? '',
    'intern_only' => $tab === 'interns' ? '1' : ($_GET['intern_only'] ?? ''),
];
$tasks = $schema_ready ? $TM->tasks($filters) : [];
$performance = ($schema_ready && $tab === 'monitoring') ? $TM->performance() : [];
$interns = ($schema_ready && $tab === 'interns') ? $TM->interns() : [];
$selected_task = $tasks[0] ?? null;

$TC = new AlertTickerController($conn, $uid);
$ticker_items = $TC->formatAlertsForTicker();
$critical_count = (int)($summary['overdue_tasks'] ?? 0);
$page_title = 'Task Management & Monitoring';
$active_page = 'monitoring';
include __DIR__ . '/includes/header.php';
?>
<main class="main"><div class="main-inner tm-page">
  <div class="topbar tm-topbar">
    <div>
      <div class="page-title">Task Management & Monitoring</div>
      <div class="page-sub">Assign daily work, watch overdue risk, and keep intern progress traceable.</div>
    </div>
    <?php if($can_create): ?><button type="button" class="btn btn-primary" onclick="openModal('tmTask')"><i data-lucide="plus-circle" class="icon-sm"></i>Add Task</button><?php endif; ?>
  </div>

  <?php if($flash): ?><div class="panel tm-flash tm-flash-<?=esc($flash['type'])?>"><?=esc($flash['message'])?></div><?php endif; ?>

  <?php if(!$schema_ready): ?>
    <div class="panel"><div class="um-empty-state"><div class="empty-ic"><i data-lucide="database"></i></div><div class="empty-t">Task Management schema is not installed yet</div><div class="empty-sub">Run <code>config/migrations/2026_05_18_task_management.sql</code>, then reload this page.</div></div></div>
  <?php else: ?>
    <section class="panel tm-metrics-strip">
      <div><span>Total assigned</span><strong><?=esc($summary['total_assigned'] ?? 0)?></strong></div>
      <div><span>Active</span><strong><?=esc($summary['active_tasks'] ?? 0)?></strong></div>
      <div><span>Not started</span><strong><?=esc($summary['not_started'] ?? 0)?></strong></div>
      <div><span>In progress</span><strong><?=esc($summary['in_progress'] ?? 0)?></strong></div>
      <div><span>Completed today</span><strong><?=esc($summary['completed_today'] ?? 0)?></strong></div>
      <div><span>Overdue</span><strong><?=esc($summary['overdue_tasks'] ?? 0)?></strong></div>
      <div><span>Late</span><strong><?=esc($summary['completed_late'] ?? 0)?></strong></div>
      <div><span>Need review</span><strong><?=esc($summary['need_review'] ?? 0)?></strong></div>
    </section>
    <section class="panel tm-rate-strip">
      <div><span>Avg completion</span><strong><?=tm_duration(isset($summary['avg_completion_seconds']) ? (int)$summary['avg_completion_seconds'] : null)?></strong></div>
      <div><span>Fastest</span><strong><?=tm_duration(isset($summary['fastest_completion_seconds']) ? (int)$summary['fastest_completion_seconds'] : null)?></strong></div>
      <div><span>Slowest</span><strong><?=tm_duration(isset($summary['slowest_completion_seconds']) ? (int)$summary['slowest_completion_seconds'] : null)?></strong></div>
      <div><span>Completion rate</span><strong><?=esc($summary['completion_rate'] ?? 0)?>%</strong></div>
      <div><span>On-time rate</span><strong><?=esc($summary['on_time_rate'] ?? 0)?>%</strong></div>
      <div><span>Overdue rate</span><strong><?=esc($summary['overdue_rate'] ?? 0)?>%</strong></div>
    </section>

    <div class="filter-bar tm-tabs">
      <a class="filter-tab <?=$tab==='my'?'active':''?>" href="?tab=my"><i data-lucide="check-square" class="icon-sm"></i>My Tasks</a>
      <?php if($can_monitor): ?>
      <a class="filter-tab <?=$tab==='assigned'?'active':''?>" href="?tab=assigned"><i data-lucide="send" class="icon-sm"></i>Assigned Tasks</a>
      <a class="filter-tab <?=$tab==='monitoring'?'active':''?>" href="?tab=monitoring"><i data-lucide="activity" class="icon-sm"></i>Monitoring</a>
      <a class="filter-tab <?=$tab==='interns'?'active':''?>" href="?tab=interns"><i data-lucide="graduation-cap" class="icon-sm"></i>Intern Monitoring</a>
      <a class="filter-tab <?=$tab==='review'?'active':''?>" href="?tab=review&status=need_review"><i data-lucide="clipboard-check" class="icon-sm"></i>Review Queue</a>
      <?php endif; ?>
    </div>

    <form method="get" class="tm-filter panel">
      <input type="hidden" name="tab" value="<?=esc($tab)?>">
      <?php if($can_monitor): ?>
      <select class="form-select compact-select" name="user_id"><option value="">All Users</option><?php foreach($users as $u): ?><option value="<?=$u['id']?>" <?=((string)($_GET['user_id'] ?? '')===(string)$u['id'])?'selected':''?>><?=esc($u['display_name'])?></option><?php endforeach; ?></select>
      <select class="form-select compact-select" name="role_id"><option value="">All Roles</option><?php foreach($roles as $r): ?><option value="<?=$r['id']?>" <?=((string)($_GET['role_id'] ?? '')===(string)$r['id'])?'selected':''?>><?=esc($r['name'])?></option><?php endforeach; ?></select>
      <select class="form-select compact-select" name="division_id"><option value="">All Divisions</option><?php foreach($divisions as $d): ?><option value="<?=$d['id']?>" <?=((string)($_GET['division_id'] ?? '')===(string)$d['id'])?'selected':''?>><?=esc($d['name'])?></option><?php endforeach; ?></select>
      <?php endif; ?>
      <select class="form-select compact-select" name="status"><option value="">Any Status</option><?php foreach(['assigned','not_started','in_progress','completed_on_time','completed_late','overdue','need_review','reviewed','cancelled','reassigned'] as $s): ?><option value="<?=$s?>" <?=($_GET['status'] ?? '')===$s?'selected':''?>><?=tm_label($s)?></option><?php endforeach; ?></select>
      <select class="form-select compact-select" name="priority"><option value="">Any Priority</option><?php foreach(['low','normal','high','urgent'] as $p): ?><option value="<?=$p?>" <?=($_GET['priority'] ?? '')===$p?'selected':''?>><?=tm_label($p)?></option><?php endforeach; ?></select>
      <select class="form-select compact-select" name="category"><option value="">Any Category</option><?php foreach(['daily_checklist','case_follow_up','domain_transfer','balance_transfer','finance_log_mutasi','ssl_check','mom_follow_up','training_task','intern_task','custom'] as $c): ?><option value="<?=$c?>" <?=($_GET['category'] ?? '')===$c?'selected':''?>><?=tm_label($c)?></option><?php endforeach; ?></select>
      <input class="form-input" type="date" name="due_date" value="<?=esc($_GET['due_date'] ?? '')?>" aria-label="Due date">
      <?php if($can_monitor): ?><label class="tm-check"><input type="checkbox" name="intern_only" value="1" <?=!empty($_GET['intern_only'])?'checked':''?>><span>Intern only</span></label><?php endif; ?>
      <button class="btn btn-primary" type="submit"><i data-lucide="filter" class="icon-sm"></i>Apply</button>
    </form>

    <?php if($tab === 'monitoring'): ?>
      <div class="panel tm-panel">
        <div class="panel-head"><span class="panel-title">User Performance Snapshot</span><span class="panel-meta"><?=count($performance)?> users</span></div>
        <div class="tm-performance-grid">
          <?php foreach($performance as $p): $rate=((int)$p['assigned_tasks']>0?round(((int)$p['completed_tasks']/(int)$p['assigned_tasks'])*100):0); ?>
          <div class="tm-user-snapshot">
            <div><strong><?=esc($p['user_name'])?></strong><span><?=esc($p['role_name'] ?? 'Role')?> · <?=esc($p['division_name'] ?? 'No division')?></span></div>
            <div class="tm-snap-stats"><span><?=esc($p['assigned_tasks'])?> assigned</span><span><?=esc($p['completed_tasks'])?> done</span><span><?=esc($p['overdue_tasks'])?> overdue</span><span><?=esc($p['need_review'] ?? 0)?> review</span><span><?=tm_duration(isset($p['avg_completion_seconds']) ? (int)$p['avg_completion_seconds'] : null)?> avg</span><span><?=esc($p['on_time_rate'] ?? 0)?>% on time</span></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if($tab === 'interns'): ?>
      <div class="panel tm-panel">
        <div class="panel-head"><span class="panel-title">Intern Monitoring</span><span class="panel-meta"><?=count($interns)?> interns</span></div>
        <div class="tm-intern-grid">
          <?php foreach($interns as $i): ?>
          <div class="tm-intern-card">
            <div><strong><?=esc($i['user_name'])?></strong><span><?=esc($i['university_name'])?> · <?=esc($i['study_program'] ?? 'Program not set')?></span></div>
            <div class="tm-intern-meta"><span><?=esc($i['internship_start_date'])?> to <?=esc($i['internship_end_date'])?></span><span>Mentor: <?=esc($i['mentor_name'] ?? 'No mentor')?></span><span><?=esc(tm_label($i['skill_level'] ?? 'beginner'))?></span></div>
            <div class="tm-snap-stats"><span><?=esc($i['assigned_tasks'])?> tasks</span><span><?=esc($i['completed_tasks'])?> completed</span><span><?=esc($i['overdue_tasks'] ?? 0)?> overdue</span><span><?=esc($i['review_tasks'])?> review</span><span><?=tm_duration(isset($i['avg_completion_seconds']) ? (int)$i['avg_completion_seconds'] : null)?> avg</span></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="tm-split-layout">
    <div class="panel tm-panel">
      <div class="panel-head"><span class="panel-title"><?=esc($tab === 'my' ? 'My Task List' : 'Task Assignment Overview')?></span><span class="panel-meta"><?=count($tasks)?> shown</span></div>
      <?php if(!$tasks): ?>
        <div class="um-empty-state"><div class="empty-ic"><i data-lucide="list-checks"></i></div><div class="empty-t">No matching tasks</div><div class="empty-sub">Assigned tasks also appear in Checklist, and timed tasks appear in Reminders.</div></div>
      <?php else: ?>
      <div class="table-wrap">
        <table class="tracs-table tm-table">
          <thead><tr><th>Task</th><th>Assigned To</th><th>Priority</th><th>Due</th><th>Status</th><th>Time Left / Overdue</th><th>Completion Time</th><th>Last Update</th><th>Actions</th></tr></thead>
          <tbody>
          <?php foreach($tasks as $task): $delta = tm_time_delta($task['due_at'] ?? null, (string)$task['assignment_status']); ?>
            <tr>
              <td><strong><?=esc($task['title'])?></strong><?php if(!empty($task['description'])): ?><span><?=esc($task['description'])?></span><?php endif; ?></td>
              <td><?=esc($task['assignee_name'])?><span><?=esc($task['role_name'] ?? '')?> · <?=esc($task['division_name'] ?? 'No division')?></span></td>
              <td><span class="badge <?=tm_badge_class($task['priority'], 'priority')?>"><?=esc(tm_label($task['priority']))?></span></td>
              <td><?=!empty($task['due_at']) ? esc(date('d M Y, H:i', strtotime($task['due_at']))) : '—'?></td>
              <td><span class="badge <?=tm_badge_class($task['assignment_status'])?>"><?=esc(tm_label($task['assignment_status']))?></span></td>
              <td><span class="<?=esc($delta['class'])?>"><?=esc($delta['label'])?></span></td>
              <td><?=tm_duration(isset($task['completion_seconds']) ? (int)$task['completion_seconds'] : null)?></td>
              <td><?=!empty($task['assignment_updated_at']) ? esc(date('d M Y, H:i', strtotime($task['assignment_updated_at']))) : '—'?></td>
              <td><button type="button" class="btn btn-ghost btn-sm" onclick="tmOpenUpdate(<?=$task['assignment_id']?>,'<?=esc($task['assignment_status'])?>')"><i data-lucide="pencil" class="icon-xs"></i>Update</button></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
    <aside class="panel tm-detail-panel">
      <div class="panel-head"><span class="panel-title">Task Timing Insight</span></div>
      <?php if(!$selected_task): ?>
        <div class="um-empty-state um-empty-compact"><div class="empty-t">Select or create a task to see SLA timing, reminder, and review details.</div></div>
      <?php else: $delta = tm_time_delta($selected_task['due_at'] ?? null, (string)$selected_task['assignment_status']); ?>
      <div class="tm-detail-body">
        <div class="tm-detail-title"><strong><?=esc($selected_task['title'])?></strong><span><?=esc($selected_task['assignee_name'])?> · <?=esc(tm_label($selected_task['category']))?></span></div>
        <div class="tm-detail-grid">
          <div><span>SLA status</span><strong class="<?=esc($delta['class'])?>"><?=esc($delta['label'])?></strong></div>
          <div><span>Status</span><strong><?=esc(tm_label($selected_task['assignment_status']))?></strong></div>
          <div><span>Assigned</span><strong><?=!empty($selected_task['assigned_at']) ? esc(date('d M Y, H:i', strtotime($selected_task['assigned_at']))) : '-'?></strong></div>
          <div><span>Started</span><strong><?=!empty($selected_task['started_at']) ? esc(date('d M Y, H:i', strtotime($selected_task['started_at']))) : '-'?></strong></div>
          <div><span>Completed</span><strong><?=!empty($selected_task['completed_at']) ? esc(date('d M Y, H:i', strtotime($selected_task['completed_at']))) : '-'?></strong></div>
          <div><span>Completion duration</span><strong><?=tm_duration(isset($selected_task['completion_seconds']) ? (int)$selected_task['completion_seconds'] : null)?></strong></div>
          <div><span>Start delay</span><strong><?=tm_duration(isset($selected_task['start_delay_seconds']) ? (int)$selected_task['start_delay_seconds'] : null)?></strong></div>
          <div><span>Overdue duration</span><strong><?=tm_duration(isset($selected_task['overdue_seconds']) ? (int)$selected_task['overdue_seconds'] : null)?></strong></div>
          <div><span>Reminder</span><strong><?=!empty($selected_task['linked_reminder_id']) ? 'Linked #' . (int)$selected_task['linked_reminder_id'] : 'No timed reminder'?></strong></div>
          <div><span>Review</span><strong><?=!empty($selected_task['reviewed_at']) ? 'Reviewed' : ($selected_task['requires_review'] ? 'Required' : 'Optional')?></strong></div>
        </div>
      </div>
      <?php endif; ?>
    </aside>
    </div>
  <?php endif; ?>
</div></main>

<?php if($schema_ready && $can_create): ?>
<div class="modal-overlay hidden" id="tmTaskModal">
  <form method="post" class="modal modal-lg">
    <?=csrf_input()?><input type="hidden" name="action" value="create_task"><input type="hidden" name="return_tab" value="<?=esc($tab)?>">
    <div class="modal-head"><div><div class="modal-title">Add Task</div><div class="modal-sub">Assign once or daily, with checklist and reminder sync.</div></div><button type="button" class="modal-close" onclick="closeModal('tmTask')"><i data-lucide="x"></i></button></div>
    <div class="modal-body tm-form">
      <div class="form-row"><div class="form-group"><label class="form-label">Task Title</label><input class="form-input" name="title" required></div><div class="form-group"><label class="form-label">Category</label><select class="form-select" name="category"><?php foreach(['daily_checklist','case_follow_up','domain_transfer','balance_transfer','finance_log_mutasi','ssl_check','mom_follow_up','training_task','intern_task','custom'] as $c): ?><option value="<?=$c?>"><?=tm_label($c)?></option><?php endforeach; ?></select></div></div>
      <div class="form-group"><label class="form-label">Instruction</label><textarea class="form-textarea" name="description" rows="4"></textarea></div>
      <div class="form-row"><div class="form-group"><label class="form-label">Priority</label><select class="form-select" name="priority"><option value="normal">Normal</option><option value="low">Low</option><option value="high">High</option><option value="urgent">Urgent</option></select></div><div class="form-group"><label class="form-label">Reference URL</label><input class="form-input" type="url" name="reference_url" placeholder="https://..."></div></div>
      <div class="form-row"><div class="form-group"><label class="form-label">Due Date</label><input class="form-input" type="date" name="due_date"></div><div class="form-group"><label class="form-label">Due Time</label><input class="form-input" type="time" name="due_time"></div></div>
      <div class="form-row"><label class="tm-check"><input type="checkbox" name="is_recurring" value="1"><span>Daily recurring task</span></label><label class="tm-check"><input type="checkbox" name="requires_review" value="1"><span>Require review after completion</span></label></div>
      <div class="form-row"><div class="form-group"><label class="form-label">Assign Users</label><select class="form-select" name="assignee_user_ids[]" multiple><?php foreach($users as $u): ?><option value="<?=$u['id']?>"><?=esc($u['display_name'])?></option><?php endforeach; ?></select></div><div class="form-group"><label class="form-label">Assign Roles</label><select class="form-select" name="assignee_role_ids[]" multiple><?php foreach($roles as $r): ?><option value="<?=$r['id']?>"><?=esc($r['name'])?></option><?php endforeach; ?></select></div></div>
      <div class="form-group"><label class="form-label">Assign Divisions</label><select class="form-select" name="assignee_division_ids[]" multiple><?php foreach($divisions as $d): ?><option value="<?=$d['id']?>"><?=esc($d['name'])?></option><?php endforeach; ?></select></div>
    </div>
    <div class="modal-foot"><button type="button" class="btn btn-ghost" onclick="closeModal('tmTask')">Cancel</button><button type="submit" class="btn btn-primary"><i data-lucide="send" class="icon-sm"></i>Assign Task</button></div>
  </form>
</div>
<?php endif; ?>

<?php if($schema_ready): ?>
<div class="modal-overlay hidden" id="tmUpdateModal">
  <form method="post" class="modal">
    <?=csrf_input()?><input type="hidden" name="action" value="update_assignment"><input type="hidden" name="assignment_id" id="tmAssignmentId"><input type="hidden" name="return_tab" value="<?=esc($tab)?>">
    <div class="modal-head"><div><div class="modal-title">Update Task</div><div class="modal-sub">Progress is saved to the assignment history.</div></div><button type="button" class="modal-close" onclick="closeModal('tmUpdate')"><i data-lucide="x"></i></button></div>
    <div class="modal-body">
      <div class="form-group"><label class="form-label">Status</label><select class="form-select" name="status" id="tmStatus"><?php foreach(['assigned','not_started','in_progress','completed','need_review','reviewed','cancelled','reassigned'] as $s): ?><option value="<?=$s?>"><?=tm_label($s)?></option><?php endforeach; ?></select></div>
      <div class="form-group"><label class="form-label">Progress / Completion Note</label><textarea class="form-textarea" name="progress_note" rows="4"></textarea></div>
      <?php if($can_monitor): ?><div class="form-group"><label class="form-label">Review Note</label><textarea class="form-textarea" name="review_note" rows="3"></textarea></div><?php endif; ?>
    </div>
    <div class="modal-foot"><button type="button" class="btn btn-ghost" onclick="closeModal('tmUpdate')">Cancel</button><button type="submit" class="btn btn-primary"><i data-lucide="check" class="icon-sm"></i>Save Update</button></div>
  </form>
</div>
<script>
function tmOpenUpdate(id,status){document.getElementById('tmAssignmentId').value=id;document.getElementById('tmStatus').value=status||'in_progress';openModal('tmUpdate');window.TRACSDropdowns?.syncAll();}
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
