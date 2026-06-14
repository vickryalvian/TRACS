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
