# TRACS MOM — Visual Guides & Architecture Diagrams

---

## 📊 Data Flow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                      USER CREATES MEETING                       │
│  mom.php?action=new → openNewMOM() → Save via api_mom.php      │
└────────────────────────┬────────────────────────────────────────┘
                         ↓
              ┌──────────────────────┐
              │   MOM WORKSPACE      │
              │  (mom.php?mom_id=X)  │
              └──────┬───────────────┘
                     │
        ┌────────────┼────────────┬──────────────┐
        ↓            ↓            ↓              ↓
    ┌────────┐  ┌─────────┐  ┌────────┐  ┌──────────┐
    │ AGENDA │  │ DISCUSS │  │DECISION│  │ ACTIONS  │
    │ ITEMS  │  │ NOTES   │  │        │  │ ITEMS    │
    └───┬────┘  └────┬────┘  └───┬────┘  └────┬─────┘
        │            │           │            │
        │ (optional) │ Select    │ From       │ From
        │            │ text→     │ decision   │ decision
        │            │ action    │            │ or form
        └────────┬───┴────────┬──┴────────────┴────┐
                 ↓            ↓                    ↓
          ┌────────────────────────────────────────────┐
          │    API: api_mom.php                        │
          │  Validate → Authenticate → Execute → Log   │
          └────────────┬───────────────────────────────┘
                       ↓
          ┌────────────────────────────────────────────┐
          │    DATABASE: tracs_mom_*                   │
          │  Insert/Update/Read operations             │
          └────────────┬───────────────────────────────┘
                       ↓
         ┌─────────────────────────────────────────────┐
         │   SMART INTEGRATIONS (Auto-Triggered)      │
         └────────┬────────────────┬──────────┬────────┘
                  ↓                ↓          ↓
            ┌────────────┐  ┌──────────┐  ┌──────┐
            │ REMINDERS  │  │  CASES   │  │TICKER│
            │ Module     │  │  Module  │  │Alert │
            │ (synced)   │  │ (linked) │  │      │
            └────┬───────┘  └────┬─────┘  └──┬───┘
                 │               │           │
                 └─────────────┬─┴───────────┘
                               ↓
                    ┌──────────────────────┐
                    │   DASHBOARD         │
                    │ Widget: Actions      │
                    │ Progress %           │
                    │ Pending items        │
                    └──────────────────────┘
```

---

## 🎯 Meeting Workflow

```
START MEETING
    ↓
┌─────────────────────────┐
│ 1. SET OBJECTIVE        │
│    • Why meet?          │
│    • What to achieve?   │
└────────────┬────────────┘
             ↓
┌─────────────────────────┐
│ 2. ADD PARTICIPANTS     │
│    • Who's attending?   │
└────────────┬────────────┘
             ↓
┌─────────────────────────┐
│ 3. BUILD AGENDA         │
│    • Topics to discuss  │
│    • Check off as done  │
└────────────┬────────────┘
             ↓
┌─────────────────────────┐
│ 4. TAKE NOTES           │
│    • Rich discussion    │
│    • Timestamps         │
│    • Categorize         │
└────────────┬────────────┘
             ↓
    ┌────────────────────┐
    │ SELECT TEXT        │
    │ → Quick Actions    │
    │ ├─ Create Action   │
    │ ├─ Create Reminder │
    │ └─ Add Decision    │
    └────────────────────┘
             ↓
┌─────────────────────────┐
│ 5. RECORD DECISIONS     │
│    • What was decided?  │
│    • Why (rationale)?   │
│    • Who owns it?       │
└────────────┬────────────┘
             ↓
┌─────────────────────────┐
│ 6. CREATE ACTIONS       │
│    • Title (required)   │
│    • Assigned to        │
│    • Priority           │
│    • Due date           │
└────────────┬────────────┘
             ↓
┌──────────────────────────────────┐
│ 7. AUTO: CREATE REMINDERS        │
│    └─ For critical/high priority │
│    └─ Sync to Reminders module   │
└────────────┬─────────────────────┘
             ↓
┌──────────────────────────────────┐
│ 8. OPTIONAL: LINK CASES          │
│    ├─ Link existing case         │
│    └─ Create new case            │
└────────────┬─────────────────────┘
             ↓
┌─────────────────────────┐
│ 9. REVIEW PROGRESS      │
│    • % Actions done     │
│    • Participants list  │
│    • Decision count     │
└────────────┬────────────┘
             ↓
┌─────────────────────────┐
│ 10. CLOSE MEETING       │
│     • Mark completed    │
│     • Lock from editing │
└────────────┬────────────┘
             ↓
POST-MEETING: MONITORING
    ├─ Dashboard shows actions
    ├─ Ticker alerts overdue
    ├─ Reminders tracked
    └─ Case progress monitored
             ↓
         RESOLUTION
