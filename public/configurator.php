<?php
require_once __DIR__ . '/../core/security/csrf.php';
tracs_start_session();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth/auth_check.php';
require_once __DIR__ . '/../core/access_control.php';
tracs_require_page_permission($conn, 'dashboard.view');
require_once __DIR__ . '/../modules/infrastructure-configurator/controller.php';
require_once __DIR__ . '/../modules/infrastructure-configurator/templates.php';
require_once __DIR__ . '/includes/page_helpers.php';

$can_manage = tracs_user_can($conn, 'settings.manage');
$action = $_GET['action'] ?? '';
header('Cache-Control: no-store');
if ($action !== '') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        if (!in_array($action, ['catalog', 'calculate', 'save_item', 'save_tax', 'templates', 'save_template'], true)) {
            http_response_code(404);
            throw new InvalidArgumentException('Unknown action.');
        }
        $readOnly = in_array($action, ['catalog', 'templates'], true);
        if ((!$readOnly && $_SERVER['REQUEST_METHOD'] !== 'POST') || ($readOnly && $_SERVER['REQUEST_METHOD'] !== 'GET')) {
            http_response_code(405);
            throw new InvalidArgumentException('Method not allowed.');
        }
        if (in_array($action, ['save_item', 'save_tax'], true) && !$can_manage) {
            http_response_code(403);
            throw new InvalidArgumentException('Master Data access is required.');
        }
        if (!$readOnly) verify_csrf();
        $input = $readOnly ? [] : json_decode(file_get_contents('php://input'), true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($input)) throw new InvalidArgumentException('Invalid request.');
        if (in_array($action, ['templates', 'save_template'], true)) {
            $owner = (int)$_SESSION['user_id'];
            $result = $action === 'templates' ? tracs_configurator_templates($conn, $owner) : tracs_configurator_save_template($conn, $owner, $input);
            echo json_encode(['success' => true, 'data' => $result], JSON_THROW_ON_ERROR);
            exit;
        }
        if ($action === 'save_item') tracs_configurator_save_item($conn, $input);
        if ($action === 'save_tax') {
            $rate = $input['tax_rate'] ?? null;
            $revision = filter_var($input['revision'] ?? null, FILTER_VALIDATE_INT);
            if (!is_numeric($rate) || !is_finite((float)$rate) || $rate < 0 || $rate > 1 || $revision === false) throw new InvalidArgumentException('Enter PPN between 0 and 100%.');
            $stmt = $conn->prepare('UPDATE tracs_configurator_settings SET tax_rate=?, revision=revision+1 WHERE id=1 AND revision=?');
            $stmt->bind_param('di', $rate, $revision);
            $stmt->execute();
            if ($stmt->affected_rows !== 1) throw new InvalidArgumentException('PPN changed in another session. Refresh prices before saving.');
            $stmt->close();
        }
        $catalog = (new InfrastructureConfiguratorController())->getInitialViewModel($conn, $can_manage);
        $result = $action === 'calculate' ? tracs_configurator_calculate($catalog, $input) : $catalog;
        echo json_encode(['success' => true, 'data' => $result], JSON_THROW_ON_ERROR);
    } catch (InvalidArgumentException | JsonException $e) {
        if (http_response_code() < 400) http_response_code(422);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } catch (Throwable $e) {
        error_log('Configurator: ' . $e->getMessage());
        http_response_code(503);
        echo json_encode(['success' => false, 'message' => 'Master Data is unavailable. Please try again later.']);
    }
    exit;
}

require_once __DIR__ . '/../modules/alert-ticker/controller.php';
$uid = (int)($_SESSION['user_id'] ?? 0);
$user_email = $_SESSION['user_email'] ?? 'operator@tracs.local';
$ticker_items = (new AlertTickerController($conn, $uid))->formatAlertsForTicker();
$critical_count = 0;
$page_title = 'Configurator';
$active_page = 'configurator';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/../modules/infrastructure-configurator/view.php';
include __DIR__ . '/includes/footer.php';
