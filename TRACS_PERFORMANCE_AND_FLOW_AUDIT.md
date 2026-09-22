# TRACS Performance & Flow Audit

**Audit date:** 2026-09-22
**Scope:** Browser rendering, frontend state and requests, PHP controllers/services, SQL access patterns, dirty-state handling, and Minutes of Meeting (MoM) item editing.
**Change status:** Implementation approved on 2026-09-22. The safe P0/P1 changes listed below have been applied; measurement-dependent database and pagination work remains gated on a production-shaped database benchmark.

## Implementation Status

Completed in this execution:

- Added request-local table, column, user, role-permission, and permission-decision caches with explicit invalidation hooks. Authorization remains evaluated per request; no cross-request permission cache was introduced.
- Added opt-in sampled request diagnostics (`TRACS_PERF_DIAGNOSTICS=1`) that record request ID, route, status, PHP duration, SQL query count, buffered response size when available, and peak memory without logging query text, query strings, cookies, credentials, or request bodies.
- Cached MoM installation, schema-capability, and lifecycle checks within each controller request.
- Guarded Case status/attachment and Abuse Report schema assurance once per database connection per request, reducing repeated compatibility DDL checks while retaining the current rollout fallback.
- Changed Cases and Abuse Reports to build only the active presentation from one in-memory dataset. View changes reuse the already-loaded data and do not make a new API or database request.
- Debounced Client Portfolio search, aborted superseded list requests, and coalesced duplicate focus/visibility refresh events without suppressing storage-based cross-tab updates.
- Mounted only the active Shift Assignment responsive presentation.
- Corrected User Management division preset baseline ordering and verified exact-value restoration through the shared dirty-state guard.
- Completed create/update distinction for existing MoM agenda items, discussion notes, decisions, and action items. Each editor hydrates the full record, retains its item ID, replaces the existing DOM record by ID, and resets dirty state after a successful save.
- Fixed the MoM action update bind signature and preserved linked-reminder synchronization and activity logging.
- Hardened MoM item mutations by requiring the parent `mom_id`, constraining SQL writes by both item and meeting IDs, bounding text input sizes, validating enum values, preserving CSRF and permission gates, and returning generic not-found responses for parent/item mismatches.

Intentionally deferred until representative database measurements are available:

- Server pagination and compact projections for Cases, Abuse Reports, and Client Portfolio.
- New database indexes. These require `EXPLAIN ANALYZE` and row-count evidence to avoid unnecessary write and storage cost.
- Removal of every runtime DDL fallback. Request-local guards are in place, but fully removing compatibility DDL requires a verified migration preflight across deployed environments.
- Calendar range redesign, dashboard aggregate rewrites, and moving maintenance jobs off request paths. These have higher regression risk and need production-shaped timing and behavior fixtures first.

## Executive Summary

The largest application-wide performance problem is not a single large business query. It is repeated permission and schema introspection during ordinary requests. A call to `tracs_user_can()` repeatedly reloads the same user, probes approximately two dozen `tracs_users` columns through `information_schema`, probes role/division tables and columns, and then may repeat the same work inside `tracs_user_permissions()`. The shared header calls permission checks approximately thirteen times. Static call-path analysis therefore puts the header alone in the range of several hundred metadata/user/permission round trips on a normal authenticated request, depending on role and installed schema. Request-local memoization can remove most of this work without changing authorization behavior.

The second systemic backend problem is schema maintenance in request paths. Cases, Abuse Reports, MoM, Calendar helpers, attachments, domains, finance, notifications, and creator tracking contain `CREATE TABLE IF NOT EXISTS`, `ALTER TABLE`, `information_schema`, or backfill work reachable from page reads. Even when no migration is required, the checks add round trips; when a migration is required, a user request can take metadata locks or perform large updates.

High-record-count pages then compound that fixed overhead:

- Cases, Abuse Reports, Clients, Checklist, and Reminders load unbounded collections.
- Cases and Abuse Reports render both board/card and table/list presentations even though only one is visible.
- Client Portfolio aggregates services, add-ons, and billing across the whole related dataset before returning an unpaginated client list, and some filters/sorts are applied in PHP after the query.
- Calendar loads a full year and fans out across as many as thirteen source collectors, with repeated permission/capability checks.
- The dashboard loads several full collections, derives statistics in PHP, and separately queries overlapping records for the ticker.

The multi-view diagnosis is important: Cases and Abuse Reports do **not** make a database/API request when the user toggles views. Their delay is caused by eager duplicate data transformation and DOM construction. Shift Assignment's day/week/month control is different: it changes the requested date range, so a new request is valid; its avoidable cost is rendering desktop and mobile record trees simultaneously.

Dirty-state handling has a sound shared foundation: it compares initial and current values, recognizes restored values, treats programmatic hydration as a baseline when integrated correctly, tracks dynamic state, and clears state after successful saves. The audit baseline exposed a Case exact-value restoration failure and a User Management division-preset baseline ordering issue. After implementation, the main dirty-state, Configurator, React editor, and MoM editor browser checks pass.

MoM editing is incomplete and currently risks duplication. Existing agenda items, notes, and decisions have no complete edit flow. The action-item Edit button only copies two visible fields into the add form, omits assignee/priority/due date, retains no item identity, and submits `add_action_item`; it therefore creates a second item instead of updating the original. Although an update API exists, its controller has a `bind_param` type-count mismatch and is not safe to rely on until corrected and tested.

The safest first implementation is: request-local authorization/schema memoization, removal of request-time DDL from normal reads, MoM update-path correction, and active-view-only rendering. These are localized changes with high expected benefit and do not require a framework migration or architecture rewrite.

## Audit Method and Measurement Limits

The audit traced these paths statically:

`Browser -> shared assets/page script -> page/API entry point -> controller/service/model -> SQL -> response payload -> rendered DOM`

Reviewed areas include shared header/footer bootstrapping, dashboard, Cases, Abuse Reports, Client Portfolio, Calendar, Shift Assignment, MoM, reminders/checklist/activity, task recurrence, Domain Price Crosscheck, Infrastructure Configurator/Pulse, User Management, and the shared unsaved-changes guard.

### Measurements available in this environment

| Measurement | Result |
|---|---:|
| `public/assets/tracs.js` | 344 KiB on disk, uncompressed |
| `public/assets/tracs.css` | 348 KiB on disk, uncompressed |
| `public/assets/mom-functions.js` | 64 KiB on disk, uncompressed |
| `public/assets/abuse-reports.js` | 72 KiB on disk, uncompressed |
| Calendar production JS chunk | 256 KiB on disk, uncompressed |
| Shared React `Button` chunk | 192 KiB on disk, uncompressed |
| Clients production JS chunk | 92 KiB on disk, uncompressed |
| Shift Assignment production JS chunk | 96 KiB on disk, uncompressed |
| Configurator dirty-state browser test | Passed |
| React editor dirty-state browser test | Passed |
| Unsaved-change submit contract test | Passed |
| Main dirty-state browser test | Audit baseline failed; implementation validation passed |
| MoM PHP syntax checks | Passed; syntax checks do not detect the runtime bind mismatch |

