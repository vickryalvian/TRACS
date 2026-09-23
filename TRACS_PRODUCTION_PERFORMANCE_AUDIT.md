# TRACS Production Performance Audit

**Audit date:** 2026-09-22  
**Production baseline:** `https://tracs.vickry.id`, `/opt/tracs` on `103.82.93.75`  
**Audit mode:** Read-only production inspection plus deployed-source review  
**Implementation status:** P0/P1 quick wins deployed on 2026-09-23; historical retention and list-query redesign remain gated

## Implementation Update: 2026-09-23

The first production-safe batch has been deployed:

- Idle notification scheduler completions and harmless lock skips no longer write database audit rows. The last idle completion row was written at `2026-09-23 08:43:43 WIB`; repeated scheduler cycles after deployment added no rows.
- Application request diagnostics are enabled at a 2% sample rate. A controlled request recorded route, status, 0.54 ms PHP duration, two SQL queries, response bytes, and peak memory without query text, request body, cookies, or credentials.
- Nginx now records a separate privacy-minimized timing log with URI path, status, bytes, total request time, and upstream time. Existing access logging remains intact and the timing log uses the existing daily rotation policy.
- Nginx now compresses CSS, JavaScript, JSON, XML, SVG, and font responses. Tested CSS and JavaScript returned `Content-Encoding: gzip` and `Vary: Accept-Encoding`.
- Versioned static files now return one-year immutable browser caching. Dynamic login remained `no-store`.
- PHP-FPM now records requests exceeding two seconds in a dedicated rotated slow log.
- PHP's redundant in-request file-session garbage collection is disabled for the FPM pool. The existing `phpsessionclean.timer` remains active every 30 minutes.
- The deployed Client bundle already matched the locally validated debounce, abort, and lifecycle-refresh coalescing build, so no bundle replacement was necessary.
- A reviewed retention-index migration and bounded prune command were deployed but not executed. The command is dry-run by default and refuses to delete without an explicit retention period, the required index, and `--execute`.

Immediate transfer measurements with gzip enabled:

| Asset | Before | After | Reduction |
|---|---:|---:|---:|
| `tracs.css` | 352,652 bytes | 66,736 bytes | 81.1% |
| `tracs.js` | 352,126 bytes | 86,449 bytes | 75.4% |
| Client entry | 92,361 bytes | 24,051 bytes | 74.0% |

The initial timing-log sample contained 65 requests with 4.1 ms average Nginx request time and 34 ms maximum. This sample is mainly deployment verification traffic and is not an authenticated workload baseline.

Production rollback backup: `/opt/tracs/backups/performance-quick-wins-20260923-084344/`.

During verification, editing `.env` briefly changed its group and made it unreadable by PHP-FPM. The issue was detected through the error log and immediately corrected to `vickry:www-data` mode `640`; database connectivity, diagnostics, and HTTP health checks then passed. Future environment edits must preserve the `www-data` group.

## Executive Summary

TRACS is not currently CPU-bound, RAM-bound, or connection-bound. The VPS had low load, about 4.9 GiB of available RAM, and a MariaDB maximum observed connection count of 5. Adding Redis or increasing server size would not address the most important current problems.

The highest-priority production issue is uncontrolled notification audit-log growth. `tracs_notification_logs` contains about 1.9 million rows and occupies 503.28 MiB, roughly 96% of the entire 524.66 MiB database. A bounded sample taken before and after a notification code update showed that repeated duplicate-trigger logging stopped at approximately 17:46 WIB on the audit date. The worker still writes one successful scheduler-completion row every minute even when it creates nothing, which is about 525,600 low-value rows per year. The historical table remains oversized. An exact `COUNT(*)` scan took about 30 seconds and was stopped before any further unbounded audit queries were attempted.

The second priority is observability. MariaDB Performance Schema, the slow-query log, PHP-FPM slow-request logging, Nginx request timing, and the deployed application diagnostics flag are all disabled. It is therefore impossible to produce trustworthy per-page before/after latency and query-count comparisons from current production telemetry. The application already contains safe, sampled request diagnostics, but they are not enabled.

The third immediate opportunity is static delivery. Versioned CSS and JavaScript are served without explicit browser cache headers. Nginx gzip is enabled only at its default scope because `gzip_types` is commented out. A typical authenticated page loads shared files of about 352 KiB each for `tracs.css` and `tracs.js`, and some React chunks are 190 to 295 KiB. These assets are neither compressed in the tested response nor assigned a long-lived cache policy.

