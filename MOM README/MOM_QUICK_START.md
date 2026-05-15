# TRACS MOM Module — Quick Start & Deliverables Summary

---

## 📦 Complete Deliverables

This package contains everything needed to integrate MOM into TRACS:

### Core Files
| File | Type | Purpose | Deployment Path |
|------|------|---------|-----------------|
| `mom.php` | PHP Page | Main meeting workspace | `/tracs/public/mom.php` |
| `MOMController.php` | PHP Class | Business logic & DB operations | `/tracs/modules/mom/controller.php` |
| `api_mom.php` | PHP API | REST endpoint for AJAX | `/tracs/api/mom.php` |
| `mom-styles.css` | CSS | Component styles | `/tracs/public/assets/mom-styles.css` |
| `mom-functions.js` | JavaScript | Client-side functionality | `/tracs/public/assets/mom-functions.js` |
| `mom_database_schema.sql` | SQL | Database tables & views | Run in MySQL directly |

### Documentation
| File | Purpose |
|------|---------|
| `MOM_IMPLEMENTATION_GUIDE.md` | Complete implementation guide |
| `MOM_QUICK_START.md` | This file |

---

## ⚡ Quick Installation (15 minutes)

### 1. Create Database Tables (1 minute)

```bash
# Connect to TRACS database
mysql -u tracs_user -p tracs_db

# Paste contents of mom_database_schema.sql
# Or run from file:
mysql -u tracs_user -p tracs_db < mom_database_schema.sql

# Verify
SHOW TABLES LIKE 'tracs_mom%';
```

### 2. Deploy Files (3 minutes)

```bash
# Navigate to TRACS root
cd /var/www/html/tracs

# Create MOM module directory
mkdir -p modules/mom

# Copy files
cp mom.php public/
cp MOMController.php modules/mom/controller.php
cp api_mom.php api/mom.php
cp mom-styles.css public/assets/
cp mom-functions.js public/assets/

# Set permissions
chmod 644 public/mom.php
chmod 644 modules/mom/controller.php
chmod 644 api/mom.php
chmod 644 public/assets/mom-styles.css
chmod 644 public/assets/mom-functions.js
```

### 3. Update Existing Files (5 minutes)

#### A. `/tracs/public/includes/header.php`

Find the sidebar navigation section (around line 35) and add:

```php
    <a href="mom.php" class="nav-item <?=$active_page==='mom'?'active':''?>">
      <i data-lucide="clipboard-list" class="icon-md"></i>
      <span class="nav-tip">Meetings</span>
    </a>
```

#### B. `/tracs/public/assets/tracs.css`

At the very end of the file, add:

```css
/* MOM Module Styles */
@import url('mom-styles.css');
```

#### C. `/tracs/public/assets/tracs.js`

Before the closing `</script>` tag, add:

```javascript
// Load MOM functions
if(document.currentScript) {
  const momScript = document.createElement('script');
  momScript.src = 'assets/mom-functions.js';
  momScript.onload = () => {
    // MOM functions loaded
  };
  document.head.appendChild(momScript);
}
```

Or simpler, add this script tag in footer.php before closing `</body>`:

```html
<script src="assets/mom-functions.js"></script>
```

#### D. `/tracs/public/includes/footer.php`

Before the line `</div><!-- /body-row -->`, add all MOM modals:

