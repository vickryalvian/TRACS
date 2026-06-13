<?php

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../modules/shifting-assignment/ShiftingAssignmentService.php';

$service = new ShiftingAssignmentService($conn, $uid);
$action = trim((string)($_GET['action'] ?? $body['action'] ?? $_POST['action'] ?? 'data'));

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if ($action === 'assignment') {
            $id = (int)($_GET['id'] ?? 0);
            $assignment = $service->getAssignment($id);
            if (!$assignment) fail_not_found();
            ok($assignment);
        }
        if ($action === 'history') {
            ok($service->getAssignmentHistory((int)($_GET['id'] ?? 0)));
        }
        if ($action === 'monthly_template') {
            $template = $service->getMonthlyTemplate((int)($_GET['id'] ?? 0));
            if (!$template) fail_not_found();
            ok($template);
        }
        ok($service->getPageData([
            'start' => $_GET['start'] ?? null,
            'end' => $_GET['end'] ?? null,
            'division_id' => $_GET['division_id'] ?? null,
            'user_id' => $_GET['user_id'] ?? null,
            'assignment_type' => $_GET['assignment_type'] ?? null,
            'status' => $_GET['status'] ?? null,
            'holiday_only' => $_GET['holiday_only'] ?? null,
            'q' => $_GET['q'] ?? null,
        ]));
    }

    $input = !empty($_POST) ? $_POST : $body;
    $action = trim((string)($input['action'] ?? $action));
    switch ($action) {
        case 'save_assignment':
            $result = $service->saveAssignment($input);
            logAct($conn, $uid, empty($input['id']) ? 'created' : 'updated', 'Shifting Assignment', 'Saved workforce schedule assignment', $result['id']);
            ok($result, 'Assignment saved.');

        case 'resize_assignment':
            $result = $service->resizeAssignment(
                (int)($input['id'] ?? 0),
                (string)($input['start_datetime'] ?? ''),
                (string)($input['end_datetime'] ?? '')
            );
            logAct($conn, $uid, 'resized', 'Shifting Assignment', 'Resized workforce schedule assignment', $result['id']);
            ok($result, 'Shift updated successfully.');

        case 'update_status':
            $id = (int)($input['id'] ?? 0);
            $service->updateAssignmentStatus($id, (string)($input['status'] ?? ''));
            logAct($conn, $uid, 'status_changed', 'Shifting Assignment', 'Updated workforce assignment status', $id);
            ok(['id' => $id], 'Assignment status updated.');

        case 'confirm_assignment':
            $id = (int)($input['id'] ?? 0);
            $service->confirmAssignment($id);
            logAct($conn, $uid, 'approved', 'Shifting Assignment', 'Confirmed workforce assignment', $id);
            ok(['id' => $id], 'Assignment confirmed.');

        case 'dismiss_warning':
            $service->dismissWarning($input);
            logAct(
                $conn,
                $uid,
                'warning_dismissed',
                'Shifting Assignment',
                'Dismissed smart warning: ' . (string)($input['warning_type'] ?? 'warning'),
                !empty($input['assignment_id']) ? (int)$input['assignment_id'] : null
            );
            ok(null, 'Warning dismissed.');

        case 'copy_last_week':
            $count = $service->copyLastWeek((string)($input['start'] ?? date('Y-m-d')), !empty($input['division_id']) ? (int)$input['division_id'] : null);
            logAct($conn, $uid, 'copied', 'Shifting Assignment', "Copied {$count} assignment(s) from last week");
            ok(['copied' => $count], "{$count} assignment(s) copied from last week.");

        case 'replace_agent':
            $newId = $service->replaceAgent((int)($input['assignment_id'] ?? 0), (int)($input['new_user_id'] ?? 0), $input['notes'] ?? null);
            logAct($conn, $uid, 'replaced', 'Shifting Assignment', 'Replaced agent on workforce assignment', $newId);
            ok(['id' => $newId], 'Replacement assignment created.');

        case 'save_template':
            $id = $service->saveTemplate($input);
            logAct($conn, $uid, empty($input['id']) ? 'created' : 'updated', 'Shift Templates', 'Saved flexible shift template', $id);
            ok(['id' => $id], 'Shift template saved.');

        case 'preview_monthly_template':
            $preview = $service->previewMonthlyTemplate($input);
            logAct(
                $conn,
                $uid,
                'previewed',
                'Monthly Shift Templates',
                'Previewed monthly template ' . $preview['template_name'] . ' for ' . $preview['target_month'],
                (int)($input['id'] ?? $input['template_id'] ?? 0) ?: null
            );
            ok($preview, 'Template preview generated.');

        case 'save_monthly_template':
            $result = $service->saveMonthlyTemplate($input);
            $actionName = empty($input['id']) ? 'created' : 'updated';
            logAct(
                $conn,
                $uid,
                $actionName,
                'Monthly Shift Templates',
                ucfirst($actionName) . ' monthly template for ' . $result['preview']['target_month']
                    . ' with ' . $result['assignment_count'] . ' assignment(s)',
                $result['id']
            );
            ok($result, empty($input['id']) ? 'Monthly template saved as draft.' : 'Monthly template updated.');

        case 'duplicate_monthly_template':
            $result = $service->duplicateMonthlyTemplate(
                (int)($input['id'] ?? 0),
                (string)($input['target_month'] ?? ''),
                $input['name'] ?? null
            );
            logAct(
                $conn,
                $uid,
                'duplicated',
                'Monthly Shift Templates',
                'Duplicated monthly template to ' . $result['preview']['target_month']
                    . ' with ' . $result['assignment_count'] . ' assignment(s)',
                $result['id']
            );
            ok($result, 'Monthly template duplicated.');

        case 'apply_monthly_template':
            $result = $service->applyMonthlyTemplate(
                (int)($input['id'] ?? 0),
                !empty($input['apply_non_conflicting'])
            );
            logAct(
                $conn,
                $uid,
                'applied',
                'Monthly Shift Templates',
                'Applied ' . $result['template_name'] . ' to ' . $result['target_month']
                    . ': ' . $result['created'] . ' created, ' . count($result['skipped']) . ' skipped',
                $result['template_id']
            );
            foreach ($result['assignment_ids'] as $assignmentId) {
                logAct(
                    $conn,
                    $uid,
                    'generated_from_template',
                    'Shifting Assignment',
                    'Assignment generated from monthly template #' . $result['template_id'] . ' ' . $result['template_name'],
                    $assignmentId
                );
            }
            foreach ($result['skipped'] as $skip) {
                logAct(
                    $conn,
                    $uid,
                    'conflict_skipped',
                    'Monthly Shift Templates',
                    'Skipped template item #' . $skip['item_id'] . ': ' . $skip['reason'],
                    $result['template_id']
                );
            }
            if ($result['warnings']) {
                logAct(
                    $conn,
                    $uid,
                    'warning_generated',
                    'Monthly Shift Templates',
                    count($result['warnings']) . ' warning(s) recalculated after monthly template apply',
                    $result['template_id']
                );
            }
            ok($result, $result['created'] . ' assignment(s) created from monthly template.');

        case 'archive_monthly_template':
            $id = (int)($input['id'] ?? 0);
            $service->archiveMonthlyTemplate($id);
            logAct($conn, $uid, 'archived', 'Monthly Shift Templates', 'Archived monthly shift template', $id);
            ok(['id' => $id], 'Monthly template archived.');

        case 'save_holiday':
            $id = $service->saveHoliday($input);
            logAct($conn, $uid, empty($input['id']) ? 'created' : 'updated', 'Public Holidays', 'Saved public holiday coverage date', $id);
            ok(['id' => $id], 'Holiday saved.');

        case 'save_coverage_rule':
            $id = $service->saveCoverageRule($input);
            logAct($conn, $uid, empty($input['id']) ? 'created' : 'updated', 'Coverage Rules', 'Saved workforce coverage rule', $id);
            ok(['id' => $id], 'Coverage rule saved.');

        case 'save_settings':
            $service->saveSettings($input);
            logAct($conn, $uid, 'updated', 'Workload Settings', 'Updated workforce workload settings');
            ok(null, 'Workload settings saved.');

        case 'deactivate':
            $service->deactivateRecord((string)($input['kind'] ?? ''), (int)($input['id'] ?? 0));
            logAct($conn, $uid, 'deactivated', 'Shifting Assignment', 'Deactivated scheduling configuration record', (int)($input['id'] ?? 0));
            ok(null, 'Record deactivated.');

        default:
            fail('Unknown shifting assignment action.', 400);
    }
} catch (ShiftValidationException $e) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => tracs_public_error_message($e->getMessage(), 'Validation failed.'),
        'errors' => $e->errors,
    ]);
    exit;
} catch (InvalidArgumentException|DomainException $e) {
    fail($e->getMessage(), 422);
} catch (RuntimeException $e) {
    fail($e->getMessage(), $e->getMessage() === 'Forbidden.' ? 403 : 400);
} catch (Throwable $e) {
    error_log('TRACS shifting assignment API error: ' . $e->getMessage());
    fail('Unable to process shifting assignment request.', 500);
}