High-volume application pages still have scaling limits, but the live row counts are currently small: 83 cases, 113 abuse reports, 1 client, and 1,284 shift assignments. Cases, Abuse Reports, and Client Portfolio use unbounded list queries, and Calendar requests a full year. These should be redesigned before those tables reach tens of thousands of records, but they are not the present 500 MiB database problem.

Redis is not recommended for TRACS at this stage. A Redis service is active on the VPS, but the PHP Redis extension is absent, the deployed application contains no Redis integration, current load is low, and the main database problem is write amplification and missing retention rather than repeated expensive reads. Query and payload optimization should come first.

## 1. Current Production Architecture

```text
Browser
  -> Nginx 1.24
  -> PHP-FPM 8.3, up to 10 workers
  -> server-rendered PHP and JSON endpoints
  -> MariaDB 10.11
  -> HTML, JSON, and static assets
```

Supporting runtime paths:

```text
systemd timers
  -> notification scheduler every minute
  -> infrastructure monitor every minute
  -> Dobby event worker every minute

External APIs
  -> short-lived file or session cache
  -> stale fallback where implemented
```

### Production version integrity

Production is a composite working tree, not a clean release checkout:

- Git metadata reports commit `a9e3e30`.
- 58 tracked production files differ from that commit.
- Hash comparison of 12 performance-relevant files found four differences between production and the local checkout.
- The deployed files, database metadata, service configuration, and HTTP responses in this report are therefore the source of truth.

### VPS resource snapshot

| Resource | Production observation | Assessment |
|---|---:|---|
| CPU | 2 vCPU, load average about 0.09 / 0.06 / 0.06 | Not CPU-bound at inspection time |
| RAM | 5,925 MiB total, 4,935 MiB available | Substantial headroom |
| Swap | 2,047 MiB total, 122 MiB used | No current swap pressure |
| Disk | 58 GiB total, 45 GiB available | Substantial headroom |
| PHP-FPM | `pm.max_children=10`; workers about 25 to 34 MiB RSS | Safe within current RAM |
| MariaDB | about 180 MiB RSS; maximum 5 connections observed | Not connection-bound |
| Application logs | 48 MiB | Requires rotation review, not urgent capacity pressure |
| Uploads | 11 MiB | Small |
| Application file cache | 32 KiB | Small and bounded |

## 2. Production Performance Bottlenecks

### P0. Notification log write amplification and missing retention

**Issue:** `tracs_notification_logs` dominates the database and continues to grow during idle scheduler runs.  
**Affected process:** `tracs-notification-worker.timer` and `core/notifications.php`  
**Affected table:** `tracs_notification_logs`  
**Root cause:** Historical duplicate-trigger events were written repeatedly, scheduler success is still written once per minute, and no retention process exists for this table.  
**Database impact:** About 1.9 million rows, 503.28 MiB, and roughly 96% of total database storage. A full exact row count took about 30 seconds.  
**Backend impact:** Continuous inserts, larger backups, slower table scans, larger indexes, and unnecessary buffer-pool churn.  
**Frontend impact:** Indirect. Larger database operations and backups can contend with user requests.  
**Priority:** Critical  
**Recommended solution:**

1. Keep the deployed duplicate-trigger suppression and verify for 24 hours that duplicate rows do not resume.
2. Do not write a database row for an idle successful scheduler run. Keep the existing worker file log for heartbeat/operational evidence.
3. Store only actionable database events: creation, delivery failure, permission failure, lock contention if abnormal, and scheduler exceptions.
4. Agree on an audit retention period, likely 30 to 90 days, before deleting anything.
5. Add a `created_at` retention index through a reviewed online migration, then prune in small, bounded batches from a scheduled maintenance job.
6. Back up the table and measure free disk space before the first historical prune. Do not run a single massive delete or `OPTIMIZE TABLE` during normal traffic.

**Expected improvement:** Removes current idle write amplification, prevents another multi-million-row accumulation, reduces backup and scan cost, and allows historical space to be recovered safely.  
**Implementation risk:** Medium. The code change is low-risk; historical cleanup requires a backup, batching, lock monitoring, and a maintenance window.

### P0. Production cannot attribute slow requests or SQL

