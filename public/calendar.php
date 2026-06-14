<?php

require_once __DIR__ . '/../core/security/csrf.php';
tracs_start_session();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth/auth_check.php';
require_once __DIR__ . '/../modules/alert-ticker/controller.php';
require_once __DIR__ . '/includes/page_helpers.php';

$uid = (int)($_SESSION['user_id'] ?? 0);
$user_email = $_SESSION['user_email'] ?? 'operator@tracs.local';
$ticker_items = (new AlertTickerController($conn, $uid))->formatAlertsForTicker();
$critical_count = 0;
$page_title = 'Calendar';
$active_page = 'calendar';

$calendar_styles = [];
$calendar_script = '';
$manifestPath = __DIR__ . '/assets/calendar-dist/.vite/manifest.json';
if (is_file($manifestPath)) {
    $manifest = json_decode((string)file_get_contents($manifestPath), true);
    $entry = $manifest['assets/react/calendar/main.jsx'] ?? null;
    if (is_array($entry) && !empty($entry['file'])) {
        $calendar_script = 'assets/calendar-dist/' . ltrim((string)$entry['file'], '/');
        foreach (($entry['css'] ?? []) as $cssFile) {
            $calendar_styles[] = 'assets/calendar-dist/' . ltrim((string)$cssFile, '/');
        }
    }
}

include __DIR__ . '/includes/header.php';
?>
<main class="main calendar-main">
  <div class="main-inner calendar-page-shell">
    <div id="calendar-react-root">
      <section class="panel cal:p-6">
        <div class="empty">
          <div class="empty-ic"><i data-lucide="calendar-days"></i></div>
          <div class="empty-t">Loading Calendar</div>
          <div class="empty-s"><?=$calendar_script === '' ? 'Calendar assets are not built. Run npm run build:calendar.' : 'Preparing operational schedules.'?></div>
        </div>
      </section>
    </div>
  </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
