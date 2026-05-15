<?php /* TRACS — Footer Include. Requires $ticker_items */ ?>
<!-- CASE MODAL -->
<div class="modal-overlay hidden" id="caseModal">
<div class="modal">
  <div class="modal-head">
    <div><div class="modal-title" id="caseModalTitle">New Case</div><div class="modal-sub">Fill in case details</div></div>
    <button class="modal-close" onclick="closeModal('case')"><i data-lucide="x"></i></button>
  </div>
  <div class="modal-body">
    <input type="hidden" id="caseId">
    <div class="form-group"><label class="form-label">Title *</label><input type="text" class="form-input" id="caseTitle" placeholder="e.g. PT Maju Sejahtera — Contract Dispute" autocomplete="off"></div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Status</label>
        <select class="form-select" id="caseStatus"><option value="active">Active</option><option value="pending">Pending</option><option value="stuck">Stuck</option><option value="completed">Completed</option></select>
      </div>
      <div class="form-group"><label class="form-label">Priority</label>
        <select class="form-select" id="casePriority"><option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option><option value="critical">Critical</option></select>
      </div>
    </div>
    <div class="form-group">

  <label class="form-label">
    Next Check
  </label>

  <div class="split-input-group">
    <input type="date" class="form-input split-date" id="caseNextCheckDate" data-sync="caseNextCheck" style="flex: 1.5">
    <input type="time" class="form-input split-time" id="caseNextCheckTime" data-sync="caseNextCheck" style="flex: 1">
  </div>
  <input type="hidden" class="quick-datetime" id="caseNextCheck">

  <div class="quick-time-wrap">

    <button
      type="button"
      class="quick-time-btn"
      onclick="setQuickTime('1h', this)">
      +1H
    </button>

    <button
      type="button"
      class="quick-time-btn"
      onclick="setQuickTime('2h', this)">
      +2H
    </button>

    <button
      type="button"
      class="quick-time-btn"
      onclick="setQuickTime('4h', this)">
      +4H
    </button>

    <button
      type="button"
      class="quick-time-btn"
      onclick="setQuickTime('1d', this)">
      Tomorrow
    </button>

    <button
      type="button"
      class="quick-time-btn"
      onclick="setQuickTime('3d', this)">
      +3D
    </button>

    <button
      type="button"
      class="quick-time-btn"
      onclick="setQuickTime('1w', this)">
      +1W
    </button>

    <button
      type="button"
      class="quick-time-btn"
      onclick="setQuickTime('1m', this)">
      +1M
    </button>

  </div>

</div>

    <div class="form-group"><label class="form-label">Notes</label><textarea class="form-textarea" id="caseNotes" placeholder="Case context, related documents, action items…"></textarea></div>
  </div>
  <div class="modal-foot">
    <button class="btn btn-ghost" onclick="closeModal('case')">Cancel</button>
    <button class="btn btn-primary" onclick="saveCase()"><i data-lucide="check" class="icon-sm"></i>Save Case</button>
  </div>
</div></div>