**Issue:** The deployed system lacks usable endpoint and query latency telemetry.  
**Affected pages/endpoints:** Application-wide  
**Root cause:** `TRACS_PERF_DIAGNOSTICS` is disabled, MariaDB Performance Schema and slow-query logging are off, Nginx uses a default access log without request time, and PHP-FPM slow logging is not configured.  
**Database impact:** Slow or repetitive SQL cannot be ranked by total time or frequency.  
**Backend impact:** Optimization choices cannot be validated with production evidence.  
**Frontend impact:** Page slowness cannot be separated reliably into network, PHP, SQL, and rendering time.  
**Priority:** Critical  
**Recommended solution:**

1. Enable the existing application diagnostics at a 1% to 5% sample rate for a 24 to 48 hour baseline.
2. Confirm log rotation before enabling it and aggregate only route, duration, query count, response size, status, and peak memory.
3. Add `$request_time` and `$upstream_response_time` to a dedicated Nginx access-log format.
4. Configure a PHP-FPM slow log with a conservative threshold such as 2 seconds.
5. If SQL attribution remains insufficient, enable the MariaDB slow-query log temporarily with a reviewed threshold. Do not enable unrestricted general logging.

**Expected improvement:** Produces a trustworthy top-endpoint and top-query worklist and enables before/after comparisons.  
**Implementation risk:** Low if sampled and rotated. Nginx, PHP-FPM, or MariaDB configuration changes require controlled reloads and explicit deployment approval.

### P1. Static assets are neither compressed nor explicitly cached

**Issue:** Versioned static assets are served without `Cache-Control` or `Expires`, and tested JavaScript/CSS responses were not gzip-compressed.  
**Affected pages:** All authenticated pages  
**Affected assets:** Shared `tracs.css` and `tracs.js`, MoM assets, Calendar and React bundles  
**Root cause:** The Nginx `gzip_types` list and static-asset cache location are not configured.  
**Database impact:** None.  
**Backend impact:** More network transfer and repeat static requests.  
**Frontend impact:** Slower cold and repeat page loads, especially over mobile or high-latency connections.  
**Priority:** High  
**Recommended solution:**

- Enable gzip for CSS, JavaScript, JSON, SVG, XML, and fonts. Brotli is optional and not required.
- Add a static-file location for fingerprinted build assets with `Cache-Control: public, max-age=31536000, immutable`.
- The shared PHP assets use stable paths plus `?v=<filemtime>`. They can also use long-lived caching because the URL changes on file replacement, but deployment must preserve that version change reliably.
- Keep dynamic PHP and JSON responses private/no-store unless an endpoint is explicitly proven safe to cache.
- Remove obsolete hashed build chunks from future release packages after verifying the active manifest. This is mainly deployment hygiene, not the primary runtime fix.

**Expected improvement:** Large reduction in repeat transfer size and lower cold-load transfer size. Exact milliseconds must be measured after enabling request timing and browser tests.  
**Implementation risk:** Low, provided cache rules are restricted to static extensions and versioned URLs.

### P1. Client Portfolio repeats and overbuilds list requests

**Issue:** Every filter change sends a request immediately; focus and visibility events can trigger additional identical refreshes; superseded requests are ignored but not aborted. The backend aggregates all service, add-on, and billing rows before returning an unpaginated list.  
**Affected page:** Client Portfolio  
**Affected endpoint:** `/api/v1/client-portfolio/clients.php`  
**Root cause:** No search debounce/request coalescing in deployed code, no pagination contract, whole-table aggregate subqueries, `c.*`, and PHP-side billing/attention filtering and sorting.  
**Database impact:** Work scales with all clients and related rows rather than the visible page. Search uses leading-wildcard `LIKE` across multiple columns.  
**Backend impact:** Larger arrays, repeated decoration, and a second pending-follow-up query for all returned IDs.  
**Frontend impact:** Repeated requests and potentially large JSON/DOM work.  
**Priority:** High for growth, Medium at the current production row count of one client  
**Recommended solution:**

1. Debounce text search by 250 to 350 ms, abort superseded requests, and coalesce focus/visibility refreshes.
2. Add a backward-compatible `limit` and cursor/page response while retaining the current response shape during rollout.
3. Select the bounded candidate client IDs first, then aggregate services, add-ons, billing, and reminders for only those IDs.
4. Push billing/attention predicates and ordering into SQL before pagination.
5. Return explicit list-summary columns instead of `c.*`; keep the existing detail endpoint for full records.

**Expected improvement:** Prevents request bursts and makes list cost proportional to visible clients instead of total history.  
**Implementation risk:** Medium because attention ranking, summary totals, and filters require parity fixtures.

### P1. Cases and Abuse Reports are unbounded

