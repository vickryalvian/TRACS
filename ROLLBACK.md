# TRACS Rollback Guide

## Safety Rules

- Identify whether the failed change affects code, built assets, runtime files,
  configuration, uploads, cache, or the database before rolling back.
- Back up current production files and database state before a production
  rollback, even when restoring an older backup.
- Never use `git reset --hard`, `git clean`, or destructive database commands
  without confirming that no required work or data will be lost.
- Application rollback and database rollback are separate operations.
- Preserve `.env`, production database configuration, uploads, cache, logs, and
  deployment metadata unless the reviewed rollback explicitly includes them.

## Discard Uncommitted Local Documentation Changes

Review changes first:

```bash
git status --short
git diff
```

Discard a specific tracked file:

```bash
git restore path/to/file
```

Discard all tracked local changes only after confirming they are unwanted:

```bash
git restore .
```

Remove an untracked file only after confirming it was created by the failed
batch and is not needed:

```bash
rm path/to/untracked-file
```

## Return To Main And Delete A Failed Branch

Switch back to the updated main branch:

```bash
git switch main
git pull --ff-only origin main
```

Delete a local branch that was never merged:

```bash
git branch -d refactor/phase-1-testing-baseline
```

If Git reports that the branch is not merged, inspect it before using the
force-delete form:

```bash
git log --oneline main..refactor/phase-1-testing-baseline
git branch -D refactor/phase-1-testing-baseline
```

No remote branch exists unless the branch was explicitly pushed. If a failed
branch was pushed, remote deletion requires separate approval:

```bash
git push origin --delete refactor/phase-1-testing-baseline
```

## Revert A Commit

For a shared or already-pushed history, create a new inverse commit:

```bash
git switch <target-branch>
git pull --ff-only
git revert <commit-sha>
```

Review and verify the result before pushing. Use a parent selection for a merge
commit only after confirming the correct mainline parent:

```bash
git revert -m 1 <merge-commit-sha>
```

Do not rewrite shared history to remove a published commit.

## Restore An Application Deployment

The deployment script can restore application code from a deployment backup:

```bash
REPO_DIR=/srv/tracs-repository WEB_ROOT=/var/www/tracs \
  ./deploy.sh rollback <backup-id>
```

This restores application files while preserving current production
configuration and runtime data according to the deployment script. Confirm the
selected backup ID and review `README.md` and `VPS_SECURITY_CONFIGURATION.md`
before production use.

## Restore A Database Backup

Database restoration is destructive to the target database. Stop application
writes or place the application in an approved maintenance state first.

For an uncompressed SQL backup:

```bash
mysql --login-path=tracs-deploy tracs_db < /secure/path/database.sql
```

For a gzip-compressed SQL backup:

```bash
gzip -dc /secure/path/database.sql.gz | \
  mysql --login-path=tracs-deploy tracs_db
```

Before restoring:

1. Confirm the backup timestamp, database name, and application revision.
2. Export the current database as a recovery point.
3. Confirm the credential source does not expose passwords in shell history.
4. Restore into a temporary database first when practical.
5. Verify table counts, critical users, permissions, shifts, and recent records.
6. Re-run the critical smoke and permission checklists after restoration.

## Future Migration Rollback Standard

Phase 1 does not add or run migrations. Every future database-changing batch
must include reviewed paired migration files:

```text
config/migrations/YYYY_MM_DD_change_name/up.sql
config/migrations/YYYY_MM_DD_change_name/down.sql
```

Each migration batch must also document:

- Required backup.
- Supported MySQL/MariaDB versions.
- Preconditions and affected tables.
- Expected row transformations.
- Up verification queries.
- Down limitations and data-loss warnings.
- Application revision compatibility.
- Exact restore procedure when a down migration cannot safely recreate data.

No destructive migration may rely on `down.sql` as its only recovery path.
Take a database backup before applying it.

## Phase 1 Rollback

This phase changes documentation only. Before the commit is merged, return to
`main` and delete the branch. After merge, revert the documentation commit:

```bash
git revert <phase-1-documentation-commit>
```

No database or runtime rollback is required for this phase.

## Phase 4 Frontend Foundation Rollback

Phase 4 adds an isolated `frontend/` package and documentation only. It does not
load assets from PHP, modify production navigation, change APIs, or change the
database.

Discard uncommitted Phase 4 work:

```bash
git restore --staged .
git restore .
git clean -fd frontend/
```

Switch to the approved base and delete a failed local branch:

```bash
git switch refactor/phase-3-tailwind-design-system
git branch -D refactor/phase-4-react-tailwind-foundation
```