### Measurements not available

Docker/MySQL was not running (`docker compose ps` could not connect to the Docker API), so this audit does not invent wall-clock page timings, SQL query timings, row counts, payload byte counts, or `EXPLAIN ANALYZE` results. All impact labels below are based on code-path multiplicity and scaling behavior. Before/after production work must be measured against a sanitized, production-sized database.

## Critical Findings

### C1. Authorization checks amplify into hundreds of database round trips

- **Location:** `core/user_management.php`: `tracs_table_exists()`, `tracs_select_existing_user_columns()`, `tracs_get_user_by_id()`, `tracs_user_permissions()`, `tracs_user_can()`; `core/creator_tracking.php`: `tracs_column_exists()`; `public/includes/header.php`.
- **Current behavior:** Every permission check reloads the current user. User loading probes every optional user column individually, then probes role/division capabilities. For non-super-admin users, `tracs_user_can()` calls `tracs_user_permissions()`, which loads the user again and reloads the role permission set. The shared header makes about thirteen permission calls before page-specific checks.
- **Root cause:** No request-local cache exists for table/column existence, user rows, role permission sets, or individual permission decisions.
- **Performance impact:** **Critical, application-wide.** Static analysis indicates approximately 29 queries for one user load and roughly 60 for a normal non-super-admin permission check. The exact total varies by role and schema, but the header can plausibly generate several hundred database round trips before page data is loaded.
- **Recommendation:** Add request-local caches keyed by connection/schema plus user ID, role ID, and permission key. Resolve the current user once and resolve the permission set once per request. Cache table/column existence per request. Do not weaken permission checks or persist authorization decisions across requests.
- **Risk:** Low if caches are request-scoped; medium if made cross-request because role changes could become stale.
- **Change type:** No business-logic change; service/helper optimization.
- **Estimated complexity:** Small to medium.

### C2. Schema creation, alteration, and backfills run from normal page/API reads

- **Location:** `modules/abuse-report/model.php::ensureSchema()`; `modules/case/model.php` constructor and `getCasesByUser()`; `core/creator_tracking.php`; `public/modules/mom/controller.php::isInstalled()` and `ensureOperationalSchema()`; `public/domain-transfer.php`; `public/finance.php`; attachment helpers; notification/calendar helpers.
- **Current behavior:** Page reads repeatedly execute metadata probes and `CREATE TABLE IF NOT EXISTS`; some paths can run `ALTER TABLE` or backfill `UPDATE` statements.
- **Root cause:** Deployment migration responsibilities are mixed with request-time compatibility checks.
- **Performance impact:** **Critical/high.** The common case adds metadata round trips. The migration case can take metadata locks, block concurrent reads/writes, and make a user request unpredictably slow.
- **Recommendation:** Move schema mutation to versioned migrations/deployment preflight. Keep a request-local, read-only capability snapshot as a compatibility guard. If a required migration is missing, fail with an actionable administrative error instead of altering production schema inside a user request.
- **Risk:** Medium: rollout must verify every environment has applied migrations. Keep a temporary read-only fallback during rollout, not runtime DDL.
- **Change type:** Minor operational logic adjustment; no business-data model redesign.
- **Estimated complexity:** Medium.

### C3. MoM “Edit Action” creates a new record and the update path is broken

- **Location:** `public/assets/mom-functions.js::editActionItem()`, `saveInlineActionItem()`, `saveActionItem()`; `public/api/api_mom.php`; `public/modules/mom/controller.php::updateActionItem()`; MoM action markup in `public/mom.php` and `public/includes/footer.php`.
- **Current behavior:** Edit copies only title/description into an add form. It does not retain `action_id`, does not hydrate assignee/priority/due date, and always submits `action: 'add_action_item'`. The modal contains `momActionFormId`, but save ignores it. The existing update controller binds seven values using a six-character type string (`'sssssi'`).
- **Root cause:** UI state never distinguishes create from update, and the update branch is not covered end to end.
- **Impact:** **Critical data integrity/UX.** Editing can duplicate an action item; attempting the update route can fail at runtime.
- **Recommendation:** Use one explicit editor state with `mode` and `item_id`; populate all fields from structured item data; submit `update_action_item` for existing items; fix binding and return the updated record; update the matching DOM/state entry instead of appending. Add duplicate-prevention and ownership/MoM-ID validation tests.
- **Risk:** Medium because action items may synchronize linked reminders. Preserve the existing linked-reminder update and audit behavior.
- **Change type:** Minor frontend/API logic adjustment; no schema or route redesign required.
- **Estimated complexity:** Medium.

### C4. Large collections are loaded and rendered without a bounded result set

- **Location:** `modules/case/model.php::getCasesByUser()` and `public/cases.php`; `modules/abuse-report/model.php::getReports()` and `public/abuse-reports.php`; `modules/client-portfolio/model.php::listClients()`; checklist/reminder list methods; dashboard collection loads.
- **Current behavior:** Full result sets are selected, transformed, serialized, parsed, filtered, and rendered.
- **Root cause:** Shared/team visibility was implemented as unbounded loading; pagination and summary/detail payloads are absent on the highest-growth lists.
- **Performance impact:** **Critical at high record counts.** Database, PHP memory, HTML/JSON transfer, JSON parse, DOM creation, and icon initialization all grow with total history rather than visible rows.
- **Recommendation:** Introduce backward-compatible server pagination and server-side filters on high-volume list endpoints. Keep totals in separate aggregate queries. Send list-summary fields only; load long notes/descriptions/evidence/details on demand. For board workflows, begin with a safe threshold or per-column cursor so drag/drop semantics remain intact.
- **Risk:** Medium: shared visibility, filter counts, ordering, export, and board drag behavior must be preserved.
- **Change type:** Minor API logic plus frontend rendering change.
- **Estimated complexity:** Medium to large, implemented incrementally per module.

## Performance Findings

### Backend / Database

#### P-B1. Dashboard repeats and overlaps data work

