<?php
require_once __DIR__ . '/../core/security/csrf.php';
tracs_start_session();
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/auth/auth_check.php';
require_once __DIR__.'/../core/access_control.php';
tracs_require_page_permission($conn, 'reminders.view');
require_once __DIR__.'/../modules/reminder/controller.php';
require_once __DIR__.'/../modules/alert-ticker/controller.php';
require_once __DIR__.'/includes/page_helpers.php';

$uid=$_SESSION['user_id']??0; $user_email=$_SESSION['user_email']??'operator@tracs.local';
tracs_ensure_creator_columns($conn, 'tracs_reminders', 'user_id');
$RC=new ReminderController($conn,$uid);
$TC=new AlertTickerController($conn,$uid);
$ticker_items=$TC->formatAlertsForTicker();

$all=[];
foreach($RC->getReminders()?:[] as $r){try{$all[]=$RC->formatReminder($r);}catch(Exception $e){}}

$f=$_GET['f']??'all'; $q=strtolower(trim($_GET['q']??''));
$visible_reminders=array_values(array_filter($all, fn($r)=>reminder_visible_in_checklist($r)));
$rems=$visible_reminders;
if($f!=='all') $rems=array_filter($rems,fn($r)=>match($f){
  'overdue'=>($r['status']??'')==='Overdue','today'=>($r['status']??'')==='Today',
  'upcoming'=>(($r['status']??'')!=='Overdue'&&($r['status']??'')!=='Today'&&!($r['is_completed']??0)),'done'=>!empty($r['is_completed']),default=>true});
if($q) $rems=array_filter($rems,fn($r)=>str_contains(strtolower($r['title']??''),$q));
$rems=array_values($rems);

$total=count($visible_reminders);
$overdue=count(array_filter($visible_reminders,fn($r)=>($r['status']??'')==='Overdue'));
$today=count(array_filter($visible_reminders,fn($r)=>($r['status']??'')==='Today'));
$upcoming=count(array_filter($visible_reminders,fn($r)=>($r['status']??'')!=='Overdue'&&($r['status']??'')!=='Today'&&!($r['is_completed']??0)));
$done=count(array_filter($visible_reminders,fn($r)=>!empty($r['is_completed'])));
$critical_count=$overdue;

$page_title='Reminders'; $active_page='reminders';
include 'includes/header.php';
?>
<main class="main"><div class="main-inner">

<div class="topbar">
  <div><div class="page-title">Reminders</div><div class="page-sub"><?=$total?> total · <?=$overdue?> overdue · <?=$today?> today</div></div>
</div>

<div class="stat-strip">
  <div class="stat-card red"><div class="stat-glow"></div><div class="stat-num"><?=$overdue?></div><div class="stat-label">Overdue</div></div>
  <div class="stat-card amber"><div class="stat-glow"></div><div class="stat-num"><?=$today?></div><div class="stat-label">Due Today</div></div>
  <div class="stat-card blue"><div class="stat-glow"></div><div class="stat-num"><?=$upcoming?></div><div class="stat-label">Upcoming</div></div>
  <div class="stat-card green"><div class="stat-glow"></div><div class="stat-num"><?=$done?></div><div class="stat-label">Completed</div></div>
</div>

<div class="filter-search-row">
  <div class="filter-bar">
    <?php foreach(['all'=>'All','overdue'=>'Overdue','today'=>'Today','upcoming'=>'Upcoming','done'=>'Done'] as $k=>$l):?>
    <a href="?f=<?=$k?>&q=<?=urlencode($q)?>" class="filter-tab <?=$f===$k?'active':''?>"><?=$l?><?php if($k==='overdue'&&$overdue>0):?> <span class="filter-count-badge"><?=$overdue?></span><?php endif;?></a>
    <?php endforeach;?>
  </div>
  <form method="get" class="search-form-wrap">
    <input type="hidden" name="f" value="<?=esc($f)?>">
    <i data-lucide="search" class="search-ic icon-sm"></i>
    <input type="text" name="q" class="search-input" placeholder="Search reminder title, priority, due date, or notes" value="<?=esc($q)?>">
  </form>
  <button class="btn btn-primary toolbar-add-btn" onclick="openNewReminder()">
    <i data-lucide="plus-circle" class="icon-sm"></i>
    Add Reminder
  </button>
