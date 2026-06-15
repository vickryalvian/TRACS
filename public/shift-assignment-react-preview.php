<?php
declare(strict_types=1);

header_remove('X-Powered-By');

require_once __DIR__ . '/../core/security/csrf.php';
tracs_start_session();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth/auth_check.php';
require_once __DIR__ . '/../core/access_control.php';
require_once __DIR__ . '/../modules/alert-ticker/controller.php';
require_once __DIR__ . '/includes/page_helpers.php';
require_once __DIR__ . '/includes/react_manifest.php';

tracs_require_page_permission($conn, 'shifts.view');
tracs_require_super_admin_page($conn);

$uid = (int)($_SESSION['user_id'] ?? 0);
$user_email = $_SESSION['user_email'] ?? 'operator@tracs.local';
$ticker_items = (new AlertTickerController($conn, $uid))->formatAlertsForTicker();
$critical_count = 0;
$page_title = 'Shift Assignment React Preview';
$active_page = 'shift-assignment-react-preview';

$reactAssets = tracs_react_manifest_assets('shiftAssignment');
$calendar_styles = $reactAssets['styles'];
$calendar_script = $reactAssets['script'];

include __DIR__ . '/includes/header.php';
?>
<main class="main">
  <div class="main-inner">
    <section class="panel" style="margin-bottom: var(--space-4);">
      <div class="panel-head">
        <div>
          <span class="panel-title">React Preview Pilot</span>
          <span class="panel-meta">Limited internal controlled create/edit/delete access</span>
        </div>
        <span class="badge b-pending">Super Admin Pilot</span>
      </div>
      <div class="panel-body" style="padding: var(--space-3) var(--space-4);">
        <p style="margin:0;color:var(--tx2);font-size:12px;">
          React Preview Pilot — Create/Edit/Delete actions are enabled only for Super
          Admin validation. Legacy
          <code>shifting-assignment.php</code> remains the production source of
          truth. Delete is a hard-delete pilot with validated audit-backed
          restoration. This pilot provides no template, copy, overtime, or
          holiday write action.
        </p>
      </div>
    </section>

    <?php if ($reactAssets['ready']): ?>
      <div id="tracs-shift-assignment-root">
        <section class="panel">
          <div class="empty">
            <div class="empty-ic"><i data-lucide="calendar-range"></i></div>
            <div class="empty-t">Loading Shift Assignment React preview</div>
            <div class="empty-s">Preparing scoped schedules and warnings.</div>
          </div>
        </section>
      </div>
    <?php else: ?>
      <section class="panel">
        <div class="empty">
          <div class="empty-ic"><i data-lucide="package-open"></i></div>
          <div class="empty-t">React preview assets are not built yet</div>
          <div class="empty-s">
            Run <code>cd frontend &amp;&amp; npm run build:preview</code> before opening this preview.
          </div>
        </div>
      </section>
    <?php endif; ?>
  </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