- **Location:** `public/index.php`; `modules/alert-ticker/SmartTickerEngine.php`; Case, Reminder, Checklist, MoM, Shift Report, Abuse Report, and Task Management controllers.
- **Current behavior:** The dashboard loads full cases, reminders, checklist items, shift data, MoM data, task assignments, and activity-related data. It then computes multiple statistics through repeated PHP scans. `SmartTickerEngine::buildFeed()` separately performs bounded queries against many of the same sources.
- **Root cause:** Widgets and ticker independently acquire related data, and summary widgets consume list collections rather than purpose-built aggregate results.
- **Performance impact:** High. Fixed request latency grows with both number of widgets and total record count.
- **Recommendation:** Add a request-scoped dashboard data context or pass already-loaded bounded results into ticker builders. Replace full-history widget loads with focused `COUNT`/conditional aggregate and top-N queries. Do not introduce persistent caching until invalidation requirements are documented.
- **Risk:** Medium: dashboard counts, visibility, ticker ordering, and permissions must remain identical.
- **Change type:** Minor logic adjustment.
- **Estimated complexity:** Medium.

#### P-B2. Calendar is a query fan-out with repeated capability checks

- **Location:** `modules/calendar/CalendarService.php`; `assets/react/calendar/hooks/useCalendarData.js`.
- **Current behavior:** A full-year request invokes up to thirteen collectors (cases, reminders, client follow-ups, checklist, MoM, MoM actions, shifts, holidays, notifications, domains, birthdays, internships, and manual events), then maps and sorts the combined result in PHP. Collectors repeatedly perform permission/table/column checks.
- **Root cause:** Broad date range plus uncached authorization/capability discovery.
- **Performance impact:** High, especially after C1. Even after permission memoization, source query and response cost scales with a year of events.
- **Recommendation:** First apply request-local permission/schema caching. Then request the visible month plus a small navigation buffer and cache already-fetched ranges in the client. Keep source availability metadata stable for the session or request. Avoid user-unsafe cross-user response caching.
- **Risk:** Medium: calendar navigation and all source permissions must remain correct.
- **Change type:** Minor API/range logic and frontend data-loading adjustment.
- **Estimated complexity:** Medium.

#### P-B3. Client list aggregates entire related tables before pagination

- **Location:** `modules/client-portfolio/model.php::listClients()`; `public/api/v1/client-portfolio/clients.php`; `frontend/src/modules/clients/main.jsx::useClients()`.
- **Current behavior:** The query selects `c.*`, joins aggregate subqueries that group all services, add-ons, and billing records, loads all matching clients, then runs a follow-up query for open follow-ups. Billing/attention filtering and sorting are partly performed in PHP.
- **Root cause:** List summaries are assembled globally before a bounded candidate set is selected.
- **Performance impact:** High and superlinear in practice as clients and related records grow; it also prevents correct database pagination when filters are applied after SQL.
- **Recommendation:** Select a paginated candidate client ID set first, aggregate related tables only for those IDs, push billing/attention predicates and sort keys into SQL, and select only list fields. Keep the existing on-demand detail endpoint for contacts/services/billing/history.
- **Risk:** Medium: attention and billing status semantics need fixture-based parity tests.
- **Change type:** Minor query logic and API pagination.
- **Estimated complexity:** Medium.

#### P-B4. Abuse list joins whole-table aggregates

- **Location:** `modules/abuse-report/model.php::getReports()`.
- **Current behavior:** The unbounded report query selects `r.*`, aggregates evidence for all reports, and derives latest event through a nested grouped query across the event table.
- **Root cause:** Detail-related aggregates are attached to every list row before a page/limit is selected.
- **Performance impact:** High for large report/event/evidence tables.
- **Recommendation:** Page/filter report IDs first; aggregate counts/latest event only for the selected IDs. Keep notes, full event history, and evidence detail lazy through the existing detail API.
- **Risk:** Low to medium; list badges/latest-event labels need parity tests.
- **Change type:** Minor query adjustment.
- **Estimated complexity:** Medium.

#### P-B5. Task recurrence and overdue maintenance perform looped writes on reads

- **Location:** `modules/task-management/model.php::refreshOverdueStatuses()` and `refreshRecurringTasks()`; dashboard/task page callers.
- **Current behavior:** Page loads discover due records, then update assignments one at a time and write logs per assignment. Recurrence additionally loads assignments once per recurring task and issues more updates for linked checklist/reminder rows.
- **Root cause:** Scheduled maintenance is implemented as a lazy request-time engine.
- **Performance impact:** High when many tasks become due together; increases lock time and makes page latency depend on unrelated maintenance backlog. It is an explicit query-in-loop/N+1 write pattern.
- **Recommendation:** Move maintenance to a scheduled worker/cron or a separately invoked idempotent maintenance endpoint. Until then, guard it once per interval, use transactions and set-based updates where audit requirements allow, and cap work while exposing backlog metrics.
- **Risk:** Medium to high: recurrence, audit logs, linked reminders/checklists, and notification timing are business behavior.
- **Change type:** Minor operational logic adjustment; no architecture rewrite required.
- **Estimated complexity:** Medium.

#### P-B6. MoM detail repeatedly checks schema and separately queries each collection

- **Location:** `public/modules/mom/controller.php`; `public/mom.php`.
- **Current behavior:** `isInstalled()` checks eight tables. `getMOM()` can call schema assurance and auto-start due meetings. Detail pages then query agenda, notes, decisions, actions, audit, cases, reminders, and screenshots separately. List rendering also requests several collections per MoM card in places.
- **Root cause:** Schema/lifecycle side effects are coupled to getters; detail acquisition is not request-scoped.
- **Performance impact:** High fixed overhead and possible N-times-per-meeting behavior.
- **Recommendation:** Cache installation/capability status per request; run auto-start once per request or scheduled maintenance; add one controller method that returns a detail bundle while retaining the existing underlying queries and response fields. Do not use a giant join that multiplies child rows.
- **Risk:** Medium: auto-start and reminder/ops-status integration must remain intact.
- **Change type:** Minor service logic adjustment.
- **Estimated complexity:** Medium.

#### P-B7. Checklist and reminders remain unbounded shared lists

- **Location:** `modules/checklist/model.php::getTasksByUser()`; `modules/reminder/model.php::getRemindersByUser()`; `public/checklist.php`; `public/reminders.php`; dashboard callers.
- **Current behavior:** All shared items, including completed history, are returned and rendered. Reminder retrieval also probes columns via `information_schema` for each call.
- **Root cause:** Team-wide visibility is conflated with unlimited history.
- **Performance impact:** Medium now, high as historical rows accumulate.
- **Recommendation:** Default to active plus recent completed items, add server pagination/history controls, and cache the reminder column capability per request. Preserve full export/history routes.
- **Risk:** Medium: users must still be able to reach old completed records.
- **Change type:** Minor query/frontend change.
- **Estimated complexity:** Small to medium.

#### P-B8. Full-page HTML is fetched to refresh a Cases dataset

