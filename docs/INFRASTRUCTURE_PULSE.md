# Infrastructure Pulse

Infrastructure Pulse is a mock/API-ready NOC summary page for TRACS. It shows a shift-style infrastructure report, realtime-style server metrics, graph panels, and an incident feed.

## Use The Page

1. Open `public/infrastructure-pulse.php` from TRACS.
2. Review the summary cards at the top for global status, incidents, latency, uptime, Indonesia status, and Singapore status.
3. Use the `Infrastructure Summary Report` panel for handover-style context.
4. Click a server in `Realtime Metrics`, `Needs Attention`, or `Stable Nodes` to focus the report and graphs on that server.
5. Use `TV Mode` when the page needs to be displayed on a NOC screen.

## Add A Server In The UI

1. Click `Manage Servers`.
2. Fill in:
   - `Code`: short unique server code, for example `JKT1`.
   - `Name`: display name.
   - `Region`, `Country`, `Provider`.
   - `Status`: `Healthy`, `Recovery`, `Degraded`, `Critical`, or `Maintenance`.
   - `Latency ms`, `Packet loss %`, and `30D uptime %`.
3. Click `Add Server`.

The server appears immediately in the current dashboard session. This is mock/session data only; it is not saved to the database yet.

## Remove A Server In The UI

1. Click `Manage Servers`.
2. Find the server in `Current Mock Servers`.
3. Click `Remove`.

Removal is also session-only until a backend registry API is connected.

## Change Default Servers

Default mock servers live in:

```text
public/assets/infrastructure-pulse-data.js
```

Edit the `DATACENTERS` array. Recommended minimum fields:

```js
{
  id: 'id-jkt-edge-1',
  code: 'JKT1',
  shortCode: 'JKT1',
  name: 'Jakarta Edge 1',
  provider: 'IDCloudHost',
  facility: 'Jakarta Edge',
  country: 'Indonesia',
  city: 'Jakarta',
  region: 'Jabodetabek',
  status: 'healthy',
  latency: 22,
  uptime: 99.99,
  packetLoss: 0.01,
  incidentCount: 0
}
```

Optional fields are normalized by the mock store, so missing `provider`, `facility`, `city`, `region`, `incidentCount`, or `history` will fall back safely.

## Backend Integration Notes

The page is ready for a future backend to replace mock data through:

- `/api/infrastructure/status`
- `/api/infrastructure/metrics`
- `/api/infrastructure/events`
- `/api/infrastructure/stream`

When the backend is ready, connect the API response to the existing store `ingest()` flow in `public/assets/infrastructure-pulse-data.js`.
