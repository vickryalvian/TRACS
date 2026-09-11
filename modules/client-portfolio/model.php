<?php
declare(strict_types=1);

require_once __DIR__ . '/../../core/creator_tracking.php';
require_once __DIR__ . '/ClientReminderRecords.php';
require_once __DIR__ . '/../../core/user_management.php';
require_once __DIR__ . '/../../core/notifications.php';
require_once __DIR__ . '/../../core/dobby_events.php';

final class ClientPortfolioModel
{
    private DateTimeZone $zone;

    public function __construct(private mysqli $db)
    {
        $this->zone = new DateTimeZone('Asia/Jakarta');
    }

    public function schemaReady(): bool
    {
        foreach (['tracs_clients', 'tracs_client_contacts', 'tracs_client_services', 'tracs_client_service_addons', 'tracs_client_service_renewal_history', 'tracs_client_billing_records', 'tracs_client_followups', 'tracs_client_activity_logs'] as $table) {
            if (!tracs_table_exists($this->db, $table)) return false;
        }
        return true;
    }

    public function users(): array
    {
        $statusFilter = tracs_column_exists($this->db, 'tracs_users', 'status') ? "COALESCE(status,'active') <> 'removed' AND " : '';
        $sql = "SELECT id, COALESCE(NULLIF(name,''), email) AS name, email FROM tracs_users WHERE {$statusFilter}is_active = 1 ORDER BY name ASC, email ASC";
        $res = $this->db->query($sql);
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function listClients(array $filters, int $actorId, bool $canViewAll): array
    {
        if (!$this->schemaReady()) return ['clients' => [], 'summary' => $this->emptySummary(), 'attention' => []];

        $where = [];
        $types = '';
        $params = [];
        $scope = (string)($filters['scope'] ?? 'mine');
        if (!$canViewAll || $scope !== 'all') {
            $where[] = 'c.owner_user_id = ?';
            $types .= 'i';
            $params[] = $actorId;
        } elseif (!empty($filters['owner_user_id'])) {
            $where[] = 'c.owner_user_id = ?';
            $types .= 'i';
            $params[] = (int)$filters['owner_user_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'c.status = ?';
            $types .= 's';
            $params[] = (string)$filters['status'];
        }
        if (!empty($filters['q'])) {
            $where[] = "(c.company_name LIKE ? OR c.client_code LIKE ? OR pc.name LIKE ? OR pc.email LIKE ?)";
            $needle = '%' . (string)$filters['q'] . '%';
            $types .= 'ssss';
            array_push($params, $needle, $needle, $needle, $needle);
        }
        if (!empty($filters['service_type'])) {
            $where[] = "EXISTS (SELECT 1 FROM tracs_client_services fs WHERE fs.client_id = c.id AND fs.service_type = ?)";
            $types .= 's';
            $params[] = (string)$filters['service_type'];
        }
        if (!empty($filters['service_status'])) {
            $where[] = "EXISTS (SELECT 1 FROM tracs_client_services fs WHERE fs.client_id = c.id AND fs.status = ?)";
            $types .= 's';
            $params[] = (string)$filters['service_status'];
        }
        if (!empty($filters['renewal_window'])) {
            $days = $this->renewalWindowDays($filters['renewal_window']);
            if ($days !== null) {
                $where[] = "(EXISTS (
                    SELECT 1 FROM tracs_client_services fs
                    WHERE fs.client_id = c.id
                      AND fs.status IN ('active','monitoring','pending_renewal','suspended')
                      AND fs.renewal_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
                ) OR EXISTS (
                    SELECT 1 FROM tracs_client_services fs
                    INNER JOIN tracs_client_service_addons fa ON fa.service_id = fs.id
                    WHERE fs.client_id = c.id
                      AND fa.status IN ('active','monitoring','pending_renewal','suspended')
                      AND fa.renewal_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
                ))";
                $types .= 'ii';
                array_push($params, $days, $days);
            }
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "
            SELECT c.*,
                   COALESCE(NULLIF(u.name,''), u.email, 'Unassigned') AS owner_name,
                   pc.name AS primary_contact_name,
                   pc.email AS primary_contact_email,
                   pc.phone AS primary_contact_phone,
                   COALESCE(svc.service_count, 0) AS service_count,
                   COALESCE(addons.addon_count, 0) AS addon_count,
                   svc.service_types,
                   CASE
                     WHEN svc.nearest_renewal_date IS NULL THEN addons.nearest_addon_renewal_date
                     WHEN addons.nearest_addon_renewal_date IS NULL THEN svc.nearest_renewal_date
                     WHEN svc.nearest_renewal_date <= addons.nearest_addon_renewal_date THEN svc.nearest_renewal_date
                     ELSE addons.nearest_addon_renewal_date
                   END AS nearest_renewal_date,
                   (COALESCE(svc.service_mrr_amount, 0) + COALESCE(addons.addon_mrr_amount, 0)) AS mrr_amount,
                   bill.nearest_due_date,
                   COALESCE(bill.invoice_this_week_count, 0) AS invoice_this_week_count,
                   COALESCE(bill.waiting_payment_count, 0) AS waiting_payment_count,
                   COALESCE(bill.tax_pending_count, 0) AS tax_pending_count,
                   COALESCE(bill.lifetime_billed_amount, 0) AS lifetime_billed_amount,
                   COALESCE(bill.total_paid_amount, 0) AS total_paid_amount,
                   COALESCE(bill.outstanding_amount, 0) AS outstanding_amount
            FROM tracs_clients c
            LEFT JOIN tracs_users u ON u.id = c.owner_user_id
            LEFT JOIN tracs_client_contacts pc ON pc.client_id = c.id AND pc.is_primary = 1
            LEFT JOIN (
                SELECT s.client_id,
                       COUNT(CASE WHEN s.status NOT IN ('inactive','terminated') THEN s.id END) AS service_count,
                       GROUP_CONCAT(DISTINCT s.service_type ORDER BY s.service_type SEPARATOR ', ') AS service_types,
                       MIN(CASE WHEN s.status NOT IN ('inactive','terminated') THEN s.renewal_date END) AS nearest_renewal_date,
                       SUM(CASE WHEN s.status NOT IN ('inactive','terminated') THEN
                            CASE s.billing_cycle
                              WHEN 'monthly' THEN COALESCE(s.price, 0)
                              WHEN 'quarterly' THEN COALESCE(s.price, 0) / 3
                              WHEN 'semiannual' THEN COALESCE(s.price, 0) / 6
                              WHEN 'annual' THEN COALESCE(s.price, 0) / 12
                              ELSE 0
                            END
                         ELSE 0 END)
                         AS service_mrr_amount
                FROM tracs_client_services s
                GROUP BY s.client_id
            ) svc ON svc.client_id = c.id
            LEFT JOIN (
                SELECT s.client_id,
                       COUNT(CASE WHEN a.status NOT IN ('inactive','terminated') THEN a.id END) AS addon_count,
                       MIN(CASE WHEN a.status NOT IN ('inactive','terminated') THEN a.renewal_date END) AS nearest_addon_renewal_date,
                       SUM(CASE WHEN a.status NOT IN ('inactive','terminated') THEN
                            CASE a.billing_cycle
                              WHEN 'monthly' THEN COALESCE(a.price, 0)
                              WHEN 'quarterly' THEN COALESCE(a.price, 0) / 3
                              WHEN 'semiannual' THEN COALESCE(a.price, 0) / 6
                              WHEN 'annual' THEN COALESCE(a.price, 0) / 12
                              ELSE 0
                            END
                         ELSE 0 END) AS addon_mrr_amount
                FROM tracs_client_services s
                INNER JOIN tracs_client_service_addons a ON a.service_id = s.id
                GROUP BY s.client_id
            ) addons ON addons.client_id = c.id
            LEFT JOIN (
                SELECT client_id,
                       MIN(CASE WHEN payment_status <> 'paid' AND due_date IS NOT NULL THEN due_date END) AS nearest_due_date,
                       SUM(CASE WHEN invoice_status = 'upcoming' AND invoice_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS invoice_this_week_count,
                       SUM(CASE WHEN payment_status IN ('waiting','overdue') THEN 1 ELSE 0 END) AS waiting_payment_count,
                       SUM(CASE WHEN tax_invoice_required = 1 AND payment_status = 'paid' AND tax_invoice_sent_at IS NULL THEN 1 ELSE 0 END) AS tax_pending_count,
                       SUM(CASE WHEN invoice_status <> 'cancelled' THEN COALESCE(amount, 0) ELSE 0 END) AS lifetime_billed_amount,
                       SUM(CASE WHEN invoice_status <> 'cancelled' AND payment_status = 'paid' THEN COALESCE(amount, 0) ELSE 0 END) AS total_paid_amount,
                       SUM(CASE WHEN invoice_status <> 'cancelled' AND payment_status IN ('waiting','overdue') THEN COALESCE(amount, 0) ELSE 0 END) AS outstanding_amount
                FROM tracs_client_billing_records
                GROUP BY client_id
            ) bill ON bill.client_id = c.id
            {$whereSql}
            ORDER BY c.updated_at DESC, c.company_name ASC
        ";
        $rows = $this->preparedRows($sql, $types, $params);
        $clients = array_map(fn(array $row): array => $this->decorateClient($row), $rows);
        if ($clients) {
            $ids = array_map(fn(array $c): int => (int)$c['id'], $clients);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $followupSql = ClientReminderRecords::query();
            $pending = $this->preparedRows("SELECT * FROM ({$followupSql}) f WHERE client_id IN ({$placeholders}) AND status='open' AND due_at IS NOT NULL ORDER BY due_at,id", str_repeat('i', count($ids)), $ids);
            $next = [];
            foreach ($pending as $reminder) $next[(int)$reminder['client_id']] ??= $reminder;
            foreach ($clients as &$client) {
                $reminder = $next[(int)$client['id']] ?? null;
                $client['next_reminder'] = $reminder;
                if (!$reminder) continue;
                $client['next_action'] = $reminder['title'];
                $client['next_action_due_at'] = $reminder['due_at'];
                $client['days_until_action'] = (int)(new DateTimeImmutable('today', $this->zone))->diff(new DateTimeImmutable($reminder['due_at'], $this->zone))->format('%r%a');
                $today = new DateTimeImmutable('today', $this->zone);
                $due = new DateTimeImmutable($reminder['due_at'], $this->zone);
                if ($due < $today->modify('+8 days') && $client['attention_level'] === 'normal') {
                    $client['attention_level'] = $due < $today ? 'critical' : 'due';
                    $client['attention_reason'] = 'Client reminder due';
                    $client['attention_rank'] = $due < $today ? 1 : 3;
                }
            }
            unset($client);
        }

        if (!empty($filters['billing_status'])) {
            $clients = array_values(array_filter($clients, fn(array $c): bool => (string)$c['billing_status'] === (string)$filters['billing_status']));
        }
        if (!empty($filters['attention'])) {
            $attention = (string)$filters['attention'];
            $clients = array_values(array_filter($clients, fn(array $c): bool => $attention === 'attention' ? $c['attention_level'] !== 'normal' : $c['attention_level'] === $attention));
        }

        usort($clients, fn(array $a, array $b): int => [$a['attention_rank'], $a['days_until_action'] ?? 9999, $a['company_name']] <=> [$b['attention_rank'], $b['days_until_action'] ?? 9999, $b['company_name']]);
        return [
            'clients' => $clients,
            'summary' => $this->summary($clients),
            'attention' => array_slice(array_values(array_filter($clients, fn(array $c): bool => $c['attention_level'] !== 'normal')), 0, 8),
        ];
    }

    public function getClient(int $id, int $actorId, bool $canViewAll): ?array
    {
        $client = $this->one("SELECT c.*, COALESCE(NULLIF(u.name,''), u.email, 'Unassigned') AS owner_name FROM tracs_clients c LEFT JOIN tracs_users u ON u.id=c.owner_user_id WHERE c.id=? LIMIT 1", 'i', [$id]);
        if (!$client || (!$canViewAll && (int)$client['owner_user_id'] !== $actorId)) return null;
        $client['contacts'] = $this->preparedRows("SELECT * FROM tracs_client_contacts WHERE client_id=? ORDER BY is_primary DESC, name ASC", 'i', [$id]);
        $client['services'] = $this->preparedRows("SELECT * FROM tracs_client_services WHERE client_id=? ORDER BY status ASC, renewal_date IS NULL, renewal_date ASC, service_name ASC", 'i', [$id]);
        $serviceIds = array_map(fn(array $service): int => (int)$service['id'], $client['services']);
        $addons = $this->addonsForServices($serviceIds);
        foreach ($client['services'] as &$service) {
            $service['addons'] = $addons[(int)$service['id']] ?? [];
        }
        unset($service);
        $client['billing'] = $this->preparedRows("SELECT b.*, s.service_name FROM tracs_client_billing_records b LEFT JOIN tracs_client_services s ON s.id=b.service_id WHERE b.client_id=? ORDER BY COALESCE(b.due_date,b.invoice_date,b.created_at) DESC, b.id DESC", 'i', [$id]);
        $followupSql = ClientReminderRecords::query();
        $client['followups'] = $this->preparedRows("SELECT f.*, COALESCE(NULLIF(u.name,''), u.email, 'Unassigned') AS assignee_name FROM ({$followupSql}) f LEFT JOIN tracs_users u ON u.id=f.assigned_to WHERE f.client_id=? ORDER BY f.status ASC, f.due_at IS NULL, f.due_at ASC", 'i', [$id]);
        $client['activity'] = $this->preparedRows("SELECT * FROM tracs_client_activity_logs WHERE client_id=? ORDER BY created_at DESC LIMIT 80", 'i', [$id]);
        $client['renewal_history'] = $this->preparedRows("SELECT h.*, s.service_name FROM tracs_client_service_renewal_history h LEFT JOIN tracs_client_services s ON s.id=h.service_id WHERE h.client_id=? ORDER BY h.created_at DESC LIMIT 80", 'i', [$id]);
        return $this->decorateClient($this->withDetailSignals($client));
    }

    public function createClient(array $input, int $actorId, string $actorName): int
    {
        $name = $this->text($input['company_name'] ?? '', 190);
        if ($name === '') throw new InvalidArgumentException('Company name is required.');
        $owner = max(1, (int)($input['owner_user_id'] ?? $actorId));
        $code = $this->nullableText($input['client_code'] ?? null, 40);
        $status = $this->enum($input['status'] ?? 'active', ['active','monitoring','inactive'], 'active');
        $notes = $this->nullableText($input['notes'] ?? null, 2000);
        $stmt = $this->db->prepare("INSERT INTO tracs_clients (client_code, company_name, owner_user_id, status, notes, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
        if (!$stmt) throw new RuntimeException('Unable to create client.');
        $stmt->bind_param('ssissi', $code, $name, $owner, $status, $notes, $actorId);
        if (!$stmt->execute()) throw new RuntimeException('Unable to create client.');
        $id = (int)$stmt->insert_id;
        $stmt->close();
        $this->savePrimaryContact($id, $input);
        $this->log($id, $actorId, $actorName, 'client.created', "Client created: {$name}");
        return $id;
    }

    public function updateClient(int $id, array $input, int $actorId, string $actorName): void
    {
        $current = $this->one("SELECT * FROM tracs_clients WHERE id=? LIMIT 1", 'i', [$id]);
        if (!$current) throw new RuntimeException('Client not found.');
        $name = $this->text($input['company_name'] ?? $current['company_name'], 190);
        if ($name === '') throw new InvalidArgumentException('Company name is required.');
        $owner = max(1, (int)($input['owner_user_id'] ?? $current['owner_user_id']));
        $code = $this->nullableText($input['client_code'] ?? $current['client_code'], 40);
        $status = $this->enum($input['status'] ?? $current['status'], ['active','monitoring','inactive'], 'active');
        $notes = $this->nullableText($input['notes'] ?? $current['notes'], 2000);
        $stmt = $this->db->prepare("UPDATE tracs_clients SET client_code=?, company_name=?, owner_user_id=?, status=?, notes=?, updated_at=NOW() WHERE id=?");
        if (!$stmt) throw new RuntimeException('Unable to update client.');
        $stmt->bind_param('ssissi', $code, $name, $owner, $status, $notes, $id);
        if (!$stmt->execute()) throw new RuntimeException('Unable to update client.');
        $stmt->close();
        $this->savePrimaryContact($id, $input);
        $event = (int)$current['owner_user_id'] !== $owner ? 'client.owner_changed' : 'client.updated';
        $this->log($id, $actorId, $actorName, $event, $event === 'client.owner_changed' ? "Client owner changed: {$name}" : "Client updated: {$name}");
    }

    public function addService(int $clientId, array $input, int $actorId, string $actorName): int
    {
        $name = $this->text($input['service_name'] ?? '', 190);
        if ($name === '') throw new InvalidArgumentException('Service name is required.');
        $type = $this->nullableText($input['service_type'] ?? null, 80);
        $ref = $this->nullableText($input['service_reference'] ?? null, 120);
        $spec = $this->nullableText($input['plan_spec'] ?? null, 4000);
        $cycle = $this->enum($input['billing_cycle'] ?? 'monthly', ['monthly','quarterly','semiannual','annual','one_time','custom'], 'monthly');
        $price = $this->nullableMoney($input['price'] ?? null);
        $start = $this->nullableDate($input['start_date'] ?? null);
        $billingDay = $this->nullableBillingDay($input['billing_day'] ?? null);
        $renewal = $this->nullableDate($input['renewal_date'] ?? null);
        $autoRenew = !empty($input['auto_renew']) ? 1 : 0;
        $status = $this->serviceStatus($input['status'] ?? 'active');
        $stmt = $this->db->prepare("INSERT INTO tracs_client_services (client_id, service_name, service_type, service_reference, plan_spec, billing_cycle, price, start_date, billing_day, renewal_date, auto_renew, status, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
        if (!$stmt) throw new RuntimeException('Unable to add service.');
        $stmt->bind_param('isssssdsisisi', $clientId, $name, $type, $ref, $spec, $cycle, $price, $start, $billingDay, $renewal, $autoRenew, $status, $actorId);
        if (!$stmt->execute()) throw new RuntimeException('Unable to add service.');
        $id = (int)$stmt->insert_id;
        $stmt->close();
        $this->log($clientId, $actorId, $actorName, 'client.service_added', "Service added: {$name}");
        if ($renewal) $this->addFollowup($clientId, ['title' => "Renewal follow-up: {$name}", 'action_type' => 'renewal', 'due_at' => $renewal.' 09:00:00', 'service_id' => $id], $actorId, $actorName);
        return $id;
    }

    public function addAddon(int $clientId, array $input, int $actorId, string $actorName): int
    {
        $serviceId = !empty($input['service_id']) ? (int)$input['service_id'] : 0;
        $service = $this->serviceForClient($clientId, $serviceId);
        if (!$service) throw new InvalidArgumentException('Choose a valid service for this addon.');
        $name = $this->text($input['addon_name'] ?? $input['name'] ?? '', 190);
        if ($name === '') throw new InvalidArgumentException('Addon name is required.');
        $price = $this->nullableMoney($input['price'] ?? null);
        $cycle = $this->enum($input['billing_cycle'] ?? 'included', ['included','monthly','quarterly','semiannual','annual','one_time','custom'], 'included');
        $renewal = $this->nullableDate($input['renewal_date'] ?? null);
        $status = $this->serviceStatus($input['status'] ?? 'active');
        $notes = $this->nullableText($input['notes'] ?? null, 2000);
        $stmt = $this->db->prepare("INSERT INTO tracs_client_service_addons (service_id, name, price, billing_cycle, renewal_date, status, notes, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
        if (!$stmt) throw new RuntimeException('Unable to add addon.');
        $stmt->bind_param('isdssssi', $serviceId, $name, $price, $cycle, $renewal, $status, $notes, $actorId);
        if (!$stmt->execute()) throw new RuntimeException('Unable to add addon.');
        $id = (int)$stmt->insert_id;
        $stmt->close();
        $this->log($clientId, $actorId, $actorName, 'client.addon_added', "Addon added to {$service['service_name']}: {$name}");
        return $id;
    }

    public function renewService(int $clientId, array $input, int $actorId, string $actorName): int
    {
        $serviceId = !empty($input['service_id']) ? (int)$input['service_id'] : 0;
        $service = $this->serviceForClient($clientId, $serviceId);
        if (!$service) throw new InvalidArgumentException('Choose a valid service to renew.');
        $newDate = $this->nullableDate($input['new_renewal_date'] ?? $input['renewal_date'] ?? null);
        if ($newDate === null) throw new InvalidArgumentException('New renewal date is required.');
        $newPrice = array_key_exists('new_price', $input) || array_key_exists('price', $input) ? $this->nullableMoney($input['new_price'] ?? $input['price'] ?? null) : ($service['price'] !== null ? (float)$service['price'] : null);
        $note = $this->nullableText($input['note'] ?? null, 2000);
        $stmt = $this->db->prepare("UPDATE tracs_client_services SET renewal_date=?, price=?, status='active', updated_at=NOW() WHERE id=? AND client_id=?");
        if (!$stmt) throw new RuntimeException('Unable to renew service.');
        $stmt->bind_param('sdii', $newDate, $newPrice, $serviceId, $clientId);
        if (!$stmt->execute()) throw new RuntimeException('Unable to renew service.');
        $stmt->close();

        $history = $this->db->prepare("INSERT INTO tracs_client_service_renewal_history (service_id, client_id, old_renewal_date, new_renewal_date, old_price, new_price, note, created_by, created_by_name, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        if ($history) {
            $oldDate = $service['renewal_date'] ?? null;
            $oldPrice = $service['price'] !== null ? (float)$service['price'] : null;
            $history->bind_param('iissddsis', $serviceId, $clientId, $oldDate, $newDate, $oldPrice, $newPrice, $note, $actorId, $actorName);
            $history->execute();
            $history->close();
        }
        $this->log($clientId, $actorId, $actorName, 'client.service_renewed', "Service renewed: {$service['service_name']}");
        $followupSql = ClientReminderRecords::query();
        $pending = $this->one("SELECT id FROM ({$followupSql}) f WHERE client_id=? AND service_id=? AND action_type='renewal' AND status='open' ORDER BY due_at,id LIMIT 1", 'ii', [$clientId, $serviceId]);
        if ($pending) {
            $this->updateFollowup((int)$pending['id'], ['due_at' => $newDate.' 09:00:00'], $actorId, $actorName);
        } else {
            $this->addFollowup($clientId, ['title' => 'Renewal follow-up: '.$service['service_name'], 'action_type' => 'renewal', 'due_at' => $newDate.' 09:00:00', 'service_id' => $serviceId], $actorId, $actorName);
        }
        return $serviceId;
    }

    public function addBilling(int $clientId, array $input, int $actorId, string $actorName): int
    {
        $serviceId = !empty($input['service_id']) ? (int)$input['service_id'] : null;
        if ($serviceId && !$this->serviceForClient($clientId, $serviceId)) throw new InvalidArgumentException('Service does not belong to this client.');
        $amount = $this->nullableMoney($input['amount'] ?? null);
        $invoiceStatus = $this->enum($input['invoice_status'] ?? 'upcoming', ['upcoming','sent','cancelled'], 'upcoming');
        $paymentStatus = $this->enum($input['payment_status'] ?? 'waiting', ['waiting','paid','overdue'], 'waiting');
        $taxRequired = !empty($input['tax_invoice_required']) ? 1 : 0;
        $stmt = $this->db->prepare("INSERT INTO tracs_client_billing_records (client_id, service_id, period_start, period_end, invoice_number, invoice_date, due_date, amount, invoice_status, payment_status, payment_date, tax_invoice_required, tax_invoice_number, tax_invoice_sent_at, notes, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
        if (!$stmt) throw new RuntimeException('Unable to add billing record.');
        $periodStart = $this->nullableDate($input['period_start'] ?? null);
        $periodEnd = $this->nullableDate($input['period_end'] ?? null);
        $invoiceNumber = $this->nullableText($input['invoice_number'] ?? null, 120);
        $invoiceDate = $this->nullableDate($input['invoice_date'] ?? null);
        $dueDate = $this->nullableDate($input['due_date'] ?? null);
        $paymentDate = $this->nullableDate($input['payment_date'] ?? null);
        $taxNumber = $this->nullableText($input['tax_invoice_number'] ?? null, 120);
        $taxSent = !empty($input['tax_invoice_sent_at']) ? $this->dateTime($input['tax_invoice_sent_at']) : null;
        $notes = $this->nullableText($input['notes'] ?? null, 2000);
        $stmt->bind_param('iisssssdsssisssi', $clientId, $serviceId, $periodStart, $periodEnd, $invoiceNumber, $invoiceDate, $dueDate, $amount, $invoiceStatus, $paymentStatus, $paymentDate, $taxRequired, $taxNumber, $taxSent, $notes, $actorId);
        if (!$stmt->execute()) throw new RuntimeException('Unable to add billing record.');
        $id = (int)$stmt->insert_id;
        $stmt->close();
        $event = $paymentStatus === 'paid' ? 'client.payment_marked_paid' : ($taxSent ? 'client.tax_invoice_sent' : ($invoiceStatus === 'sent' ? 'client.invoice_sent' : 'client.billing_added'));
        $this->log($clientId, $actorId, $actorName, $event, 'Billing record saved.');
        if ($invoiceDate && $invoiceStatus === 'upcoming') $this->addFollowup($clientId, ['title' => 'Send invoice'.($invoiceNumber ? ': '.$invoiceNumber : ''), 'action_type' => 'send_invoice', 'due_at' => $invoiceDate.' 09:00:00', 'billing_record_id' => $id, 'service_id' => $serviceId], $actorId, $actorName);
        if ($taxRequired && !$taxSent && !empty($input['tax_invoice_due_date'])) {
            $taxDue = $this->nullableDate($input['tax_invoice_due_date']);
            $this->addFollowup($clientId, ['title' => 'Send tax invoice', 'action_type' => 'send_tax_invoice', 'due_at' => $taxDue.' 09:00:00', 'billing_record_id' => $id, 'service_id' => $serviceId], $actorId, $actorName);
        }
        return $id;
    }

    public function addFollowup(int $clientId, array $input, int $actorId, string $actorName): int
    {
        $title = $this->text($input['title'] ?? '', 220);
        if ($title === '') throw new InvalidArgumentException('Follow-up title is required.');
        if (empty($input['due_at'])) throw new InvalidArgumentException('Reminder date is required.');
        $dueAt = $this->dateTime($input['due_at']);
        $assignedTo = !empty($input['assigned_to']) ? (int)$input['assigned_to'] : $actorId;
        if ($assignedTo !== $actorId && !tracs_user_can($this->db, 'clients.view_all', $actorId)) throw new InvalidArgumentException('Choose yourself as assignee.');
        if (!$this->one('SELECT id FROM tracs_users WHERE id=? AND is_active=1', 'i', [$assignedTo])) throw new InvalidArgumentException('Choose an active assignee.');
        $type = $this->text($input['action_type'] ?? 'general_followup', 80);
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $type)) throw new InvalidArgumentException('Invalid activity type.');
        $priority = $this->enum($input['priority'] ?? 'medium', ['low','medium','high','critical'], 'medium');
        $serviceId = !empty($input['service_id']) ? (int)$input['service_id'] : null;
        $billingId = !empty($input['billing_record_id']) ? (int)$input['billing_record_id'] : null;
        if ($serviceId && !$this->serviceForClient($clientId, $serviceId)) throw new InvalidArgumentException('Service does not belong to this client.');
        if ($billingId && !$this->one('SELECT id FROM tracs_client_billing_records WHERE id=? AND client_id=?', 'ii', [$billingId, $clientId])) throw new InvalidArgumentException('Billing record does not belong to this client.');
        $desc = $this->text($input['description'] ?? '', 4000);
        $stmt = $this->db->prepare("INSERT INTO tracs_reminders (user_id,title,description,due_date,priority,is_completed,created_by,created_by_name,created_at,updated_at) VALUES (?,?,?,?,?,0,?,?,NOW(),NOW())");
        $stmt->bind_param('issssis', $assignedTo, $title, $desc, $dueAt, $priority, $actorId, $actorName);
        if (!$stmt->execute()) throw new RuntimeException('Unable to create reminder.');
        $reminderId = (int)$stmt->insert_id;
        $stmt->close();
        $stmt = $this->db->prepare("INSERT INTO tracs_client_followups (client_id,service_id,billing_record_id,reminder_id,action_type,title,due_at,priority,status,assigned_to,created_by,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,'open',?,?,NOW(),NOW())");
        $stmt->bind_param('iiiissssii', $clientId, $serviceId, $billingId, $reminderId, $type, $title, $dueAt, $priority, $assignedTo, $actorId);
        if (!$stmt->execute()) throw new RuntimeException('Unable to link client reminder.');
        $id = (int)$stmt->insert_id;
        $stmt->close();
        $this->log($clientId, $actorId, $actorName, 'client.followup_created', "Follow-up created: {$title}");
        tracs_notify_reminder_created($this->db, $reminderId, $assignedTo, $title, $dueAt, $actorId);
        return $id;
    }

    public function followupClientId(int $id): int
    {
        return (int)($this->one('SELECT client_id FROM tracs_client_followups WHERE id=?', 'i', [$id])['client_id'] ?? 0);
    }

    public function updateFollowup(int $id, array $input, int $actorId, string $actorName): int
    {
        $sql = ClientReminderRecords::query();
        $row = $this->one("SELECT * FROM ({$sql}) f WHERE id=?", 'i', [$id]);
        if (!$row) throw new InvalidArgumentException('Follow-up not found.');
        $title = $this->text($input['title'] ?? $row['title'], 220);
        $due = $input['due_at'] ?? $row['due_at'];
        if ($title === '' || !$due) throw new InvalidArgumentException('Title and reminder date are required.');
        $due = $this->dateTime($due);
        $status = $input['status'] ?? $row['status'];
        if (!in_array($status, ['open','completed'], true)) throw new InvalidArgumentException('Choose Pending or Done.');
        $done = $status === 'completed' ? 1 : 0;
        $description = $this->text($input['description'] ?? $row['description'] ?? '', 4000);
        $rid = (int)$row['reminder_id'];
        if (!$rid) {
            $assigned = (int)($row['assigned_to'] ?: $actorId);
            $priority = $row['priority'];
            $stmt = $this->db->prepare("INSERT INTO tracs_reminders (user_id,title,description,due_date,priority,is_completed,created_by,created_by_name,created_at,updated_at) VALUES (?,?,?,?,?,0,?,?,NOW(),NOW())");
            $stmt->bind_param('issssis', $assigned, $title, $description, $due, $priority, $actorId, $actorName);
            $stmt->execute();
            $rid = (int)$stmt->insert_id;
            $stmt->close();
            $stmt = $this->db->prepare('UPDATE tracs_client_followups SET reminder_id=? WHERE id=?');
            $stmt->bind_param('ii', $rid, $id);
            $stmt->execute();
            $stmt->close();
        }
        $stmt = $this->db->prepare("UPDATE tracs_reminders SET title=?,description=?,due_date=?,is_completed=?,completed_at=IF(?=1,NOW(),NULL),completed_by=IF(?=1,?,NULL),archived_at=NULL,updated_at=NOW() WHERE id=?");
        $stmt->bind_param('sssiiiii', $title, $description, $due, $done, $done, $done, $actorId, $rid);
        if (!$stmt->execute()) throw new RuntimeException('Unable to update reminder.');
        $stmt->close();
        $this->log((int)$row['client_id'], $actorId, $actorName, 'client.followup_updated', "Reminder updated: {$title}");
        return (int)$row['client_id'];
    }

    public function completeFollowup(int $followupId, int $actorId, string $actorName): int
    {
        return $this->updateFollowup($followupId, ['status' => 'completed'], $actorId, $actorName);
    }

    private function decorateClient(array $row): array
    {
        $attention = $this->attention($row);
        $row['service_count'] = (int)($row['service_count'] ?? 0);
        $row['addon_count'] = (int)($row['addon_count'] ?? 0);
        $row['invoice_this_week_count'] = (int)($row['invoice_this_week_count'] ?? 0);
        $row['waiting_payment_count'] = (int)($row['waiting_payment_count'] ?? 0);
        $row['tax_pending_count'] = (int)($row['tax_pending_count'] ?? 0);
        $row['mrr_amount'] = (float)($row['mrr_amount'] ?? 0);
        $row['lifetime_billed_amount'] = (float)($row['lifetime_billed_amount'] ?? 0);
        $row['total_paid_amount'] = (float)($row['total_paid_amount'] ?? 0);
        $row['outstanding_amount'] = (float)($row['outstanding_amount'] ?? 0);
        return array_merge($row, $attention);
    }

    private function withDetailSignals(array $client): array
    {
        $today = new DateTimeImmutable('today', $this->zone);
        $weekEnd = $today->modify('+7 days');
        $client['service_count'] = count(array_filter($client['services'] ?? [], fn(array $service): bool => !in_array((string)($service['status'] ?? ''), ['inactive','terminated'], true)));
        $client['addon_count'] = 0;
        $client['mrr_amount'] = 0.0;
        $renewals = array_filter(array_map(fn(array $service): ?string => !in_array((string)($service['status'] ?? ''), ['inactive','terminated'], true) ? ($service['renewal_date'] ?? null) : null, $client['services'] ?? []));
        foreach (($client['services'] ?? []) as $service) {
            if (!in_array((string)($service['status'] ?? ''), ['inactive','terminated'], true)) {
                $client['mrr_amount'] += $this->monthlyEquivalent($service['price'] ?? null, (string)($service['billing_cycle'] ?? 'custom'));
            }
            foreach (($service['addons'] ?? []) as $addon) {
                if (in_array((string)($addon['status'] ?? ''), ['inactive','terminated'], true)) continue;
                $client['addon_count']++;
                $client['mrr_amount'] += $this->monthlyEquivalent($addon['price'] ?? null, (string)($addon['billing_cycle'] ?? 'included'));
                if (!empty($addon['renewal_date'])) $renewals[] = (string)$addon['renewal_date'];
            }
        }
        sort($renewals);
        $client['nearest_renewal_date'] = $renewals[0] ?? null;

        $dueDates = [];
        $client['invoice_this_week_count'] = 0;
        $client['waiting_payment_count'] = 0;
        $client['tax_pending_count'] = 0;
        $client['lifetime_billed_amount'] = 0.0;
        $client['total_paid_amount'] = 0.0;
        $client['outstanding_amount'] = 0.0;
        foreach (($client['billing'] ?? []) as $record) {
            if ((string)($record['invoice_status'] ?? '') === 'cancelled') continue;
            $amount = (float)($record['amount'] ?? 0);
            $client['lifetime_billed_amount'] += $amount;
            $invoiceDate = $this->dateOrNull($record['invoice_date'] ?? null);
            if ((string)($record['invoice_status'] ?? '') === 'upcoming' && $invoiceDate && $invoiceDate >= $today && $invoiceDate <= $weekEnd) {
                $client['invoice_this_week_count']++;
            }
            if (in_array((string)($record['payment_status'] ?? ''), ['waiting', 'overdue'], true)) {
                $client['waiting_payment_count']++;
                $client['outstanding_amount'] += $amount;
                if (!empty($record['due_date'])) $dueDates[] = (string)$record['due_date'];
            }
            if ((string)($record['payment_status'] ?? '') === 'paid') $client['total_paid_amount'] += $amount;
            if ((int)($record['tax_invoice_required'] ?? 0) === 1 && (string)($record['payment_status'] ?? '') === 'paid' && empty($record['tax_invoice_sent_at'])) {
                $client['tax_pending_count']++;
            }
        }
        sort($dueDates);
        $client['nearest_due_date'] = $dueDates[0] ?? null;
        return $client;
    }

    private function attention(array $row): array
    {
        $today = new DateTimeImmutable('today', $this->zone);
        $due = $this->dateOrNull($row['nearest_due_date'] ?? null);
        $renewal = $this->dateOrNull($row['nearest_renewal_date'] ?? null);
        if ((int)($row['waiting_payment_count'] ?? 0) > 0 && $due && $due < $today) {
            return $this->attentionState('critical', 1, 'Invoice overdue', 'Check payment', $due, $today);
        }
        if ((int)($row['tax_pending_count'] ?? 0) > 0) {
            return $this->attentionState('warning', 3, 'Tax invoice pending', 'Send tax invoice', null, $today);
        }
        if ($renewal) {
            $days = (int)$today->diff($renewal)->format('%r%a');
            if ($days >= 0 && $days <= 30) return $this->attentionState('warning', 4, 'Renewal within 30 days', 'Review renewal', $renewal, $today);
        }
        if ((int)($row['invoice_this_week_count'] ?? 0) > 0) {
            return $this->attentionState('due', 5, 'Invoice this week', 'Prepare invoice', $due, $today);
        }
        if ((int)($row['waiting_payment_count'] ?? 0) > 0) {
            return $this->attentionState('watch', 6, 'Waiting payment', 'Monitor payment', $due, $today);
        }
        return ['attention_level' => 'normal', 'attention_rank' => 9, 'attention_reason' => 'No immediate action', 'next_action' => 'Monitor', 'next_action_due_at' => null, 'days_until_action' => null, 'billing_status' => 'normal'];
    }

    private function attentionState(string $level, int $rank, string $reason, string $action, ?DateTimeImmutable $due, DateTimeImmutable $today): array
    {
        return [
            'attention_level' => $level,
            'attention_rank' => $rank,
            'attention_reason' => $reason,
            'next_action' => $action,
            'next_action_due_at' => $due?->format('Y-m-d'),
            'days_until_action' => $due ? (int)$today->diff($due)->format('%r%a') : null,
            'billing_status' => $level === 'critical' ? 'overdue' : ($level === 'watch' ? 'waiting' : 'normal'),
        ];
    }

    private function summary(array $clients): array
    {
        $summary = $this->emptySummary();
        $summary['total'] = count($clients);
        foreach ($clients as $client) {
            if ($client['attention_level'] !== 'normal') $summary['action_required']++;
            $summary['invoice_this_week'] += (int)$client['invoice_this_week_count'];
            $summary['waiting_payment'] += (int)$client['waiting_payment_count'];
            $summary['tax_invoice_pending'] += (int)$client['tax_pending_count'];
            if ($client['attention_reason'] === 'Renewal within 30 days') $summary['renewal_soon']++;
            $summary['total_paid_amount'] += (float)($client['total_paid_amount'] ?? 0);
            $summary['outstanding_amount'] += (float)($client['outstanding_amount'] ?? 0);
            $summary['mrr_amount'] += (float)($client['mrr_amount'] ?? 0);
        }
        return $summary;
    }

    private function emptySummary(): array
    {
        return ['total' => 0, 'action_required' => 0, 'invoice_this_week' => 0, 'waiting_payment' => 0, 'tax_invoice_pending' => 0, 'renewal_soon' => 0, 'total_paid_amount' => 0.0, 'outstanding_amount' => 0.0, 'mrr_amount' => 0.0];
    }

    private function savePrimaryContact(int $clientId, array $input): void
    {
        $name = $this->text($input['contact_name'] ?? '', 150);
        if ($name === '') return;
        $email = $this->nullableText($input['contact_email'] ?? null, 190);
        $phone = $this->nullableText($input['contact_phone'] ?? null, 80);
        $role = $this->nullableText($input['contact_role'] ?? null, 120);
        $this->db->query("UPDATE tracs_client_contacts SET is_primary=0 WHERE client_id=" . (int)$clientId);
        $stmt = $this->db->prepare("INSERT INTO tracs_client_contacts (client_id, name, email, phone, role_title, is_primary, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())");
        if (!$stmt) return;
        $stmt->bind_param('issss', $clientId, $name, $email, $phone, $role);
        $stmt->execute();
        $stmt->close();
    }

    private function log(int $clientId, int $actorId, string $actorName, string $event, string $summary): void
    {
        $stmt = $this->db->prepare("INSERT INTO tracs_client_activity_logs (client_id, user_id, actor_name, event_type, summary, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        if ($stmt) {
            $stmt->bind_param('iisss', $clientId, $actorId, $actorName, $event, $summary);
            $stmt->execute();
            $stmt->close();
        }
        if (tracs_table_exists($this->db, 'tracs_activity_logs')) {
            $module = 'Clients';
            $action = str_replace('client.', '', $event);
            $stmt = $this->db->prepare("INSERT INTO tracs_activity_logs (user_id, action, module, description, reference_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            if ($stmt) {
                $stmt->bind_param('isssi', $actorId, $action, $module, $summary, $clientId);
                $stmt->execute();
                $stmt->close();
            }
        }
        tracs_dobby_enqueue_client_activity($this->db, $clientId, $actorId, $actorName, $event, $summary);
    }

    private function addonsForServices(array $serviceIds): array
    {
        $serviceIds = array_values(array_filter(array_map('intval', $serviceIds), fn(int $id): bool => $id > 0));
        if (!$serviceIds) return [];
        $placeholders = implode(',', array_fill(0, count($serviceIds), '?'));
        $rows = $this->preparedRows(
            "SELECT * FROM tracs_client_service_addons WHERE service_id IN ({$placeholders}) ORDER BY status ASC, renewal_date IS NULL, renewal_date ASC, name ASC",
            str_repeat('i', count($serviceIds)),
            $serviceIds
        );
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int)$row['service_id']][] = $row;
        }
        return $grouped;
    }

    private function serviceForClient(int $clientId, int $serviceId): ?array
    {
        if ($clientId <= 0 || $serviceId <= 0) return null;
        return $this->one("SELECT * FROM tracs_client_services WHERE id=? AND client_id=? LIMIT 1", 'ii', [$serviceId, $clientId]);
    }

    private function serviceStatus(mixed $value): string
    {
        return $this->enum($value, ['active','monitoring','pending_renewal','suspended','terminated','inactive'], 'active');
    }

    private function renewalWindowDays(mixed $value): ?int
    {
        return match ((string)$value) {
            '7' => 7,
            '30' => 30,
            '90' => 90,
            default => null,
        };
    }

    private function monthlyEquivalent(mixed $price, string $cycle): float
    {
        $amount = $price === null || $price === '' ? 0.0 : (float)$price;
        return match ($cycle) {
            'monthly' => $amount,
            'quarterly' => $amount / 3,
            'semiannual' => $amount / 6,
            'annual' => $amount / 12,
            default => 0.0,
        };
    }

    private function preparedRows(string $sql, string $types = '', array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return [];
        if ($types !== '') $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    private function one(string $sql, string $types, array $params): ?array
    {
        $rows = $this->preparedRows($sql, $types, $params);
        return $rows[0] ?? null;
    }

    private function text(mixed $value, int $max): string
    {
        $text = trim(preg_replace('/[\x00-\x1F\x7F]+/', ' ', (string)$value) ?? '');
        return function_exists('mb_substr') ? mb_substr($text, 0, $max) : substr($text, 0, $max);
    }

    private function nullableText(mixed $value, int $max): ?string
    {
        $text = $this->text($value ?? '', $max);
        return $text === '' ? null : $text;
    }

    private function enum(mixed $value, array $allowed, string $default): string
    {
        $value = trim((string)$value);
        return in_array($value, $allowed, true) ? $value : $default;
    }

    private function nullableMoney(mixed $value): ?float
    {
        if ($value === null || trim((string)$value) === '') return null;
        $float = filter_var($value, FILTER_VALIDATE_FLOAT);
        if ($float === false || $float < 0) throw new InvalidArgumentException('Amount must be a positive number.');
        return (float)$float;
    }

    private function nullableBillingDay(mixed $value): ?int
    {
        if ($value === null || trim((string)$value) === '') return null;
        $day = filter_var($value, FILTER_VALIDATE_INT);
        if ($day === false || $day < 1 || $day > 31) throw new InvalidArgumentException('Billing day must be between 1 and 31.');
        return (int)$day;
    }

    private function nullableDate(mixed $value): ?string
    {
        $value = trim((string)$value);
        if ($value === '') return null;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $this->zone);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$date || ($errors !== false && ($errors['warning_count'] || $errors['error_count']))) throw new InvalidArgumentException('Date must use YYYY-MM-DD.');
        return $date->format('Y-m-d');
    }

    private function dateTime(mixed $value): string
    {
        $value = trim((string)$value);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, $this->zone) ?: DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, $this->zone);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$date || ($errors !== false && ($errors['warning_count'] || $errors['error_count']))) throw new InvalidArgumentException('Date/time is invalid.');
        return $date->format('Y-m-d H:i:s');
    }

    private function dateOrNull(mixed $value): ?DateTimeImmutable
    {
        $value = trim((string)$value);
        if ($value === '') return null;
        try { return new DateTimeImmutable($value, $this->zone); } catch (Throwable) { return null; }
    }
}