```

---

## 🏗️ System Architecture

```
┌──────────────────────────────────────────────────────────────┐
│                        TRACS CORE                           │
│  ┌─────────────────────────────────────────────────────────┐ │
│  │              Navigation & Authentication                │ │
│  │  (header.php, session, auth_check.php)                  │ │
│  └─────────────────────────────────────────────────────────┘ │
└──────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────┐
│                    MOM MODULE LAYER                         │
│  ┌──────────────────────────────────────────────────────────┐│
│  │  PUBLIC/PAGES                                           ││
│  │  ├─ mom.php (Workspace + List View)                    ││
│  │  └─ Modals (footer.php integration)                    ││
│  └──────┬───────────────────────────────────────────────────┘│
│         │                                                    │
│  ┌──────▼───────────────────────────────────────────────────┐│
│  │  API LAYER                                              ││
│  │  └─ api/mom.php (REST endpoint)                        ││
│  │     ├─ Validate & Authorize                            ││
│  │     ├─ Parse JSON                                      ││
│  │     └─ Route to Controller                             ││
│  └──────┬───────────────────────────────────────────────────┘│
│         │                                                    │
│  ┌──────▼───────────────────────────────────────────────────┐│
│  │  BUSINESS LOGIC                                         ││
│  │  └─ modules/mom/MOMController.php                      ││
│  │     ├─ Create/Read/Update/Delete MOMs                 ││
│  │     ├─ Manage Agenda, Notes, Decisions, Actions      ││
│  │     ├─ Link Cases & Reminders                         ││
│  │     └─ Log Activities                                 ││
│  └──────┬───────────────────────────────────────────────────┘│
│         │                                                    │
│  ┌──────▼───────────────────────────────────────────────────┐│
│  │  DATABASE LAYER                                         ││
│  │  └─ tracs_mom_* tables                                 ││
│  │     ├─ tracs_moms                                      ││
│  │     ├─ tracs_mom_agenda                                ││
│  │     ├─ tracs_mom_notes                                 ││
│  │     ├─ tracs_mom_decisions                             ││
│  │     ├─ tracs_mom_actions                               ││
│  │     ├─ tracs_mom_case_links                            ││
│  │     ├─ tracs_mom_screenshots                           ││
│  │     └─ tracs_mom_audit_log                             ││
│  └──────────────────────────────────────────────────────────┘│
└──────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────┐
│                 PRESENTATION LAYER                          │
│  ┌────────────────────────────────────────────────────────┐ │
│  │  CSS                          JavaScript              │ │
│  │  ├─ mom-styles.css            ├─ mom-functions.js   │ │
│  │  ├─ Light/dark adaptive       ├─ Modal mgmt         │ │
│  │  ├─ Responsive layout         ├─ AJAX operations    │ │
│  │  └─ Component styling         └─ Event handlers     │ │
│  └────────────────────────────────────────────────────────┘ │
└──────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────┐
│                   INTEGRATION POINTS                        │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │  Reminders   │  │    Cases     │  │  Dashboard   │      │
│  │   Module     │  │   Module     │  │   Widget     │      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │ Activity Log │  │   Ticker     │  │ Sidebar Nav  │      │
│  │   System     │  │    System    │  │              │      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
└──────────────────────────────────────────────────────────────┘
```

---

## 🔄 Action Item Lifecycle

```
ACTION CREATED
    │
    ├─ From text selection in discussion
    ├─ From decision
    └─ From new action form
    │
    ↓
┌─────────────────────────────┐
│ ACTION PROPERTIES SET       │
│ • Title (required)          │
│ • Description (optional)    │
│ • Assigned to (person name) │
│ • Priority (critical→low)   │
│ • Due date (recommended)    │
└────────────────┬────────────┘
                 ↓
         ┌───────────────┐
         │ SAVE TO DB    │
         │ tracs_mom_    │
         │ actions       │
         └───────┬───────┘
                 ↓
    ┌────────────────────────────┐
    │ OPTIONAL: CREATE REMINDER  │
    │ ├─ One-click button        │
    │ ├─ Auto-fills from action  │
    │ └─ Syncs to Reminders DB   │
    └────────────┬───────────────┘
                 ↓
        ┌────────────────────┐
        │ REMINDER INTEGRATION
        ├─ Shows in Reminders tab
        ├─ Dashboard widget
        ├─ Ticker if overdue
        └─ Escalation alerts
                 ↓
    ┌────────────────────────────┐
    │ ACTION TRACKING            │
    ├─ Checkbox (complete)       │
    ├─ Status updates            │
    ├─ Progress bar (sidebar)    │
    └─ Activity log recorded     │
                 ↓
