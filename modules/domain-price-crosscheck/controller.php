<?php
/**
 * TRACS — Domain Price Crosscheck Controller
 * Handles business logic, role verification, auditing, and summaries.
 */

require_once __DIR__ . '/model.php';
require_once __DIR__ . '/../activity-log/controller.php';

class DomainPriceCrosscheckController {
    private $model;
    private $activityLogger;
    private $userId;
    private $db;

    public function __construct($db, $userId = 0) {
        $this->db = $db;
        $this->model = new DomainPriceCrosscheckModel($db);
        $this->activityLogger = new ActivityLogController($db, $userId);
        $this->userId = $userId;
    }

    /**
     * Get months list.
     */
    public function getMonths(): array {
        return $this->model->getMonths();
    }

    /**
     * Get a monthly record by YYYY-MM code.
     */
    public function getMonthByCode(string $month): ?array {
        return $this->model->getMonthByCode($month);
    }

    /**
     * Get active TLDs.
     */
    public function getActiveTlds(?string $category = null): array {
        return $this->model->getActiveTlds($category);
    }

    /**
     * Get active sources.
     */
    public function getActiveSources(): array {
        return $this->model->getActiveSources();
    }

    /**
     * Add or reactivate a domain extension for the pricing matrix.
     */
    public function addTldExtension(string $tldName, string $category, ?int $sortOrder, string $ipAddress): int {
        $normalized = strtolower(trim($tldName));
        if ($normalized === '') {
            throw new Exception("Domain extension is required.");
        }
        if ($normalized[0] !== '.') {
            $normalized = '.' . $normalized;
        }
        if (!preg_match('/^\.[a-z0-9-]+(?:\.[a-z0-9-]+)*$/', $normalized)) {
            throw new Exception("Invalid domain extension format. Example: .app or .web.id");
        }
        if (strlen($normalized) > 30) {
            throw new Exception("Domain extension must be 30 characters or fewer.");
        }
        if (!in_array($category, ['gtld', 'cctld'], true)) {
            throw new Exception("Invalid extension category.");
        }
        if ($sortOrder !== null && ($sortOrder < 1 || $sortOrder > 999999)) {
            throw new Exception("Invalid sort order.");
        }

        $existing = $this->model->getTldByName($normalized);
        if ($existing && (int)($existing['is_active'] ?? 0) === 1) {
            throw new Exception("Domain extension already exists.");
        }

        $baseSort = $category === 'cctld' ? 5000 : 100;
        $finalSort = $sortOrder && $sortOrder > 0 ? $sortOrder : $baseSort;
        $tldId = $this->model->saveTldExtension($normalized, $category, $finalSort);
        if (!$tldId) {
            throw new Exception("Failed to save domain extension.");
        }

        $this->activityLogger->logActivity('created', 'Domain Price Extension', "Saved {$category} extension {$normalized}", $tldId);
        return $tldId;
    }

    /**
     * Update a domain extension's matrix category and display order.
     */
    public function updateTldExtension(int $id, string $category, ?int $sortOrder, string $ipAddress): bool {
        if ($id <= 0) {
            throw new Exception("Invalid extension.");
        }
        if (!in_array($category, ['gtld', 'cctld'], true)) {
            throw new Exception("Invalid extension category.");
        }

        $allTlds = array_merge($this->model->getActiveTlds('gtld'), $this->model->getActiveTlds('cctld'));
        $current = null;
        foreach ($allTlds as $tld) {
            if ((int)$tld['id'] === $id) {
                $current = $tld;
                break;
            }
        }
        if (!$current) {
            throw new Exception("Extension not found.");
        }

        $finalSort = $sortOrder && $sortOrder > 0 ? $sortOrder : (int)($current['sort_order'] ?? ($category === 'cctld' ? 5000 : 100));
        if ($finalSort < 1 || $finalSort > 999999) {
            throw new Exception("Invalid sort order.");
        }

        if (!$this->model->updateTldExtension($id, $category, $finalSort)) {
            throw new Exception("Failed to update domain extension.");
        }

        $this->activityLogger->logActivity('updated', 'Domain Price Extension', "Updated extension {$current['tld_name']}", $id);
        return true;
    }

    /**
     * Soft-delete a domain extension from active pricing matrices.
     */
    public function deleteTldExtension(int $id, string $ipAddress): bool {
        if ($id <= 0) {
            throw new Exception("Invalid extension.");
        }

        $allTlds = array_merge($this->model->getActiveTlds('gtld'), $this->model->getActiveTlds('cctld'));
        $current = null;
        foreach ($allTlds as $tld) {
            if ((int)$tld['id'] === $id) {
                $current = $tld;
                break;
            }
        }
        if (!$current) {
            throw new Exception("Extension not found.");
        }

        if (!$this->model->deactivateTldExtension($id)) {
            throw new Exception("Failed to delete domain extension.");
        }

        $this->activityLogger->logActivity('deleted', 'Domain Price Extension', "Deleted extension {$current['tld_name']}", $id);
        return true;
    }