**Issue:** Both modules load all visible records into PHP and the browser.  
**Affected pages:** Cases and Abuse Reports  
**Affected query behavior:** Cases selects all records and sorts with `FIELD(...)`; Abuse Reports selects `r.*` and joins aggregates over complete evidence/event tables.  
**Root cause:** Board behavior was designed around one in-memory collection and no list pagination contract exists.  
**Database impact:** Query, sort, aggregate, and transfer cost grows with total history.  
**Backend impact:** Unbounded fetch, transformation, and JSON serialization.  
**Frontend impact:** Filtering/search runs in the browser. The currently deployed rendering improvement builds only the active board or list rows, but it still holds the complete dataset.  
**Priority:** High for future growth, Medium at 83 cases and 113 abuse reports  
**Recommended solution:**

- Introduce bounded server-side filtering/search first for table/list views.
- Use keyset pagination for stable descending activity/ID lists.
- For boards, load a bounded recent/active set per status and expose explicit "load more" behavior before allowing columns to contain thousands of draggable records.
- Page report IDs first, then calculate evidence count and latest event for only those IDs.
- Keep detail, notes, timeline, and evidence lazy-loaded.

**Expected improvement:** Keeps database, response, and DOM cost bounded as history grows.  
**Implementation risk:** Medium because counts, drag ordering, exports, and shared visibility must remain consistent.

### P1. Calendar always loads a full year

**Issue:** The deployed hook requests January 1 through December 31 for the selected year and the service fans out across many collectors.  
**Affected page:** Calendar and client calendar integration  
**Root cause:** Year-level acquisition is simpler than range-aware loading but transfers and processes events outside the visible month.  
**Database impact:** Multiple range queries and capability checks for a broad window.  
**Backend impact:** Larger combined event mapping and sorting.  
**Frontend impact:** Larger payload and refresh cost; changing year refetches the entire year.  
**Priority:** High for growth, Medium at current data volume  
**Recommended solution:** Request the visible month plus a small navigation buffer, retain already-fetched ranges in memory, and invalidate only affected ranges after mutations.  
**Expected improvement:** Makes calendar response cost proportional to the visible period.  
**Implementation risk:** Medium because all event sources and cross-page invalidation must remain correct.

### P2. Schema mutation remains reachable from request paths

**Issue:** 35 deployed PHP files reference runtime schema inspection or mutation, including `CREATE TABLE IF NOT EXISTS` and `ALTER TABLE`.  
**Affected areas:** Notifications, Cases, Abuse Reports, MoM, Calendar, domains, finance, attachments, ticker, user preferences, and security compatibility code  
**Root cause:** Deployment migration compatibility is mixed into web requests.  
**Database impact:** Metadata queries on normal reads and potential metadata locks when schema differs.  
**Backend impact:** Variable response time and harder deployment diagnosis.  
**Frontend impact:** Requests can stall or fail unpredictably during implicit schema changes.  
**Priority:** Medium  
**Recommended solution:** Move mutations into versioned migrations and a deployment preflight. Keep request-local read-only capability checks temporarily, then fail with an actionable administrative error when a required migration is missing.  
**Expected improvement:** Removes metadata work and production DDL risk from user requests.  
**Implementation risk:** Medium because every deployed environment must first pass migration verification.

## 3. Current Query Analysis

### Production database profile

| Metric | Observation |
|---|---:|
| Total database size | 524.66 MiB |
| Data size | 410.27 MiB |
| Index size | 114.39 MiB |
| Notification log | 503.28 MiB, about 1.9 million rows |
| Infrastructure monitoring history | 7.03 MiB, about 70,500 rows |
| Next-largest application table | 3.66 MiB |
| InnoDB buffer pool | 128 MiB |
| Buffer-pool logical reads | 68,871,085 |
| Buffer-pool physical reads | 50,302 |
| Approximate buffer hit ratio | 99.93% |
| Temporary tables | 27,014,824 |
| Temporary disk tables | 24,970,388, about 92% of temporary tables |
| Maximum connections observed | 5 of 151 |
| Tables without primary key | 0 |

The high buffer hit ratio and low connection count do not support a server-size or connection-pool intervention. The very high temporary-disk-table and full-scan counters warrant attribution, but Performance Schema and slow-query logging are currently unavailable. They must not be used to guess at indexes without query evidence.

### Confirmed query patterns

#### Notification log