<!-- REMINDER MODAL -->
<div class="modal-overlay hidden" id="remModal">
<div class="modal">
  <div class="modal-head">
    <div><div class="modal-title" id="remModalTitle">New Reminder</div><div class="modal-sub">Schedule a time-based alert</div></div>
    <button class="modal-close" onclick="closeModal('rem')"><i data-lucide="x"></i></button>
  </div>
  <div class="modal-body">
    <input type="hidden" id="remId">
    <div class="form-group"><label class="form-label">Title *</label><input type="text" class="form-input" id="remTitle" placeholder="e.g. Send somasi letter to opposing counsel" autocomplete="off"></div>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Priority</label>
        <select class="form-select" id="remPriority">
          <option value="low">Low</option>
          <option value="medium" selected>Medium</option>
          <option value="high">High</option>
          <option value="critical">Critical</option>
        </select>
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Due Date & Time *</label>
      <div class="split-input-group">
        <input type="date" class="form-input split-date" id="remDueDate" data-sync="remDue" style="flex: 1.5">
        <input type="time" class="form-input split-time" id="remDueTime" data-sync="remDue" style="flex: 1">
      </div>
      <input type="hidden" class="quick-datetime" id="remDue">
      <div class="quick-time-wrap">
        <button type="button" class="quick-time-btn" onclick="setQuickTime('1h', this)">+1H</button>
        <button type="button" class="quick-time-btn" onclick="setQuickTime('2h', this)">+2H</button>
        <button type="button" class="quick-time-btn" onclick="setQuickTime('4h', this)">+4H</button>
        <button type="button" class="quick-time-btn" onclick="setQuickTime('1d', this)">Tomorrow</button>
        <button type="button" class="quick-time-btn" onclick="setQuickTime('3d', this)">+3D</button>
        <button type="button" class="quick-time-btn" onclick="setQuickTime('1w', this)">+1W</button>
        <button type="button" class="quick-time-btn" onclick="setQuickTime('1m', this)">+1M</button>
      </div>
    </div>
    <div class="form-group"><label class="form-label">Description</label><textarea class="form-textarea" id="remDesc" placeholder="Optional notes or context…" style="min-height:64px"></textarea></div>
  </div>
  <div class="modal-foot">
    <button class="btn btn-ghost" onclick="closeModal('rem')">Cancel</button>
    <button class="btn btn-primary" onclick="saveReminder()"><i data-lucide="check" class="icon-sm"></i>Save Reminder</button>
  </div>
</div></div>

<!-- TASK MODAL -->
<div class="modal-overlay hidden" id="taskModal">
<div class="modal modal-sm">
  <div class="modal-head">
    <div><div class="modal-title" id="taskModalTitle">New Task</div><div class="modal-sub">Add to checklist</div></div>
    <button class="modal-close" onclick="closeModal('task')"><i data-lucide="x"></i></button>
  </div>
  <div class="modal-body">
    <input type="hidden" id="taskId">
    <div class="form-group"><label class="form-label">Title *</label><input type="text" class="form-input" id="taskTitle" placeholder="e.g. Prepare BAP for mediation session" autocomplete="off"></div>
    <div class="form-group"><label class="form-label">Description</label><textarea class="form-textarea" id="taskDesc" placeholder="Optional notes…" style="min-height:60px"></textarea></div>
  </div>
  <div class="modal-foot">
    <button class="btn btn-ghost" onclick="closeModal('task')">Cancel</button>
    <button class="btn btn-primary" onclick="saveTask()"><i data-lucide="check" class="icon-sm"></i>Save Task</button>
  </div>
</div></div>

<!-- SHIFT REPORT MODAL -->
<div class="modal-overlay hidden" id="shiftModal">
<div class="modal">
  <div class="modal-head">
    <div><div class="modal-title" id="shiftModalTitle">New Shift Report</div><div class="modal-sub">Handover active items</div></div>
    <button class="modal-close" onclick="closeModal('shift')"><i data-lucide="x"></i></button>
  </div>
  <div class="modal-body">
    <input type="hidden" id="shiftId">
    <div class="form-group"><label class="form-label">Title *</label><input type="text" class="form-input" id="shiftTitle" placeholder="e.g. Server reboot pending" autocomplete="off"></div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Work Date *</label>
        <input type="date" class="form-input" id="shiftDate" value="<?=date('Y-m-d')?>">
      </div>
      <div class="form-group"><label class="form-label">Shift</label>
        <select class="form-select" id="shiftName">
          <option value="Shift 1">Shift 1</option>
          <option value="Shift 2">Shift 2</option>
          <option value="Shift 3">Shift 3</option>
        </select>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Priority</label>
        <select class="form-select" id="shiftPriority">
          <option value="low">Low</option>
          <option value="medium" selected>Medium</option>
          <option value="high">High</option>
          <option value="critical">Critical</option>
        </select>
      </div>
      <div class="form-group" style="visibility:hidden"></div>
    </div>
    <div class="form-group"><label class="form-label">Details</label><textarea class="form-textarea" id="shiftDetails" placeholder="Handover context, steps taken, next actions..." style="min-height:100px"></textarea></div>
  </div>
  <div class="modal-foot">
    <button class="btn btn-ghost" onclick="closeModal('shift')">Cancel</button>
    <button class="btn btn-primary" onclick="saveShiftReport()"><i data-lucide="check" class="icon-sm"></i>Save Report</button>
  </div>
