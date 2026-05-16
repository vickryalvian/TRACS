<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../core/security/csrf.php';
tracs_start_session();
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/auth/auth_check.php';
require_once __DIR__.'/../modules/mom/controller.php';
require_once __DIR__.'/../modules/reminder/controller.php';
require_once __DIR__.'/../modules/case/controller.php';
require_once __DIR__.'/../modules/alert-ticker/controller.php';
require_once __DIR__.'/includes/page_helpers.php';

$uid=$_SESSION['user_id']??0; $user_email=$_SESSION['user_email']??'operator@tracs.local';
tracs_ensure_creator_columns($conn, 'tracs_cases', 'user_id');
tracs_ensure_creator_columns($conn, 'tracs_reminders', 'user_id');

$MC=new MOMController($conn,$uid);
$RC=new ReminderController($conn,$uid);
$CC=new CaseController($conn,$uid);
$TC=new AlertTickerController($conn,$uid);
$mom_installed=$MC->isInstalled();

// Get MOM ID from URL, or start new
$mom_id=intval($_GET['mom_id']??0);
$ticker_items=$TC->formatAlertsForTicker();

$mom=null;
$mom_details=[];
$related_reminders=[];
$related_cases=[];
$screenshots=[];
$weekly_suggestions=[];

if($mom_installed && $mom_id>0){
  $mom=$MC->getMOM($mom_id);
  if($mom){
    $mom_details=$MC->formatMOM($mom);
    $related_reminders=$MC->getRelatedReminders($mom_id);
    $related_cases=$MC->getRelatedCases($mom_id);
    $screenshots=$MC->getScreenshots($mom_id);
  }
}else{
  // New MOM — suggest agenda items
  $mom_details=['id'=>0,'title'=>'','type'=>'weekly','status'=>'upcoming','objective'=>'','participants'=>'','created_at'=>date('Y-m-d H:i:s')];
}

// Fetch weekly suggestions (unresolved cases from last 7 days)
$weekly_suggestions=$mom_installed ? $MC->getWeeklySuggestions() : [];

// Fetch all MOM meetings for list
$all_moms=$mom_installed ? array_map([$MC,'formatMOM'],$MC->getMOMs()?:[]) : [];

function mom_recent_history(array $mom): bool {
  $status = $mom['status'] ?? '';
  if(!in_array($status, ['completed','cancelled'], true)) return false;
  $checked_at = $mom['completed_at'] ?? $mom['cancelled_at'] ?? $mom['updated_at'] ?? null;
  if(empty($checked_at)) return false;
  return strtotime((string)$checked_at) >= strtotime('-24 hours');
}

$upcoming_moms=array_values(array_filter($all_moms,fn($m)=>($m['status']??'')==='upcoming'));
$ongoing_moms=array_values(array_filter($all_moms,fn($m)=>($m['status']??'')==='ongoing'));
$history_moms=array_values(array_filter($all_moms,fn($m)=>mom_recent_history($m)));
$total_moms=count($upcoming_moms)+count($ongoing_moms)+count($history_moms);
usort($ongoing_moms, fn($a,$b)=>strtotime($b['started_at']??$b['meeting_at']??$b['created_at']??'now')<=>strtotime($a['started_at']??$a['meeting_at']??$a['created_at']??'now'));
usort($upcoming_moms, fn($a,$b)=>strtotime($a['meeting_at']??$a['created_at']??'now')<=>strtotime($b['meeting_at']??$b['created_at']??'now'));
usort($history_moms, fn($a,$b)=>strtotime($b['completed_at']??$b['cancelled_at']??$b['meeting_at']??$b['created_at']??'now')<=>strtotime($a['completed_at']??$a['cancelled_at']??$a['meeting_at']??$a['created_at']??'now'));
$queue_moms=array_merge($ongoing_moms,$upcoming_moms);
$critical_count=count($ongoing_moms)+count(array_filter($upcoming_moms,fn($m)=>($m['type']??'')==='urgent'));

$page_title='Minutes of Meeting'; $active_page='mom';
include 'includes/header.php';
?>
<main class="main"><div class="main-inner">

<?php if(!$mom_installed): ?>
<div class="topbar">
  <div><div class="page-title">Minutes of Meeting</div><div class="page-sub">Database setup required</div></div>
</div>
<div class="panel">
  <div class="panel-head"><span class="panel-title">MOM is not installed yet</span></div>
  <div class="empty">
    <div class="empty-ic"><i data-lucide="database"></i></div>
    <div class="empty-t">MOM database schema is incomplete</div>
    <div class="empty-s">Run the full <code>config/mom_database_schema.sql</code> file against the active TRACS database, then refresh this page.</div>
  </div>
</div>
<?php else: ?>

<?php if($mom_id>0 && $mom): ?>
<!-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
     MOM WORKSPACE VIEW (Active Meeting)
     ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->