- Full exact row count took about 30 seconds.
- Existing `(status, created_at)` and `(notification_id, created_at)` indexes support their specific lookups.
- There is no standalone `created_at` index for retention pruning.
- The deployed application has no read path for the log table, so continuous idle success entries have no user-facing value.

#### Cases

- Fetches all cases with no `LIMIT`.
- Includes long `notes` in the list payload.
- Sorts with `FIELD(status, ...)`, then board order, date, and update time.
- Current count is small, but the query is not bounded for future growth.

#### Abuse Reports

- Fetches `r.*` with no `LIMIT`.
- Aggregates all evidence by report.
- Finds latest activity through a grouped full event-table subquery.
- Filters and search are performed in browser memory.

#### Client Portfolio

- Fetches `c.*` with no `LIMIT`.
- Aggregates complete service, add-on, and billing tables before filtering the result.
- Runs a follow-up query for all returned client IDs.
- Billing status, attention filtering, and final sort happen after SQL in PHP.
- Search uses `LIKE '%term%'`, which cannot use ordinary B-tree indexes efficiently.

#### Calendar

- Requests a full year.
- Combines as many as thirteen event sources before mapping and sorting.
- Request-local table, column, user, and permission caches are already present and should be retained.

### Index policy

Do not add broad speculative indexes to the current small business tables. The one evidence-backed candidate is a notification-log retention index on `created_at`, because the table is already near two million rows and a retention query otherwise has no efficient access path. Even this index must be introduced as a reviewed online migration with space and lock monitoring.

For Cases, Abuse Reports, Clients, Calendar, and dashboard queries, collect sampled endpoint/query evidence and run `EXPLAIN ANALYZE` against exact proposed queries before adding or removing indexes. Existing foreign-key, user/status, and scheduling indexes already impose write cost and should not be duplicated.

## 4. Current Cache Analysis

| Cache layer | Current production state | Assessment |
|---|---|---|
| PHP OPcache | Enabled for FPM, 128 MiB, 10,000 files, timestamp validation off | Appropriate for manual deployment, but every PHP deploy must reload FPM or explicitly reset OPcache |
| Request-local PHP cache | Table, column, user, permission-set, permission-decision, and schema readiness caches exist | Good. Retain request scope to avoid stale authorization |
| Holiday file cache | 12-hour TTL plus stale/static fallback | Appropriate |
| Currency-rate file cache | 5-minute TTL plus stale fallback | Appropriate; add locking only if metrics show stampede |
| Screenshot-region file cache | 10-minute TTL plus stale fallback | Appropriate; add locking only if metrics show stampede |
| Billing session cache | 5-minute per-session cache | Acceptable at current traffic; duplicates external calls across users |
| Browser/UI state | View preference and selected state in local storage; Cases/Abuse reuse the loaded dataset | Appropriate for presentation state, not a replacement for bounded server loading |
| HTTP static cache | No explicit cache headers observed | Missing and high-value |
| Dynamic HTTP cache | Authentication pages are no-store | Correct |
| MariaDB query cache | Disabled | Correct for MariaDB write-heavy application behavior |
| Redis | Service active, but no PHP extension and no application integration | Do not introduce yet |
| Memcached | Service inactive and PHP extension absent | Not used |

### Cache correctness concerns

- Authorization caches are request-local and explicitly invalidated by user-management mutations. They should not be promoted to Redis without a versioned invalidation design.
- File caches write directly to their final path and have no regeneration lock. At current traffic and payload size this is acceptable, but atomic temp-file rename and a short lock would be appropriate if concurrent misses are observed.
- File-session locking can serialize concurrent requests for the same user while a long request holds the session open. Measure this before changing the session handler; close sessions early on read-only endpoints where safe.
- Do not cache editable lists or detail records broadly until mutation invalidation is implemented and tested.

## 5. Recommended Cache Architecture

### Current recommendation

```text
Browser
  -> long-lived cache for versioned static assets
  -> private/no-store for authenticated HTML and mutable JSON

TRACS PHP
  -> request-local capability/user/permission cache
  -> small file caches for external reference APIs
  -> short session cache for low-volume external billing data
  -> optimized, bounded SQL

MariaDB
  -> source of truth
```

Redis is intentionally absent from the recommended near-term path. The application should first remove idle writes, bound list queries, improve static delivery, and collect latency/query evidence.

### Redis reconsideration trigger

Reconsider Redis only if production measurements show one or more of the following after query fixes:

- the same expensive aggregate is executed frequently across PHP workers;
- external reference data must be shared consistently across users and workers;
- database CPU or latency is dominated by safe-to-cache read aggregates;
- the application runs on multiple web nodes and file/session cache locality becomes a problem.