</div></div>

<!-- TICKER MANAGER MODAL -->
<div class="modal-overlay hidden" id="tickerModal">
<div class="modal modal-lg">
  <div class="modal-head">
    <div><div class="modal-title">Manage Announcements</div><div class="modal-sub">Control the live ticker bar messages</div></div>
    <button class="modal-close" onclick="closeModal('ticker')"><i data-lucide="x"></i></button>
  </div>
  <div class="modal-body">
    <div style="display:flex;gap:7px;align-items:flex-end">
      <div class="form-group" style="flex:1"><label class="form-label">New Message</label><input type="text" class="form-input" id="newTickerText" placeholder="Announcement text…" onkeydown="if(event.key==='Enter')addTickerMsg()"></div>
      <div class="form-group" style="width:105px"><label class="form-label">Type</label>
        <select class="form-select" id="newTickerCls"><option value="normal">Normal</option><option value="info">Info</option><option value="urgent">Urgent</option><option value="critical">Critical</option></select>
      </div>
      <button class="btn btn-primary" onclick="addTickerMsg()" style="height:34px;flex-shrink:0"><i data-lucide="plus" class="icon-sm"></i>Add</button>
    </div>
    <div style="border:1px solid var(--bd1);border-radius:var(--r2);overflow:hidden;max-height:300px;overflow-y:auto">
      <?php
      $mgr=array_filter($ticker_items??[],fn($t)=>isset($t['id']));
      if(empty($mgr)):?>
      <div class="empty"><div class="empty-ic"><i data-lucide="megaphone"></i></div><div class="empty-t">No custom announcements</div></div>
      <?php else: foreach($mgr as $i=>$it):
        $iid=$it['id']??$i;
        $cls=htmlspecialchars($it['class']??'normal');
        $txt=htmlspecialchars($it['text']??'');
        // Only show custom messages (those with an id), not auto-generated
        if(!isset($it['id']))continue;
      ?>
      <div class="tmgr-row" id="tmgr-<?=$iid?>">
        <span class="tmgr-type <?=$cls?>"><?=$cls?></span>
        <span class="tmgr-text"><?=$txt?></span>
        <button class="btn btn-danger btn-icon" onclick="archiveTickerMsg(<?=$iid?>)" title="Remove">
          <i data-lucide="trash-2" class="icon-sm"></i>
        </button>
      </div>
      <?php endforeach; endif;?>
    </div>
    <div style="font-size:10px;color:var(--tx3);font-family:var(--mono)">System alerts (critical cases, overdue items) appear automatically and cannot be removed here.</div>
  </div>
  <div class="modal-foot"><button class="btn btn-ghost" onclick="closeModal('ticker')">Close</button></div>
</div></div>

<!-- CONFIRM MODAL -->
<div class="modal-overlay hidden" id="confirmModal">
<div class="modal modal-sm">
  <div class="modal-head">
    <div><div class="modal-title" id="c-title">Confirm</div><div class="modal-sub">Please review this action</div></div>
    <button class="modal-close" onclick="closeModal('confirm')"><i data-lucide="x"></i></button>
  </div>
  <div class="modal-body">
    <p id="c-msg" style="margin:0;color:var(--tx2);line-height:1.5"></p>
  </div>
  <div class="modal-foot">
    <button class="btn btn-ghost" onclick="closeModal('confirm')">Cancel</button>
    <button class="btn btn-danger" id="c-ok"><i data-lucide="check" class="icon-sm"></i>Confirm</button>
  </div>
