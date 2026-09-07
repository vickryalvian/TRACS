<?php
declare(strict_types=1);

require_once __DIR__ . '/../modules/client-portfolio/controller.php';

function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

$db = new mysqli(getenv('TRACS_TEST_DB_HOST') ?: '127.0.0.1', getenv('TRACS_TEST_DB_USER') ?: 'root', getenv('TRACS_TEST_DB_PASS') ?: 'root_secret', '', (int)(getenv('TRACS_TEST_DB_PORT') ?: 3307));
$database = 'tracs_clients_test_' . bin2hex(random_bytes(6));
$db->query("CREATE DATABASE `{$database}`");
try {
    $db->select_db($database);
    $db->query("SET time_zone='+07:00'");
    $db->query('CREATE TABLE tracs_users (id INT PRIMARY KEY, name VARCHAR(150), email VARCHAR(190), is_active TINYINT DEFAULT 1)');
    $db->query("INSERT INTO tracs_users (id,name,email) VALUES (1,'Owner One','one@example.test'),(2,'Owner Two','two@example.test')");
    foreach (['2026_09_01_client_portfolio_mvp.sql', '2026_09_01_client_portfolio_addons_spend.sql'] as $migration) {
        preg_match_all('/CREATE TABLE IF NOT EXISTS.*?;/s', file_get_contents(__DIR__ . '/../config/migrations/' . $migration), $tables);
        foreach ($tables[0] as $table) $db->query($table);
    }
    $db->query('ALTER TABLE tracs_client_services ADD plan_spec TEXT, ADD auto_renew TINYINT DEFAULT 0');
    $model = new ClientPortfolioModel($db);
    $controller = new ClientPortfolioController($db, 1);
    $id = $controller->create(['company_name' => 'Alpha', 'contact_name' => 'Primary'], 'Owner One');
    $model->updateClient($id, ['company_name' => 'Alpha', 'contact_name' => 'Primary Updated'], 1, 'Owner One');
    $model->updateClient($id, ['company_name' => 'Alpha', 'contact_name' => 'Primary Updated'], 1, 'Owner One');
    check(count($model->getClient($id, 1, false)['contacts']) === 1, 'Profile edits duplicated the primary PIC.');
    $pic = $model->saveContact($id, ['name' => 'Billing PIC', 'email' => 'billing@example.test', 'role_title' => 'Billing'], 1, 'Owner One');
    $model->saveContact($id, ['contact_id' => $pic, 'name' => 'Billing PIC', 'is_primary' => true], 1, 'Owner One');
    $contacts = $model->getClient($id, 1, false)['contacts'];
    check(count($contacts) === 2 && (int)$contacts[0]['id'] === $pic, 'Multiple PICs / primary selection failed.');
    check(count(array_filter($contacts, fn($c) => (int)$c['is_primary'] === 1)) === 1, 'Expected exactly one primary PIC.');
    $other = $controller->create(['company_name' => 'Other owner', 'owner_user_id' => 2], 'Owner One');
    check($model->getClient($other, 1, false) === null, 'Owned detail access leaked another owner.');
    try {
        $model->saveContact($other, ['contact_id' => $pic, 'name' => 'Invalid'], 1, 'Owner One');
        throw new RuntimeException('Cross-client PIC edit was accepted.');
    } catch (InvalidArgumentException) {}
    $before = (int)$db->query('SELECT COUNT(*) FROM tracs_clients')->fetch_row()[0];
    try {
        $controller->create(['company_name' => 'Invalid contact', 'contact_name' => 'Invalid', 'contact_email' => 'invalid'], 'Owner One');
        throw new RuntimeException('Invalid email was accepted.');
    } catch (InvalidArgumentException) {}
    check((int)$db->query('SELECT COUNT(*) FROM tracs_clients')->fetch_row()[0] === $before, 'Failed creation left a partial client.');
    $today = new DateTimeImmutable('today', new DateTimeZone('Asia/Jakarta'));
    foreach ([5, 15] as $days) $model->addService($id, ['service_name' => 'Service ' . $days, 'renewal_date' => $today->modify("+{$days} days")->format('Y-m-d'), 'price' => 100], 1, 'Owner One');
    $model->addBilling($id, ['invoice_date' => $today->format('Y-m-d'), 'due_date' => $today->modify('-1 day')->format('Y-m-d'), 'amount' => 500, 'payment_status' => 'overdue'], 1, 'Owner One');
    $model->addBilling($id, ['invoice_date' => $today->format('Y-m-d'), 'due_date' => $today->modify('-1 day')->format('Y-m-d'), 'amount' => 999, 'payment_status' => 'overdue', 'invoice_status' => 'cancelled'], 1, 'Owner One');
    $list = $model->listClients([], 1, false);
    check($list['summary']['renewal_soon'] === 2 && $list['summary']['invoice_this_week'] === 1, 'Overlapping KPI signals or cancelled invoices counted incorrectly.');
    check($list['clients'][0]['outstanding_amount'] === 500.0, 'Cancelled invoice affected outstanding amount.');
    check($model->listClients(['signal' => 'renewal'], 1, false)['total'] === 1, 'Renewal KPI filter failed.');
    check($model->listClients(['signal' => 'invoice'], 1, false)['total'] === 1, 'Invoice KPI filter failed.');
    check($model->listClients(['q' => 'Primary Updated'], 1, false)['total'] === 1, 'Search ignored additional PICs.');
    check($model->listClients(['renewal_from' => $today->modify('+8 days')->format('Y-m-d'), 'renewal_to' => $today->modify('+10 days')->format('Y-m-d')], 1, false)['total'] === 0, 'Date range matched different services.');
    check($model->listClients(['scope' => 'all'], 1, false)['total'] === 1, 'Scope bypassed owner permission.');
    $service = $model->getClient($id, 1, false)['services'][0];
    $model->renewService($id, ['service_id' => $service['id'], 'new_renewal_date' => $today->modify('+6 days')->format('Y-m-d'), 'new_price' => ''], 1, 'Owner One');
    check((float)$model->getClient($id, 1, false)['services'][0]['price'] === 100.0, 'Blank renewal price erased the existing price.');
    for ($i = 0; $i < 30; $i++) $controller->create(['company_name' => sprintf('Client %02d', $i)], 'Owner One');
    $page = $model->listClients(['sort' => 'company_name', 'direction' => 'desc', 'page' => 2], 1, false);
    check($page['total'] === 31 && count($page['clients']) === 6 && $page['page'] === 2, 'Server pagination failed.');
    check(end($page['clients'])['company_name'] === 'Alpha', 'Server sorting failed.');
    $empty = $model->listClients(['q' => 'not found'], 1, false);
    check($empty['total'] === 0 && $empty['available_total'] === 31, 'Filtered empty state metadata failed.');
    echo "Clients database flow passed: ownership, PICs, rollback, KPI overlap, cancelled invoices, filters, sort, pagination.\n";
} finally {
    $db->query("DROP DATABASE `{$database}`");
    $db->close();
}