After the Phase 4 commit is merged, revert it without rewriting history:

```bash
git revert <phase-4-frontend-foundation-commit>
```

Local generated files can be removed independently:

```bash
rm -rf frontend/dist frontend/node_modules
```

No database restore is required for Phase 4 because it contains no migration or
data operation. Future database changes must still include reviewed `up.sql`
and `down.sql` files and a tested backup restoration procedure.

## Phase 5 PHP API Foundation Rollback

Phase 5 adds internal `api/` helpers, one CLI check, and documentation. Existing
public routes do not load these files, so rollback requires no endpoint switch,
cache purge, database restore, or UI rollback.

Discard uncommitted Phase 5 work:

```bash
git restore --staged .
git restore .
git clean -fd api/ tests/
```

Switch to the approved base and delete a failed local branch:

```bash
git switch refactor/phase-4-react-tailwind-foundation
git branch -D refactor/phase-5-php-api-foundation
```

After merge, revert without rewriting history:

```bash
git revert <phase-5-php-api-foundation-commit>
```

No database schema or data changed. Future database batches must include
reviewed `up.sql` and `down.sql`, a pre-change backup, verification queries, and
a tested restoration procedure.

## Phase 5.5 Pilot API Contract Rollback

Phase 5.5 adds one route under `public/api/v1/`, its internal formatter, tests,
and documentation. No page or React module calls the route yet.

Discard uncommitted work:

```bash
git restore --staged .
git restore .
git clean -fd api/v1/ public/api/v1/ tests/php-api-contract.php
```

Switch to the approved base and delete a failed local branch:

```bash
git switch refactor/phase-5-php-api-foundation
git branch -D refactor/phase-5-5-pilot-api-contract
```

After merge, revert without rewriting history:

```bash
git revert <phase-5-5-pilot-api-contract-commit>
```

No database restore, asset rollback, or UI fallback is required. After rollback,
`/api/v1/context.php` should return `404`; existing APIs and pages remain
unchanged.

## Phase 6 Shift Assignment Contract Rollback

Phase 6 adds a read-only versioned route, pure response formatter, contract
check, and documentation. It does not alter the legacy Shift Assignment page,
API, business logic, schema, or data.

Before commit, review `git diff`, then discard only Phase 6 work:

```bash
git restore --staged .
git restore .
rm -rf api/v1/shift-assignment public/api/v1/shift-assignment
rm tests/shift-assignment-api-contract.php
rm docs/shift-assignment-api-contract.md
```

After commit:

```bash
git revert <phase-6-commit-sha>
```

To abandon the unmerged local branch:

```bash
git switch refactor/phase-5-5-pilot-api-contract
git branch -D refactor/phase-6-shift-assignment-api-contracts
```

No database restore, asset rebuild, or UI rollback is required.

## Phase 7 Shift Assignment Read API Rollback

Phase 7 adds one GET-only route, its internal validator/resource formatter,
contract tests, and documentation. It does not alter existing pages, the
legacy Shift Assignment API, business logic, schema, or data.

Before commit, review the diff and remove only Phase 7 work:

```bash
git restore --staged .
git restore .
rm api/v1/shift-assignment/assignments.php
rm public/api/v1/shift-assignment/assignments.php
rm tests/shift-assignment-assignments-api-contract.php
```

After commit:

```bash
git revert <phase-7-commit-sha>
```

To abandon the unmerged branch:

```bash
git switch refactor/phase-6-shift-assignment-api-contracts
git branch -D refactor/phase-7-shift-assignment-read-api
```

No database restore, frontend rebuild, or production page rollback is required.

## Phase 8 Shift Assignment React Shell Rollback

Phase 8 adds an unmounted frontend entry, frontend contract check, and
documentation. It does not change PHP pages, navigation, APIs, schema, or data.

Before commit:

```bash
git restore --staged .
git restore .
rm -rf frontend/src/modules/shift-assignment
rm frontend/tests/apiClient-contract.mjs
rm -rf frontend/dist
```

After commit:

```bash
git revert <phase-8-commit-sha>
```

To abandon the unmerged branch:

```bash
git switch refactor/phase-7-shift-assignment-read-api
git branch -D refactor/phase-8-shift-assignment-react-shell
```

No database restore, asset deployment rollback, PHP fallback switch, or
production navigation change is required because the entry is not mounted.

## Phase 9 Authenticated React Preview Rollback

Phase 9 adds an unlinked authenticated preview page, a manifest resolver, a
preview-only build target, generated preview assets, tests, and documentation.
It does not replace the legacy page or alter navigation, APIs, schema, or data.

