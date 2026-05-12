<?php
if(session_status()===PHP_SESSION_NONE)session_start();
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/auth/auth_check.php';
require_once __DIR__.'/../modules/activity-log/controller.php';
require_once __DIR__.'/../modules/alert-ticker/controller.php';
require_once __DIR__.'/includes/page_helpers.php';

$uid=$_SESSION['user_id']??0; $user_email=$_SESSION['user_email']??'operator@tracs.local';
$AC=new ActivityLogController($conn,$uid);
$TC=new AlertTickerController($conn,$uid);
$ticker_items=$TC->formatAlertsForTicker();

$limit=(int)($_GET['limit']??50);
$module_filter=trim($_GET['module']??'');
$q=strtolower(trim($_GET['q']??''));

$raw=$module_filter
  ? ($AC->getActivityByModule($module_filter,$limit)?:[])
  : ($AC->getRecentActivity($limit)?:[]);

$acts=[];
foreach($raw as $a){try{$acts[]=$AC->formatActivity($a);}catch(Exception $e){}}
if($q) $acts=array_filter($acts,fn($a)=>str_contains(strtolower($a['description']??''),$q)||str_contains(strtolower($a['action']??''),$q));
$acts=array_values($acts);

$today_count=$AC->getTodayCount();
$modules=['','Cases','Reminders','Checklist','Finance','Domains'];
$critical_count=0;

$page_title='Activity Log'; $active_page='activity';
include 'includes/header.php';
?>
<main class="main"><div class="main-inner">

<div class="topbar">
  <div><div class="page-title">Activity Log</div><div class="page-sub"><?=count($acts)?> entries · <?=$today_count?> today</div></div>
  <div class="topbar-right">
    <form method="get" style="display:flex;gap:7px">
      <select name="module" class="form-select" style="width:130px;padding:5px 10px;font-size:11.5px" onchange="this.form.submit()">
        <?php foreach($modules as $m):?><option value="<?=esc($m)?>" <?=$module_filter===$m?'selected':''?>><?=$m?:'All Modules'?></option><?php endforeach;?>
      </select>
      <input type="hidden" name="limit" value="<?=$limit?>">
    </form>
    <form method="get" class="search-form-wrap" style="width:200px">
      <i data-lucide="search" class="search-ic icon-sm"></i>
      <input type="text" class="search-input" placeholder="Search…" oninput="filterActs(this)">
    </form>
  </div>
</div>

<div class="stat-strip">
  <div class="stat-card blue"><div class="stat-glow"></div><div class="stat-num"><?=$today_count?></div><div class="stat-label">Today</div></div>
  <div class="stat-card green"><div class="stat-glow"></div><div class="stat-num"><?=count($acts)?></div><div class="stat-label">Shown</div></div>
</div>

<div class="panel" id="act-panel">
  <div class="panel-head">
    <span class="panel-title">Recent Activity</span>
    <div class="panel-right">
      <?php foreach([25,50,100] as $lv):?>
      <a href="?limit=<?=$lv?>&module=<?=urlencode($module_filter)?>" class="btn btn-ghost btn-sm <?=$limit===$lv?'active':''?>" style="<?=$limit===$lv?'background:var(--blue-lt);color:var(--blue);border-color:var(--blue-bd)':''?>"><?=$lv?></a>
      <?php endforeach;?>
    </div>
  </div>
  <?php if(empty($acts)):?>
  <div class="empty"><div class="empty-ic"><i data-lucide="activity"></i></div><div class="empty-t">No activity logged yet</div></div>
  <?php else: foreach($acts as $a):
    $icon=esc($a['icon']??'📝');
    $action=esc(ucfirst($a['action']??''));
    $module=esc($a['module']??'');
    $desc=esc($a['description']??'');
    $time=esc($a['time_ago']??'');
    $full_time=!empty($a['created_at'])?esc(date('d M Y H:i',strtotime($a['created_at']))):'';
  ?>
  <div class="act-row" data-act-row>
    <div class="act-ic"><i data-lucide="<?=$icon?>" class="icon-sm"></i></div>
    <div class="flex1">
      <div class="act-text"><strong><?=$action?></strong><span>· <?=$module?></span></div>
      <div class="act-desc" title="<?=$desc?>"><?=$desc?></div>
      <div class="act-time"><?=$time?><?php if($full_time):?> · <?=$full_time?><?php endif;?></div>
    </div>
  </div>
  <?php endforeach;endif;?>
</div>

</div></main>
<?php include 'includes/footer.php';?>