If those triggers occur, use Redis as an optional cache behind a small application interface. MariaDB remains the source of truth. The application must continue correctly when Redis is unavailable.

Suggested future keys:

```text
tracs:v1:master:services
tracs:v1:calendar:user:{user_id}:{range_hash}
tracs:v1:dashboard:user:{user_id}:{scope_hash}
tracs:v1:client:list:{scope_hash}:{filter_hash}:{cursor}
tracs:v1:mom:stats:{scope_hash}:{period}
```

Use mutation-driven namespace versions or tag/version counters instead of wildcard deletion. Never use `FLUSHALL`. Permission and authentication decisions should remain request-scoped unless a rigorously versioned invalidation mechanism exists.

## 6. Recommended Implementation Sequence

### Quick wins

1. **Observe first:** enable 1% to 5% application request sampling for 24 to 48 hours and add Nginx upstream/request timing.
2. **Stop idle notification database logs:** retain failure/action records and the existing worker file heartbeat.
3. **Design and approve retention:** back up, add the reviewed time index, batch-prune historical notification logs, and verify lock/replication impact if applicable.
4. **Compress and cache static assets:** enable gzip MIME types and immutable caching for versioned assets.
5. **Reduce Client request duplication:** debounce search, abort superseded calls, and coalesce focus/visibility refreshes.
6. **Establish baseline measurements:** record endpoint latency percentiles, query count, response bytes, peak PHP memory, and browser transfer/render timing.

### Medium-term optimizations

1. Add backward-compatible server pagination and compact list projections to Client Portfolio first, then Cases and Abuse Reports.
2. Rewrite Client Portfolio to select bounded candidate IDs before related-table aggregates.
3. Page Abuse Report IDs before evidence/latest-event aggregation.
4. Load Calendar by visible range and reuse fetched ranges in the browser.
5. Replace dashboard full-collection scans with permission-equivalent aggregate and top-N queries.
6. Move request-time schema mutations into versioned deployment migrations.
7. Review session duration and call `session_write_close()` early on safe read-only requests after measuring lock contention.

### Future scaling improvements

1. Adopt keyset pagination on stable `(timestamp, id)` orderings when deep pages become material.
2. Consider full-text search only after search volume and row counts justify it; do not introduce Elasticsearch/OpenSearch for the current dataset.
3. Add short-lived aggregate caching only for queries proven expensive after SQL optimization.
4. Re-evaluate Redis using measured hit-rate and memory-size estimates.
5. Partition or archive high-volume operational history only if retention requirements exceed practical online table size.

## 7. Validation Plan

Before each implementation batch, capture the following for the same user scope, filter, and dataset:

| Metric | Before | After |
|---|---:|---:|
| Page/navigation |  |  |
| p50/p95 backend duration |  |  |
| SQL query count |  |  |
| Response bytes |  |  |
| Peak PHP memory |  |  |
| Browser requests |  |  |
| Transferred JS/CSS bytes |  |  |
| DOM nodes/render time |  |  |
| Database rows examined |  |  |

Functional checks must cover:

- create, edit, delete, and status transitions;
- permissions and shared/division scope;
- search, filters, sorting, pagination, and exports;
- board/card/table switching and drag ordering;
- modals and lazy-loaded detail;
- dirty-state and unsaved-change protection;
- notifications, browser push state, and scheduler behavior;
- Calendar, reminders, MoM, Clients, Cases, Abuse Reports, and dashboard totals;
- cache invalidation after every mutation path.

### Production safety gates

- Do not delete or archive notification logs until retention is approved and a verified backup exists.
- Do not add the retention index without checking free disk and online-DDL behavior.
- Do not enable broad SQL logging or full request logging containing query strings, bodies, cookies, or credentials.
- Do not restart production services outside a reviewed deployment window.
- Do not deploy the local checkout wholesale. Production has material file drift and must receive an explicit allowlist of reviewed files.

## 8. Audit Limits

- No authenticated production browser session was forged or reused, so private page timing and rendered DOM profiling were not performed.
- Current Nginx logs do not include request duration, and MariaDB has no statement digest or slow-query history. Exact endpoint/query rankings require the observability phase.
- Table-row values from `information_schema` are approximate for InnoDB except for the bounded checks noted above.
- The one exact notification-log count was deliberately not repeated because it took about 30 seconds.
- No production data, schema, service, configuration, cache, or code was changed during this audit.