</div>
</div>

<!-- FEEDBACK MODAL -->
<div class="modal-overlay hidden" id="feedbackModal">
<div class="modal">
  <div class="modal-head">
    <div><div class="modal-title" id="feedbackModalTitle">New Cancellation Feedback</div><div class="modal-sub">Log retention insights</div></div>
    <button class="modal-close" onclick="closeModal('feedback')"><i data-lucide="x"></i></button>
  </div>
  <div class="modal-body">
    <input type="hidden" id="feedbackId">
    <div class="form-row">
      <div class="form-group"><label class="form-label">Submitter *</label><input type="text" class="form-input" id="feedbackSubmitter" placeholder="Your name" autocomplete="off"></div>
      <div class="form-group"><label class="form-label">Email Address</label><input type="email" class="form-input" id="feedbackEmail" placeholder="customer@email.com" autocomplete="off"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Cancelled Service *</label>
        <select class="form-select" id="feedbackService">
          <option value="">Select Service</option>
          <?php foreach(['Domain', 'Cloud Hosting cPanel', 'Wordpress Hosting', 'Reseller Hosting cPanel', 'Website Instant', 'Cloud VPS', 'VPS Pro', 'VPS Rocket', 'VPS AMD Extreme', 'SSL Comodo', 'Managed VPS WHM', 'Cyberpanel VPS', 'Email & Collaboration (Zimbra)', 'Dedicated Server', 'Baremetal Server', 'Colocation Server', 'Object Storage', 'Cloud Storage Drive', 'License', 'Kubernetes', 'Reseller Hosting Plesk', 'Cloud Hosting Plesk'] as $s): ?>
          <option value="<?=esc($s)?>"><?=esc($s)?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label class="form-label">Reason *</label>
        <select class="form-select" id="feedbackReason">
          <option value="">Select Reason</option>
          <?php foreach(['Service No Longer Required', 'Document activation requirements', 'Missing required features', 'Frequent downtime', 'Slow server performance', 'Network latency / packet loss', 'Resource limits', 'DDoS / security-related instability', 'Slow Response Time', 'Issue not resolved', 'Repeated Issue', 'Price Increase', 'Cheaper Competitor Found', 'Billing/Payment method issue', 'Service Expansion (Upgrade / New Order)', 'Unknown/No Feedback'] as $r): ?>
          <option value="<?=esc($r)?>"><?=esc($r)?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-group"><label class="form-label">WHMCS Reference / Domain / Hostname</label><input type="text" class="form-input" id="feedbackReference" placeholder="e.g. example.com or INV-123" autocomplete="off"></div>
    <div class="form-group"><label class="form-label">Payment Resolution</label>
      <select class="form-select" id="feedbackResolution">
        <option value="">Select Resolution</option>
        <?php foreach(['End of Billing Periode', 'Refund to Credit Balance', 'Refund to Bank Account / Paypal / CC'] as $res): ?>
        <option value="<?=esc($res)?>"><?=esc($res)?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label class="form-label">Additional Details</label><textarea class="form-textarea" id="feedbackDetails" placeholder="Customer comments, context, retention efforts..." style="min-height:100px"></textarea></div>
  </div>
  <div class="modal-foot">
    <button class="btn btn-ghost" onclick="closeModal('feedback')">Cancel</button>
    <button class="btn btn-primary" onclick="saveFeedback()"><i data-lucide="check" class="icon-sm"></i>Save Feedback</button>
  </div>
</div></div>

