# Infrastructure Pulse Handover

Infrastructure Pulse is currently a frontend prototype driven by `public/assets/infrastructure-pulse-data.js`. The full page, dashboard summary widget, and TV Mode widget all consume the same mock status model through `window.TRACSInfrastructure`.

## Future Backend Shape

- Backend workers should ping datacenter endpoints or monitoring exporters; browsers should not ping real IPs.
- Healthy targets can be checked every 10-15 seconds.
- Warning targets can be checked every 5 seconds.
- Critical targets can be checked every 1 second until recovery is confirmed.
- The frontend should update every second from cached latest metrics rather than querying the database every second.
- Redis or another cache layer should hold the latest status snapshot for fast reads and fanout.
- Historical storage should aggregate data:
  - Raw 1 second samples retained briefly.
  - 1 minute aggregates retained longer.
  - 1 hour aggregates retained long term.

## API Hooks

The frontend is already shaped around these future endpoints:

- `/api/infrastructure/status`
- `/api/infrastructure/metrics`
- `/api/infrastructure/events`
- `/api/infrastructure/stream`

`/api/infrastructure/stream` is intended for SSE/EventSource or WebSocket delivery. A server-sent event payload should match the current snapshot shape: `generatedAt`, `summary`, `nodes`, and `events`.

## TRACS Integration Hooks

- Active incidents can create or link TRACS cases.
- Scheduled maintenance can be represented by reminders.
- High-severity events can be promoted into the ticker.
- TV Mode embeds the compact widget through `renderInfrastructurePulseTVWidget()`.
- The dashboard summary card links to `infrastructure-pulse.php` for the full NOC view.
