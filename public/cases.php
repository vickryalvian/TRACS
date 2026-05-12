<?php
if(session_status()===PHP_SESSION_NONE)session_start();
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/auth/auth_check.php';
require_once __DIR__.'/../modules/case/controller.php';
require_once __DIR__.'/../modules/alert-ticker/controller.php';
require_once __DIR__.'/includes/page_helpers.php';

$uid=$_SESSION['user_id']??0; $user_email=$_SESSION['user_email']??'operator@tracs.local';
$CC=new CaseController($conn,$uid); $TC=new AlertTickerController($conn,$uid);
$ticker_items=$TC->formatAlertsForTicker();

$all=array_map([$CC,'formatCase'],$CC->getCases()?:[]);
$total=count($all);
$critical=count(array_filter($all,fn($c)=>($c['priority']??'')==='critical'));
$stuck=count(array_filter($all,fn($c)=>($c['status']??'')==='stuck'));
$active=count(array_filter($all,fn($c)=>($c['status']??'')==='active'));
$critical_count=$critical;

$f=$_GET['f']??'all'; $q=strtolower(trim($_GET['q']??''));
$cases=$all;
if($f!=='all') $cases=array_filter($cases,fn($c)=>match($f){'critical'=>($c['priority']??'')==='critical','stuck'=>($c['status']??'')==='stuck','active'=>($c['status']??'')==='active','overdue'=>str_starts_with($c['time_until']??'','Overdue'),default=>true});
if($q) $cases=array_filter($cases,fn($c)=>str_contains(strtolower($c['title']??''),$q));
$cases=array_values($cases);

$page_title='Cases'; $active_page='cases';
include 'includes/header.php';
?>
<main class="main"><div class="main-inner">

<div class="topbar">
  <div><div class="page-title">Cases</div><div class="page-sub"><?=$total?> total · <?=$critical?> critical · <?=$stuck?> stuck</div></div>
  <div class="topbar-right">
    <button class="btn btn-primary" onclick="openNewCase()"><i data-lucide="plus-circle" class="icon-sm"></i>New Case</button>
  </div>
</div>

<div class="stat-strip">
  <div class="stat-card red"><div class="stat-glow"></div><div class="stat-num"><?=$critical?></div><div class="stat-label">Critical</div></div>
  <div class="stat-card purple"><div class="stat-glow"></div><div class="stat-num"><?=$stuck?></div><div class="stat-label">Stuck</div></div>
  <div class="stat-card green"><div class="stat-glow"></div><div class="stat-num"><?=$active?></div><div class="stat-label">Active</div></div>
  <div class="stat-card blue"><div class="stat-glow"></div><div class="stat-num"><?=$total?></div><div class="stat-label">Total</div></div>
</div>

<div class="filter-search-row">
  <div class="filter-bar">
    <?php foreach(['all'=>'All','active'=>'Active','critical'=>'Critical','stuck'=>'Stuck','overdue'=>'Overdue'] as $k=>$l):?>
    <a href="?f=<?=$k?>&q=<?=urlencode($q)?>" class="filter-tab <?=$f===$k?'active':''?>"><?=$l?></a>
    <?php endforeach;?>
  </div>
  <form method="get" class="search-form-wrap">
    <input type="hidden" name="f" value="<?=esc($f)?>">
    <i data-lucide="search" class="search-ic icon-sm"></i>
    <input type="text" name="q" class="search-input" placeholder="Search cases…" value="<?=esc($q)?>">
  </form>
</div>

<div class="panel">
  <div class="panel-head"><span class="panel-title">Case List</span><span class="panel-meta"><?=count($cases)?> shown</span></div>
  <?php if(empty($cases)):?>
  <div class="empty"><div class="empty-ic"><i data-lucide="briefcase"></i></div><div class="empty-t">No cases found</div><div class="empty-s">Try a different filter or create a new case</div></div>
  <?php else: ?>
  <div class="table-wrap">
    <table class="tracs-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Title</th>
          <th>Status</th>
          <th>Priority</th>
          <th>Next Check</th>
          <th>Time Until</th>
          <th style="width:80px"></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach($cases as $c):
        $cid=intval($c['id']??0);$title=esc($c['title']??'Untitled');
        $st=strtolower($c['status']??'pending');$pr=strtolower($c['priority']??'low');
        $time=esc($c['time_until']??'—');$over=str_starts_with($time,'Overdue');
        [$sb,$sl]=status_badge($st);$pb=prio_badge($pr);
        $ndt=safe_dt_local($c['next_check_at']??null);
        $fmt_date=safe_dt($c['next_check_at']??null, 'd M Y, H:i');
      ?>
      <tr data-cid="<?=$cid?>" data-title="<?=esc($c['title']??'')?>" data-status="<?=esc($st)?>" data-priority="<?=esc($pr)?>" data-next="<?=$ndt?>" data-notes="<?=esc($c['notes']??'')?>">
        <td class="tracs-rownum"><?=$cid?></td>
        <td style="max-width:300px">
          <div style="font-weight:500;color:var(--tx1);white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="<?=$title?>"><?=$title?></div>
        </td>
        <td><span class="badge <?=$sb?>"><span class="badge-dot"></span><?=$sl?></span></td>
        <td><span class="badge <?=$pb?>"><?=ucfirst($pr)?></span></td>
        <td style="white-space:nowrap;color:var(--tx2)"><?=$fmt_date?></td>
        <td><span class="case-time <?=$over?'ov':''?>"><?=$time?></span></td>
        <td class="tracs-acts">
          <button class="btn btn-ghost btn-icon" onclick="openEditCase(<?=$cid?>)" title="Edit"><i data-lucide="edit-2" class="icon-sm"></i></button>
          <button class="btn btn-danger btn-icon" onclick="deleteCase(<?=$cid?>)" title="Delete"><i data-lucide="trash-2" class="icon-sm"></i></button>
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