- **Location:** `public/assets/tracs.js::caseRefreshFromServer()`.
- **Current behavior:** After a create/update, the browser fetches the current page URL, downloads all server-rendered HTML, parses it into a new document, and extracts `#caseDataset`.
- **Root cause:** No focused JSON refresh endpoint is used for the list state.
- **Performance impact:** Medium/high: it repeats header permissions, page queries, template rendering, transfer, and HTML parsing for one dataset refresh.
- **Recommendation:** Return the updated list record from mutation APIs and reconcile it locally. If a full refresh remains necessary, expose a permission-equivalent paginated JSON list endpoint. Preserve current response contracts by adding fields rather than removing them.
- **Risk:** Medium: server-calculated fields and cross-user updates must remain fresh.
- **Change type:** Minor API/frontend logic adjustment.
- **Estimated complexity:** Medium.

#### P-B9. Domain/finance creation checks are in page/API paths despite existing pagination

- **Location:** `public/domain-transfer.php`, domain APIs, `public/finance.php`, finance APIs.
- **Current behavior:** The primary lists are paginated, which is good, but table creation/capability work is duplicated in page and API entry points and list queries use broad row projections.
- **Root cause:** Self-installing module pattern.
- **Performance impact:** Medium fixed overhead; low scaling risk relative to unbounded modules.
- **Recommendation:** Move schema creation to migrations, share a request-local capability check, and replace `SELECT alias.*` with explicit list columns where large text fields are not needed.
- **Risk:** Low.
- **Change type:** No business-logic change; database setup cleanup.
- **Estimated complexity:** Small.

### Frontend / Rendering

#### P-F1. Cases renders both board and table for every state change

- **Location:** `public/assets/tracs.js::renderCaseWorkspace()`, `renderBoard()`, `renderTable()`, `bindCaseCardMenus()`; `public/cases.php`.
- **Current behavior:** The dataset is parsed once, but every search, filter, sort, status update, drag completion, and initial load rebuilds both board cards and table rows with `innerHTML`, binds card menus, and refreshes icons. One presentation is hidden.
- **Root cause:** Presentation state is not separated from dataset/filter state.
- **Performance impact:** High: approximately two record DOM representations are created, and optimistic status changes can render before the request and again in `finally`.
- **Recommendation:** Keep the single dataset. Compute the filtered/sorted state once; render only the active presentation; lazily create the other on first switch and invalidate only when data/filter revisions change. Use event delegation for card menus and scope icon hydration to inserted nodes.
- **Risk:** Medium: preserve drag/drop ordering, scroll restoration, table sorting, and optimistic rollback.
- **Change type:** Frontend rendering change only.
- **Estimated complexity:** Medium.

#### P-F2. Abuse Reports rebuilds both board and list on every view switch

- **Location:** `public/assets/abuse-reports.js::renderBoard()` and `renderList()`.
- **Current behavior:** `renderBoard()` filters the full dataset, scans it repeatedly for summary counts, filters it again for every workflow stage, rebuilds every board column, then always calls `renderList(visible)`. View switches invoke this full function. Initial state also calls `parsePayload()` twice.
- **Root cause:** Board rendering owns all view rendering; option markup and per-stage grouping are recomputed per record/render.
- **Performance impact:** High for large reports. The list editor can duplicate large reporter/assignee option sets in every row.
- **Recommendation:** Parse JSON once. Produce summaries and stage groups in one pass. Render only the active presentation and memoize stable option markup. Retain lazy detail loading.
- **Risk:** Low to medium.
- **Change type:** Frontend rendering change only.
- **Estimated complexity:** Small to medium.

#### P-F3. Client search sends a request on every keystroke and stale requests keep running

- **Location:** `frontend/src/modules/clients/main.jsx::useClients()` and filter controls.
- **Current behavior:** Every filter state change issues a list request. A sequence number ignores stale responses but does not abort their network/server/database work.
- **Root cause:** No debounce or `AbortController` in the client list hook.
- **Performance impact:** High during typing and under concurrent use.
- **Recommendation:** Debounce free-text search around 250–300 ms, apply select filters immediately, and abort superseded requests. Add pagination at the same endpoint.
- **Risk:** Low.
- **Change type:** Frontend request change.
- **Estimated complexity:** Small.

#### P-F4. Client/Calendar focus handling can issue duplicate refreshes

- **Location:** `frontend/src/modules/clients/main.jsx`; `assets/react/calendar/hooks/useCalendarData.js`.
- **Current behavior:** The Clients app refreshes clients/details on focus, storage, and visibility. The embedded calendar separately refreshes its full-year dataset on the same events. Save flows explicitly refresh both and also write a calendar update marker.
- **Root cause:** Independent listeners react to the same lifecycle event without coalescing or freshness windows.
- **Performance impact:** Medium/high, especially because calendar refresh is expensive.
- **Recommendation:** Centralize refresh orchestration for the Clients page, coalesce focus+visibility events, and use a short freshness TTL. Continue to force refresh after a successful relevant mutation.
- **Risk:** Low to medium: do not suppress genuine cross-tab updates.
- **Change type:** Frontend request change.
- **Estimated complexity:** Small to medium.

#### P-F5. Shift Assignment mounts desktop and mobile representations together

- **Location:** `frontend/src/modules/shift-assignment/ShiftAssignmentApp.jsx`, `ShiftAssignmentTable.jsx`, `ShiftAssignmentBoard.jsx`.
- **Current behavior:** Both components map the same assignments; CSS hides one based on breakpoint.
- **Root cause:** Responsive presentation is controlled only by CSS after both React trees are constructed.
- **Performance impact:** Medium for large date ranges; nearly doubles record component creation and DOM nodes.
- **Recommendation:** Render the appropriate presentation from a stable media-query hook, or share one semantic tree if feasible without redesign. This is separate from day/week/month range changes, which should continue to fetch different datasets.
- **Risk:** Low to medium: verify responsive transitions and accessibility.
- **Change type:** Frontend rendering change.
- **Estimated complexity:** Small.

#### P-F6. Global assets and icon scans increase every page's startup cost

- **Location:** `public/includes/header.php`, `public/includes/footer.php`, `public/assets/tracs.js`.
- **Current behavior:** All authenticated pages load the 344 KiB global JS, 348 KiB global CSS, date-range code, the unsaved guard, Google Fonts, Flatpickr CSS/JS, and Lucide. The footer calls `lucide.createIcons()` across the page; large-list renderers call icon creation again.
- **Root cause:** Broad shared bundle and global initialization.
- **Performance impact:** Medium, most visible on cold load and low-powered devices.
- **Recommendation:** Keep the design system, but load Flatpickr/date-range behavior only on pages that use it, split page-specific portions of `tracs.js` when measurable, self-host/pin external assets if operationally appropriate, and hydrate icons only within changed containers.
- **Risk:** Medium if bundle splitting changes global ordering; begin with conditional third-party loading and scoped icon refresh.
- **Change type:** Frontend asset-loading change.
- **Estimated complexity:** Small to medium.