┌─────────────────────────────┐
│ ACTION COMPLETED            │
│ ├─ Mark checkbox            │
│ ├─ Reminder marks complete  │
│ ├─ Dashboard updates        │
│ └─ Activity logged          │
└─────────────────────────────┘
```

---

## 📱 UI Layout

```
┌──────────────────────────────────────────────────────────────┐
│                      HEADER (TRACS)                         │
│  Ticker Bar  │  Logo  │  Title  │  Nav Buttons              │
├──────────────────────────────────────────────────────────────┤
│  │          │                                              │  │
│  │  SIDEBAR │         MOM WORKSPACE (LEFT PANEL)            │  │
│  │          │                                              │  │
│  │  MOM Nav │  ┌─────────────────────────────────────────┐ │  │
│  │  ─────── │  │ MOM HEADER                              │ │  │
│  │ ┌──────┐ │  │ Title │ Type │ Status │ Date    │ btns │ │  │
│  │ │Cases │ │  ├─────────────────────────────────────────┤ │  │
│  │ │Remind│ │  │ OBJECTIVE SECTION                       │ │  │
│  │ │Tasks │ │  ├─────────────────────────────────────────┤ │  │
│  │ │MOM   │ │  │ AGENDA ITEMS                            │ │  │
│  │ │Shift │ │  ├─────────────────────────────────────────┤ │  │
│  │ └──────┘ │  │ DISCUSSION NOTES                        │ │  │
│  │          │  │ [Select text → quick actions menu]      │ │  │
│  │          │  ├─────────────────────────────────────────┤ │  │
│  │          │  │ DECISIONS                               │ │  │
│  │          │  ├─────────────────────────────────────────┤ │  │
│  │          │  │ ACTION ITEMS (with progress %)          │ │  │
│  │          │  └─────────────────────────────────────────┘ │  │
│  │          │                                              │  │
│  │          │   RIGHT SIDEBAR (STICKY)                     │  │
│  │          │  ┌──────────────────────┐                   │  │
│  │          │  │ PARTICIPANTS         │                   │  │
│  │          │  │ └─ Edit Participants │                   │  │
│  │          │  ├──────────────────────┤                   │  │
│  │          │  │ REMINDERS (linked)   │                   │  │
│  │          │  │ ┌─ Reminder 1      ┐ │                   │  │
│  │          │  │ └─ Reminder 2      ┘ │                   │  │
│  │          │  ├──────────────────────┤                   │  │
│  │          │  │ LINKED CASES         │                   │  │
│  │          │  │ └─ Case #123       → │                   │  │
│  │          │  ├──────────────────────┤                   │  │
│  │          │  │ ACTION PROGRESS      │                   │  │
│  │          │  │ [████░░░░] 40% Done │                   │  │
│  │          │  ├──────────────────────┤                   │  │
│  │          │  │ OPERATIONAL INSIGHTS │                   │  │
│  │          │  │ • Unresolved: 5      │                   │  │
│  │          │  │ • Overdue: 2         │                   │  │
│  │          │  │ • SLA Risk: —        │                   │  │
│  │          │  └──────────────────────┘                   │  │
│  │          │                                              │  │
└──────────────────────────────────────────────────────────────┘
│                      FOOTER                                  │
└──────────────────────────────────────────────────────────────┘
```

---

## 🌐 API Request/Response Flow

```
CLIENT (JavaScript)
    │
    ├─ User action (create, save, delete)
    │
    └─ AJAX POST: api/mom.php
       └─ JSON body:
          {
            "action": "create_action_item",
            "mom_id": 123,
            "title": "Send email to customer",
            "assigned_to": "John",
            "priority": "high",
            "due_date": "2025-05-15"
          }

SERVER (PHP)
    │
    ├─ Check session (authenticate)
    │
    ├─ Parse JSON input
    │
    ├─ Validate:
    │   ├─ Required fields
    │   ├─ Data types
    │   └─ Enum values
    │
    ├─ Call MOMController method
    │   └─ createActionItem()
    │
    ├─ Execute database operation
    │   └─ INSERT into tracs_mom_actions
    │
    ├─ Log activity
    │   └─ INSERT into tracs_activity_logs
    │
    └─ Return JSON response:
       {
         "ok": true,
         "action_id": 789,
         "msg": "Action item created"
       }

CLIENT (JavaScript)
    │
    ├─ Receive response
    │
    ├─ Check: if(r.ok)
    │
    ├─ Update UI:
    │   ├─ Close modal
    │   ├─ Add item to list
    │   └─ Show success toast
    │
    └─ Optional: Reload page
       └─ Fetch updated data