Before commit:

```bash
git restore --staged .
git restore .
rm public/shift-assignment-react-preview.php
rm public/includes/react_manifest.php
rm tests/shift-assignment-react-preview.php
rm frontend/vite.preview.config.js
rm -rf public/assets/react-dist
```

After commit:

```bash
git revert <phase-9-commit-sha>
```

To abandon the unmerged branch:

```bash
git switch refactor/phase-8-shift-assignment-react-shell
git branch -D refactor/phase-9-shift-assignment-react-preview
```

No database restore or legacy UI fallback operation is required. Removing the
preview page or assets returns its URL to unavailable while
`public/shifting-assignment.php` continues normally.

## Phase 10 Preview Parity Testing Rollback

Phase 10 adds documentation and a non-mutating source-level parity test only.
It does not change pages, navigation, APIs, business logic, schema, or data.

Before commit:

```bash
git restore --staged .
git restore .
rm docs/shift-assignment-preview-parity.md
rm docs/shift-assignment-role-test-matrix.md
rm tests/shift-assignment-preview-parity.php
```

After commit:

```bash
git revert <phase-10-commit-sha>
```

To abandon the unmerged branch:

```bash
git switch refactor/phase-9-shift-assignment-react-preview
git branch -D refactor/phase-10-shift-preview-parity-testing
```

No database restore, frontend rebuild, navigation rollback, or legacy page
fallback is required.

## Phase 11 Internal Pilot Access Rollback

Phase 11 adds an exact-role Super Admin guard and clarifies the existing pilot
banner, plus tests and documentation. It does not change navigation, APIs,
schema, data, Calendar, or the legacy Shift Assignment page.

Before commit:

```bash
git restore --staged .
git restore .
rm tests/shift-assignment-internal-pilot.php
```

After commit:

```bash
git revert <phase-11-commit-sha>
```

To abandon the unmerged branch:

```bash
git switch refactor/phase-10-shift-preview-parity-testing
git branch -D refactor/phase-11-shift-preview-internal-pilot
```

No database restore, asset rebuild, navigation rollback, or legacy-page
fallback is required.

## Phase 12 Read-Only Production Candidate Rollback

Phase 12 changes only the isolated read-only React candidate, generated preview
assets, frontend/tests, and documentation. It does not change legacy Shift
Assignment, navigation, APIs, business logic, schema, data, or Calendar.

Before commit:

```bash
git restore --staged .
git restore .
rm frontend/tests/preview-bundle-contract.mjs
rm tests/shift-assignment-readonly-candidate.php
cd frontend && npm run build:preview
```

After commit:

```bash
git revert <phase-12-commit-sha>
```

To abandon the unmerged branch:

```bash
git switch refactor/phase-11-shift-preview-internal-pilot
git branch -D refactor/phase-12-shift-readonly-production-candidate
```

No database restore, navigation rollback, or legacy-page fallback is required.

## Phase 13 Write API Contract Planning Rollback

Phase 13 adds documentation and a non-mutating source guard only. It does not
add endpoints, permissions, schema, data writes, UI actions, navigation, or
changes to the legacy page.

Before commit:

```bash
git restore --staged .
git restore .
rm docs/shift-assignment-write-api-contract.md
rm tests/shift-assignment-write-contract-plan.php
```

After commit:

```bash
git revert <phase-13-commit-sha>
```

To abandon the unmerged branch:

```bash
git switch refactor/phase-12-shift-readonly-production-candidate
git branch -D refactor/phase-13-shift-write-api-contracts
```

No database restore, frontend rebuild, navigation rollback, or legacy-page
fallback is required.

## Phase 14 Controlled Create Assignment API Rollback

Phase 14 adds POST handling to the existing v1 assignments resource, an
exact-role API helper, contract tests, and documentation. It does not add a
schema migration, React write UI, navigation, Calendar, or legacy-page change.

Before commit:

```bash
git restore --staged .
git restore .
rm tests/shift-assignment-create-api-contract.php
```

After commit:

```bash
git revert <phase-14-commit-sha>
```

To abandon the unmerged branch:

```bash
git switch refactor/phase-13-shift-write-api-contracts
git branch -D refactor/phase-14-shift-create-assignment-api
```

Code rollback does not delete assignments already created through an approved
staging or production pilot. If the endpoint was exercised, restore the
recorded database backup or follow an explicitly approved, audited data
correction procedure. Never delete real schedules ad hoc.

## Phase 15 Disposable Integration Testing Rollback