#### P-F7. Long list rendering has no chunking or virtualization

- **Location:** Cases, Abuse Reports, Client Portfolio, Checklist, Reminders, and other high-volume table/card views.
- **Current behavior:** All returned rows/cards are inserted at once.
- **Root cause:** No result bound and no rendering threshold.
- **Performance impact:** High at large N: long tasks, layout/paint cost, and memory increase with the entire dataset.
- **Recommendation:** Prefer server pagination first. For tables that legitimately need hundreds of rows, add row virtualization after accessibility and sticky-header tests. For boards, use per-column incremental loading rather than generic virtualization because drag/drop requires special handling.
- **Risk:** Medium.
- **Change type:** Frontend rendering plus API pagination.
- **Estimated complexity:** Medium to large.

## Kanban / Table View Findings

### Cases: Board <-> Table

**Observed request flow**

`GET /cases.php -> getCases() unbounded SQL -> PHP mapping/stat scans -> embedded caseDataset JSON -> JSON parse -> filter/sort -> render board + render table -> hide one`

**On view toggle**

`setCaseWorkspaceView()` only hides/shows panels and writes a localStorage preference. It does not query the database or call an API. This is already the correct data-reuse behavior.

**Why it still feels slow**

The expensive work already happened during initial load and every subsequent dataset/filter mutation: both views exist in full. Search is debounced by only 100 ms, and each accepted input rebuilds both trees. Status changes can trigger two complete renders. Menu listeners and icon replacement add more per-node work.

**Safe optimization**

Retain `caseBoardState.rawCases` as the single source of truth. Add a monotonically increasing data/filter revision and a per-view rendered revision. Render only the selected view; on first toggle, render the other from the same filtered dataset. Re-render the active view immediately after changes and mark the inactive view stale. This avoids an API/DB change and preserves state ownership.

### Abuse Reports: Board <-> List

**Observed request flow**

`GET /abuse-reports.php -> schema assurance + unbounded aggregate SQL -> embedded JSON -> parse twice -> filter/recount/regroup -> render board + render list -> hide one`

**On view toggle**

No API/database request occurs, but the toggle calls the function that rebuilds both presentations.

**Why it is slow**

The full set is scanned for filtering and several summaries, then scanned once per workflow stage. Both card and list markup are regenerated. List editing embeds repeated select option sets per row.

**Safe optimization**

Parse once, group/count once, render the active view only, and memoize stable select options. Keep the same state array and existing detail API.

### Shift Assignment: day/week/month and responsive table/card

Day/week/month changes alter start/end dates; a request for the new range is expected and should not be removed. The hook already aborts superseded filter requests. The avoidable duplication is responsive rendering: desktop table and mobile board both map all assignments even when CSS hides one.

### Target behavior

For Cases and Abuse Reports:

`Load bounded dataset -> keep one normalized state -> derive filters once -> render active presentation -> lazily render/reuse alternate presentation`

For Shift Assignment:

`Change date range -> fetch range -> retain one dataset -> render only current responsive presentation`

## Dirty State Findings

### Shared mechanism assessment

`public/assets/unsaved-changes-guard.js` is the correct architectural direction and should remain the single shared implementation. It stores initial values, recalculates current values, supports checkbox/radio/multi-select/file/contenteditable/currency controls, tracks custom dynamic state, ignores GET/search/filter controls, protects modal/page exits, and resets baselines after successful saves.

It should be normalized and extended, not replaced with page-specific boolean flags.

### Confirmed problems

#### D1. Case exact-value restoration still reports dirty in the current browser suite

- **Location:** `public/assets/unsaved-changes-guard.js`; Case modal integration in `public/assets/tracs.js`; `tests/dirty-state-browser.cjs` lines 60–68.
- **Observed behavior:** API hydration is initially clean. Clearing `caseNotes` correctly becomes dirty. Filling the exact original text (`Original notes`) remains dirty; the test expected clean and failed.
- **Expected behavior:** Modify and restore exactly to the captured value must clear dirty state.
- **Recommendation:** Instrument the guard in the test to identify the remaining dirty control/state tracker and correct baseline ownership. Add the control identity/current/original value to test-only diagnostics. Do not paper over it by marking the entire modal saved on each input.
- **Risk:** Medium: a broad fix could suppress real attachment or field changes.

#### D2. “Add User to Division” captures its baseline before applying the division

- **Location:** `public/user-management.php::umCreateUser()` and `umCreateUserInDivision()`.
- **Observed behavior:** `umCreateUser()` opens the modal, which captures the baseline, and `umCreateUserInDivision()` then programmatically changes `umDivisionId`. Even without an input event, close-time comparison can detect the changed value and warn immediately.
- **Expected behavior:** The requested division is initialization state; open and close without a user change must not warn.
- **Recommendation:** Apply the preset before opening/capturing, or call shared `captureInitialState()` after all preset values/dropdown synchronization finish.
- **Risk:** Low.

#### D3. MoM existing-item editing has no coherent dirty-state boundary

- **Location:** `public/mom.php`; `public/assets/mom-functions.js`; shared MoM modals in `public/includes/footer.php`.
- **Observed behavior:** Existing items are mostly static. Action Edit loads partial values into an add form, so baseline, identity, cancel restoration, and successful-save reset are not defined for the actual edited item.
- **Expected behavior:** Hydrate all existing values and item ID, capture baseline after hydration, compare exact current state, restore/cancel safely, and mark saved only after successful update.
- **Recommendation:** Integrate each reusable MoM item editor with the shared guard; do not create a separate `isDirty` boolean.
- **Risk:** Medium, coupled to the CRUD correction.

### Coverage/status matrix