<div class="mom-workspace">

  <!-- LEFT PANEL: Discussion & Notes -->
  <div class="mom-main">
    
    <!-- MOM Header -->
    <div class="mom-header">
      <div class="mom-header-left">
        <div>
          <h1 class="mom-title"><?=esc($mom_details['title']??'New Meeting')?></h1>
          <div class="mom-meta">
            <span class="mom-badge mom-badge-<?=esc($mom_details['type']??'weekly')?>"><?=ucfirst($mom_details['type']??'Weekly')?></span>
            <span class="mom-date"><?=safe_dt($mom_details['meeting_at']??($mom_details['created_at']??null),'d M Y, H:i')?></span>
            <span class="mom-status mom-status-<?=esc($mom_details['status']??'upcoming')?>"><?=ucfirst($mom_details['status']??'Upcoming')?></span>
            <?=tracs_creator_meta($mom_details)?>
          </div>
        </div>
      </div>
      <div class="mom-header-actions">
        <?php if(!empty($mom_details['meeting_url'])): ?>
        <a class="btn btn-ghost btn-icon" href="<?=esc($mom_details['meeting_url'])?>" target="_blank" rel="noopener noreferrer" title="Open Meeting URL"><i data-lucide="video" class="icon-sm"></i></a>
        <?php endif; ?>
        <button class="btn btn-ghost btn-icon" data-edit-mom-id="<?=$mom_id?>" data-objective="<?=esc($mom_details['objective']??'')?>" data-meeting-url="<?=esc($mom_details['meeting_url']??'')?>" data-participants="<?=esc($mom_details['participants']??'')?>" onclick="editMOMHeader(<?=$mom_id?>, '<?=esc(safe_dt_local($mom_details['meeting_at']??null))?>')" title="Edit Meeting"><i data-lucide="edit-2" class="icon-sm"></i></button>
        <?php if(($mom_details['status']??'')==='upcoming'): ?>
        <button class="btn btn-ghost btn-icon" onclick="cancelMOM(<?=$mom_id?>)" title="Cancel Meeting"><i data-lucide="ban" class="icon-sm"></i></button>
        <?php elseif(($mom_details['status']??'')==='ongoing'): ?>
        <button class="btn btn-primary btn-sm" onclick="closeMOM(<?=$mom_id?>)"><i data-lucide="check-circle-2" class="icon-sm"></i>Complete</button>
        <?php endif; ?>
        <a href="mom.php" class="btn btn-ghost btn-icon" title="Back to List"><i data-lucide="arrow-left" class="icon-sm"></i></a>
      </div>
    </div>

    <?php if(($mom_details['status']??'')==='completed'): ?>
    <div class="mom-section">
      <div class="section-head">
        <span class="section-title"><i data-lucide="file-text" class="icon-sm"></i>MOM Summary</span>
        <button class="btn btn-primary btn-sm" onclick="saveMOMSummary(<?=$mom_id?>)">Save MOM</button>
      </div>
      <div class="section-body">
        <textarea class="form-textarea" id="momSummaryText" placeholder="Summarize discussion results, decisions, customer impact, and follow-up actions" style="min-height:120px"><?=esc($mom_details['summary']??'')?></textarea>
      </div>
    </div>
    <?php endif; ?>

    <!-- Objective Section -->
    <div class="mom-section">
      <div class="section-head">
        <span class="section-title"><i data-lucide="target" class="icon-sm"></i>Objective</span>
        <button class="btn btn-primary btn-sm" onclick="saveMOMObjective(<?=$mom_id?>)">Save</button>
      </div>
      <div class="section-body mom-inline-form">
        <textarea class="form-textarea" id="momObjectiveText" placeholder="Describe the meeting objective, operational scope, or expected outcome"><?=esc($mom_details['objective']??'')?></textarea>
      </div>
    </div>

    <div class="mom-section-row mom-agenda-discussion-row">
    <!-- Agenda Section -->
    <div class="mom-section">
      <div class="section-head">
        <span class="section-title"><i data-lucide="list" class="icon-sm"></i>Agenda</span>
        <button class="btn btn-primary btn-sm" onclick="saveInlineAgendaItem(<?=$mom_id?>)">Add Item</button>
      </div>
      <div class="section-body">
        <div class="mom-inline-form mom-inline-row">
          <input class="form-input" id="momAgendaTopic" placeholder="Agenda topic, e.g. Review stuck domain transfers">
        </div>
        <?php
          $agenda=$MC->getAgendaItems($mom_id)??[];
          foreach($agenda as $ai):
          $aist=$ai['status']??'pending';
          $aidone=($aist==='completed');
        ?>
          <div class="agenda-item agenda-item-<?=$aist?>">
            <input type="checkbox" class="agenda-check" <?=$aidone?'checked':''?> onchange="toggleAgendaItem(<?=intval($ai['id']??0)?>,this.checked)">
            <div class="agenda-content">
              <div class="agenda-topic"><?=esc($ai['topic']??'')?></div>
              <?php if($ai['notes']??null): ?>
                <div class="agenda-notes"><?=esc($ai['notes'])?></div>
              <?php endif; ?>
            </div>
            <button class="btn btn-danger btn-icon btn-sm" onclick="deleteAgendaItem(<?=intval($ai['id']??0)?>)" title="Delete"><i data-lucide="x" class="icon-xs"></i></button>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Discussion & Notes Section -->
    <div class="mom-section">
      <div class="section-head">
        <span class="section-title"><i data-lucide="message-circle" class="icon-sm"></i>Discussion Notes</span>
        <button class="btn btn-primary btn-sm" onclick="saveInlineDiscussionNote(<?=$mom_id?>)">Add Note</button>
      </div>
      <div class="section-body mom-notes-area" id="momNotesArea">
        <div class="mom-inline-form mom-note-inline">
          <select class="form-select" id="momInlineNoteType">
            <option value="discussion">Discussion</option>
            <option value="insight">Insight</option>
            <option value="action">Action</option>
            <option value="decision">Decision</option>
          </select>
          <textarea class="form-textarea" id="momInlineNoteContent" placeholder="Capture discussion details, customer impact, or operational context"></textarea>
        </div>
        <?php
          $notes=$MC->getDiscussionNotes($mom_id)??[];
          foreach($notes as $n):
          $nid=intval($n['id']??0);
          $ntype=$n['note_type']??'discussion';
        ?>
          <div class="discussion-note discussion-note-<?=$ntype?>" data-note-id="<?=$nid?>">
            <div class="note-header">
              <span class="note-type"><?=ucfirst($ntype)?></span>
              <span class="note-time"><?=safe_dt($n['created_at']??null,'H:i')?></span>
              <?=tracs_creator_meta($n, $n['created_at'] ?? null, false)?>
              <button class="btn btn-danger btn-icon btn-xs" onclick="deleteNote(<?=$nid?>)" title="Delete"><i data-lucide="x" class="icon-xs"></i></button>
            </div>
            <div class="note-text" onmouseup="handleTextSelection(this.parentElement.parentElement)"><?=nl2br(esc($n['content']??''))?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    </div>

    <!-- Decisions Section -->
    <div class="mom-section">
      <div class="section-head">
        <span class="section-title"><i data-lucide="check-square" class="icon-sm"></i>Decisions</span>
        <button class="btn btn-primary btn-sm" onclick="saveInlineDecision(<?=$mom_id?>)">Add Decision</button>
      </div>
      <div class="section-body">
        <div class="mom-inline-form mom-decision-inline">
          <input class="form-input" id="momInlineDecisionText" placeholder="Decision, e.g. Escalate domain transfer blockers">
          <input class="form-input" id="momInlineDecisionOwner" placeholder="Decision owner, e.g. CS Supervisor">
          <textarea class="form-textarea" id="momInlineDecisionRationale" placeholder="Add operational context, customer impact, or reason for the decision"></textarea>
        </div>
        <?php
          $decisions=$MC->getDecisions($mom_id)??[];
          foreach($decisions as $d):
          $did=intval($d['id']??0);
          $downer=$d['owner']??'—';
        ?>
          <div class="decision-card">
            <div class="decision-head">
              <strong><?=esc($d['decision']??'Decision')?></strong>
              <button class="btn btn-danger btn-icon btn-xs" onclick="deleteDecision(<?=$did?>)" title="Delete"><i data-lucide="x" class="icon-xs"></i></button>
            </div>
            <?php if($d['rationale']??null): ?>
              <div class="decision-rationale">
                <span class="label">Rationale:</span>
                <?=esc($d['rationale'])?>
              </div>
            <?php endif; ?>
            <?php if($downer!=='—'): ?>
              <div class="decision-owner">
                <span class="label">Owner:</span>
                <?=esc($downer)?>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Action Items Section -->
    <div class="mom-section">
      <div class="section-head">
        <span class="section-title"><i data-lucide="zap" class="icon-sm"></i>Action Items</span>
        <button class="btn btn-primary btn-sm" onclick="saveInlineActionItem(<?=$mom_id?>)">Add Action</button>
      </div>
      <div class="section-body">
        <div class="mom-inline-form mom-action-inline">
          <input class="form-input" id="momInlineActionTitle" placeholder="Action title, e.g. Update WHMCS ticket notes">
          <input class="form-input" id="momInlineActionAssignee" placeholder="Operator, team, or role, e.g. CS Billing">
          <select class="form-select" id="momInlineActionPriority">
            <option value="medium">Medium</option>
            <option value="low">Low</option>
            <option value="high">High</option>
            <option value="critical">Critical</option>
          </select>
          <input class="form-input" type="date" id="momInlineActionDueDate">
          <textarea class="form-textarea" id="momInlineActionDesc" placeholder="Add action context, expected outcome, or next step"></textarea>
        </div>
        <?php
          $actions=$MC->getActionItems($mom_id)??[];
          foreach($actions as $a):
          $aid=intval($a['id']??0);
          $aprio=$a['priority']??'medium';
          $astatus=$a['status']??'pending';
          $adone=($astatus==='completed');
          $aowner=$a['assigned_to']??'—';
          $adue=$a['due_date']??null;
        ?>
          <div class="action-item action-item-<?=$aprio?> action-item-<?=$astatus?>" data-aid="<?=$aid?>">
            <input type="checkbox" class="action-check" <?=$adone?'checked':''?> onchange="completeAction(<?=$aid?>,this.checked)">
            <div class="action-content">
              <div class="action-title"><?=esc($a['title']??'Untitled')?></div>
              <?php if($a['description']??null): ?>
                <div class="action-desc"><?=esc($a['description'])?></div>
              <?php endif; ?>
              <div class="action-meta">
                <span class="action-owner"><?=esc($aowner)?></span>
                <?php if($adue): ?>
                  <span class="action-due"><?=safe_dt($adue,'d M Y')?></span>
                <?php endif; ?>
              </div>
            </div>
            <div class="action-btns">
              <button class="btn btn-ghost btn-icon btn-sm" onclick="createReminderFromAction(<?=$aid?>)" title="Create Reminder"><i data-lucide="bell" class="icon-sm"></i></button>
              <button class="btn btn-ghost btn-icon btn-sm" onclick="createCaseFromAction(<?=$aid?>)" title="Create Case"><i data-lucide="briefcase" class="icon-sm"></i></button>
              <button class="btn btn-ghost btn-icon btn-sm" onclick="editActionItem(<?=$aid?>)" title="Edit"><i data-lucide="edit-2" class="icon-sm"></i></button>
              <button class="btn btn-danger btn-icon btn-sm" onclick="deleteActionItem(<?=$aid?>)" title="Delete"><i data-lucide="trash-2" class="icon-sm"></i></button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

  </div><!-- /mom-main -->

  <!-- RIGHT PANEL: Operational Sidebar (Sticky) -->
  <aside class="mom-sidebar">
    
    <!-- Meeting Info Card -->
    <div class="mom-card" data-sidebar-edit="participants">
      <div class="card-head">
        <span class="card-title"><i data-lucide="users" class="icon-sm"></i>Participants</span>
        <button class="btn btn-ghost btn-icon btn-sm" onclick="toggleMOMSidebarEdit('participants')" title="Edit Participants"><i data-lucide="edit-2" class="icon-sm"></i></button>
      </div>
      <div class="card-body">
        <div class="participant-list" id="momParticipantTags">
          <?php foreach(array_filter(array_map('trim',explode(',',$mom_details['participants']??''))) as $p): ?>
            <span class="participant-tag"><?=esc($p)?></span>
          <?php endforeach; ?>
        </div>
        <div class="mom-inline-form mom-sidebar-inline mom-sidebar-edit">
          <input class="form-input" id="momParticipantsText" value="<?=esc($mom_details['participants']??'')?>" placeholder="Participant names or teams, comma-separated">
          <button class="btn btn-primary btn-sm" onclick="saveMOMParticipants(<?=$mom_id?>)">Save</button>
        </div>
      </div>
    </div>

    <!-- Related Reminders -->
    <div class="mom-card">
      <div class="card-head">
        <span class="card-title"><i data-lucide="bell" class="icon-sm"></i>Reminders</span>
      </div>
      <div class="card-body mom-reminders-list">
        <?php if(empty($related_reminders)): ?>
          <p class="empty-text">No reminders linked</p>
        <?php else: foreach($related_reminders as $r): 
          $rstat=$r['status']??'Upcoming';
          $rprio=$r['priority']??'medium';
          $rclass=rem_status_class($rstat);
        ?>
          <div class="reminder-item reminder-item-<?=$rprio?>" data-rid="<?=intval($r['id']??0)?>">
            <div class="reminder-stat <?=$rclass?>"><?=$rstat?></div>
            <div class="reminder-info">
              <div class="reminder-title"><?=esc($r['title']??'')?></div>
              <div class="reminder-due"><?=safe_dt($r['due_date']??null,'d M, H:i')?></div>
            </div>
            <button class="btn btn-ghost btn-icon btn-xs" onclick="openEditReminder(<?=intval($r['id']??0)?>)" title="Edit"><i data-lucide="edit-2" class="icon-xs"></i></button>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <!-- Related Cases -->
    <div class="mom-card" data-sidebar-edit="cases">
      <div class="card-head">
        <span class="card-title"><i data-lucide="briefcase" class="icon-sm"></i>Linked Cases</span>
        <button class="btn btn-ghost btn-icon btn-sm" onclick="toggleMOMSidebarEdit('cases')" title="Edit Linked Cases"><i data-lucide="edit-2" class="icon-sm"></i></button>
      </div>
      <div class="card-body mom-cases-list">
        <div class="mom-inline-form mom-sidebar-inline mom-sidebar-edit">
          <input class="form-input" id="momInlineCaseId" inputmode="numeric" placeholder="Linked case ID, e.g. 1024">
          <button class="btn btn-primary btn-sm" onclick="saveMOMSidebarCases(<?=$mom_id?>)">Save</button>
        </div>
        <?php if(empty($related_cases)): ?>
          <p class="empty-text">No cases linked</p>
        <?php else: foreach($related_cases as $c): 
          $cprio=$c['priority']??'low';
          $cstat=$c['status']??'active';
        ?>
          <div class="case-item case-item-<?=$cprio?> case-item-<?=$cstat?>" data-case-id="<?=intval($c['id']??0)?>">
            <div class="case-badge case-badge-<?=$cprio?>"><?=strtoupper(substr($cprio,0,1))?></div>
            <div class="case-info">
              <div class="case-id">#<?=intval($c['id']??0)?></div>
              <div class="case-title"><?=esc($c['title']??'')?></div>
            </div>
            <?php if(($mom_details['status']??'')==='completed'): $cid=(int)($c['id']??0); ?>
            <div class="mom-case-resolution mom-sidebar-edit">
              <select class="form-select" id="momCaseStatus<?=$cid?>">
                <option value="completed" <?=$cstat==='completed'?'selected':''?>>Solved</option>
                <option value="active" <?=$cstat==='active'?'selected':''?>>Active</option>
                <option value="pending" <?=$cstat==='pending'?'selected':''?>>Pending</option>
                <option value="stuck" <?=$cstat==='stuck'?'selected':''?>>Stuck</option>
              </select>
              <input class="form-input" id="momCaseNote<?=$cid?>" placeholder="Add resolution note or follow-up detail">
              <button class="btn btn-primary btn-sm" onclick="resolveLinkedCaseFromMOM(<?=$mom_id?>, <?=$cid?>)">Update</button>
            </div>
            <?php endif; ?>
            <div class="case-actions">
              <button class="btn btn-danger btn-icon btn-xs mom-sidebar-edit" onclick="markMOMCaseForRemoval(this)" title="Remove Link"><i data-lucide="x" class="icon-xs"></i></button>
              <a href="cases.php?action=edit&id=<?=intval($c['id']??0)?>" class="btn btn-ghost btn-icon btn-xs" title="View"><i data-lucide="external-link" class="icon-xs"></i></a>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <!-- Action Tracker -->
    <div class="mom-card">
      <div class="card-head">
        <span class="card-title"><i data-lucide="zap" class="icon-sm"></i>Actions</span>
      </div>
      <div class="card-body">
        <?php 
          $total_actions=count($actions);
          $done_actions=count(array_filter($actions??[],fn($a)=>($a['status']??'')==='completed'));
          $pct_done=$total_actions>0?round($done_actions/$total_actions*100):0;
        ?>
        <div class="progress-bar">
          <div class="progress-fill" style="width:<?=$pct_done?>%"></div>
        </div>
        <div class="progress-text"><?=$done_actions?>/<?=$total_actions?> completed</div>
        
        <?php if(!empty($actions)): ?>
          <div class="action-quick-list" style="margin-top:12px;border-top:1px solid var(--bd1);padding-top:8px">
            <?php foreach(array_slice($actions,0,3) as $a): 
              $astatus=$a['status']??'pending';
              $adone=($astatus==='completed');
            ?>
              <div class="quick-action-item" style="opacity:<?=$adone?'.5':'1'?>">
                <span style="<?=$adone?'text-decoration:line-through':''?>"><?=esc(substr($a['title']??'',0,30))?></span>
              </div>
            <?php endforeach; ?>
            <?php if(count($actions)>3): ?>
              <div class="quick-action-more">+<?=count($actions)-3?> more</div>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if(($mom_details['status']??'')==='completed'): ?>
    <div class="mom-card mom-screenshots-card" data-sidebar-edit="screenshots">
      <div class="card-head">
        <span class="card-title"><i data-lucide="image" class="icon-sm"></i>Screenshots</span>
        <button class="btn btn-ghost btn-icon btn-sm" onclick="toggleMOMSidebarEdit('screenshots')" title="Edit Screenshots"><i data-lucide="edit-2" class="icon-sm"></i></button>
      </div>
      <div class="card-body">
        <div class="mom-sidebar-edit mom-shot-editbar">
          <label class="btn btn-ghost btn-sm">
            <i data-lucide="image-plus" class="icon-sm"></i>Add
            <input type="file" accept="image/*" style="display:none" onchange="uploadMOMScreenshot(<?=$mom_id?>, this)">
          </label>
          <button class="btn btn-primary btn-sm" onclick="saveMOMSidebarScreenshots(<?=$mom_id?>)">Save</button>
        </div>
        <div class="mom-shot-list">
        <?php if(empty($screenshots)): ?>
          <p class="empty-text">No screenshots uploaded</p>
        <?php else: foreach($screenshots as $shot): ?>
          <div class="mom-shot-item" data-shot-id="<?=intval($shot['id']??0)?>">
            <a class="mom-shot-link" href="uploads/mom/<?=esc($shot['filename']??'')?>" target="_blank" rel="noopener noreferrer" title="Open screenshot">
              <img class="mom-shot-thumb" src="uploads/mom/<?=esc($shot['filename']??'')?>" alt="MOM screenshot">
            </a>
            <button class="btn btn-danger btn-icon btn-xs mom-shot-remove mom-sidebar-edit" onclick="markMOMScreenshotForRemoval(this)" title="Delete Screenshot"><i data-lucide="trash-2" class="icon-xs"></i></button>
          </div>
        <?php endforeach; endif; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Operational Insights -->
    <div class="mom-card">
      <div class="card-head">
        <span class="card-title"><i data-lucide="trend-up" class="icon-sm"></i>Insights</span>
      </div>
      <div class="card-body">
        <div class="insight-item">
          <span class="insight-label">Unresolved Cases</span>
          <span class="insight-value"><?=count(array_filter(array_map([$CC,'formatCase'],$CC->getCases()?:[]),fn($c)=>($c['status']??'')!=='completed'))?></span>
        </div>
        <div class="insight-item">
          <span class="insight-label">Overdue Reminders</span>
          <span class="insight-value"><?=count(array_filter(array_map([$RC,'formatReminder'],$RC->getReminders()?:[]),fn($r)=>($r['status']??'')==='Overdue'))?></span>
        </div>
        <div class="insight-item">
          <span class="insight-label">SLA at Risk</span>
          <span class="insight-value">—</span>
        </div>
      </div>
    </div>

  </aside><!-- /mom-sidebar -->

