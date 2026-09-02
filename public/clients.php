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

tracs_require_page_permission($conn, 'clients.view');

$uid = (int)($_SESSION['user_id'] ?? 0);
$user_email = $_SESSION['user_email'] ?? 'operator@tracs.local';
$ticker_items = (new AlertTickerController($conn, $uid))->formatAlertsForTicker();
$critical_count = 0;
$page_title = 'Clients';
$active_page = 'clients';

$reactAssets = tracs_react_manifest_assets('clients');
$calendar_styles = $reactAssets['styles'];
$calendar_script = $reactAssets['script'];

include __DIR__ . '/includes/header.php';
?>
<main class="main">
  <div class="main-inner">
    <?php if ($reactAssets['ready']): ?>
      <div id="tracs-clients-root">
        <section class="panel">
          <div class="empty">
            <div class="empty-ic"><i data-lucide="building-2"></i></div>
            <div class="empty-t">Loading Clients</div>
            <div class="empty-s">Checking ownership, billing signals, and operational follow-ups.</div>
          </div>
        </section>
      </div>
    <?php else: ?>
      <section class="panel">
        <div class="empty">
          <div class="empty-ic"><i data-lucide="package-open"></i></div>
          <div class="empty-t">Client Portfolio assets are not built yet</div>
          <div class="empty-s">Run <code>cd frontend &amp;&amp; npm run build:preview</code>, then reload this page.</div>
        </div>
      </section>
    <?php endif; ?>
  </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