| Surface | Audit result | Required follow-up |
|---|---|---|
| Case create | Immediate open/close and attachment add/remove scenarios pass until the later edit-reversion failure | Retain coverage |
| Case edit | Hydration clean; exact note restoration currently fails | Fix D1 before broad rollout |
| Reminder / checklist / real-time completion checkboxes | Immediate-persist controls are explicitly ignored; add/edit modal fields use shared guard | Add focused browser tests for failed saves |
| Task / monitoring add, update, edit, reassign | POST forms and modal close paths are auto-registered | Add representative nested-modal/failed-save coverage |
| Abuse detail / bulk / note / evidence | Uses guarded modal with custom save handling; view preview has no editable state | Test overview/bulk reversion and async hydration |
| Client create/edit, contact/service/billing/follow-up forms | React `useUnsavedForm` baseline/current comparison | Browser suite passed representative React create/edit behavior |
| Shift create/edit | React `useUnsavedForm`; superseded editor initialization is handled | Browser suite passed |
| Shift delete/history/template preview | Delete/view surfaces should not use generic dirty warnings; preview surfaces opt out where appropriate | Retain explicit confirmation tests |
| Calendar editors | React shared guard integration | Browser suite passed representative behavior |
| Configurator master/item/template dialogs | Custom dynamic-state tracker and explicit capture | Dedicated browser suite passed, including exact reversion and failed/successful save |
| Infrastructure Pulse | Captures after render/hydration and on reset/edit | Add browser test for add/edit/tab switches |
| Domain Price Crosscheck modals and matrix | Opens through shared helper; matrix has page-level tracking | Add multi-modal initialization/reversion test, especially programmatic exchange-rate preview |
| Domain Transfer / Finance edit modals | Page-level guard and save reset calls exist | Add open/close and programmatic hydration tests |
| User create/edit | General modal setup occurs before open | Fix Add User to Division ordering (D2); add regression test |
| Profile/avatar crop | Standard forms are covered; crop state is custom visual state | Add explicit file/crop/revert test before claiming full coverage |
| View More/read-only/detail previews | No warning should appear when there are no editable controls | Keep these outside dirty tracking |
| Delete confirmations | Confirmation is not an unsaved edit | Do not attach dirty-state prompts; keep destructive confirmation only |
| MoM create and suggested-case selection | Custom suggested-case state tracker exists and staged selection/reversion test passes | Retain |
| MoM item editors | Incomplete CRUD makes full dirty-state correctness impossible | Implement after CRUD state model is defined |

### Required dirty-state contract

Every editable surface should use this sequence:

1. Begin initialization or construct local draft.
2. Load defaults/existing data, dropdown choices, dynamic fields, files, and linked items.
3. Capture one normalized baseline.
4. Compare normalized current values and custom state to that baseline.
5. If current equals baseline, close without warning.
6. If different, guard close/navigation.
7. On successful save only, replace the baseline with the persisted state.
8. On failed save, retain draft and dirty status.

## MoM Editing Findings

### Current CRUD capability by item type

| Item | View | Add | Edit/update | Remove | Current defect |
|---|---|---|---|---|---|
| Agenda | Yes | Yes | Status only from UI | Yes | Topic/notes remain static; API update accepts only status; controller cannot intentionally clear topic/notes because empty string means “keep old” |
| Discussion note | Yes | Yes | **No update endpoint/controller/UI** | Yes | Existing content/type cannot be edited |
| Decision | Yes | Yes | **No update endpoint/controller/UI** | Yes | Existing decision/rationale/owner/status cannot be edited |
| Action item | Yes | Yes | Endpoint/controller exist but UI submits add; controller binding is invalid | Yes | Edit duplicates; incomplete hydration; runtime update risk |

### Why existing items cannot be edited correctly

1. Existing rows are rendered primarily as display markup rather than an editable draft model.
2. Item identity is not carried into the active action editor. `momActionFormId` exists but is unused by save.
3. `editActionItem()` scrapes title/description from displayed text rather than hydrating a complete structured object.
4. Both inline and modal action saves hard-code `add_action_item`.
5. The API does not provide update operations for notes or decisions and restricts agenda update to status.
6. The action controller's parameter type string does not match its seven bound values.
7. Add responses return IDs, but update responses do not return a canonical updated item, encouraging append/reload behavior rather than targeted replacement.

### Recommended safe correction

- Keep existing tables, IDs, routes, permissions, relationships, linked reminders, and audit semantics.
- Add backward-compatible update actions for agenda content, discussion notes, and decisions; extend the existing action update.
- Require both `mom_id` and `item_id` (or verify item-to-MoM membership server-side) before mutations.
- Render or fetch structured item data, including all editable fields and IDs; do not scrape formatted text.
- Use one form per item type with `mode: create|edit` and `item_id`.
- On create, append the server-returned canonical item once.
- On update, replace the matching state/DOM item by ID; never append.
- On cancel, discard the draft and leave the persisted item unchanged.
- On successful save, reset the shared dirty baseline; on failure, keep the editor open and dirty.
- Ensure clearing an optional field is distinguishable from “field omitted.” The agenda controller's current `CASE WHEN ?=''` logic prevents clearing values and should move to presence-aware payload handling.
- Add integration tests covering create, edit all fields, clear optional fields, remove, cancel, failed update, linked reminder synchronization, and no-duplicate update.

## Database / Query Findings

### Repeated queries

- Current user, role, division, and permission set are repeatedly queried within one request.
- Table/column existence is repeatedly queried from `information_schema`.
- Dashboard widgets and ticker query overlapping records independently.
- Cases refresh fetches and executes the whole page pipeline again.
- MoM getters repeatedly invoke schema/lifecycle checks.
- Client/Calendar focus handlers can repeat list/detail/calendar requests.

### N+1 and query-in-loop behavior

- `refreshRecurringTasks()` loads assignments per recurring task, then updates/logs per assignment and may update linked checklist/reminder rows.
- `refreshOverdueStatuses()` updates/logs each overdue assignment separately.
- MoM list/detail rendering has repeated child-collection calls per meeting in some page sections.
- User permission checks act as an application-wide repeated-query multiplier even where the business query itself is efficient.

### Expensive queries

- Client Portfolio whole-table aggregate subqueries for services, add-ons, and billing.
- Abuse Reports evidence count and latest-event aggregates before result bounding.
- Calendar's thirteen-source fan-out followed by PHP merge/sort.
- Unbounded Cases query plus attachment aggregate and workflow ordering.
- Dashboard full-list retrieval followed by repeated PHP statistic scans.

### Unnecessary data load

- `SELECT c.*`/`SELECT r.*` on client and abuse list paths includes long notes/descriptions not always displayed.
- Full Case notes are embedded in the list dataset while a detail API already exists.
- Checklist and reminder completed history is loaded by default.
- Both visual presentations are built from the same full payload.

### Index assessment

The install schema already contains many appropriate base indexes, including Cases `next_check_at`, `(status, board_order)`, Reminder overdue/upcoming composites, MoM status/meeting time and action due date, Client relationship/date indexes, Abuse board/priority/assignee/event indexes, and Activity recent composites. Index creation should therefore **not** be the first or automatic response.

Potential index changes must be justified with `EXPLAIN ANALYZE` on production-shaped data after query bounding. Candidates to test, not blindly add, are:

- Abuse latest-event lookup aligned with `MAX(id)` per `report_id`, e.g. `(report_id, id)`, if the current `(report_id, created_at)` does not satisfy the plan.
- Composite list ordering/filter indexes only after final paginated predicates are known.
- MoM child `(mom_id, created_at)` indexes if high child counts make ordered detail reads measurable; current single-column `mom_id` indexes may already be sufficient for normal meeting sizes.