```php
<!-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
     MOM MODALS
     ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->

<!-- NEW/EDIT MOM MODAL -->
<div class="modal-overlay hidden" id="momFormModal">
<div class="modal">
  <div class="modal-head">
    <div>
      <div class="modal-title" id="momModalTitle">New Meeting</div>
      <div class="modal-sub" id="momModalSub">Create minutes of meeting</div>
    </div>
    <button class="modal-close" onclick="closeModal('mom-form')"><i data-lucide="x"></i></button>
  </div>
  <div class="modal-body">
    <input type="hidden" id="momFormId">
    <div class="form-group">
      <label class="form-label">Title *</label>
      <input type="text" class="form-input" id="momFormTitle" placeholder="e.g. Weekly Sync — May 14" autocomplete="off">
    </div>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Type</label>
        <select class="form-select" id="momFormType">
          <option value="weekly">Weekly</option>
          <option value="training">Training</option>
          <option value="coordination">Coordination</option>
          <option value="urgent">Urgent</option>
        </select>
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Objective</label>
      <textarea class="form-textarea" id="momFormObjective" placeholder="Meeting purpose..."></textarea>
    </div>
    <div class="form-group">
      <label class="form-label">Participants</label>
      <input type="text" class="form-input" id="momFormParticipants" placeholder="John, Sarah, Mike..." autocomplete="off">
    </div>
  </div>
  <div class="modal-foot">
    <button class="btn btn-ghost" onclick="closeModal('mom-form')">Cancel</button>
    <button class="btn btn-primary" onclick="saveMOM()"><i data-lucide="check" class="icon-sm"></i>Save</button>
  </div>
</div>
</div>

<!-- ACTION ITEM MODAL -->
<div class="modal-overlay hidden" id="momActionFormModal">
<div class="modal">
  <div class="modal-head">
    <div>
      <div class="modal-title" id="momActionModalTitle">New Action Item</div>
      <div class="modal-sub">Add actionable task</div>
    </div>
    <button class="modal-close" onclick="closeModal('mom-action-form')"><i data-lucide="x"></i></button>
  </div>
  <div class="modal-body">
    <input type="hidden" id="momActionFormId">
    <div class="form-group">
      <label class="form-label">Title *</label>
      <input type="text" class="form-input" id="momActionFormTitle" placeholder="Action title" autocomplete="off">
    </div>
    <div class="form-group">
      <label class="form-label">Description</label>
      <textarea class="form-textarea" id="momActionFormDesc" placeholder="Details..."></textarea>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Assigned To</label>
        <input type="text" class="form-input" id="momActionFormAssignee" placeholder="Name" autocomplete="off">
      </div>
      <div class="form-group">
        <label class="form-label">Priority</label>
        <select class="form-select" id="momActionFormPriority">
          <option value="low">Low</option>
          <option value="medium" selected>Medium</option>
          <option value="high">High</option>
          <option value="critical">Critical</option>
        </select>
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Due Date</label>
      <input type="date" class="form-input" id="momActionFormDueDate">
    </div>
  </div>
  <div class="modal-foot">
    <button class="btn btn-ghost" onclick="closeModal('mom-action-form')">Cancel</button>
    <button class="btn btn-primary" onclick="saveActionItem()"><i data-lucide="check" class="icon-sm"></i>Save</button>
  </div>
</div>
</div>

<!-- DISCUSSION NOTE MODAL -->
<div class="modal-overlay hidden" id="momNoteFormModal">
<div class="modal">
  <div class="modal-head">
    <div>
      <div class="modal-title">Add Discussion Note</div>
      <div class="modal-sub">Capture meeting discussion</div>
    </div>
    <button class="modal-close" onclick="closeModal('mom-note-form')"><i data-lucide="x"></i></button>
  </div>
  <div class="modal-body">
    <input type="hidden" id="momNoteFormId">
    <div class="form-group">
      <label class="form-label">Note Type</label>
      <select class="form-select" id="momNoteFormType">
        <option value="discussion">Discussion</option>
        <option value="decision">Decision</option>
        <option value="action">Action</option>
        <option value="insight">Insight</option>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label">Content *</label>
      <textarea class="form-textarea" id="momNoteFormContent" placeholder="Write your note..." style="min-height:100px"></textarea>
    </div>
  </div>
  <div class="modal-foot">
    <button class="btn btn-ghost" onclick="closeModal('mom-note-form')">Cancel</button>
    <button class="btn btn-primary" onclick="saveDiscussionNote()"><i data-lucide="check" class="icon-sm"></i>Save Note</button>
  </div>
</div>
</div>

<!-- DECISION MODAL -->
<div class="modal-overlay hidden" id="momDecisionFormModal">
<div class="modal">
  <div class="modal-head">
    <div>
      <div class="modal-title" id="momDecisionModalTitle">Add Decision</div>
      <div class="modal-sub">Log meeting decision with context</div>
    </div>
    <button class="modal-close" onclick="closeModal('mom-decision-form')"><i data-lucide="x"></i></button>
  </div>
  <div class="modal-body">
    <input type="hidden" id="momDecisionFormId">
    <div class="form-group">
      <label class="form-label">Decision *</label>
      <textarea class="form-textarea" id="momDecisionFormText" placeholder="What was decided?" style="min-height:60px"></textarea>
    </div>
    <div class="form-group">
      <label class="form-label">Rationale</label>
      <textarea class="form-textarea" id="momDecisionFormRationale" placeholder="Why this decision?" style="min-height:60px"></textarea>
    </div>
    <div class="form-group">
      <label class="form-label">Owner</label>
      <input type="text" class="form-input" id="momDecisionFormOwner" placeholder="Person responsible" autocomplete="off">
    </div>
  </div>
  <div class="modal-foot">
    <button class="btn btn-ghost" onclick="closeModal('mom-decision-form')">Cancel</button>
    <button class="btn btn-primary" onclick="saveDecision()"><i data-lucide="check" class="icon-sm"></i>Save Decision</button>
  </div>
</div>
</div>
```