```

---

## 🔐 Security Validation Layer

```
INPUT RECEIVED (api_mom.php)
    │
    ├─ Session Check
    │   └─ $_SESSION['user_id'] required?
    │
    ├─ Input Parsing
    │   └─ json_decode() or $_POST
    │
    ├─ Action Validation
    │   ├─ Action name is string?
    │   └─ Action is recognized?
    │
    ├─ User Ownership Check
    │   └─ Does user own this resource?
    │
    ├─ Data Validation
    │   ├─ Required fields present?
    │   ├─ Data types correct?
    │   └─ Enum values valid?
    │
    ├─ SQL Injection Prevention
    │   └─ Prepared statements + parameter binding
    │
    ├─ Execute Operation
    │   └─ MOMController::method()
    │
    ├─ Log Activity
    │   └─ activity_logs table
    │
    └─ Return Response
       └─ JSON: {ok, msg, data}
```

---

## 📊 Database Relationships

```
tracs_moms (Main table)
    │
    ├─→ tracs_mom_agenda (1:Many)
    │   └─ Topics discussed
    │
    ├─→ tracs_mom_notes (1:Many)
    │   └─ Discussion notes
    │
    ├─→ tracs_mom_decisions (1:Many)
    │   └─ Decisions made
    │
    ├─→ tracs_mom_actions (1:Many)
    │   ├─ linked_reminder_id → tracs_reminders (0:1)
    │   └─ linked_case_id → tracs_cases (0:1)
    │
    ├─→ tracs_mom_case_links (1:Many)
    │   └─ case_id → tracs_cases
    │
    ├─→ tracs_mom_screenshots (1:Many)
    │   └─ Screenshot attachments
    │
    └─→ tracs_mom_audit_log (1:Many)
        └─ Activity trail
```

---

## 🎨 Color Coding Reference

```
PRIORITY COLORS
┌──────────────┬──────────────────┐
│ Critical     │ Red (#dc2626)    │
├──────────────┼──────────────────┤
│ High         │ Amber (#d97706)  │
├──────────────┼──────────────────┤
│ Medium       │ Blue (#2563eb)   │
├──────────────┼──────────────────┤
│ Low          │ Gray (#aab0ba)   │
└──────────────┴──────────────────┘

STATUS COLORS
┌──────────────┬──────────────────┐
│ Active       │ Green (#16a34a)  │
├──────────────┼──────────────────┤
│ Completed    │ Green (lighter)  │
├──────────────┼──────────────────┤
│ Overdue      │ Red (#dc2626)    │
├──────────────┼──────────────────┤
│ Today        │ Amber (#d97706)  │
├──────────────┼──────────────────┤
│ Upcoming     │ Cyan (#0891b2)   │
├──────────────┼──────────────────┤
│ Stuck        │ Purple (#7c3aed) │
└──────────────┴──────────────────┘
```

---

## 📈 Metrics Dashboard

```
MOM METRICS (Visible in Sidebar)

Action Completion:
  [████░░░░░] 40% (2/5 completed)
  
Unresolved Cases:
  ◉ 5 cases (linked to discussions)
  
Overdue Reminders:
  ⚠ 2 items overdue
  
SLA at Risk:
  — (0 items at risk)
```

---

## 🎯 Quick Action Menu (Text Selection)

```
When user selects text in discussion:

┌─────────────────────────────────┐
│ Selection Menu (appears at top)  │
├─────────────────────────────────┤
│ ✓ Create Action                 │
│ ★ Create Reminder               │
│ ≡ Add Decision                  │
│ ⊕ Create Case                   │
└─────────────────────────────────┘

Selected text: "Fix database performance issue"
    │
    ├─ Create Action:
    │   └─ Title auto-filled
    │       Open action form
    │
    ├─ Create Reminder:
    │   └─ Set time
    │       Auto-link to action
    │
    ├─ Add Decision:
    │   └─ Decision text filled
    │       Add rationale
    │
    └─ Create Case:
        └─ New case created
            Auto-linked to MOM
```

---

## 🔄 Weekly Meeting Suggestions

```
WEEKLY MEETING: Suggested Cases

SUGGESTION ENGINE:
  └─ Find cases from last 7 days
     ├─ Status: NOT completed
     ├─ AND one of:
     │   ├─ Status = stuck
     │   ├─ Priority = critical
     │   ├─ Overdue follow-up (>3 days)
     │   └─ No recent update
     │
     └─ Sort by:
         ├─ Priority (critical first)
         └─ Days open (longest first)

RESULT DISPLAY:
  Table shows:
  ├─ Case ID
  ├─ Title
  ├─ Priority (color-coded)
  ├─ Status (badge)
  ├─ Days open
  └─ Suggestion reason
       ├─ "Stuck case"
       ├─ "Critical unresolved"
       ├─ "Overdue follow-up"
       └─ "Unresolved"
```

---

**End of Visual Guides**

For detailed architecture and implementation, see MOM_IMPLEMENTATION_GUIDE.md