Adding overlapping indexes before query redesign would increase write cost and may not improve the unbounded scans.

## Frontend Rendering Findings

The largest frontend gains come from reducing how much is rendered, not from micro-optimizing individual template strings:

1. Render one presentation at a time for Cases and Abuse Reports.
2. Bound server results before considering virtualization.
3. Debounce and abort Client search requests.
4. Coalesce focus/visibility refreshes.
5. Mount one responsive Shift representation.
6. Scope Lucide work to changed containers.
7. Load long detail fields and related histories only when opened.
8. Conditionally load page-specific date-picker and other heavy code.

Virtualization is appropriate only after pagination and accessibility requirements are established. It is lower risk for plain tables than draggable Kanban columns.

## Recommended Optimizations

### P0 — Critical

#### R0.1 Request-local authorization and schema capability cache

- **Affected files/components:** `core/user_management.php`, `core/creator_tracking.php`, permission-heavy services including Calendar and shared header callers.
- **What should change:** Cache table/column existence, normalized user row, role permissions, and permission decisions for the lifetime of one PHP request.
- **Why:** Removes the largest fixed query multiplier while preserving every permission decision.
- **Expected improvement:** Hundreds of round trips can collapse to one capability/user/permission load on permission-heavy pages.
- **Regression risk:** Low with request-only cache and tests for multiple users/roles in separate requests.

#### R0.2 Remove request-time DDL/backfills from read paths

- **Affected files/components:** Abuse, Cases, MoM, domains, finance, attachment helpers, creator tracking, notifications/calendar setup.
- **What should change:** Apply schema through migrations; retain read-only capability checks and actionable errors.
- **Why:** Prevents metadata lock stalls and unpredictable page latency.
- **Expected improvement:** Lower fixed latency and elimination of worst-case DDL pauses.
- **Regression risk:** Medium during deployment; mitigate with migration preflight.

#### R0.3 Correct MoM existing-item update semantics

- **Affected files/components:** `public/mom.php`, `public/assets/mom-functions.js`, `public/api/api_mom.php`, `public/modules/mom/controller.php`, shared MoM modal markup.
- **What should change:** Complete item identity/hydration/update APIs, fix action bind types, replace by ID, and integrate shared dirty state.
- **Why:** Prevents duplicates and enables the requested edit flow.
- **Expected improvement:** Data integrity and predictable editing rather than speed.
- **Regression risk:** Medium because linked reminders/audit history must be preserved.

#### R0.4 Add measurement instrumentation before changing list/query behavior

- **Affected files/components:** PHP request bootstrap/database wrapper, target APIs, browser performance harness.
- **What should change:** In non-production or sampled admin diagnostics, record request ID, SQL count/time, slowest query, response bytes, and server time; add browser marks for parse/render/view switch.
- **Why:** Establishes a trustworthy baseline and prevents speculative optimization.
- **Expected improvement:** Enables acceptance gates and identifies the remaining true bottleneck after C1.
- **Regression risk:** Low if disabled by default and never logs sensitive payloads.

### P1 — High Impact

#### R1.1 Active-view-only rendering for Cases and Abuse Reports

- **Affected files/components:** `public/assets/tracs.js`, `public/assets/abuse-reports.js`.
- **What should change:** One derived dataset, revision-aware lazy presentation rendering, one-pass counts/groups, delegated events, scoped icon refresh.
- **Why:** Removes duplicate transforms/DOM work without changing API/business behavior.
- **Expected improvement:** Approximately halves record presentation construction on initial/filter updates; view switches reuse data without network calls.
- **Regression risk:** Low to medium.

#### R1.2 Paginate Cases, Abuse Reports, and Client Portfolio

- **Affected files/components:** respective models/controllers/pages/APIs and frontend state.
- **What should change:** Add server-side pagination/filter/sort, summary totals, and compact list projections. Preserve export as a separate full-data action.
- **Why:** Bounds database, PHP, payload, and DOM cost.
- **Expected improvement:** Converts total-history scaling to page-size scaling.
- **Regression risk:** Medium; board ordering and shared visibility need focused tests.

#### R1.3 Optimize Client Portfolio candidate/aggregate query

- **Affected files/components:** `modules/client-portfolio/model.php`, client list API, React client list.
- **What should change:** Page candidate IDs first, aggregate only candidates, move post-query filters/sorts into SQL, debounce/abort search.
- **Why:** Current work grows across every related table.
- **Expected improvement:** Large reduction in DB rows examined and duplicate concurrent requests.
- **Regression risk:** Medium.

#### R1.4 Bound Calendar range and coalesce refreshes

- **Affected files/components:** `CalendarService.php`, `useCalendarData.js`, Clients page integration.
- **What should change:** Visible-range requests with client range cache; one refresh coordinator and freshness window.
- **Why:** Full-year multi-source reloads are expensive and repeated.
- **Expected improvement:** Substantially smaller payload/source result sets and fewer duplicate requests.
- **Regression risk:** Medium.

#### R1.5 Normalize dirty-state regressions

- **Affected files/components:** shared guard, Case modal integration/test, User Management preset path, MoM editors.
- **What should change:** Fix Case reversion based on test diagnostics; capture User division baseline after preset; require all async initialization to finish before capture.
- **Why:** False positives erode trust and can train users to dismiss real warnings.
- **Expected improvement:** Correct UX and lower accidental-discard risk.
- **Regression risk:** Medium; protect dynamic files/items with tests.

### P2 — Medium

#### R2.1 Dashboard focused aggregates and request-level reuse

- **Affected files/components:** `public/index.php`, widget controllers, `SmartTickerEngine.php`.
- **What should change:** Replace full-list stats with aggregate/top-N queries and share results within the request.
- **Why:** Removes overlapping work.
- **Expected improvement:** Meaningful dashboard TTFB reduction.
- **Regression risk:** Medium.

#### R2.2 Move recurrence/overdue/SLA maintenance off page reads

- **Affected files/components:** Task Management refresh methods, Abuse SLA scheduling, MoM auto-start.
- **What should change:** Idempotent scheduled execution with observable last-run/backlog and safe fallback.
- **Why:** User-facing latency should not depend on maintenance backlog.
- **Expected improvement:** More consistent response time and shorter transactions.
- **Regression risk:** Medium to high due to scheduling semantics.

#### R2.3 Replace Cases full-page refresh with response reconciliation

- **Affected files/components:** mutation APIs and `caseRefreshFromServer()`.
- **What should change:** Return canonical updated list record and update local state; optionally background-refresh only when version/signature changes.
- **Why:** Avoids re-running an entire authenticated page request.
- **Expected improvement:** Faster post-save completion and lower server load.
- **Regression risk:** Medium.

#### R2.4 Render one Shift responsive presentation

