<?php
declare(strict_types=1);

require_once __DIR__ . '/../../core/creator_tracking.php';
require_once __DIR__ . '/../../core/user_management.php';
require_once __DIR__ . '/../../core/notifications.php';

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
            $where[] = "(c.company_name LIKE ? OR c.client_code LIKE ? OR EXISTS (SELECT 1 FROM tracs_client_contacts qc WHERE qc.client_id=c.id AND (qc.name LIKE ? OR qc.email LIKE ?)))";
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
        if (!empty($filters['pic'])) {
            $where[] = "EXISTS (SELECT 1 FROM tracs_client_contacts fc WHERE fc.client_id=c.id AND (fc.name LIKE ? OR fc.email LIKE ?))";
            $types .= 'ss';
            array_push($params, '%' . $filters['pic'] . '%', '%' . $filters['pic'] . '%');
        }
        if (in_array($filters['billing_status'] ?? '', ['paid', 'waiting', 'overdue'], true)) {
            $where[] = "EXISTS (SELECT 1 FROM tracs_client_billing_records fb WHERE fb.client_id=c.id AND fb.invoice_status <> 'cancelled' AND fb.payment_status=?)";
            $types .= 's';
            $params[] = $filters['billing_status'];
        }
        if (in_array($filters['tax_status'] ?? '', ['pending', 'sent'], true)) {
            $sent = $filters['tax_status'] === 'sent' ? 'IS NOT NULL' : 'IS NULL';
            $where[] = "EXISTS (SELECT 1 FROM tracs_client_billing_records fb WHERE fb.client_id=c.id AND fb.invoice_status <> 'cancelled' AND fb.tax_invoice_required=1 AND fb.payment_status='paid' AND fb.tax_invoice_sent_at {$sent})";
        }
        $renewalConditions = [];
        $renewalParams = [];
        foreach (['renewal_from' => '>=', 'renewal_to' => '<='] as $key => $operator) {
            if (!empty($filters[$key])) {
                $renewalConditions[] = "fs.renewal_date {$operator} ?";
                $renewalParams[] = $this->nullableDate($filters[$key]);
            }
        }
        if (count($renewalParams) === 2 && $renewalParams[0] > $renewalParams[1]) throw new InvalidArgumentException('Renewal end must be on or after the start date.');
        if ($renewalConditions) {
            $where[] = "EXISTS (SELECT 1 FROM tracs_client_services fs WHERE fs.client_id=c.id AND fs.status NOT IN ('inactive','terminated') AND " . implode(' AND ', $renewalConditions) . ')';
            $types .= str_repeat('s', count($renewalParams));
            array_push($params, ...$renewalParams);
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
                   COALESCE(svc.renewal_soon_count, 0) AS renewal_soon_count,
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
                       COUNT(CASE WHEN s.status NOT IN ('inactive','terminated') AND s.renewal_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN s.id END) AS renewal_soon_count,
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
                WHERE invoice_status <> 'cancelled'
                GROUP BY client_id
            ) bill ON bill.client_id = c.id
            {$whereSql}
            ORDER BY c.updated_at DESC, c.company_name ASC
        ";
        $rows = $this->preparedRows($sql, $types, $params);
        $clients = array_map(fn(array $row): array => $this->decorateClient($row), $rows);

        if (($filters['signal'] ?? '') === 'invoice') {
            $clients = array_values(array_filter($clients, fn(array $c): bool => (int)$c['invoice_this_week_count'] > 0));
        } elseif (($filters['signal'] ?? '') === 'renewal') {
            $clients = array_values(array_filter($clients, fn(array $c): bool => (int)$c['renewal_soon_count'] > 0));
        }
        if (!empty($filters['attention'])) {
            $attention = (string)$filters['attention'];
            $clients = array_values(array_filter($clients, fn(array $c): bool => $attention === 'attention' ? $c['attention_level'] !== 'normal' : $c['attention_level'] === $attention));
        }

        usort($clients, fn(array $a, array $b): int => [$a['attention_rank'], $a['days_until_action'] ?? 9999, $a['company_name']] <=> [$b['attention_rank'], $b['days_until_action'] ?? 9999, $b['company_name']]);
        $summary = $this->summary($clients);
        $attention = array_values(array_filter($clients, fn(array $c): bool => $c['attention_level'] !== 'normal'));
        $sort = (string)($filters['sort'] ?? 'attention_rank');
        if (in_array($sort, ['company_name','client_code','owner_name','status','primary_contact_name','nearest_renewal_date','outstanding_amount','attention_rank'], true)) {
            $direction = ($filters['direction'] ?? '') === 'desc' ? -1 : 1;
            usort($clients, static function (array $a, array $b) use ($sort, $direction): int {
                $left = $a[$sort] ?? null;
                $right = $b[$sort] ?? null;
                if ($left === null || $right === null) return ($left === null) <=> ($right === null);
                $order = in_array($sort, ['outstanding_amount', 'attention_rank'], true) ? $left <=> $right : strnatcasecmp((string)$left, (string)$right);
                return ($order ?: ((int)$a['id'] <=> (int)$b['id'])) * $direction;
            });
        }
        $total = count($clients);
        $page = max(1, min((int)($filters['page'] ?? 1), max(1, (int)ceil($total / 25))));
        $available = $canViewAll
            ? $this->one('SELECT COUNT(*) AS total FROM tracs_clients', '', [])
            : $this->one('SELECT COUNT(*) AS total FROM tracs_clients WHERE owner_user_id=?', 'i', [$actorId]);
        return [
            'clients' => array_slice($clients, ($page - 1) * 25, 25),
            'total' => $total,
            'available_total' => (int)($available['total'] ?? 0),
            'page' => $page,
            'page_size' => 25,
            'summary' => $summary,
            'attention' => array_slice($attention, 0, 5),
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
        $client['followups'] = $this->preparedRows("SELECT f.*, COALESCE(NULLIF(u.name,''), u.email, 'Unassigned') AS assignee_name FROM tracs_client_followups f LEFT JOIN tracs_users u ON u.id=f.assigned_to WHERE f.client_id=? ORDER BY f.status ASC, f.due_at IS NULL, f.due_at ASC", 'i', [$id]);
        $client['activity'] = $this->preparedRows("SELECT * FROM tracs_client_activity_logs WHERE client_id=? ORDER BY created_at DESC LIMIT 80", 'i', [$id]);
        $client['renewal_history'] = $this->preparedRows("SELECT h.*, s.service_name FROM tracs_client_service_renewal_history h LEFT JOIN tracs_client_services s ON s.id=h.service_id WHERE h.client_id=? ORDER BY h.created_at DESC LIMIT 80", 'i', [$id]);
        return $this->decorateClient($this->withDetailSignals($client));
    }

    public function createClient(array $input, int $actorId, string $actorName): int
    {
        $name = $this->text($input['company_name'] ?? '', 190);
        if ($name === '') throw new InvalidArgumentException('Company name is required.');
        $owner = max(1, (int)($input['owner_user_id'] ?? $actorId));
        $code = $this->nullableText($input['client_code'] ?? null, 40) ?? 'CL-' . strtoupper(bin2hex(random_bytes(5)));
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
        $priceInput = $input['new_price'] ?? $input['price'] ?? null;
        $newPrice = $priceInput !== null && trim((string)$priceInput) !== '' ? $this->nullableMoney($priceInput) : ($service['price'] !== null ? (float)$service['price'] : null);
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
        return $serviceId;
    }

    public function addBilling(int $clientId, array $input, int $actorId, string $actorName): int
    {
        $serviceId = !empty($input['service_id']) ? (int)$input['service_id'] : null;
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
        return $id;
    }

    public function addFollowup(int $clientId, array $input, int $actorId, string $actorName): int
    {
        $title = $this->text($input['title'] ?? '', 220);
        if ($title === '') throw new InvalidArgumentException('Follow-up title is required.');
        $assignedTo = !empty($input['assigned_to']) ? (int)$input['assigned_to'] : $actorId;
        $dueAt = !empty($input['due_at']) ? $this->dateTime($input['due_at']) : null;
        $type = $this->enum($input['action_type'] ?? 'general_followup', ['send_invoice','check_payment','send_tax_invoice','renewal','general_followup'], 'general_followup');
        $priority = $this->enum($input['priority'] ?? 'medium', ['low','medium','high','critical'], 'medium');
        $serviceId = !empty($input['service_id']) ? (int)$input['service_id'] : null;
        $billingId = !empty($input['billing_record_id']) ? (int)$input['billing_record_id'] : null;
        $reminderId = null;
        if ($dueAt !== null) {
            $desc = 'Client Portfolio follow-up';
            $stmt = $this->db->prepare("INSERT INTO tracs_reminders (user_id, title, description, due_date, priority, is_completed, created_by, created_by_name, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 0, ?, ?, NOW(), NOW())");
            if ($stmt) {
                $stmt->bind_param('issssis', $assignedTo, $title, $desc, $dueAt, $priority, $actorId, $actorName);
                $stmt->execute();
                $reminderId = (int)$stmt->insert_id ?: null;
                $stmt->close();
                if ($reminderId) tracs_notify_reminder_created($this->db, $reminderId, $assignedTo, $title, $dueAt, $actorId);
            }
        }
        $stmt = $this->db->prepare("INSERT INTO tracs_client_followups (client_id, service_id, billing_record_id, reminder_id, action_type, title, due_at, priority, status, assigned_to, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'open', ?, ?, NOW(), NOW())");
        if (!$stmt) throw new RuntimeException('Unable to add follow-up.');
        $stmt->bind_param('iiiissssii', $clientId, $serviceId, $billingId, $reminderId, $type, $title, $dueAt, $priority, $assignedTo, $actorId);
        if (!$stmt->execute()) throw new RuntimeException('Unable to add follow-up.');
        $id = (int)$stmt->insert_id;
        $stmt->close();
        $this->log($clientId, $actorId, $actorName, 'client.followup_created', "Follow-up created: {$title}");
        return $id;
    }

    public function completeFollowup(int $followupId, int $actorId, string $actorName): int
    {
        $row = $this->one("SELECT * FROM tracs_client_followups WHERE id=? LIMIT 1", 'i', [$followupId]);
        if (!$row) throw new RuntimeException('Follow-up not found.');
        if (!$this->getClient((int)$row['client_id'], $actorId, tracs_user_can($this->db, 'clients.view_all', $actorId))) throw new RuntimeException('Client not found.');
        $stmt = $this->db->prepare("UPDATE tracs_client_followups SET status='completed', completed_at=NOW(), updated_at=NOW() WHERE id=?");
        if (!$stmt) throw new RuntimeException('Unable to complete follow-up.');
        $stmt->bind_param('i', $followupId);
        $stmt->execute();
        $stmt->close();
        if (!empty($row['reminder_id'])) {
            $rid = (int)$row['reminder_id'];
            $r = $this->db->prepare("UPDATE tracs_reminders SET is_completed=1, completed_at=NOW(), completed_by=?, updated_at=NOW() WHERE id=?");
            if ($r) {
                $r->bind_param('ii', $actorId, $rid);
                $r->execute();
                $r->close();
            }
        }
        $this->log((int)$row['client_id'], $actorId, $actorName, 'client.followup_completed', 'Follow-up completed: ' . (string)$row['title']);
        return (int)$row['client_id'];
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
            $summary['renewal_soon'] += (int)($client['renewal_soon_count'] ?? 0);
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
        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('PIC email is invalid.');
        $current = $this->one('SELECT id FROM tracs_client_contacts WHERE client_id=? AND is_primary=1 LIMIT 1', 'i', [$clientId]);
        if ($current) {
            $stmt = $this->db->prepare('UPDATE tracs_client_contacts SET name=?, email=?, phone=?, role_title=?, updated_at=NOW() WHERE id=? AND client_id=?');
            $stmt->bind_param('ssssii', $name, $email, $phone, $role, $current['id'], $clientId);
        } else {
            $stmt = $this->db->prepare("INSERT INTO tracs_client_contacts (client_id, name, email, phone, role_title, is_primary, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())");
            $stmt->bind_param('issss', $clientId, $name, $email, $phone, $role);
        }
        if (!$stmt->execute()) throw new RuntimeException('Unable to save primary PIC.');
        $stmt->close();
    }

    public function saveContact(int $clientId, array $input, int $actorId, string $actorName): int
    {
        $id = (int)($input['contact_id'] ?? 0);
        $name = $this->text($input['name'] ?? '', 150);
        if ($name === '') throw new InvalidArgumentException('PIC name is required.');
        $email = $this->nullableText($input['email'] ?? null, 190);
        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('PIC email is invalid.');
        $phone = $this->nullableText($input['phone'] ?? null, 80);
        $role = $this->nullableText($input['role_title'] ?? null, 120);
        $primary = !empty($input['is_primary']) ? 1 : 0;
        $this->db->begin_transaction();
        try {
            $this->one('SELECT id FROM tracs_clients WHERE id=? FOR UPDATE', 'i', [$clientId]);
            $current = $id ? $this->one('SELECT * FROM tracs_client_contacts WHERE id=? AND client_id=?', 'ii', [$id, $clientId]) : null;
            if ($id && !$current) throw new InvalidArgumentException('PIC not found.');
            $existingPrimary = $this->one('SELECT id FROM tracs_client_contacts WHERE client_id=? AND is_primary=1 LIMIT 1', 'i', [$clientId]);
            if (!$existingPrimary || (int)($current['is_primary'] ?? 0) === 1) $primary = 1;
            if ($primary) $this->db->query('UPDATE tracs_client_contacts SET is_primary=0 WHERE client_id=' . $clientId);
            if ($id) {
                $stmt = $this->db->prepare('UPDATE tracs_client_contacts SET name=?, email=?, phone=?, role_title=?, is_primary=?, updated_at=NOW() WHERE id=? AND client_id=?');
                $stmt->bind_param('ssssiii', $name, $email, $phone, $role, $primary, $id, $clientId);
            } else {
                $stmt = $this->db->prepare('INSERT INTO tracs_client_contacts (client_id,name,email,phone,role_title,is_primary,created_at,updated_at) VALUES (?,?,?,?,?,?,NOW(),NOW())');
                $stmt->bind_param('issssi', $clientId, $name, $email, $phone, $role, $primary);
            }
            if (!$stmt->execute()) throw new RuntimeException('Unable to save PIC.');
            $id = $id ?: (int)$stmt->insert_id;
            $stmt->close();
            $this->log($clientId, $actorId, $actorName, 'client.contact_saved', 'PIC saved: ' . $name);
            $this->db->commit();
            return $id;
        } catch (Throwable $error) {
            $this->db->rollback();
            throw $error;
        }
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
        $date = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value, $this->zone) ?: DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, $this->zone);
        if (!$date) throw new InvalidArgumentException('Date/time is invalid.');
        return $date->format('Y-m-d H:i:s');
    }

    private function dateOrNull(mixed $value): ?DateTimeImmutable
    {
        $value = trim((string)$value);
        if ($value === '') return null;
        try { return new DateTimeImmutable($value, $this->zone); } catch (Throwable) { return null; }
    }
}