</div><!-- /mom-workspace -->

<?php else: ?>
<!-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
     MOM LIST VIEW (All Meetings)
     ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->

<div class="topbar">
  <div><div class="page-title">Minutes of Meeting</div><div class="page-sub"><?=$total_moms?> total · <?=count($upcoming_moms)?> upcoming · <?=count($ongoing_moms)?> ongoing</div></div>
</div>

<div class="stat-strip">
  <div class="stat-card blue"><div class="stat-glow"></div><div class="stat-num"><?=$total_moms?></div><div class="stat-label">Total Meetings</div></div>
  <div class="stat-card cyan"><div class="stat-glow"></div><div class="stat-num"><?=count($upcoming_moms)?></div><div class="stat-label">Upcoming</div></div>
  <div class="stat-card green"><div class="stat-glow"></div><div class="stat-num"><?=count($ongoing_moms)?></div><div class="stat-label">Ongoing</div></div>
  <div class="stat-card <?php $w=count(array_filter($all_moms,fn($m)=>($m['type']??'')==='weekly'));echo $w>0?'purple':'green'?>" ><div class="stat-glow"></div><div class="stat-num"><?=count($history_moms)?></div><div class="stat-label">History</div></div>
  <div class="stat-card amber"><div class="stat-glow"></div><div class="stat-num"><?=count(array_filter($all_moms,fn($m)=>($m['type']??'')==='urgent'))?></div><div class="stat-label">Urgent</div></div>