<?php if(in_array(($active_page??''), ['mom','dashboard'], true)): ?>
<!-- MOM MODALS -->
<div class="modal-overlay hidden" id="momFormModal"><div class="modal">
  <div class="modal-head">
    <div><div class="modal-title" id="momModalTitle">Add New Meeting</div><div class="modal-sub" id="momModalSub">Schedule operational coordination</div></div>
    <button class="modal-close" onclick="closeModal('momForm')"><i data-lucide="x"></i></button>
  </div>
  <div class="modal-body">
    <input type="hidden" id="momFormId">
    <div class="form-group"><label class="form-label">Title *</label><input type="text" class="form-input" id="momFormTitle" placeholder="e.g. Weekly Operations Sync" autocomplete="off"></div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Type</label><select class="form-select" id="momFormType"><option value="weekly">Weekly</option><option value="training">Training</option><option value="coordination">Coordination</option><option value="urgent">Urgent</option></select></div>
      <div class="form-group"><label class="form-label">Date</label><input type="date" class="form-input" id="momFormDate"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Time</label><input type="time" class="form-input" id="momFormTime"></div>
      <div class="form-group"><label class="form-label">Quick Time</label><div class="mom-quick-times"><button type="button" class="mom-quick-btn" data-mom-quick-hours="0">Now</button><button type="button" class="mom-quick-btn" data-mom-quick-hours="1">+1H</button><button type="button" class="mom-quick-btn" data-mom-quick-hours="24">Tomorrow</button></div></div>
    </div>
    <div class="form-group"><label class="form-label">Objective</label><textarea class="form-textarea" id="momFormObjective" placeholder="Meeting purpose..."></textarea></div>
    <div class="form-group"><label class="form-label">Meeting URL</label><input type="url" class="form-input" id="momFormUrl" placeholder="https://meet.google.com/... or https://zoom.us/..." autocomplete="off"></div>
    <div class="form-group"><label class="form-label">Participants</label><input type="text" class="form-input" id="momFormParticipants" placeholder="John, Sarah, Mike..." autocomplete="off"></div>
    <?php if(!empty($weekly_suggestions ?? [])): ?>
    <div class="form-group" id="momSuggestedCasesWrap">
      <label class="form-label">Suggested Cases To Discuss</label>
      <div class="mom-suggestion-list">
        <?php foreach(array_slice($weekly_suggestions, 0, 6) as $s):
          $sid=(int)($s['case_id']??0);
          $sprio=esc($s['priority']??'low');
          $sreason=esc(ucfirst(str_replace('_',' ', $s['suggestion_reason']??'unresolved')));
        ?>
        <button type="button" class="mom-suggestion-item" data-case-id="<?=$sid?>" onclick="toggleMOMSuggestedCase(this)">
          <span class="mom-suggestion-check"><i data-lucide="plus" class="icon-sm"></i></span>
          <span class="mom-suggestion-body">
            <span class="mom-suggestion-title">#<?=$sid?> <?=esc($s['title']??'Untitled case')?></span>
            <span class="mom-suggestion-meta"><span class="badge b-<?=$sprio?>"><?=ucfirst($sprio)?></span> <?=$sreason?></span>
          </span>
        </button>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
  <div class="modal-foot"><button class="btn btn-ghost" onclick="closeModal('momForm')">Cancel</button><button class="btn btn-primary" onclick="saveMOM()"><i data-lucide="check" class="icon-sm"></i>Schedule Meeting</button></div>
</div></div>