Phase 15 adds test-only CLI files, a narrow explicit role-permission helper for
the controlled create gate, contract updates, and documentation. It adds no
schema migration, React UI, navigation, Calendar, or legacy-page change.

Before commit:

```bash
git restore --staged .
git restore .
rm tests/shift-assignment-create-api-integration.php
rm tests/fixtures/shift-assignment-api-request.php
```

After commit:

```bash
git revert <phase-15-commit-sha>
```

To abandon the unmerged branch:

```bash
git switch refactor/phase-14-shift-create-assignment-api
git branch -D refactor/phase-15-shift-create-api-integration-testing
```

The runner drops its target automatically. If interrupted, verify and remove
only the safely marked disposable database:

```sql
DROP DATABASE IF EXISTS tracs_phase15_test;
```

Never run cleanup against an unmarked database.

## Phase 16 Controlled React Create UI Pilot Rollback

Phase 16 changes only the isolated React preview bundle, its preview warning,
frontend contracts, and documentation. It adds no route, schema migration,
navigation entry, Calendar change, or legacy Shift Assignment change.

Before commit:

```bash
git restore --staged .
git restore .
rm frontend/src/modules/shift-assignment/components/ShiftCreateModal.jsx
rm frontend/src/modules/shift-assignment/components/ShiftToast.jsx
rm frontend/src/modules/shift-assignment/utils/shiftCreate.js
rm frontend/tests/shift-create-contract.mjs
rm tests/shift-assignment-create-ui-pilot.php
```

After commit:

```bash
git revert <phase-16-commit-sha>
```

To abandon the unmerged branch:

```bash
git switch refactor/phase-15-shift-create-api-integration-testing
git branch -D refactor/phase-16-shift-create-ui-pilot
```

Code rollback does not remove an assignment created during an approved
disposable/staging browser test. Clean only the disposable database or use an
approved audited data-correction procedure.

## Phase 17 Disposable Browser Validation Rollback

Phase 17 adds test-only disposable browser environment/contract files,
documentation evidence, and one preview-shell include for the existing
`page_helpers.php` dependency. It adds no production navigation, API route,
schema migration, Calendar change, or legacy Shift Assignment change.

Before commit:

```bash
git restore --staged .
git restore .
rm tests/shift-assignment-create-ui-browser-environment.php
rm tests/shift-assignment-create-ui-browser-validation.php
```

After commit:

```bash
git revert <phase-17-commit-sha>
```

To abandon the unmerged branch:

```bash
git switch refactor/phase-16-shift-create-ui-pilot
git branch -D refactor/phase-17-shift-create-ui-staging-validation
```

Emergency test-resource cleanup:

```bash
docker rm -f tracs_phase17_app
TRACS_ENV=test TRACS_ALLOW_MUTATION_TESTS=1 \
TRACS_TEST_DB_NAME=tracs_phase17_test \
php tests/shift-assignment-create-ui-browser-environment.php cleanup
```

Never substitute an unmarked database name in the cleanup command.

## Phase 18 Controlled Update API Rollback

Phase 18 adds the isolated PATCH route, pure validation/response helpers,
contract and disposable integration tests, and documentation. It does not add
React edit UI, navigation, schema, Calendar, or legacy-page changes.

Before commit:

```bash
git restore --staged .
git restore .
rm api/v1/shift-assignment/assignment.php
rm public/api/v1/shift-assignment/assignment.php
rm tests/shift-assignment-update-api-contract.php
rm tests/shift-assignment-update-api-integration.php
```

After commit:

```bash
git revert <phase-18-commit-sha>
```

To abandon the unmerged branch:

```bash
git switch refactor/phase-17-shift-create-ui-staging-validation
git branch -D refactor/phase-18-shift-update-assignment-api
```

The integration runner drops its target automatically. If interrupted, remove
only the safely marked disposable database:

```sql
DROP DATABASE IF EXISTS tracs_phase18_test;
```

Code rollback does not reverse an update made through an approved non-test
environment. Restore the recorded database backup or use an explicitly
approved, audited correction; never overwrite real schedules ad hoc.

## Phase 19 Controlled React Edit UI Rollback

Phase 19 changes only the isolated preview UI, its server-provided update
capability, a compatibility normalization in the existing PATCH helper,
frontend/PHP contracts, disposable browser verification, and documentation.
It adds no endpoint, schema, navigation, Calendar, or legacy-page change.

Before commit:

