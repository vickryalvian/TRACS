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


## Telegram notification persona

Dobby also acts as the Telegram narrator for important TRACS operational events. This reuses the configured Telegram bot credentials; it does not create a second bot. Configure one of these token/chat pairs outside Git:

```bash
DOBBY_TELEGRAM_BOT_TOKEN=...
DOBBY_TELEGRAM_CHAT_ID=...
# or the existing TRACS/Telegram names if production already uses them:
TRACS_TELEGRAM_BOT_TOKEN=...
TRACS_TELEGRAM_CHAT_ID=...
TELEGRAM_BOT_TOKEN=...
TELEGRAM_CHAT_ID=...
```

The shared formatter/transport lives in `core/dobby_notifications.php`. It currently sends:

- Infrastructure Pulse real-server unhealthy transitions (`monitor.down` / `monitor.warning`).
- Infrastructure Pulse recovery transitions (`monitor.recovered`).
- Deployment and rollback milestones from `bin/tracs-dobby-deploy-event.php`.

Monitoring notifications are transition-based: repeated checks with the same unhealthy status do not resend a Telegram message. Telegram failures are logged and never fail the original monitoring pass, manual ping, deployment or rollback.

## Dobby UI sound

The uploaded interaction sound is stored at `public/assets/audio/dobby-interaction.mp3`. `public/assets/tracs.js` exposes `window.DobbySound.play("open")` and `window.DobbySound.play("complete", { eventId })`. The dashboard Dobby element is the current user-gesture activation point, and `dobby:task-completed` is supported for future real Dobby task state events. Failed audio playback is ignored so browser autoplay rules cannot break navigation or task completion.