<div class="modal-overlay hidden" id="momActionFormModal"><div class="modal">
  <div class="modal-head">
    <div><div class="modal-title" id="momActionModalTitle">New Action Item</div><div class="modal-sub">Add actionable task from meeting</div></div>
    <button class="modal-close" onclick="closeModal('momActionForm')"><i data-lucide="x"></i></button>
  </div>
  <div class="modal-body">
    <input type="hidden" id="momActionFormId">
    <div class="form-group"><label class="form-label">Action Title *</label><input type="text" class="form-input" id="momActionFormTitle" placeholder="Action title" autocomplete="off"></div>
    <div class="form-group"><label class="form-label">Description</label><textarea class="form-textarea" id="momActionFormDesc" placeholder="Additional context..."></textarea></div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Assigned To</label><input type="text" class="form-input" id="momActionFormAssignee" placeholder="Name or initials" autocomplete="off"></div>
      <div class="form-group"><label class="form-label">Priority</label><select class="form-select" id="momActionFormPriority"><option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option><option value="critical">Critical</option></select></div>
    </div>
    <div class="form-group"><label class="form-label">Due Date</label><input type="date" class="form-input" id="momActionFormDueDate"></div>
  </div>
  <div class="modal-foot"><button class="btn btn-ghost" onclick="closeModal('momActionForm')">Cancel</button><button class="btn btn-primary" onclick="saveActionItem()"><i data-lucide="check" class="icon-sm"></i>Save Action</button></div>
</div></div>

<div class="modal-overlay hidden" id="momNoteFormModal"><div class="modal">
  <div class="modal-head">
    <div><div class="modal-title" id="momNoteModalTitle">Add Discussion Note</div><div class="modal-sub">Capture meeting discussion</div></div>
    <button class="modal-close" onclick="closeModal('momNoteForm')"><i data-lucide="x"></i></button>
  </div>
  <div class="modal-body">
    <input type="hidden" id="momNoteFormId">
    <div class="form-group"><label class="form-label">Note Type</label><select class="form-select" id="momNoteFormType"><option value="discussion">Discussion</option><option value="decision">Decision</option><option value="action">Action</option><option value="insight">Insight</option></select></div>
    <div class="form-group"><label class="form-label">Content *</label><textarea class="form-textarea" id="momNoteFormContent" placeholder="Write your note..." style="min-height:100px"></textarea></div>
  </div>
  <div class="modal-foot"><button class="btn btn-ghost" onclick="closeModal('momNoteForm')">Cancel</button><button class="btn btn-primary" onclick="saveDiscussionNote()"><i data-lucide="check" class="icon-sm"></i>Save Note</button></div>
</div></div>

<div class="modal-overlay hidden" id="momDecisionFormModal"><div class="modal">
  <div class="modal-head">
    <div><div class="modal-title" id="momDecisionModalTitle">Add Decision</div><div class="modal-sub">Log meeting decision with context</div></div>
    <button class="modal-close" onclick="closeModal('momDecisionForm')"><i data-lucide="x"></i></button>
  </div>
  <div class="modal-body">
    <input type="hidden" id="momDecisionFormId">
    <div class="form-group"><label class="form-label">Decision *</label><textarea class="form-textarea" id="momDecisionFormText" placeholder="What was decided?" style="min-height:60px"></textarea></div>
    <div class="form-group"><label class="form-label">Rationale</label><textarea class="form-textarea" id="momDecisionFormRationale" placeholder="Why this decision?" style="min-height:60px"></textarea></div>
    <div class="form-group"><label class="form-label">Owner</label><input type="text" class="form-input" id="momDecisionFormOwner" placeholder="Person responsible" autocomplete="off"></div>
  </div>
  <div class="modal-foot"><button class="btn btn-ghost" onclick="closeModal('momDecisionForm')">Cancel</button><button class="btn btn-primary" onclick="saveDecision()"><i data-lucide="check" class="icon-sm"></i>Save Decision</button></div>
</div></div>
<?php endif; ?>

</div><!-- /body-row -->
</div><!-- /shell -->
<?php $_tracs_js_v = @filemtime(__DIR__.'/../assets/tracs.js') ?: time(); ?>
<script src="assets/tracs.js?v=<?=$_tracs_js_v?>"></script>
<?php if(in_array(($active_page??''), ['mom','dashboard'], true)): ?>
<?php $_mom_js_v = @filemtime(__DIR__.'/../assets/mom-functions.js') ?: time(); ?>
<script src="assets/mom-functions.js?v=<?=$_mom_js_v?>"></script>
<?php endif; ?>
<script>
  lucide.createIcons();
</script>
</body>
</html>