</div>

<div class="panel">
  <div class="panel-head"><span class="panel-title">Reminder List</span><span class="panel-meta"><?=count($rems)?> shown</span></div>
  <?php if(empty($rems)):?>
  <div class="empty"><div class="empty-ic"><i data-lucide="bell"></i></div><div class="empty-t">No reminders found</div><div class="empty-s">Create one with New Reminder</div></div>
  <?php else: ?>
  <div class="table-wrap">
    <table class="tracs-table">
      <thead>
        <tr>
          <th style="width:40px;text-align:center"></th>
          <th>Title</th>
          <th>Priority</th>
          <th>Status</th>
          <th>Due Date</th>
          <th style="width:170px;text-align:right">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php 
        // Sort: Incomplete first, then by ID (Newest first)
        usort($rems, function($a, $b) {
          if (($a['is_completed']??0) !== ($b['is_completed']??0)) return ($a['is_completed']??0) <=> ($b['is_completed']??0);
          return ($b['id']??0) <=> ($a['id']??0);
        });
        foreach($rems as $r):
        $rid=intval($r['id']??0);$rtit=esc($r['title']??'Untitled');
        $rstat=$r['status']??'—';$rprio=strtolower($r['priority']??'low');
        $rdone=!empty($r['is_completed']);$rdesc=esc($r['description']??'');
        $rdue_raw=$r['due_date']??null;
        $rdue_fmt=$rdue_raw?date('d M Y, H:i',strtotime($rdue_raw)):'No date';
        $rdue_dt=safe_dt_local($rdue_raw);
        $scls=rem_status_class($rstat);$pb=prio_badge($rprio);
      ?>
      <tr class="checkable-row <?=$rdone?'is-completed':''?>" data-rid="<?=$rid?>" data-completed="<?=$rdone?'1':'0'?>" data-title="<?=esc($r['title']??'')?>" data-priority="<?=esc($rprio)?>" data-due="<?=$rdue_dt?>" data-desc="<?=esc($r['description']??'')?>">
        <td style="text-align:center"><input type="checkbox" class="rem-check" <?=$rdone?'checked':''?> onchange="toggleReminder(<?=$rid?>,this.checked)"></td>
        <td style="max-width:300px">
          <div style="font-weight:500;color:var(--tx1);white-space:nowrap;overflow:hidden;text-overflow:ellipsis" class="rem-title <?=$rdone?'done':''?>"><?=$rtit?></div>
          <?php if($rdesc):?><div class="rem-desc-inline" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="<?=$rdesc?>"><?=$rdesc?></div><?php endif;?>
          <?=tracs_creator_meta($r)?>
        </td>
        <td><span class="badge <?=$pb?>"><?=ucfirst($rprio)?></span></td>
        <td><span class="<?=$scls?>"><?=esc($rstat)?></span></td>
        <td>
          <span class="rem-date-display"><?=$rdue_fmt?></span>
        </td>
        <td>
          <div class="rem-table-actions">
            <?php if($rdone): ?>
            <button class="btn btn-ghost btn-sm rem-primary-action" type="button" onclick="toggleReminder(<?=$rid?>,false)" title="Reopen reminder">
              <i data-lucide="rotate-ccw" class="icon-xs"></i>Reopen
            </button>
            <?php else: ?>
            <button class="btn btn-success btn-sm rem-done-btn rem-primary-action" type="button" onclick="completeReminder(<?=$rid?>)" title="Mark reminder as done">
              <i data-lucide="check" class="icon-xs"></i>Mark Done
            </button>
            <?php endif; ?>
          <details class="row-action-menu">
            <summary class="btn btn-ghost btn-icon" title="Actions" aria-label="Row actions"><i data-lucide="more-vertical" class="icon-sm"></i></summary>
            <div class="row-action-popover">
              <button class="btn btn-ghost btn-sm" type="button" onclick="openEditReminder(<?=$rid?>)"><i data-lucide="pencil" class="icon-xs"></i>Edit</button>
              <button class="btn btn-danger btn-sm" type="button" onclick="deleteReminder(<?=$rid?>)"><i data-lucide="trash-2" class="icon-xs"></i>Delete</button>
            </div>
          </details>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif;?>

</div>
</div></main>
<?php include 'includes/footer.php';?>