#### E. `/tracs/public/includes/page_helpers.php`

Add these helper functions at the end:

```php
<?php
/**
 * Reminder status class for badges
 */
function rem_status_class($status) {
  $status = strtolower($status ?? '');
  return match($status) {
    'overdue' => 'status-overdue',
    'today' => 'status-today',
    'upcoming' => 'status-upcoming',
    default => 'status-upcoming'
  };
}

/**
 * Safe datetime conversion to local format
 */
function safe_dt_local($datetime) {
  if(!$datetime) return '';
  try {
    return strtotime($datetime);
  } catch(Exception $e) {
    return '';
  }
}

/**
 * Format datetime nicely
 */
function safe_dt($datetime, $format = 'd M Y, H:i') {
  if(!$datetime) return '—';
  try {
    return date($format, strtotime($datetime));
  } catch(Exception $e) {
    return '—';
  }
}
?>
```

### 4. Test Installation (3 minutes)

1. Navigate to `https://your-tracs.local/public/mom.php`
2. Should show "Minutes of Meeting" page with meeting list
3. Click "New Meeting" button
4. Fill form and save
5. Should see meeting appear in list
6. Click meeting ID to open workspace
7. Try adding agenda item, note, action, decision
8. Verify sidebar shows related data

### 5. Verify Integration (3 minutes)

- [ ] MOM appears in sidebar navigation
- [ ] New meeting can be created
- [ ] Meeting workspace loads
- [ ] Can add agenda items
- [ ] Can add discussion notes
- [ ] Can add decisions
- [ ] Can create action items
- [ ] Can create reminders from actions
- [ ] Actions appear in Reminders module
- [ ] Browser console shows no errors

---

## 🎯 Key Features At A Glance

### Meeting Workspace
- **Objective**: Define why meeting is happening
- **Participants**: Track who attended
- **Agenda**: Checklist of topics to discuss
- **Discussion Notes**: Rich notes with timestamps
- **Decisions**: Record decisions with rationale and owner
- **Action Items**: Create tasks with priority and deadline

### Operational Integration
- **Quick Reminders**: One-click convert action → reminder
- **Case Linking**: Link or create operational cases
- **Dashboard Widget**: Actions show on main dashboard
- **Ticker Alerts**: Overdue actions alert on ticker
- **Activity Log**: Complete audit trail of all changes

### Sidebar Panel (Sticky)
- **Participants**: Quick reference
- **Related Reminders**: Linked action reminders
- **Linked Cases**: Related cases
- **Progress Tracker**: % actions complete
- **Operational Insights**: Key metrics

---

## 🔐 Security Features

✅ **Session-based authentication** (inherits from TRACS)  
✅ **User ownership verification** (users only see own MOMs)  
✅ **Prepared statements** (SQL injection prevention)  
✅ **Input validation** (all fields validated)  
✅ **Output escaping** (XSS prevention)  
✅ **Enum validation** (type/priority/status fields)  

---

## 🎨 Design System Alignment