    /**
     * Add or reactivate a registrar source for the pricing matrix.
     */
    public function addPricingSource(string $sourceName, string $sourceType, ?int $sortOrder, string $ipAddress): int {
        $normalized = trim(preg_replace('/\s+/', ' ', $sourceName));
        if ($normalized === '') {
            throw new Exception("Registrar source name is required.");
        }
        if (strlen($normalized) > 100) {
            throw new Exception("Registrar source name must be 100 characters or fewer.");
        }
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9 .&+_()\/-]*$/', $normalized)) {
            throw new Exception("Registrar source name may only contain letters, numbers, spaces, and basic symbols.");
        }
        if (!in_array($sourceType, ['registrar', 'internal', 'registry'], true)) {
            throw new Exception("Invalid source type.");
        }
        if ($sortOrder !== null && ($sortOrder < 1 || $sortOrder > 999999)) {
            throw new Exception("Invalid source sort order.");
        }

        $existing = $this->model->getSourceByName($normalized);
        if ($existing && (int)($existing['is_active'] ?? 0) === 1) {
            throw new Exception("Registrar source already exists.");
        }

        $finalSort = $sortOrder && $sortOrder > 0 ? $sortOrder : 10;
        if (!$sortOrder) {
            foreach ($this->model->getActiveSources() as $source) {
                if (($source['source_type'] ?? '') === $sourceType) {
                    $finalSort = max($finalSort, (int)($source['sort_order'] ?? 0) + 10);
                }
            }
        }

        $sourceId = $this->model->savePricingSource($normalized, $sourceType, $finalSort);
        if (!$sourceId) {
            throw new Exception("Failed to save registrar source.");
        }

        $this->activityLogger->logActivity('created', 'Domain Price Source', "Saved {$sourceType} source {$normalized}", $sourceId);
        return $sourceId;
    }

    /**
     * Update a registrar source's display type and order.
     */
    public function updatePricingSource(int $id, string $sourceType, ?int $sortOrder, string $ipAddress): bool {
        if ($id <= 0) {
            throw new Exception("Invalid registrar source.");
        }
        if (!in_array($sourceType, ['registrar', 'internal', 'registry'], true)) {
            throw new Exception("Invalid source type.");
        }

        $current = null;
        foreach ($this->model->getActiveSources() as $source) {
            if ((int)$source['id'] === $id) {
                $current = $source;
                break;
            }
        }
        if (!$current) {
            throw new Exception("Registrar source not found.");
        }

        $finalSort = $sortOrder && $sortOrder > 0 ? $sortOrder : (int)($current['sort_order'] ?? 10);
        if ($finalSort < 1 || $finalSort > 999999) {
            throw new Exception("Invalid source sort order.");
        }

        if (!$this->model->updatePricingSource($id, $sourceType, $finalSort)) {
            throw new Exception("Failed to update registrar source.");
        }

        $this->activityLogger->logActivity('updated', 'Domain Price Source', "Updated source {$current['source_name']}", $id);
        return true;
    }

    /**
     * Soft-delete a registrar source from active pricing matrices.
     */
    public function deletePricingSource(int $id, string $ipAddress): bool {
        if ($id <= 0) {
            throw new Exception("Invalid registrar source.");
        }

        $current = null;
        foreach ($this->model->getActiveSources() as $source) {
            if ((int)$source['id'] === $id) {
                $current = $source;
                break;
            }
        }
        if (!$current) {
            throw new Exception("Registrar source not found.");
        }
        if (($current['source_type'] ?? '') !== 'registrar') {
            throw new Exception("Only registrar sources can be disabled here.");
        }
        if (in_array((string)$current['source_name'], ['Liquid Registrar', 'Webnic Registrar'], true)) {
            throw new Exception("Liquid Registrar and Webnic Registrar are required default sources and cannot be disabled.");
        }

        if (!$this->model->deactivatePricingSource($id)) {
            throw new Exception("Failed to disable registrar source.");
        }

        $this->activityLogger->logActivity('deleted', 'Domain Price Source', "Disabled source {$current['source_name']}", $id);
        return true;
    }

    /**
     * Get month details by ID.
     */
    public function getMonthDetails(int $id): ?array {
        $month = $this->model->getMonthById($id);
        if (!$month) return null;
        
        $entries = $this->model->getEntriesForMonth($id);
        $auditLogs = $this->model->getAuditLogs($id);

        return [
            'month' => $month,
            'entries' => $entries,
            'audit_logs' => $auditLogs
        ];
    }

    /**
     * Get TLD notes for a month.
     */
    public function getTldNotes(int $id): array {
        return $this->model->getTldNotes($id);
    }

    /**
     * Check if an intern has access to this month.
     */
    public function hasInternAccess(int $monthId, string $userRoleSlug, int $userId): bool {
        if ($userRoleSlug !== 'intern') {
            return true;
        }
        $task = $this->model->getAssignedTaskForMonth($monthId);
        if ($task && (int)$task['assigned_to'] === $userId) {
            return true;
        }
        return false;
    }

    /**
     * Get assigned task for month.
     */
    public function getAssignedTask(int $monthId): ?array {
        return $this->model->getAssignedTaskForMonth($monthId);
    }

    /**
     * Assign a task for a monthly record.
     */
    public function assignTask(int $monthId, int $assignedTo, string $dueDate, string $priority, string $ipAddress): array {
        $month = $this->model->getMonthById($monthId);
        if (!$month) throw new Exception("Monthly record not found.");

        $userName = function_exists('tracs_current_user_display') ? tracs_current_user_display($this->db) : 'System';
        $title = "Domain Price Crosscheck - " . date('F Y', strtotime($month['month'] . '-01'));

        $this->db->begin_transaction();
        try {
            $taskId = $this->model->createTask($assignedTo, $title, $dueDate, $priority, $this->userId, $userName);
            if (!$taskId) throw new Exception("Failed to create reminder task.");

            if (!$this->model->linkTaskToMonth($monthId, $taskId, $assignedTo, $this->userId)) {
                throw new Exception("Failed to link task to month.");
            }

            $this->model->createNotification(
                $assignedTo,
                "You have been assigned to Domain Price Crosscheck - {$month['month']}.",
                "info",
                "domain_price_crosscheck",
                $monthId,
                $this->userId,
                $userName
            );

            $this->model->logAction(
                $monthId, $this->userId, $userName,
                'task_assigned', "Task assigned to user ID {$assignedTo}",
                $ipAddress, null, null, 'assigned_to', null, (string)$assignedTo
            );

            $this->db->commit();
            $this->activityLogger->logActivity('assigned', 'Domain Price Task', "Assigned month {$month['month']} to user ID {$assignedTo}", $monthId);

            return ['success' => true, 'message' => "Task assigned successfully!"];
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Create a new month record.
     */
    public function createMonthRecord(string $month, float $exchangeRate, string $ipAddress, ?int $templateMonthId = null, bool $copyNotes = false): ?int {
        // Validate month format (YYYY-MM)
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            throw new Exception("Invalid month format. Expected YYYY-MM.");
        }
        if ($exchangeRate <= 0) {
            throw new Exception("Exchange rate must be positive.");
        }

        // Check if month already exists
        $existing = $this->model->getMonthByCode($month);
        if ($existing) {
            throw new Exception("A monthly record for this period already exists. Please select the existing record instead.");
        }

        $this->db->begin_transaction();
        try {
            $id = $this->model->createMonth($month, $exchangeRate, $this->userId);
            if (!$id) {
                $this->db->rollback();
                return null;
            }
            $userName = function_exists('tracs_current_user_display') ? tracs_current_user_display($this->db) : 'System';
            $copiedEntries = 0;
            $copiedNotes = 0;
            if ($templateMonthId) {
                $template = $this->model->getMonthById($templateMonthId);
                if (!$template) {
                    throw new Exception("Previous monthly record template not found.");
                }
                $copiedEntries = $this->model->copyMonthEntries($templateMonthId, $id, $this->userId);
                if ($copyNotes) {
                    $copiedNotes = $this->model->copyMonthNotes($templateMonthId, $id, $this->userId);
                }
            }
            $this->model->logAction(
                $id,
                $this->userId,
                $userName,
                $templateMonthId ? 'duplicated_from_template' : 'created',
                $templateMonthId
                    ? "Created monthly draft record for {$month} using previous month pricing template ID {$templateMonthId}. Copied {$copiedEntries} editable price entries" . ($copyNotes ? " and {$copiedNotes} note records" : "") . ". Exchange rate should be reviewed."
                    : "Created monthly draft record for {$month} with exchange rate IDR " . number_format($exchangeRate, 2),
                $ipAddress,
                null,
                null,
                'status',
                null,
                'draft'
            );
            $this->db->commit();
            $this->activityLogger->logActivity('created', 'Domain Price', "Created monthly draft record for {$month}", $id);
            return $id;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Update exchange rate (draft months only).
     */
    public function updateExchangeRate(int $monthId, float $exchangeRate, string $ipAddress, ?string $changeReason = null): bool {
        $month = $this->model->getMonthById($monthId);
        if (!$month) throw new Exception("Monthly record not found.");
        if ($month['status'] !== 'draft') {
            throw new Exception("Cannot update exchange rate of non-draft record.");
        }
        if ($exchangeRate <= 0) {
            throw new Exception("Exchange rate must be positive.");
        }

        $oldExchangeRate = (float)$month['exchange_rate_usd_idr'];
        if (abs($oldExchangeRate - $exchangeRate) < 0.0001) {
            // Unchanged: prevent duplicate log writes and return true
            return true;
        }

        if ($this->model->updateExchangeRate($monthId, $exchangeRate, $this->userId)) {
            $userName = function_exists('tracs_current_user_display') ? tracs_current_user_display($this->db) : 'System';
            $this->model->logAction(
                $monthId,
                $this->userId,
                $userName,
                'exchange_rate_updated',
                "Updated exchange rate from IDR " . number_format($oldExchangeRate, 2) . " to IDR " . number_format($exchangeRate, 2) . ($changeReason ? " (Reason: {$changeReason})" : ""),
                $ipAddress,
                null,
                null,
                'exchange_rate_usd_idr',
                (string)$oldExchangeRate,
                (string)$exchangeRate,
                $changeReason
            );
            return true;
        }
        return false;
    }

    /**
     * Submit month for review.
     */
    public function submitForReview(int $monthId, string $ipAddress): bool {
        $month = $this->model->getMonthById($monthId);
        if (!$month) throw new Exception("Monthly record not found.");
        if ($month['status'] !== 'draft') {
            throw new Exception("Only draft records can be submitted for review.");
        }

        $userName = function_exists('tracs_current_user_display') ? tracs_current_user_display($this->db) : 'System';

        $this->model->updateMonthStatus($monthId, 'pending_review', $this->userId);
        
        // Sync Task
        $task = $this->model->getAssignedTaskForMonth($monthId);
        if ($task) {
            // Notify the creator of the task that review is ready
            if ($task['created_by']) {
                $this->model->createNotification(
                    $task['created_by'],
                    "{$month['month']} Domain Price Crosscheck is waiting for review.",
                    "info", "domain_price_crosscheck", $monthId, $this->userId, $userName
                );
            }
        }

        $this->model->logAction(
            $monthId,
            $this->userId,
            $userName,
            'submitted',
            "Submitted monthly record {$month['month']} for review",
            $ipAddress,
            null,
            null,
            'status',
            'draft',
            'pending_review'
        );
        $this->activityLogger->logActivity('submitted', 'Domain Price', "Submitted monthly record {$month['month']} for review", $monthId);
        return true;
    }

    /**
     * Revert month record back to draft (In Progress).
     */
    public function revertToDraft(int $monthId, string $ipAddress): bool {
        $month = $this->model->getMonthById($monthId);
        if (!$month) throw new Exception("Monthly record not found.");
        if ($month['status'] !== 'pending_review') {
            throw new Exception("Only pending_review records can be reverted to draft.");
        }

        $userName = function_exists('tracs_current_user_display') ? tracs_current_user_display($this->db) : 'System';

        $this->model->updateMonthStatus($monthId, 'draft', $this->userId);

        // Sync Task
        $task = $this->model->getAssignedTaskForMonth($monthId);
        if ($task) {
            $this->model->syncTaskStatus($task['task_id'], false, $this->userId);
            $this->model->createNotification(
                $task['assigned_to'],
                "{$month['month']} Domain Price Crosscheck has been unlocked for revision.",
                "warning", "domain_price_crosscheck", $monthId, $this->userId, $userName
            );
        }

        $this->model->logAction(
            $monthId,
            $this->userId,
            $userName,
            'reverted_to_draft',
            "Reverted monthly record {$month['month']} to Draft",
            $ipAddress,
            null,
            null,
            'status',
            'pending_review',
            'draft'
        );
        $this->activityLogger->logActivity('reverted_to_draft', 'Domain Price', "Reverted monthly record {$month['month']} to draft", $monthId);
        return true;
    }

    /**
     * Approve and lock monthly snapshot.
     */
    public function approveAndLock(int $monthId, string $note, string $ipAddress): bool {
        $month = $this->model->getMonthById($monthId);
        if (!$month) throw new Exception("Monthly record not found.");
        if ($month['status'] !== 'pending_review') {
            throw new Exception("Only pending_review records can be approved.");
        }
        $userName = function_exists('tracs_current_user_display') ? tracs_current_user_display($this->db) : 'System';

        if ($this->model->updateMonthStatus($monthId, 'approved', $this->userId, $note)) {
            // Sync Task
            $task = $this->model->getAssignedTaskForMonth($monthId);
            if ($task) {
                $this->model->syncTaskStatus($task['task_id'], true, $this->userId);
                $this->model->createNotification(
                    $task['assigned_to'],
                    "{$month['month']} Domain Price Crosscheck has been approved.",
                    "success", "domain_price_crosscheck", $monthId, $this->userId, $userName
                );
            }

            $this->model->logAction(
                $monthId,
                $this->userId,
                $userName,
                'approved',
                "Approved monthly record {$month['month']} and locked snapshot. Note: {$note}",
                $ipAddress,
                null,
                null,
                'status',
                'pending_review',
                'approved',
                $note
            );
            $this->activityLogger->logActivity('approved', 'Domain Price', "Approved monthly record {$month['month']}", $monthId);
            return true;
        }
        return false;
    }

    /**
     * Unlock an approved month record.
     */
    public function unlockMonth(int $monthId, string $reason, string $ipAddress): bool {
        $month = $this->model->getMonthById($monthId);
        if (!$month) throw new Exception("Monthly record not found.");
        if ($month['status'] !== 'approved') {
            throw new Exception("Only approved records can be unlocked.");
        }

        if ($this->model->unlockMonth($monthId, $this->userId, $reason)) {
            $userName = function_exists('tracs_current_user_display') ? tracs_current_user_display($this->db) : 'System';
            $this->model->logAction(
                $monthId,
                $this->userId,
                $userName,
                'unlocked',
                "Unlocked monthly record {$month['month']}. Reason: {$reason}",
                $ipAddress,
                null,
                null,
                'status',
                'approved',
                'draft',
                $reason
            );
            $this->activityLogger->logActivity('unlocked', 'Domain Price', "Unlocked monthly record {$month['month']}", $monthId);
            return true;
        }
        return false;
    }

    /**
     * Duplicate a previous month into an editable draft template.
     */
    public function duplicatePreviousMonth(int $fromMonthId, string $newMonth, float $exchangeRate, string $ipAddress, bool $copyNotes = false): ?int {
        // Validate month format (YYYY-MM)
        if (!preg_match('/^\d{4}-\d{2}$/', $newMonth)) {
            throw new Exception("Invalid month format. Expected YYYY-MM.");
        }
        if ($exchangeRate <= 0) {
            throw new Exception("Exchange rate must be positive.");
        }

        // Check if month already exists
        $existing = $this->model->getMonthByCode($newMonth);
        if ($existing) {
            throw new Exception("A monthly record for this period already exists. Please select the existing record instead.");
        }

        $this->db->begin_transaction();
        try {
            $id = $this->model->duplicateMonthMetadata($fromMonthId, $newMonth, $exchangeRate, $this->userId);
            if (!$id) {
                $this->db->rollback();
                return null;
            }
            $userName = function_exists('tracs_current_user_display') ? tracs_current_user_display($this->db) : 'System';
            $copiedEntries = $this->model->copyMonthEntries($fromMonthId, $id, $this->userId);
            $copiedNotes = $copyNotes ? $this->model->copyMonthNotes($fromMonthId, $id, $this->userId) : 0;
            $this->model->logAction(
                $id,
                $this->userId,
                $userName,
                'duplicated_from_template',
                "Created editable draft for {$newMonth} from month ID {$fromMonthId}. Copied {$copiedEntries} editable price entries" . ($copyNotes ? " and {$copiedNotes} note records" : "") . ". Approval metadata, audit logs, and task state were not copied.",
                $ipAddress,
                null,
                null,
                null,
                null,
                null,
                "Duplicated from month ID {$fromMonthId}"
            );
            $this->db->commit();
            $this->activityLogger->logActivity('created', 'Domain Price', "Created monthly record for {$newMonth} by duplicating", $id);
            return $id;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Save a batch of price matrix entries.
     */
    public function saveMatrixEntries(int $monthId, array $entries, string $ipAddress): array {
        // 1. Validate Month exists and is in 'draft' status
        $month = $this->model->getMonthById($monthId);
        if (!$month) {
            throw new Exception("Monthly record not found.");
        }
        if ($month['status'] !== 'draft') {
            throw new Exception("Price entries can only be saved for records in 'Draft' status.");
        }

        // 2. Fetch existing entries to compare for auditing
        $existingRaw = $this->model->getEntriesForMonth($monthId);
        $existing = [];
        foreach ($existingRaw as $entry) {
            $key = "{$entry['tld_id']}_" . ($entry['source_id'] ?? 'null') . "_{$entry['price_type']}";
            $existing[$key] = $entry;
        }

        // Get monthly exchange rate
        $exchangeRate = (float)$month['exchange_rate_usd_idr'];

        // Derive the editable IDCH Internal Pricing draft from the lowest active
        // registrar USD value. Matrix order is the deterministic tie-breaker.
        $activeSources = $this->model->getActiveSources();
        $activeRegistrarMeta = [];
        $internalSourceId = null;
        foreach ($activeSources as $source) {
            $sourceId = (int)$source['id'];
            if (($source['source_type'] ?? '') === 'registrar') {
                $activeRegistrarMeta[$sourceId] = [
                    'name' => (string)$source['source_name'],
                    'sort_order' => (int)$source['sort_order'],
                ];
            }
            if (($source['source_name'] ?? '') === 'IDCH Internal Pricing' || ($source['source_type'] ?? '') === 'internal') {
                $internalSourceId = $sourceId;
            }
        }

        if ($internalSourceId !== null && $exchangeRate > 0 && $activeRegistrarMeta) {
            $parseDraftValue = static function ($rawValue): ?float {
                $raw = trim((string)$rawValue);
                if ($raw === '') {
                    return null;
                }
                $clean = preg_replace('/[^\d.]/', '', str_replace(',', '', $raw));
                if ($clean === '' || !is_numeric($clean)) {
                    throw new Exception("Invalid numeric pricing value.");
                }
                $numeric = (float)$clean;
                if ($numeric < 0) {
                    throw new Exception("Pricing values must be non-negative.");
                }
                return $numeric;
            };

            $registrarCandidates = [];
            $internalEntryIndexes = [];
            foreach ($entries as $index => $item) {
                $tldId = (int)($item['tld_id'] ?? 0);
                $sourceId = isset($item['source_id']) && $item['source_id'] !== '' && $item['source_id'] !== null
                    ? (int)$item['source_id']
                    : null;
                $priceType = trim((string)($item['price_type'] ?? ''));
                $currency = strtoupper(trim((string)($item['currency'] ?? '')));

                if ($sourceId === $internalSourceId && in_array($priceType, ['cost_register', 'cost_renewal'], true)) {
                    $internalEntryIndexes["{$tldId}_{$priceType}"] = $index;
                }
                if (
                    $tldId <= 0
                    || !isset($activeRegistrarMeta[$sourceId])
                    || !in_array($priceType, ['cost_register', 'cost_renewal'], true)
                    || $currency !== 'USD'
                ) {
                    continue;
                }

                $usdValue = $parseDraftValue($item['value'] ?? '');
                if ($usdValue === null) {
                    continue;
                }
                $registrarCandidates["{$tldId}_{$priceType}"][] = [
                    'tld_id' => $tldId,
                    'price_type' => $priceType,
                    'source_id' => $sourceId,
                    'source_name' => $activeRegistrarMeta[$sourceId]['name'],
                    'sort_order' => $activeRegistrarMeta[$sourceId]['sort_order'],
                    'usd_value' => $usdValue,
                ];
            }

            foreach ($registrarCandidates as $key => $candidates) {
                usort($candidates, static function (array $left, array $right): int {
                    $valueComparison = $left['usd_value'] <=> $right['usd_value'];
                    if ($valueComparison !== 0) {
                        return $valueComparison;
                    }
                    $orderComparison = $left['sort_order'] <=> $right['sort_order'];
                    return $orderComparison !== 0 ? $orderComparison : ($left['source_id'] <=> $right['source_id']);
                });

                $selected = $candidates[0];
                $derivedValue = $selected['usd_value'] * $exchangeRate * 1.30;
                $derivedEntry = [
                    'tld_id' => $selected['tld_id'],
                    'source_id' => $internalSourceId,
                    'price_type' => $selected['price_type'],
                    'currency' => 'IDR',
                    // domain_price_entries.original_value is DECIMAL(15,4).
                    'value' => number_format($derivedValue, 4, '.', ''),
                    'derived_from_kurs' => true,
                    'calculation_note' => "Calculated from {$selected['source_name']} USD x KURS x 1.30.",
                ];

                if (isset($internalEntryIndexes[$key])) {
                    $entries[$internalEntryIndexes[$key]] = $derivedEntry;
                } else {
                    $entries[] = $derivedEntry;
                }
            }
        }

        $savedCount = 0;
        $deletedCount = 0;
        $userName = function_exists('tracs_current_user_display') ? tracs_current_user_display($this->db) : 'System';

        $this->db->begin_transaction();

        try {
            foreach ($entries as $item) {
                $tldId = (int)($item['tld_id'] ?? 0);
                $sourceId = isset($item['source_id']) && $item['source_id'] !== '' && $item['source_id'] !== null ? (int)$item['source_id'] : null;
                $priceType = trim($item['price_type'] ?? '');
                $currency = strtoupper(trim($item['currency'] ?? ''));
                $rawValue = trim($item['value'] ?? '');

                if ($tldId <= 0 || empty($priceType)) {
                    continue;
                }

                // Check key
                $key = "{$tldId}_" . ($sourceId ?? 'null') . "_{$priceType}";
                $oldEntry = $existing[$key] ?? null;
                $oldValStr = $oldEntry ? (string)$oldEntry['original_value'] : null;

                // If value is empty or cleared
                if ($rawValue === '') {
                    if ($oldEntry !== null) {
                        // Delete entry
                        $this->model->deleteEntry($monthId, $tldId, $sourceId, $priceType);
                        $deletedCount++;

                        // Log audit trail
                        $this->model->logAction(
                            $monthId,
                            $this->userId,
                            $userName,
                            'price_deleted',
                            "Cleared {$priceType} value for TLD ID {$tldId}" . ($sourceId ? " and Source ID {$sourceId}" : ""),
                            $ipAddress,
                            $tldId,
                            $sourceId,
                            $priceType,
                            $oldValStr,
                            null
                        );
                    }
                    continue;
                }

                // Clean value: remove currency prefix and thousands separator
                // Accepted formats: 7, 7.00, $7.00, 122500, Rp122,500
                $cleanValueStr = preg_replace('/[^\d.]/', '', str_replace(',', '', $rawValue));
                if (!is_numeric($cleanValueStr)) {
                    throw new Exception("Invalid numeric format: '{$rawValue}'");
                }
                $cleanValue = (float)$cleanValueStr;

                if ($cleanValue < 0) {
                    throw new Exception("Pricing values must be non-negative.");
                }

                // Process currencies and values
                $usdValue = 0.0;
                $idrValue = 0.0;
                $calculatedFromKurs = 0;

                if ($currency === 'USD') {
                    if ($exchangeRate <= 0) {
                        throw new Exception("Set the monthly USD to IDR exchange rate before entering USD prices.");
                    }
                    $usdValue = $cleanValue;
                    $idrValue = $usdValue * $exchangeRate;
                    $calculatedFromKurs = 1;
                } elseif ($currency === 'IDR') {
                    $idrValue = $cleanValue;
                    $usdValue = 0.0; // Selling prices in IDR do not calculate backward USD
                    $calculatedFromKurs = !empty($item['derived_from_kurs']) ? 1 : 0;
                } else {
                    throw new Exception("Invalid currency specified: '{$currency}'");
                }

                // Compare if value actually changed
                $hasChanged = true;
                if ($oldEntry !== null) {
                    // Check if currency and original value are the same
                    if ($oldEntry['currency'] === $currency && abs((float)$oldEntry['original_value'] - $cleanValue) < 0.0001) {
                        $hasChanged = false;
                    }
                }

                if ($hasChanged) {
                    $data = [
                        'month_id' => $monthId,
                        'tld_id' => $tldId,
                        'source_id' => $sourceId,
                        'price_type' => $priceType,
                        'currency' => $currency,
                        'original_value' => $cleanValue,
                        'usd_value' => $usdValue,
                        'idr_value' => $idrValue,
                        'calculated_from_kurs' => $calculatedFromKurs,
                        'created_by' => $this->userId
                    ];

                    if (!$this->model->saveEntry($data)) {
                        throw new Exception("Failed to save price entry.");
                    }
                    $savedCount++;

                    // Log audit trail
                    $newPrecision = !empty($item['derived_from_kurs']) ? 4 : 2;
                    $oldPrecision = $oldEntry && (float)($oldEntry['calculated_from_kurs'] ?? 0) > 0 && $oldEntry['currency'] === 'IDR' ? 4 : 2;
                    $newValStr = ($currency === 'USD' ? '$' : 'Rp') . number_format($cleanValue, $newPrecision);
                    $oldValFormatted = $oldEntry
                        ? ($oldEntry['currency'] === 'USD' ? '$' : 'Rp') . number_format((float)$oldEntry['original_value'], $oldPrecision)
                        : null;
                    
                    $calculationNote = trim((string)($item['calculation_note'] ?? ''));
                    $this->model->logAction(
                        $monthId,
                        $this->userId,
                        $userName,
                        $oldEntry ? 'price_updated' : 'price_created',
                        "Set {$priceType} to {$newValStr} (Currency: {$currency}) for TLD ID {$tldId}" . ($sourceId ? " and Source ID {$sourceId}" : "") . ($calculationNote !== '' ? " {$calculationNote}" : ""),
                        $ipAddress,
                        $tldId,
                        $sourceId,
                        $priceType,
                        $oldValFormatted,
                        $newValStr
                    );
                }
            }

            $this->db->commit();

            if ($savedCount > 0 || $deletedCount > 0) {
                $this->activityLogger->logActivity('updated', 'Domain Price', "Saved matrix changes: saved {$savedCount}, cleared {$deletedCount} entries for month {$month['month']}", $monthId);
            }

            return [
                'success' => true,
                'saved' => $savedCount,
                'deleted' => $deletedCount,
                'message' => "Matrix saved successfully! Updated {$savedCount} cells and cleared {$deletedCount} cells."
            ];

        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Recalculate and persist summary data for all TLDs in a month.
     * Implements: lowest-source detection, margin analysis, below-cost detection,
     * MoM cost comparison, auto_status and suggested_action priority rules.
     *
     * Auto Status priority (Phase 5):
     *   1. Below Cost  2. Below 30% target margin  3. Missing Data
     *   4. Registrar Cost Increased  5. Source Changed  6. Safe
     */
    public function recalculateSummary(int $monthId, string $ipAddress): array {
        $month = $this->model->getMonthById($monthId);
        if (!$month) throw new Exception("Monthly record not found.");
        if ($month['status'] === 'approved') {
            throw new Exception("Approved monthly records must be unlocked before recalculation.");
        }

        $exchangeRate = (float)$month['exchange_rate_usd_idr'];
        $activeTlds   = $this->model->getActiveTlds('gtld');

        // Fetch all entries for this month, keyed [tld_id][price_type] => entry
        $rawEntries = $this->model->getEntriesForMonth($monthId);
        $entriesMap = [];
        foreach ($rawEntries as $e) {
            $entriesMap[$e['tld_id']][$e['price_type']][] = $e;
        }

        // Fetch previous month baseline keyed by tld_id
        $prevSummaries = $this->model->getPreviousMonthSummaries($monthId);

        $belowCostCount = 0;
        $lowMarginCount = 0;
        $totalTlds      = count($activeTlds);
        $savedCount     = 0;

        $userName = function_exists('tracs_current_user_display')
            ? tracs_current_user_display($this->db) : 'System';

        $this->db->begin_transaction();
        try {
            foreach ($activeTlds as $tld) {
                $tldId       = (int)$tld['id'];
                $tldEntries  = $entriesMap[$tldId] ?? [];

                // ── 1. Lowest registrar cost (register & renewal) ──────────────────
                $lowestRegCost = null;
                $lowestRegSrcId = null;
                $lowestRenCost = null;
                $lowestRenSrcId = null;

                foreach ($tldEntries as $priceType => $entryList) {
                    foreach ($entryList as $entry) {
                        if (!$entry['source_id']) continue; // skip selling prices
                        $idr = (float)$entry['idr_value'];
                        if ($priceType === 'cost_register') {
                            if ($lowestRegCost === null || $idr < $lowestRegCost) {
                                $lowestRegCost  = $idr;
                                $lowestRegSrcId = (int)$entry['source_id'];
                            }
                        } elseif ($priceType === 'cost_renewal') {
                            if ($lowestRenCost === null || $idr < $lowestRenCost) {
                                $lowestRenCost  = $idr;
                                $lowestRenSrcId = (int)$entry['source_id'];
                            }
                        }
                    }
                }

                // ── 2. Selling prices (IDCH Website & PAAS) ───────────────────────
                $websiteRegPrice = null;
                $websiteRenPrice = null;
                $paasRegPrice    = null;
                $paasRenPrice    = null;

                foreach ($tldEntries as $priceType => $entryList) {
                    foreach ($entryList as $entry) {
                        $idr = (float)$entry['idr_value'];
                        if ($priceType === 'selling_website_register') $websiteRegPrice = $idr;
                        if ($priceType === 'selling_website_renewal')  $websiteRenPrice = $idr;
                        if ($priceType === 'selling_paas_register')    $paasRegPrice    = $idr;
                        if ($priceType === 'selling_paas_renewal')     $paasRenPrice    = $idr;
                    }
                }

                // ── 3. Margin calculations ─────────────────────────────────────────
                $calcMarginPct = function(?float $selling, ?float $cost): ?float {
                    if ($selling === null || $cost === null || $cost <= 0) return null;
                    return (($selling - $cost) / $cost) * 100.0;
                };

                $websiteRegMargin    = ($websiteRegPrice !== null && $lowestRegCost !== null) ? $websiteRegPrice - $lowestRegCost : null;
                $websiteRenMargin    = ($websiteRenPrice !== null && $lowestRenCost !== null) ? $websiteRenPrice - $lowestRenCost : null;
                $paasRegMargin       = ($paasRegPrice !== null && $lowestRegCost !== null) ? $paasRegPrice - $lowestRegCost : null;
                $paasRenMargin       = ($paasRenPrice !== null && $lowestRenCost !== null) ? $paasRenPrice - $lowestRenCost : null;

                $websiteRegMarginPct = $calcMarginPct($websiteRegPrice, $lowestRegCost);
                $websiteRenMarginPct = $calcMarginPct($websiteRenPrice, $lowestRenCost);
                $paasRegMarginPct    = $calcMarginPct($paasRegPrice, $lowestRegCost);
                $paasRenMarginPct    = $calcMarginPct($paasRenPrice, $lowestRenCost);

                // ── 4. Below-cost flags ────────────────────────────────────────────
                $websiteBelowCostReg = ($websiteRegMargin !== null && $websiteRegMargin < 0) ? 1 : 0;
                $websiteBelowCostRen = ($websiteRenMargin !== null && $websiteRenMargin < 0) ? 1 : 0;
                $paasBelowCostReg    = ($paasRegMargin !== null && $paasRegMargin < 0) ? 1 : 0;
                $paasBelowCostRen    = ($paasRenMargin !== null && $paasRenMargin < 0) ? 1 : 0;

                if ($websiteBelowCostReg || $websiteBelowCostRen || $paasBelowCostReg || $paasBelowCostRen) {
                    $belowCostCount++;
                }

                // ── 5. MoM comparison ─────────────────────────────────────────────
                $prev = $prevSummaries[$tldId] ?? null;
                $prevRegCost = $prev ? (float)$prev['lowest_register_cost'] : null;
                $prevRenCost = $prev ? (float)$prev['lowest_renewal_cost']  : null;

                $regDiff    = ($lowestRegCost !== null && $prevRegCost !== null) ? $lowestRegCost - $prevRegCost : null;
                $renDiff    = ($lowestRenCost !== null && $prevRenCost !== null) ? $lowestRenCost - $prevRenCost : null;
                $regChgPct  = ($prevRegCost !== null && $prevRegCost > 0 && $regDiff !== null) ? ($regDiff / $prevRegCost) * 100.0 : null;
                $renChgPct  = ($prevRenCost !== null && $prevRenCost > 0 && $renDiff !== null) ? ($renDiff / $prevRenCost) * 100.0 : null;

                // ── 6. Auto status & suggested action (priority rules) ────────────
                $isMissingData = ($lowestRegCost === null && $lowestRenCost === null);

                // Determine worst margin
                $marginPcts = array_filter([$websiteRegMarginPct, $websiteRenMarginPct, $paasRegMarginPct, $paasRenMarginPct], fn($v) => $v !== null);
                $worstMarginPct = $marginPcts ? min($marginPcts) : null;

                $priceIncreased = ($regChgPct !== null && $regChgPct > 0) || ($renChgPct !== null && $renChgPct > 0);
                $sourceChanged  = $prev && (
                    ($lowestRegSrcId !== null && (int)$prev['lowest_register_source_id'] !== $lowestRegSrcId) ||
                    ($lowestRenSrcId !== null && (int)$prev['lowest_renewal_source_id'] !== $lowestRenSrcId)
                );

                $isBelowCost = ($websiteBelowCostReg || $websiteBelowCostRen || $paasBelowCostReg || $paasBelowCostRen);
                $isBelowTargetMargin = $worstMarginPct !== null && $worstMarginPct < 30.0;

                if ($isBelowTargetMargin && !$isBelowCost) $lowMarginCount++;

                // Priority: 1>Below Cost  2>Below Target Margin  3>Missing  4>Price Up  5>Source Changed  6>Safe
                $autoStatus     = 'Safe';
                $suggestedAction = 'Keep Current Website Price';
                if ($isBelowCost) {
                    $autoStatus      = 'Below Cost';
                    $suggestedAction = 'Increase Website Price Immediately';
                } elseif ($isMissingData) {
                    $autoStatus      = 'Missing Data';
                    $suggestedAction = 'Complete Missing Data';
                } elseif ($isBelowTargetMargin) {
                    $autoStatus      = 'Below Target Margin';
                    $suggestedAction = 'Adjust Website Price to Target Margin';
                } elseif ($priceIncreased) {
                    $autoStatus      = 'Registrar Cost Increased';
                    $suggestedAction = 'Review Registrar Cost Change';
                } elseif ($sourceChanged) {
                    $autoStatus      = 'Recommended Source Changed';
                    $suggestedAction = 'Review Source Change';
                }

                // ── 7. Persist ────────────────────────────────────────────────────
                $summaryData = [
                    'lowest_register_source_id'   => $lowestRegSrcId,
                    'lowest_renewal_source_id'    => $lowestRenSrcId,
                    'lowest_register_cost'        => $lowestRegCost,
                    'lowest_renewal_cost'         => $lowestRenCost,
                    'website_register_price'      => $websiteRegPrice,
                    'website_renewal_price'       => $websiteRenPrice,
                    'paas_register_price'         => $paasRegPrice,
                    'paas_renewal_price'          => $paasRenPrice,
                    'website_margin_register'     => $websiteRegMargin,
                    'website_margin_renewal'      => $websiteRenMargin,
                    'website_margin_register_pct' => $websiteRegMarginPct,
                    'website_margin_renewal_pct'  => $websiteRenMarginPct,
                    'paas_margin_register'        => $paasRegMargin,
                    'paas_margin_renewal'         => $paasRenMargin,
                    'paas_margin_register_pct'    => $paasRegMarginPct,
                    'paas_margin_renewal_pct'     => $paasRenMarginPct,
                    'website_below_cost_register' => $websiteBelowCostReg,
                    'website_below_cost_renewal'  => $websiteBelowCostRen,
                    'paas_below_cost_register'    => $paasBelowCostReg,
                    'paas_below_cost_renewal'     => $paasBelowCostRen,
                    'prev_lowest_register_cost'   => $prevRegCost,
                    'prev_lowest_renewal_cost'    => $prevRenCost,
                    'cost_register_diff'          => $regDiff,
                    'cost_renewal_diff'           => $renDiff,
                    'cost_register_change_pct'    => $regChgPct,
                    'cost_renewal_change_pct'     => $renChgPct,
                    'auto_status'                 => $autoStatus,
                    'suggested_action'            => $suggestedAction,
                ];

                if ($this->model->saveSummaryComputed($monthId, $tldId, $summaryData)) {
                    $savedCount++;
                }
            }

            $this->db->commit();

            $this->model->logAction(
                $monthId, $this->userId, $userName,
                'recalculated',
                "Recalculated summaries for {$savedCount}/{$totalTlds} TLDs. Below-cost: {$belowCostCount}, Low-margin: {$lowMarginCount}.",
                $ipAddress
            );
            $this->activityLogger->logActivity('updated', 'Domain Price', "Recalculated monthly summary for month {$month['month']}", $monthId);

            return [
                'success'          => true,
                'tld_count'        => $totalTlds,
                'saved_count'      => $savedCount,
                'below_cost_count' => $belowCostCount,
                'low_margin_count' => $lowMarginCount,
                'message'          => "Summary recalculated: {$belowCostCount} below-cost, {$lowMarginCount} low-margin TLDs.",
            ];

        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Get computed summary stats for the stat cards.
     */
    public function getComputedSummaryStats(int $monthId): array {
        $summaries    = $this->model->getComputedSummaries($monthId);
        $belowCost    = 0;
        $lowMargin    = 0;
        $totalTlds    = count($summaries);

        foreach ($summaries as $s) {
            if ($s['website_below_cost_register'] || $s['website_below_cost_renewal'] ||
                $s['paas_below_cost_register']    || $s['paas_below_cost_renewal']) {
                $belowCost++;
            }
            $pcts = array_filter([
                $s['website_margin_register_pct'] ?? null,
                $s['website_margin_renewal_pct']  ?? null,
                $s['paas_margin_register_pct']    ?? null,
                $s['paas_margin_renewal_pct']     ?? null,
            ], fn($v) => $v !== null);
            if ($pcts && min($pcts) < 30.0) $lowMargin++;
        }

        return [
            'total_tlds'       => $totalTlds,
            'below_cost_count' => $belowCost,
            'low_margin_count' => $lowMargin,
            'summaries'        => $summaries,
        ];
    }

    /**
     * Save a manual note for a specific TLD.
     */
    public function saveTldNote(int $monthId, int $tldId, string $manualNote, string $detailedNote, string $followUpStatus, string $ipAddress): array {
        // 1. Validate Month exists and is NOT approved/archived
        $month = $this->model->getMonthById($monthId);
        if (!$month) {
            throw new Exception("Monthly record not found.");
        }
        if ($month['status'] === 'approved') {
            throw new Exception("Cannot edit notes on an approved monthly snapshot.");
        }

        // 2. Fetch existing note to compare for auditing
        $oldNote = $this->model->getTldNote($monthId, $tldId);

        $this->db->begin_transaction();

        try {
            $userName = function_exists('tracs_current_user_display') ? tracs_current_user_display($this->db) : 'System';
            
            // Check changes for auditing
            $hasManualNoteChanged = !$oldNote || ($oldNote['manual_note'] !== $manualNote);
            $hasDetailedNoteChanged = !$oldNote || ($oldNote['detailed_note'] !== $detailedNote);
            $hasStatusChanged = !$oldNote || ($oldNote['follow_up_status'] !== $followUpStatus);

            if (!$this->model->saveTldNote($monthId, $tldId, $manualNote, $detailedNote, $followUpStatus, $this->userId)) {
                throw new Exception("Failed to save TLD note.");
            }

            if ($hasManualNoteChanged) {
                $this->model->logAction(
                    $monthId, $this->userId, $userName,
                    $oldNote ? 'manual_note_updated' : 'manual_note_created',
                    "Updated manual note for TLD ID {$tldId}",
                    $ipAddress, $tldId, null, 'manual_note',
                    $oldNote ? $oldNote['manual_note'] : null,
                    $manualNote
                );
            }

            if ($hasStatusChanged) {
                $this->model->logAction(
                    $monthId, $this->userId, $userName,
                    $oldNote ? 'follow_up_status_updated' : 'follow_up_status_created',
                    "Updated follow-up status for TLD ID {$tldId}",
                    $ipAddress, $tldId, null, 'follow_up_status',
                    $oldNote ? $oldNote['follow_up_status'] : null,
                    $followUpStatus
                );
            }

            $this->db->commit();
            $this->activityLogger->logActivity('updated', 'Domain Price', "Updated notes for TLD ID {$tldId} in month {$month['month']}", $monthId);

            return [
                'success' => true,
                'message' => "Note saved successfully!"
            ];

        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
}
