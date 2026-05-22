<?php
/**
 * TRACS — Domain Price Crosscheck Model
 * Safe, robust database interaction for domain prices, TLDs, sources, and audit logs.
 */

class DomainPriceCrosscheckModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Get all active TLD extensions.
     */
    public function getActiveTlds(?string $category = null): array {
        $where = "WHERE is_active = 1";
        $types = "";
        $params = [];
        if ($category !== null) {
            $where .= " AND tld_category = ?";
            $types = "s";
            $params[] = $category;
        }

        $sql = "SELECT id, tld_name, tld_category, sort_order FROM domain_price_tlds {$where} ORDER BY sort_order ASC, tld_name ASC";
        if ($params) {
            $stmt = $this->db->prepare($sql);
            if (!$stmt) return [];
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }

        $result = $this->db->query($sql);
        if (!$result) return [];
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get all active pricing/registrar sources.
     */
    public function getActiveSources(): array {
        $sql = "SELECT id, source_name, source_type, sort_order FROM domain_price_sources WHERE is_active = 1 ORDER BY sort_order ASC, source_name ASC";
        $result = $this->db->query($sql);
        if (!$result) return [];
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /*      * Get all monthly crosscheck metadata records.
     */
    public function getMonths(): array {
        $sql = "SELECT m.*, 
                       u_c.name AS creator_name, 
                       u_s.name AS submitter_name, 
                       u_a.name AS approver_name,
                       u_u.name AS updater_name
                FROM domain_price_months m
                LEFT JOIN tracs_users u_c ON m.created_by = u_c.id
                LEFT JOIN tracs_users u_s ON m.submitted_by = u_s.id
                LEFT JOIN tracs_users u_a ON m.approved_by = u_a.id
                LEFT JOIN tracs_users u_u ON m.updated_by = u_u.id
                ORDER BY m.month DESC";
        $result = $this->db->query($sql);
        if (!$result) return [];
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get a specific monthly record by ID.
     */
    public function getMonthById(int $id): ?array {
        $sql = "SELECT m.*, 
                       u_c.name AS creator_name, 
                       u_s.name AS submitter_name, 
                       u_a.name AS approver_name,
                       u_u.name AS updater_name
                FROM domain_price_months m
                LEFT JOIN tracs_users u_c ON m.created_by = u_c.id
                LEFT JOIN tracs_users u_s ON m.submitted_by = u_s.id
                LEFT JOIN tracs_users u_a ON m.approved_by = u_a.id
                LEFT JOIN tracs_users u_u ON m.updated_by = u_u.id
                WHERE m.id = ?";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res->fetch_assoc() ?: null;
    }

    /**
     * Get a specific monthly record by YYYY-MM code.
     */
    public function getMonthByCode(string $month): ?array {
        $sql = "SELECT m.*, 
                       u_c.name AS creator_name, 
                       u_s.name AS submitter_name, 
                       u_a.name AS approver_name,
                       u_u.name AS updater_name
                FROM domain_price_months m
                LEFT JOIN tracs_users u_c ON m.created_by = u_c.id
                LEFT JOIN tracs_users u_s ON m.submitted_by = u_s.id
                LEFT JOIN tracs_users u_a ON m.approved_by = u_a.id
                LEFT JOIN tracs_users u_u ON m.updated_by = u_u.id
                WHERE m.month = ?";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param("s", $month);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res->fetch_assoc() ?: null;
    }

    /**
     * Create a new monthly crosscheck record.
     */
    public function createMonth(string $month, float $exchangeRate, int $userId): ?int {
        $parts = explode('-', $month);
        $year = (int)($parts[0] ?? date('Y'));
        
        $sql = "INSERT INTO domain_price_months (month, year, exchange_rate_usd_idr, status, created_by, created_at)
                VALUES (?, ?, ?, 'draft', ?, NOW())";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param("sidi", $month, $year, $exchangeRate, $userId);
        if ($stmt->execute()) {
            return $this->db->insert_id;
        }
        return null;
    }

    /**
     * Update exchange rate for a draft month.
     */
    public function updateExchangeRate(int $monthId, float $exchangeRate, int $userId): bool {
        $sql = "UPDATE domain_price_months 
                SET exchange_rate_usd_idr = ?, updated_by = ?, updated_at = NOW() 
                WHERE id = ? AND status = 'draft'";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return false;
        $stmt->bind_param("dii", $exchangeRate, $userId, $monthId);
        return $stmt->execute();
    }

    /**
     * Transition a monthly record status.
     */
    public function updateMonthStatus(int $monthId, string $status, int $userId, ?string $note = null): bool {
        $allowed = ['draft', 'pending_review', 'approved'];
        if (!in_array($status, $allowed, true)) return false;

        $fields = ["`status` = ?", "`updated_by` = ?", "`updated_at` = NOW()"];
        $params = [$status, $userId];
        $types = "si";

        if ($status === 'pending_review') {
            $fields[] = "`submitted_by` = ?";
            $fields[] = "`submitted_at` = NOW()";
            $params[] = $userId;
            $types .= "i";
        } elseif ($status === 'approved') {
            $fields[] = "`approved_by` = ?";
            $fields[] = "`approved_at` = NOW()";
            $fields[] = "`approval_note` = ?";
            $params[] = $userId;
            $params[] = $note ?? '';
            $types .= "is";
        }

        $params[] = $monthId;
        $types .= "i";

        $sql = "UPDATE domain_price_months SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return false;
        $stmt->bind_param($types, ...$params);
        return $stmt->execute();
    }

    /**
     * Unlock an approved month record.
     */
    public function unlockMonth(int $monthId, int $userId, string $reason): bool {
        $sql = "UPDATE domain_price_months 
                SET status = 'draft', 
                    unlocked_by = ?, 
                    unlocked_at = NOW(), 
                    unlock_reason = ?, 
                    updated_by = ?, 
                    updated_at = NOW() 
                WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return false;
        $stmt->bind_param("isii", $userId, $reason, $userId, $monthId);
        return $stmt->execute();
    }

    /**
     * Get price entries for a given month.
     */
    public function getEntriesForMonth(int $monthId): array {
        $sql = "SELECT e.*, t.tld_name, s.source_name, s.source_type
                FROM domain_price_entries e
                JOIN domain_price_tlds t ON e.tld_id = t.id
                LEFT JOIN domain_price_sources s ON e.source_id = s.id
                WHERE e.month_id = ?";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return [];
        $stmt->bind_param("i", $monthId);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Save or update a single price entry.
     */
    public function saveEntry(array $data): bool {
        $sql = "INSERT INTO domain_price_entries 
                (month_id, tld_id, source_id, price_type, currency, original_value, usd_value, idr_value, calculated_from_kurs, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    currency = VALUES(currency),
                    original_value = VALUES(original_value),
                    usd_value = VALUES(usd_value),
                    idr_value = VALUES(idr_value),
                    calculated_from_kurs = VALUES(calculated_from_kurs),
                    updated_by = VALUES(created_by),
                    updated_at = NOW()";
        
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return false;
        
        $stmt->bind_param(
            "iiissddddi",
            $data['month_id'],
            $data['tld_id'],
            $data['source_id'], // can be NULL
            $data['price_type'],
            $data['currency'],
            $data['original_value'],
            $data['usd_value'],
            $data['idr_value'],
            $data['calculated_from_kurs'],
            $data['created_by']
        );
        return $stmt->execute();
    }

    /**
     * Delete a single price entry (clears to "Not Set").
     */
    public function deleteEntry(int $monthId, int $tldId, ?int $sourceId, string $priceType): bool {
        if ($sourceId === null) {
            $sql = "DELETE FROM domain_price_entries 
                    WHERE month_id = ? AND tld_id = ? AND source_id IS NULL AND price_type = ?";
            $stmt = $this->db->prepare($sql);
            if (!$stmt) return false;
            $stmt->bind_param("iis", $monthId, $tldId, $priceType);
        } else {
            $sql = "DELETE FROM domain_price_entries 
                    WHERE month_id = ? AND tld_id = ? AND source_id = ? AND price_type = ?";
            $stmt = $this->db->prepare($sql);
            if (!$stmt) return false;
            $stmt->bind_param("iiis", $monthId, $tldId, $sourceId, $priceType);
        }
        return $stmt->execute();
    }

    /**
     * Write an audit log entry.
     */
    public function logAction(
        int $monthId,
        int $userId,
        string $userName,
        string $action,
        string $details,
        string $ipAddress,
        ?int $tldId = null,
        ?int $sourceId = null,
        ?string $fieldName = null,
        ?string $oldValue = null,
        ?string $newValue = null,
        ?string $changeReason = null
    ): bool {
        $sql = "INSERT INTO domain_price_audit_logs (
                    month_id, tld_id, source_id, actor_user_id, actor_name, 
                    action, field_name, old_value, new_value, change_reason, 
                    details, ip_address, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return false;
        $stmt->bind_param(
            "iiiissssssss",
            $monthId,
            $tldId,
            $sourceId,
            $userId,
            $userName,
            $action,
            $fieldName,
            $oldValue,
            $newValue,
            $changeReason,
            $details,
            $ipAddress
        );
        return $stmt->execute();
    }

    /**
     * Get audit logs for a month.
     */
    public function getAuditLogs(int $monthId): array {
        $sql = "SELECT * FROM domain_price_audit_logs WHERE month_id = ? ORDER BY created_at DESC LIMIT 10";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return [];
        $stmt->bind_param("i", $monthId);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Upsert computed summary columns for one TLD (Phase 10 recalculation engine).
     * Writes the auto-calculated values alongside any existing manual notes.
     */
    public function saveSummaryComputed(int $monthId, int $tldId, array $d): bool {
        $sql = "INSERT INTO domain_price_summaries
                    (month_id, tld_id,
                     lowest_register_source_id, lowest_renewal_source_id,
                     lowest_register_cost, lowest_renewal_cost,
                     website_register_price, website_renewal_price,
                     paas_register_price, paas_renewal_price,
                     website_margin_register, website_margin_renewal,
                     website_margin_register_pct, website_margin_renewal_pct,
                     paas_margin_register, paas_margin_renewal,
                     paas_margin_register_pct, paas_margin_renewal_pct,
                     website_below_cost_register, website_below_cost_renewal,
                     paas_below_cost_register, paas_below_cost_renewal,
                     prev_lowest_register_cost, prev_lowest_renewal_cost,
                     cost_register_diff, cost_renewal_diff,
                     cost_register_change_pct, cost_renewal_change_pct,
                     auto_status, suggested_action,
                     created_at, updated_at)
                VALUES (?,?, ?,?, ?,?, ?,?, ?,?, ?,?, ?,?, ?,?, ?,?, ?,?, ?,?, ?,?, ?,?, ?,?, ?,?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                     lowest_register_source_id   = VALUES(lowest_register_source_id),
                     lowest_renewal_source_id    = VALUES(lowest_renewal_source_id),
                     lowest_register_cost        = VALUES(lowest_register_cost),
                     lowest_renewal_cost         = VALUES(lowest_renewal_cost),
                     website_register_price      = VALUES(website_register_price),
                     website_renewal_price       = VALUES(website_renewal_price),
                     paas_register_price         = VALUES(paas_register_price),
                     paas_renewal_price          = VALUES(paas_renewal_price),
                     website_margin_register     = VALUES(website_margin_register),
                     website_margin_renewal      = VALUES(website_margin_renewal),
                     website_margin_register_pct = VALUES(website_margin_register_pct),
                     website_margin_renewal_pct  = VALUES(website_margin_renewal_pct),
                     paas_margin_register        = VALUES(paas_margin_register),
                     paas_margin_renewal         = VALUES(paas_margin_renewal),
                     paas_margin_register_pct    = VALUES(paas_margin_register_pct),
                     paas_margin_renewal_pct     = VALUES(paas_margin_renewal_pct),
                     website_below_cost_register = VALUES(website_below_cost_register),
                     website_below_cost_renewal  = VALUES(website_below_cost_renewal),
                     paas_below_cost_register    = VALUES(paas_below_cost_register),
                     paas_below_cost_renewal     = VALUES(paas_below_cost_renewal),
                     prev_lowest_register_cost   = VALUES(prev_lowest_register_cost),
                     prev_lowest_renewal_cost    = VALUES(prev_lowest_renewal_cost),
                     cost_register_diff          = VALUES(cost_register_diff),
                     cost_renewal_diff           = VALUES(cost_renewal_diff),
                     cost_register_change_pct    = VALUES(cost_register_change_pct),
                     cost_renewal_change_pct     = VALUES(cost_renewal_change_pct),
                     auto_status                 = VALUES(auto_status),
                     suggested_action            = VALUES(suggested_action),
                     updated_at                  = NOW()";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return false;
        $stmt->bind_param(
            "iiiiddddddddddddddiiiiddddddss",
            $monthId,
            $tldId,
            $d['lowest_register_source_id'],
            $d['lowest_renewal_source_id'],
            $d['lowest_register_cost'],
            $d['lowest_renewal_cost'],
            $d['website_register_price'],
            $d['website_renewal_price'],
            $d['paas_register_price'],
            $d['paas_renewal_price'],
            $d['website_margin_register'],
            $d['website_margin_renewal'],
            $d['website_margin_register_pct'],
            $d['website_margin_renewal_pct'],
            $d['paas_margin_register'],
            $d['paas_margin_renewal'],
            $d['paas_margin_register_pct'],
            $d['paas_margin_renewal_pct'],
            $d['website_below_cost_register'],
            $d['website_below_cost_renewal'],
            $d['paas_below_cost_register'],
            $d['paas_below_cost_renewal'],
            $d['prev_lowest_register_cost'],
            $d['prev_lowest_renewal_cost'],
            $d['cost_register_diff'],
            $d['cost_renewal_diff'],
            $d['cost_register_change_pct'],
            $d['cost_renewal_change_pct'],
            $d['auto_status'],
            $d['suggested_action']
        );
        return $stmt->execute();
    }

    /**
     * Get computed summary rows for a given month (joined to TLD and source names).
     */
    public function getComputedSummaries(int $monthId): array {
        $sql = "SELECT s.*, t.tld_name,
                       sr.source_name AS lowest_register_source_name,
                       sn.source_name AS lowest_renewal_source_name
                FROM domain_price_summaries s
                JOIN domain_price_tlds t ON s.tld_id = t.id
                LEFT JOIN domain_price_sources sr ON s.lowest_register_source_id = sr.id
                LEFT JOIN domain_price_sources sn ON s.lowest_renewal_source_id = sn.id
                WHERE s.month_id = ?
                ORDER BY t.sort_order ASC, t.tld_name ASC";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return [];
        $stmt->bind_param("i", $monthId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get the previous month's computed summaries keyed by tld_id (for MoM comparison).
     */
    public function getPreviousMonthSummaries(int $currentMonthId): array {
        $sql = "SELECT s.tld_id,
                       s.lowest_register_cost,
                       s.lowest_renewal_cost,
                       s.lowest_register_source_id,
                       s.lowest_renewal_source_id
                FROM domain_price_summaries s
                JOIN domain_price_months m ON s.month_id = m.id
                WHERE m.month < (SELECT month FROM domain_price_months WHERE id = ?)
                  AND m.status = 'approved'
                ORDER BY m.month DESC";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return [];
        $stmt->bind_param("i", $currentMonthId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        // Key by tld_id — first row per tld_id is from most recent previous approved month
        $out = [];
        foreach ($rows as $row) {
            if (!isset($out[$row['tld_id']])) {
                $out[$row['tld_id']] = $row;
            }
        }
        return $out;
    }



    /**
     * Duplicate metadata from a previous month (pricing cloning is deferred to Phase 4).
     */
    public function duplicateMonthMetadata(int $fromMonthId, string $newMonth, float $exchangeRate, int $userId): ?int {
        $parts = explode('-', $newMonth);
        $year = (int)($parts[0] ?? date('Y'));
        
        $sql = "INSERT INTO domain_price_months (month, year, exchange_rate_usd_idr, status, created_by, created_at)
                VALUES (?, ?, ?, 'draft', ?, NOW())";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param("sidi", $newMonth, $year, $exchangeRate, $userId);
        if ($stmt->execute()) {
            return $this->db->insert_id;
        }
        return null;
    }

    /**
     * Copy editable matrix entries from one monthly draft/snapshot to another.
     */
    public function copyMonthEntries(int $fromMonthId, int $toMonthId, int $userId): int {
        $sql = "INSERT INTO domain_price_entries
                    (month_id, tld_id, source_id, price_type, currency, original_value, usd_value, idr_value, calculated_from_kurs, created_by, created_at)
                SELECT ?, tld_id, source_id, price_type, currency, original_value, usd_value, idr_value, calculated_from_kurs, ?, NOW()
                FROM domain_price_entries
                WHERE month_id = ?
                ON DUPLICATE KEY UPDATE
                    currency = VALUES(currency),
                    original_value = VALUES(original_value),
                    usd_value = VALUES(usd_value),
                    idr_value = VALUES(idr_value),
                    calculated_from_kurs = VALUES(calculated_from_kurs),
                    updated_by = VALUES(created_by),
                    updated_at = NOW()";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return 0;
        $stmt->bind_param("iii", $toMonthId, $userId, $fromMonthId);
        $stmt->execute();
        return $stmt->affected_rows;
    }

    /**
     * Optionally copy manual notes only. Computed summary/approval state is intentionally not cloned.
     */
    public function copyMonthNotes(int $fromMonthId, int $toMonthId, int $userId): int {
        $sql = "INSERT INTO domain_price_summaries
                    (month_id, tld_id, manual_note, detailed_note, follow_up_status, updated_by, created_at, updated_at)
                SELECT ?, tld_id, manual_note, detailed_note, follow_up_status, ?, NOW(), NOW()
                FROM domain_price_summaries
                WHERE month_id = ?
                  AND (COALESCE(manual_note, '') <> '' OR COALESCE(detailed_note, '') <> '' OR COALESCE(follow_up_status, 'No Action') <> 'No Action')
                ON DUPLICATE KEY UPDATE
                    manual_note = VALUES(manual_note),
                    detailed_note = VALUES(detailed_note),
                    follow_up_status = VALUES(follow_up_status),
                    updated_by = VALUES(updated_by),
                    updated_at = NOW()";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return 0;
        $stmt->bind_param("iii", $toMonthId, $userId, $fromMonthId);
        $stmt->execute();
        return $stmt->affected_rows;
    }

    /**
     * Save a manual note and follow-up status for a specific TLD in a month.
     */
    public function saveTldNote(int $monthId, int $tldId, string $manualNote, string $detailedNote, string $followUpStatus, int $userId): bool {
        $sql = "INSERT INTO domain_price_summaries 
                (month_id, tld_id, manual_note, detailed_note, follow_up_status, updated_by, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    manual_note = VALUES(manual_note),
                    detailed_note = VALUES(detailed_note),
                    follow_up_status = VALUES(follow_up_status),
                    updated_by = VALUES(updated_by),
                    updated_at = NOW()";
        
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return false;
        
        $stmt->bind_param("iisssi", $monthId, $tldId, $manualNote, $detailedNote, $followUpStatus, $userId);
        return $stmt->execute();
    }

    /**
     * Get all TLD notes/summaries for a given month.
     */
    public function getTldNotes(int $monthId): array {
        $sql = "SELECT s.*, t.tld_name, u.name as updater_name
                FROM domain_price_summaries s
                JOIN domain_price_tlds t ON s.tld_id = t.id
                LEFT JOIN tracs_users u ON s.updated_by = u.id
                WHERE s.month_id = ?
                ORDER BY t.sort_order ASC, t.tld_name ASC";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return [];
        $stmt->bind_param("i", $monthId);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get a specific TLD note summary.
     */
    public function getTldNote(int $monthId, int $tldId): ?array {
        $sql = "SELECT * FROM domain_price_summaries WHERE month_id = ? AND tld_id = ?";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param("ii", $monthId, $tldId);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res->fetch_assoc() ?: null;
    }

    /**
     * Create a task in tracs_reminders.
     */
    public function createTask(int $assignedTo, string $title, string $dueDate, string $priority, int $actorId, string $actorName): ?int {
        $sql = "INSERT INTO tracs_reminders (user_id, created_by, created_by_name, title, due_date, priority)
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param("iissss", $assignedTo, $actorId, $actorName, $title, $dueDate, $priority);
        if ($stmt->execute()) {
            return $this->db->insert_id;
        }
        return null;
    }

    /**
     * Link a task to a monthly record.
     */
    public function linkTaskToMonth(int $monthId, int $taskId, int $assignedTo, int $actorId): bool {
        // Delete any existing link
        $del = $this->db->prepare("DELETE FROM domain_price_task_links WHERE month_id = ?");
        if ($del) {
            $del->bind_param("i", $monthId);
            $del->execute();
        }

        $sql = "INSERT INTO domain_price_task_links (month_id, task_id, assigned_to, created_by) VALUES (?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return false;
        $stmt->bind_param("iiii", $monthId, $taskId, $assignedTo, $actorId);
        return $stmt->execute();
    }

    /**
     * Get assigned task for a month.
     */
    public function getAssignedTaskForMonth(int $monthId): ?array {
        $sql = "SELECT l.*, r.title, r.due_date, r.priority, r.is_completed, u.name as assigned_name 
                FROM domain_price_task_links l
                JOIN tracs_reminders r ON l.task_id = r.id
                JOIN tracs_users u ON l.assigned_to = u.id
                WHERE l.month_id = ?";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param("i", $monthId);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res->fetch_assoc() ?: null;
    }

    /**
     * Sync task status to completed.
     */
    public function syncTaskStatus(int $taskId, bool $isCompleted, int $actorId): bool {
        $val = $isCompleted ? 1 : 0;
        $sql = "UPDATE tracs_reminders SET is_completed = ?, completed_at = IF(?=1, NOW(), NULL), completed_by = IF(?=1, ?, NULL) WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return false;
        $stmt->bind_param("iiiii", $val, $val, $val, $actorId, $taskId);
        return $stmt->execute();
    }

    /**
     * Create a notification in tracs_ticker_events.
     */
    public function createNotification(int $userId, string $message, string $type, string $module, int $referenceId, int $actorId, string $actorName): bool {
        $sql = "INSERT INTO tracs_ticker_events (user_id, created_by, created_by_name, message, type, module, reference_id, expires_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return false;
        $stmt->bind_param("iissssi", $userId, $actorId, $actorName, $message, $type, $module, $referenceId);
        return $stmt->execute();
    }
}