</div>

<?php
function render_mom_table($items, $MC, $mode='upcoming') {
  if(empty($items)) {
    echo '<div class="empty"><div class="empty-ic"><i data-lucide="clipboard-list"></i></div><div class="empty-t">No meetings here</div><div class="empty-s">Operational meetings will appear automatically by lifecycle status.</div></div>';
    return;
  }
  $preview_data = function($m) use ($MC) {
    $mid = (int)($m['id'] ?? 0);
    if(!$mid) return null;
    $participants = array_values(array_filter(array_map('trim', explode(',', $m['participants'] ?? ''))));
    $agenda = $MC->getAgendaItems($mid) ?? [];
    $notes = $MC->getDiscussionNotes($mid) ?? [];
    $decisions = $MC->getDecisions($mid) ?? [];
    $actions = $MC->getActionItems($mid) ?? [];
    $cases = $MC->getRelatedCases($mid) ?? [];
    $screenshots = $MC->getScreenshots($mid) ?? [];
    $objective = trim((string)($m['objective'] ?? ''));
    $summary = trim((string)($m['summary'] ?? ''));
    $meeting_url = trim((string)($m['meeting_url'] ?? ''));
    $has = $participants || $agenda || $notes || $decisions || $actions || $cases || $screenshots || $objective !== '' || $summary !== '' || $meeting_url !== '';
    if(!$has) return null;
    return compact('participants', 'agenda', 'notes', 'decisions', 'actions', 'cases', 'screenshots', 'objective', 'summary', 'meeting_url');
  };

  $render_preview = function($mid, $preview) {
    if(!$preview) return;
?>
    <tr class="mom-preview-row hidden" id="momPreview<?=$mid?>" data-mom-preview-row data-preview-for="<?=$mid?>">
      <td colspan="8">
        <div class="mom-preview-panel">
          <?php if($preview['summary'] !== ''): ?>
          <div class="mom-preview-block mom-preview-wide">
            <div class="mom-preview-label">Summary</div>
            <div class="mom-preview-text"><?=nl2br(esc($preview['summary']))?></div>
          </div>
          <?php endif; ?>
          <?php if($preview['objective'] !== ''): ?>
          <div class="mom-preview-block mom-preview-wide">
            <div class="mom-preview-label">Objective</div>
            <div class="mom-preview-text"><?=nl2br(esc($preview['objective']))?></div>
          </div>
          <?php endif; ?>
          <?php if(!empty($preview['participants'])): ?>
          <div class="mom-preview-block">
            <div class="mom-preview-label">Participants</div>
            <div class="mom-preview-tags">
              <?php foreach($preview['participants'] as $participant): ?>
              <span class="mom-preview-tag"><?=esc($participant)?></span>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>
          <?php if($preview['meeting_url'] !== ''): ?>
          <div class="mom-preview-block">
            <div class="mom-preview-label">Meeting Link</div>
            <a class="mom-preview-link" href="<?=esc($preview['meeting_url'])?>" target="_blank" rel="noopener noreferrer"><?=esc($preview['meeting_url'])?></a>
          </div>
          <?php endif; ?>
          <?php if(!empty($preview['notes'])): ?>
          <div class="mom-preview-block mom-preview-wide">
            <div class="mom-preview-label">Discussion</div>
            <div class="mom-preview-list">
              <?php foreach(array_slice($preview['notes'], 0, 4) as $note): ?>
              <div class="mom-preview-item">
                <span class="mom-preview-pill"><?=esc(ucfirst($note['note_type'] ?? 'Discussion'))?></span>
                <span><?=esc($note['content'] ?? '')?></span>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>
          <?php if(!empty($preview['agenda'])): ?>
          <div class="mom-preview-block">
            <div class="mom-preview-label">Agenda</div>
            <div class="mom-preview-list">
              <?php foreach(array_slice($preview['agenda'], 0, 4) as $item): ?>
              <div class="mom-preview-item"><?=esc($item['topic'] ?? '')?></div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>
          <?php if(!empty($preview['decisions'])): ?>
          <div class="mom-preview-block">
            <div class="mom-preview-label">Decisions</div>
            <div class="mom-preview-list">
              <?php foreach(array_slice($preview['decisions'], 0, 3) as $decision): ?>
              <div class="mom-preview-item"><?=esc($decision['decision'] ?? '')?></div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>
          <?php if(!empty($preview['actions'])): ?>
          <div class="mom-preview-block">
            <div class="mom-preview-label">Actions</div>
            <div class="mom-preview-list">
              <?php foreach(array_slice($preview['actions'], 0, 4) as $action): ?>
              <div class="mom-preview-item">
                <span><?=esc($action['title'] ?? '')?></span>
                <?php if(!empty($action['status'])): ?><span class="mom-preview-pill"><?=esc($action['status'])?></span><?php endif; ?>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>
          <?php if(!empty($preview['cases'])): ?>
          <div class="mom-preview-block">
            <div class="mom-preview-label">Linked Cases</div>
            <div class="mom-preview-list">
              <?php foreach(array_slice($preview['cases'], 0, 4) as $case): ?>
              <div class="mom-preview-item">#<?=intval($case['id'] ?? 0)?> <?=esc($case['title'] ?? '')?></div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>
          <?php if(!empty($preview['screenshots'])): ?>
          <div class="mom-preview-block mom-preview-wide">
            <div class="mom-preview-label">Screenshots</div>
            <div class="mom-preview-shots">
              <?php foreach(array_slice($preview['screenshots'], 0, 4) as $shot): ?>
              <a href="uploads/mom/<?=esc($shot['filename'] ?? '')?>" target="_blank" rel="noopener noreferrer">
                <img src="uploads/mom/<?=esc($shot['filename'] ?? '')?>" alt="MOM screenshot">
              </a>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </td>
    </tr>
<?php
  };

  if($mode === 'queue') {
?>
  <div class="mom-schedule-list">
    <?php foreach($items as $m):
      $mid=intval($m['id']??0);
      $mtitle=esc($m['title']??'Untitled');
      $mtype=$m['type']??'weekly';
      $mstat=$m['status']??'upcoming';
      $mwhen=$m['meeting_at']??($m['created_at']??null);
      $mcreated=safe_dt($mwhen,'d M Y, H:i');
      $macts=count($MC->getActionItems($mid)??[]);
    ?>
    <div class="mom-schedule-item" data-mid="<?=$mid?>">
      <div class="mom-schedule-main">
        <div class="mom-schedule-title" title="<?=$mtitle?>"><?=$mtitle?></div>
        <div class="mom-schedule-meta">
          <span class="badge badge-mom-<?=$mtype?>"><?=ucfirst($mtype)?></span>
          <span class="mom-status-badge mom-status-badge-<?=$mstat?>"><?=ucfirst($mstat)?></span>
          <span><strong><?=$macts?></strong> items</span>
        </div>
        <div class="mom-schedule-time"><?=$mcreated?></div>
        <?=tracs_creator_meta($m)?>
      </div>
      <div class="mom-schedule-actions">
        <?php if($mstat==='upcoming'): ?>
        <a href="?mom_id=<?=$mid?>" class="btn btn-ghost btn-sm">Open</a>
        <?php elseif($mstat==='ongoing'): ?>
        <button class="btn btn-primary btn-sm" onclick="closeMOM(<?=$mid?>)">Complete</button>
        <?php else: ?>
        <a href="?mom_id=<?=$mid?>" class="btn btn-ghost btn-sm">View</a>
        <?php endif; ?>
        <a href="?mom_id=<?=$mid?>" class="btn btn-ghost btn-icon" title="Open"><i data-lucide="arrow-right" class="icon-sm"></i></a>
        <?php if($mstat!=='completed'): ?><button class="btn btn-danger btn-icon" onclick="deleteMOM(<?=$mid?>)" title="Delete"><i data-lucide="trash-2" class="icon-sm"></i></button><?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
<?php
    return;
  }
?>
  <div class="table-wrap">
    <table class="tracs-table mom-table">
      <colgroup>
        <col class="mom-col-id">
        <col class="mom-col-title">
        <col class="mom-col-type">
        <col class="mom-col-status">
        <col class="mom-col-actions-count">
        <col class="mom-col-participants">
        <col class="mom-col-time">
        <col class="mom-col-controls">
      </colgroup>
      <thead>
        <tr>
          <th>#</th>
          <th>Title</th>
          <th>Type</th>
          <th>Status</th>
          <th>Actions</th>
          <th>Participants</th>
          <th>Meeting Time</th>
          <th style="width:160px"></th>
        </tr>
      </thead>
      <tbody>
	        <?php foreach($items as $m): 
	          $mid=intval($m['id']??0);
	          $mtitle=esc($m['title']??'Untitled');
          $mtype=$m['type']??'weekly';
          $mstat=$m['status']??'upcoming';
          $mwhen=$m['meeting_at']??($m['created_at']??null);
          $mcreated=safe_dt($mwhen,'d M Y, H:i');
          $mlocal=safe_dt_local($m['meeting_at']??null);
	          $macts=count($MC->getActionItems($mid)??[]);
            $mpreview=$preview_data($m);
	          $mparts=$m['participants']??'—';
	          $mparts_short=strlen($mparts)>30?substr($mparts,0,27).'…':$mparts;
	          $msearch=esc(strtolower(trim($mid.' '.($m['title']??'').' '.$mtype.' '.$mstat.' '.$mparts.' '.($m['objective']??''))));
	        ?>
	          <tr data-mid="<?=$mid?>" data-meeting-at="<?=esc($mlocal)?>" <?=$mode==='history'?'data-mom-history-row data-mom-search="'.$msearch.'"':''?>>
            <td class="tracs-rownum"><?=$mid?></td>
            <td class="mom-table-title-cell">
              <div class="mom-table-title" title="<?=$mtitle?>"><?=$mtitle?></div>
              <?=tracs_creator_meta($m)?>
            </td>
            <td><span class="badge badge-mom-<?=$mtype?>"><?=ucfirst($mtype)?></span></td>
            <td><span class="mom-status-badge mom-status-badge-<?=$mstat?>"><?=ucfirst($mstat)?></span></td>
            <td style="color:var(--tx2)"><strong><?=$macts?></strong> items</td>
            <td style="color:var(--tx3);font-size:11px" title="<?=$mparts?>"><?=$mparts_short?></td>
            <td style="color:var(--tx3);font-size:11px"><?=$mcreated?></td>
            <td class="tracs-acts">
              <?php if($mstat==='upcoming'): ?>
              <a href="?mom_id=<?=$mid?>" class="btn btn-ghost btn-sm">Open</a>
              <?php elseif($mstat==='ongoing'): ?>
              <button class="btn btn-primary btn-sm" onclick="closeMOM(<?=$mid?>)">Complete</button>
              <?php else: ?>
              <a href="?mom_id=<?=$mid?>" class="btn btn-ghost btn-sm">View MOM</a>
              <?php endif; ?>
              <button type="button" class="btn btn-ghost btn-icon mom-preview-toggle" onclick="toggleMOMPreview(<?=$mid?>, this)" title="Preview MOM" aria-controls="momPreview<?=$mid?>" aria-expanded="false" <?=$mpreview?'':'disabled'?>><i data-lucide="chevron-down" class="icon-sm"></i></button>
              <?php if($mstat!=='completed'): ?><button class="btn btn-danger btn-icon" onclick="deleteMOM(<?=$mid?>)" title="Delete"><i data-lucide="trash-2" class="icon-sm"></i></button><?php endif; ?>
            </td>
          </tr>
          <?php $render_preview($mid, $mpreview); ?>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php } ?>

	<div class="mom-list-layout">
	  <div class="panel mom-history-panel">
		    <div class="panel-head mom-history-head">
		      <div class="mom-history-title">
		        <span class="panel-title">Meeting History</span>
		      </div>
		      <span class="panel-meta mom-history-count"><?=count($history_moms)?> completed/cancelled</span>
		      <div class="search-wrap mom-history-search">
		        <i data-lucide="search"></i>
		        <input class="search-input" type="search" placeholder="Search meeting title, participant, decision, or action" oninput="filterMOMHistory(this.value);document.getElementById('momExportQ').value=this.value">
		      </div>
		      <details class="report-export-menu">
		        <summary class="btn btn-ghost btn-icon report-export-trigger" title="More actions" aria-label="More actions" data-tooltip="More actions"><i data-lucide="more-vertical" class="icon-sm"></i></summary>
		        <form method="get" action="/api/export-moms.php" class="report-export-popover">
		          <input type="hidden" name="q" id="momExportQ" value="">
		          <div class="report-export-title">
		            <i data-lucide="download" class="icon-xs"></i>
		            Export CSV
		          </div>
		          <label>From Date<input type="date" name="from" class="form-input"></label>
		          <label>To Date<input type="date" name="to" class="form-input"></label>
		          <button type="submit" class="btn btn-primary"><i data-lucide="download" class="icon-sm"></i>Download CSV</button>
		        </form>
		      </details>
		      <button class="btn btn-primary toolbar-add-btn" onclick="openNewMOM()"><i data-lucide="plus-circle" class="icon-sm"></i>Add New Meeting</button>
		    </div>
	    <?php render_mom_table($history_moms, $MC, 'history'); ?>
	  </div>
	
	  <div class="mom-side-stack">
	    <div class="panel mom-queue-panel">
	      <div class="panel-head">
	        <span class="panel-title">Current Schedule</span>
	        <span class="panel-meta"><?=count($ongoing_moms)?> ongoing · <?=count($upcoming_moms)?> upcoming</span>
	      </div>
	      <?php render_mom_table($queue_moms, $MC, 'queue'); ?>
	    </div>
	
	    <?php if($mom_id===0 && count($weekly_suggestions)>0): ?>
	    <div class="panel">
	      <div class="panel-head"><span class="panel-title">Suggested for Weekly Meeting</span><span class="panel-meta"><?=count($weekly_suggestions)?> items</span></div>
	  <div class="table-wrap">
	    <table class="tracs-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Title</th>
          <th>Priority</th>
          <th>Status</th>
          <th>Days Open</th>
          <th>Reason</th>
          <th style="width:90px"></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach($weekly_suggestions as $s): 
        $sid=intval($s['case_id']??0);
        $stitle=esc($s['title']??'');
        $sprio=$s['priority']??'low';
        $sstatus=$s['status']??'active';
        $sdays=$s['days_open']??0;
        $sreason=$s['suggestion_reason']??'unresolved';
      ?>
        <tr>
          <td class="tracs-rownum"><?=$sid?></td>
          <td style="max-width:250px">
            <div style="font-weight:500;color:var(--tx1)"><?=$stitle?></div>
          </td>
          <td><span class="badge b-<?=$sprio?>"><?=ucfirst($sprio)?></span></td>
          <td><span class="badge b-<?=$sstatus?>"><?=ucfirst($sstatus)?></span></td>
          <td><?=$sdays?> days</td>
          <td><span style="font-size:11px;color:var(--tx3)"><?=ucfirst(str_replace('_',' ',$sreason))?></span></td>
          <td><button class="btn btn-ghost btn-sm" onclick="openNewMOMWithCase(<?=$sid?>)"><i data-lucide="message-square-plus" class="icon-sm"></i>Discuss</button></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
	  </div>
	    </div>
	    <?php endif; ?>
	  </div>
	</div>

<?php endif; ?>

<?php endif; ?>

</div></main>
<?php include 'includes/footer.php'; ?>