- **Affected files/components:** Shift Assignment React app/table/board.
- **What should change:** Conditional component mount based on breakpoint.
- **Why:** Removes duplicate DOM.
- **Expected improvement:** Moderate rendering/memory reduction on large ranges.
- **Regression risk:** Low to medium.

### P3 — Optional

#### R3.1 Conditional global third-party assets and progressive bundle splitting

- **Affected files/components:** shared header/footer and global asset build.
- **What should change:** Load Flatpickr/date-range code only where needed; split page-specific global JS after coverage exists.
- **Why:** Improves cold-load parse/execute cost.
- **Expected improvement:** Small to medium depending cache/device/network.
- **Regression risk:** Medium for aggressive splitting; low for conditional page includes.

#### R3.2 Virtualize very large non-draggable tables

- **Affected files/components:** high-volume table views after pagination.
- **What should change:** Virtualize only if measured render cost remains material at the selected page size.
- **Why:** Reduces mounted rows.
- **Expected improvement:** Useful for hundreds/thousands of visible rows.
- **Regression risk:** Medium for accessibility, keyboard navigation, and print/export.

#### R3.3 Add only evidence-backed indexes

- **Affected files/components:** Abuse/MoM/list tables after final query design.
- **What should change:** Use `EXPLAIN ANALYZE` and slow-query evidence, then add the minimum composite indexes.
- **Why:** Avoids write/storage cost from redundant indexes.
- **Expected improvement:** Query-specific.
- **Regression risk:** Low operationally if tested online; requires rollout planning for large tables.

## Quick Wins

1. Add request-local memoization to `tracs_table_exists()`, `tracs_column_exists()`, `tracs_get_user_by_id()`, `tracs_user_permissions()`, and `tracs_user_can()`.
2. Parse the Abuse Reports embedded payload once instead of twice.
3. Stop calling `renderList()` from board rendering when list view is inactive.
4. Stop rendering the hidden Cases presentation on every filter/sort/status change.
5. Increase Case/client free-text debounce to roughly 250–300 ms; abort superseded Client requests.
6. Scope Lucide refreshes to newly changed containers.
7. Apply User Management's division preset before baseline capture.
8. Fix the MoM action update bind signature and make the UI use the existing update action with an item ID.
9. Select explicit compact columns on list endpoints; fetch full notes/details on demand.
10. Guard MoM installation/schema and lifecycle checks once per request while migrations are moved out of reads.

## Performance Benchmark Plan

Run against a sanitized production-shaped database with at least small, medium, and high-volume fixtures (for example 100/1,000/10,000 records where safe).

### Backend metrics

- TTFB and total PHP time by route.
- SQL query count, cumulative SQL time, and slowest five queries.
- Count of `information_schema` queries.
- Count/time of DDL or maintenance writes triggered by GET requests.
- Rows examined/returned from `EXPLAIN ANALYZE` for Cases, Abuse, Clients, Calendar sources, and dashboard summaries.
- Peak PHP memory and response payload bytes.
- p50/p95/p99 API latency under representative concurrency.

### Frontend metrics

- Number of page-init network requests and duplicate URLs.
- JSON parse time and record count.
- DOM node count after initial render.
- Long tasks, scripting, layout, and paint time.
- Time from dataset available to interactive list.
- Board -> Table and Table -> Board duration with 100, 1,000, and 5,000 records.
- Search keystrokes versus actual API requests.
- Focus/visibility event versus refresh request count.

### Acceptance gates

- No permission, ownership, filtering, notification, history, or relationship regression.
- No GET request performs DDL in a fully migrated environment.
- Permission/schema query count is constant per request, not multiplied by permission calls.
- Inactive list presentation does not create record DOM.
- Exact-value restoration clears dirty state.
- A MoM update leaves row count unchanged and changes only the targeted item and required linked records.
- No material payload or query regression at low record counts.

## Implementation Plan

### Phase 1 — Query & request optimization

1. Add diagnostics and capture baseline.
2. Add request-local permission/user/schema memoization.
3. Move runtime DDL to migrations with preflight.
4. Remove repeated page-refresh and focus-refresh requests where response reconciliation is safe.
5. Benchmark again before adding indexes.

### Phase 2 — Large dataset rendering optimization

1. Add compact list projections.
2. Add pagination to Clients, Abuse Reports, and Cases with module-specific safety rules.
3. Paginate/archive Checklist and Reminder history.
4. Add virtualization only where page-size rendering remains measurable.

### Phase 3 — Kanban/Table switching optimization

1. Implement active-view-only rendering for Cases.
2. Implement one-pass grouping/counts and active-view-only rendering for Abuse Reports.
3. Mount one Shift responsive presentation.
4. Benchmark both switch directions and filter/status updates.

### Phase 4 — Global dirty-state normalization

1. Diagnose/fix Case exact-reversion failure.
2. Correct User division preset baseline ordering.
3. Add coverage for async hydration, nested modals, dynamic lists, dates, files, dropdowns, and failed saves.
4. Require shared-guard integration for new editors.

### Phase 5 — MoM item editing fix

1. Define canonical create/update payloads without changing tables.
2. Fix action update binding and item-to-MoM validation.
3. Add agenda/note/decision update operations.
4. Hydrate complete editable item state and IDs.
5. Replace by ID after update; never append.
6. Integrate shared dirty state and linked-reminder/audit tests.

### Phase 6 — Regression & performance benchmark

1. Run permission/role and shared-visibility matrices.
2. Run CRUD, history, notifications, ownership, Calendar, and linked-record tests.
3. Compare p50/p95 SQL count/time, response size, DOM count, and view-switch timings to Phase 1 baseline.
4. Roll out behind module-level toggles or in small deployments where practical.

## Recommended Execution Order

Safest order by **highest performance gain + lowest regression risk**:

1. Instrument representative routes and capture a production-sized baseline.
2. Request-local table/column/user/permission memoization.
3. Remove request-time DDL/backfills after migration preflight.
4. Abuse parse-once and active-view-only rendering.
5. Cases active-view-only rendering and reduced duplicate renders.
6. Client search debounce/abort and refresh coalescing.
7. Correct MoM action update integrity, then complete all item edit operations.
8. Fix dirty-state regressions and expand cross-surface browser coverage.
9. Client/Abuse/Cases pagination and compact list payloads.
10. Calendar range bounding and dashboard aggregate reuse.
11. Move recurrence/SLA/auto-start maintenance to scheduled execution.
12. Add measured indexes, conditional assets, or virtualization only if benchmarks still justify them.

Further database, pagination, indexing, and maintenance-path changes should wait for a production-shaped baseline. The completed request-local, rendering, dirty-state, and MoM integrity changes do not depend on speculative database timing assumptions.