✅ Matches TRACS color scheme (light/dark adaptive)  
✅ Uses same typography (Inter + JetBrains Mono)  
✅ Consistent spacing (8px system)  
✅ Same border radius system  
✅ Lucide icons throughout  
✅ Flatpickr date pickers  
✅ Modal system unified  

---

## 📊 Database Schema

**8 Tables Created:**
- `tracs_moms` — Main meetings table
- `tracs_mom_agenda` — Agenda items
- `tracs_mom_notes` — Discussion notes
- `tracs_mom_decisions` — Decisions log
- `tracs_mom_actions` — Action items
- `tracs_mom_case_links` — Case relationships
- `tracs_mom_screenshots` — Screenshot attachments
- `tracs_mom_audit_log` — Audit trail

**3 Views Created:**
- `vw_mom_summary` — Meeting metrics
- `vw_mom_overdue_actions` — Overdue tracking
- Plus helper procedures for completion logic

---

## 🔄 Data Flow

```
Meeting Workspace
    ↓
Agenda Items (checked)
    ↓
Discussion Notes
    ↓ Text selection
Decision + Action Items
    ↓
    ├─→ Create Reminder (auto-synced)
    │   ├─→ Dashboard Widget
    │   ├─→ Ticker Alert (if overdue)
    │   └─→ Reminder Module
    │
    └─→ Create Case (optional)
        ├─→ Case Module
        └─→ Linked back to MOM

Activity Log (all changes)
    ↓
Audit Trail (permanent record)
```

---

## 🚀 Common Actions

### Create Meeting from Weekly Suggestions
```
1. Go to MOM module
2. See suggested unresolved cases
3. Click "New Meeting" (type = Weekly)
4. Click case links in suggestions
5. System shows discussion context
6. Create actions to resolve
7. System creates reminders
8. Dashboard tracks progress
```

### Convert Action to Reminder
```
1. In meeting workspace
2. Create action item
3. Click "Create Reminder" button
4. Reminder synced to Reminders module
5. Appears on dashboard
6. Ticker alerts if overdue
```

### Link Existing Case
```
1. In meeting workspace
2. Click "Link Case" in sidebar
3. Enter Case ID
4. Case appears in sidebar
5. MOM reference visible in case
```

---

## 📞 Support

**Issues?** Check these in order:

1. **Database tables missing** → Execute SQL schema
2. **PHP errors** → Check file paths match documentation
3. **JavaScript errors** → Open browser console (F12)
4. **Reminders not syncing** → Verify ReminderController works
5. **Cases not linking** → Check case ownership

**Full docs:** See `MOM_IMPLEMENTATION_GUIDE.md`

---

## ✅ Deployment Verification Checklist

```
Pre-deployment:
☐ All 6 core files obtained
☐ Database backup created
☐ Read entire IMPLEMENTATION_GUIDE.md

Installation:
☐ Database tables created (mysql import)
☐ PHP files deployed to correct paths
☐ File permissions set to 644
☐ header.php updated with nav item
☐ footer.php updated with modals
☐ tracs.css imports mom-styles.css
☐ tracs.js loads mom-functions.js
☐ page_helpers.php has helper functions

Testing:
☐ Navigate to mom.php loads
☐ "New Meeting" button works
☐ Can create and save meeting
☐ Meeting workspace opens
☐ Can add agenda items
☐ Can add discussion notes
☐ Can add decisions
☐ Can create action items
☐ Can create reminders from actions
☐ Reminders appear in Reminders module
☐ Browser console clean (no errors)

Integration:
☐ MOM shows in sidebar
☐ Dashboard widget works
☐ Ticker shows overdue actions
☐ Cases can be linked
☐ Activity log records changes
```

---

## 🎓 Next Steps

1. **Review** — Read the full Implementation Guide
2. **Deploy** — Follow Installation steps (15 min)
3. **Test** — Create sample meeting end-to-end
4. **Train** — Show team how to use
5. **Iterate** — Gather feedback, refinements

---

**MOM Module v1.0** | Production Ready  
**Compatible with:** TRACS 3.0+  
**Last Updated:** May 2025