```bash
git restore --staged .
git restore .
rm frontend/src/modules/shift-assignment/components/ShiftEditModal.jsx
rm frontend/src/modules/shift-assignment/utils/shiftEdit.js
rm frontend/tests/shift-edit-contract.mjs
rm tests/shift-assignment-edit-ui-pilot.php
```

After commit:

```bash
git revert <phase-19-commit-sha>
```

To abandon the unmerged branch:

```bash
git switch refactor/phase-18-shift-update-assignment-api
git branch -D refactor/phase-19-shift-edit-ui-pilot
```

Emergency disposable cleanup:

```bash
docker rm -f tracs_phase19_app
TRACS_ENV=test TRACS_ALLOW_MUTATION_TESTS=1 \
TRACS_TEST_DB_NAME=tracs_phase19_test \
php tests/shift-assignment-create-ui-browser-environment.php cleanup
```

Code rollback does not reverse a real assignment update. Use an approved,
audited correction or restore the recorded backup; never mutate production
schedules through test cleanup.

## Phase 20 Create/Edit Hardening Rollback

Phase 20 changes only the isolated React preview, frontend/PHP regression
contracts, rebuilt preview assets, and canonical documentation. It adds no
endpoint, schema, navigation, Calendar, or legacy-page change.

Before commit:

```bash
git restore --staged .
git restore .
rm frontend/src/modules/shift-assignment/utils/shiftMutation.js
rm frontend/tests/shift-mutation-contract.mjs
rm tests/shift-assignment-create-edit-hardening.php
```

After commit:

```bash
git revert <phase-20-commit-sha>
```

To abandon the unmerged branch:

```bash
git switch refactor/phase-19-shift-edit-ui-pilot
git branch -D refactor/phase-20-shift-create-edit-hardening
```

Emergency disposable cleanup:

```bash
docker rm -f tracs_phase20_app
TRACS_ENV=test TRACS_ALLOW_MUTATION_TESTS=1 \
TRACS_TEST_DB_NAME=tracs_phase20_test \
php tests/shift-assignment-create-ui-browser-environment.php cleanup
```

The Phase 20 mutation integrations use disposable databases and remove them
automatically. Code rollback never reverses a real assignment mutation.

## Phase 21 Controlled Delete API Rollback

Phase 21 extends the existing assignment endpoint and service with a
backend-only, transaction-protected hard delete. It adds no schema, React UI,
navigation, Calendar, or legacy-page change.

Before commit:

```bash
git restore --staged .
git restore .
rm tests/shift-assignment-delete-api-contract.php
rm tests/shift-assignment-delete-api-integration.php
```

After commit:

```bash
git revert <phase-21-commit-sha>
```

To abandon the unmerged branch:

```bash
git switch refactor/phase-20-shift-create-edit-hardening
git branch -D refactor/phase-21-shift-delete-assignment-api
```

Emergency disposable cleanup:

```sql
DROP DATABASE IF EXISTS tracs_phase21_test;
```

A code revert cannot restore a deleted production assignment. React Delete UI
must remain disabled. Any future production pilot requires a database backup
and an approved restoration procedure using the preserved before snapshot.

## Phase 22 Delete Safety Gate Rollback

Phase 22 changes documentation and non-mutating checks only. It adds no React
Delete UI, endpoint, schema, navigation, Calendar, or legacy-page change.

Before commit:

```bash
git restore --staged .
git restore .
rm tests/shift-assignment-delete-safety-gate.php
```

After commit:

```bash
git revert <phase-22-commit-sha>
```

To abandon the unmerged branch:

```bash
git switch refactor/phase-21-shift-delete-assignment-api
git branch -D refactor/phase-22-shift-delete-safeguards
```

This code rollback has no database effect. For an assignment already deleted
through Phase 21, use the reviewed manual restoration procedure in
`docs/shift-assignment-write-api-contract.md`; never assume `git revert`
restores data.

## Phase 23 Restoration Drill Rollback

Phase 23 adds a disposable-only restoration drill and documentation. It adds no
production endpoint, React UI, schema, navigation, Calendar, or legacy-page
change.

Before commit:

```bash
git restore --staged .
git restore .
rm tests/shift-assignment-delete-restore-drill.php
```

After commit:

```bash
git revert <phase-23-commit-sha>
```

To abandon the unmerged branch:

```bash
git switch refactor/phase-22-shift-delete-safeguards
git branch -D refactor/phase-23-shift-delete-restore-drill
```

Emergency disposable cleanup:

```sql
DROP DATABASE IF EXISTS tracs_phase23_test;
```

The drill restores only inside its disposable database, which is dropped in a
`finally` cleanup. It creates no production restore command or endpoint.
