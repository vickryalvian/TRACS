<?php
require_once __DIR__ . '/../core/security/csrf.php';
tracs_start_session();
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/auth/auth_check.php';
require_once __DIR__.'/../modules/checklist/controller.php';
require_once __DIR__.'/../modules/alert-ticker/controller.php';
require_once __DIR__.'/includes/page_helpers.php';

$uid=$_SESSION['user_id']??0; $user_email=$_SESSION['user_email']??'operator@tracs.local';
tracs_ensure_creator_columns($conn, 'tracs_side_tasks', 'user_id');
$KC=new ChecklistController($conn,$uid);
$TC=new AlertTickerController($conn,$uid);
$ticker_items=$TC->formatAlertsForTicker();

$all=$KC->getTasks()?:[];
$total=count($all); $done_count=count(array_filter($all,fn($t)=>!empty($t['is_completed'])));
$pending=$total-$done_count;
$pct=$total>0?round($done_count/$total*100):0;

$f=$_GET['f']??'all'; $q=strtolower(trim($_GET['q']??''));
$tasks=$all;
if($f==='pending') $tasks=array_filter($all,fn($t)=>empty($t['is_completed']));
if($f==='done')    $tasks=array_filter($all,fn($t)=>!empty($t['is_completed']));
if($q) $tasks=array_filter($tasks,fn($t)=>str_contains(strtolower($t['title']??''),$q));
$tasks=array_values($tasks);
$critical_count=0;

$page_title='Checklist'; $active_page='checklist';
include 'includes/header.php';
?>
<main class="main checklist-page"><div class="main-inner">

<div class="topbar">
  <div><div class="page-title">Checklist</div><div class="page-sub"><?=$total?> tasks · <?=$pending?> pending · <?=$pct?>% complete</div></div>
</div>

<div class="stat-strip">
  <div class="stat-card blue"><div class="stat-glow"></div><div class="stat-num"><?=$total?></div><div class="stat-label">Total Tasks</div></div>
  <div class="stat-card amber"><div class="stat-glow"></div><div class="stat-num"><?=$pending?></div><div class="stat-label">Pending</div></div>
  <div class="stat-card green"><div class="stat-glow"></div><div class="stat-num"><?=$done_count?></div><div class="stat-label">Completed</div></div>
  <div class="stat-card cyan"><div class="stat-glow"></div><div class="stat-num"><?=$pct?>%</div><div class="stat-label">Progress</div></div>
</div>

<!-- Progress bar -->
<div class="panel checklist-progress-panel">
  <div class="panel-head checklist-progress-head">
    <span class="panel-title">Completion</span>
    <div class="checklist-progress-meta">
      <span class="panel-meta" id="prog-lbl"><?=$done_count?> / <?=$total?></span>
      <span class="panel-title">Overall Progress</span>
    </div>
  </div>
  <div class="prog-wrap" style="padding-bottom:10px">
    <div class="prog-track"><div class="prog-fill" id="prog-fill" style="width:<?=$pct?>%"></div></div>
    <div class="prog-info"><span>Completion</span><span id="prog-pct"><?=$pct?>%</span></div>
  </div>
</div>

<div class="filter-search-row">
  <div class="filter-bar">
    <?php foreach(['all'=>'All','pending'=>'Pending','done'=>'Completed'] as $k=>$l):?>
    <a href="?f=<?=$k?>&q=<?=urlencode($q)?>" class="filter-tab <?=$f===$k?'active':''?>"><?=$l?></a>
    <?php endforeach;?>
  </div>
  <form method="get" class="search-form-wrap">
    <input type="hidden" name="f" value="<?=esc($f)?>">
    <i data-lucide="search" class="search-ic icon-sm"></i>
    <input type="text" name="q" class="search-input" placeholder="Search checklist task, owner context, or notes" value="<?=esc($q)?>">
  </form>
  <button class="btn btn-primary toolbar-add-btn" onclick="openNewTask()">
    <i data-lucide="plus-circle" class="icon-sm"></i>
    Add New Task
  </button>
</div>

<div class="panel checklist-list-panel">
  <div class="panel-head"><span class="panel-title">Task List</span><span class="panel-meta"><?=count($tasks)?> shown</span></div>
  <?php if(empty($tasks)):?>
  <div class="empty"><div class="empty-ic"><i data-lucide="list-checks"></i></div><div class="empty-t">No tasks found</div><div class="empty-s">Add tasks to track your operational checklist</div></div>
  <?php else: ?>
  <div class="table-wrap">
    <table class="tracs-table">
      <thead>
        <tr>
          <th style="width:40px;text-align:center"></th>
          <th>Title</th>
          <th>Created</th>
          
        </tr>
      </thead>
      <tbody>
      <?php foreach($tasks as $t):
        $tid=intval($t['id']??0);$ttit=esc($t['title']??'Untitled');
        $tdesc=esc($t['description']??'');$tdone=!empty($t['is_completed']);
        $tdate=esc($t['created_at']??'');
        $tdate_fmt=$tdate?date('d M Y',strtotime($tdate)):'';
      ?>
      <tr class="checkable-row <?=$tdone?'is-completed':''?>" data-tid="<?=$tid?>" data-completed="<?=$tdone?'1':'0'?>" data-title="<?=esc($t['title']??'')?>" data-desc="<?=esc($t['description']??'')?>">
        <td style="text-align:center"><input type="checkbox" class="rem-check task-chk" <?=$tdone?'checked':''?> onchange="toggleTask(<?=$tid?>,this.checked)"></td>
        <td style="max-width:300px">
          <div style="font-weight:500;color:var(--tx1);white-space:nowrap;overflow:hidden;text-overflow:ellipsis" class="task-title <?=$tdone?'done':''?>"><?=$ttit?></div>
          <?php if($tdesc):?><div class="task-sub" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="<?=$tdesc?>"><?=$tdesc?></div><?php endif;?>
          <?=tracs_creator_meta($t)?>
        </td>
        <td>
          <?php if($tdate_fmt):?><span class="task-date-cell"><?=$tdate_fmt?></span><?php endif;?>
          <details class="row-action-menu">
            <summary class="btn btn-ghost btn-icon" title="Actions" aria-label="Row actions"><i data-lucide="more-vertical" class="icon-sm"></i></summary>
            <div class="row-action-popover">
              <button class="btn btn-ghost btn-sm" type="button" onclick="openEditTask(<?=$tid?>)">Edit</button>
              <button class="btn btn-danger btn-sm" type="button" onclick="deleteTask(<?=$tid?>)">Delete</button>
            </div>
          </details>
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