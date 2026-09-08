# DOBBY Event Awareness

TRACS emits a small, signed stream of committed operational events for DOBBY World. This is observational only: TRACS remains the source of truth and DOBBY must not block client saves, case actions or deployments.

## Flow

```text
Committed TRACS write or deploy milestone
  -> core/dobby_events.php
  -> tracs_dobby_event_outbox
  -> bin/tracs-dobby-event-worker.php
  -> DOBBY /api/events/ingest
```

The outbox table is created by `config/migrations/2026_09_08_dobby_event_outbox.sql`.

## Event sources

Current emitted events:

- Client Portfolio activity from `modules/client-portfolio/model.php`
- Case creation from `public/api/case-create.php`
- Case resolution from `public/api/case-resolve.php`
- Deployment and rollback stages from `deploy.sh`

Only successful application actions enqueue events. User request handlers enqueue after their normal database write/log path succeeds. The delivery worker is separate and retryable, so DOBBY downtime does not fail the original TRACS operation.

## Delivery configuration

Set these outside Git:

```bash
DOBBY_INGEST_URL=https://dobby.vickry.id/api/events/ingest
DOBBY_INGEST_SECRET=...
```

Then run:

```bash
php bin/tracs-dobby-event-worker.php
```

Production uses `tracs-dobby-event-worker.timer` to run the worker every minute from `/opt/tracs`.

The worker signs `timestamp.body` with HMAC SHA-256 and sends `X-Dobby-Timestamp` and `X-Dobby-Signature`. Failed delivery increments attempts and schedules exponential retry.

## Privacy

Events include short summaries and minimized metadata. They must not include credentials, session cookies, CSRF tokens, raw form payloads, uploaded file content or full logs. Billing events are classified with `privacy: restricted`.
