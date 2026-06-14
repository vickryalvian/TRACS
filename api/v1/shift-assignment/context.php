<?php
declare(strict_types=1);

namespace TRACS\Api\V1\ShiftAssignment;

require_once __DIR__ . '/../../../core/security/direct_access.php';
\tracs_deny_direct_script_access(__FILE__);

function context_data(
    array $user,
    array $capabilities,
    array $templates,
    array $assignmentTypes,
    array $agents,
    array $divisions,
    array $settings,
    array $range,
    string $csrfToken
): array {
    $safeName = static function (mixed $value, string $fallback): string {
        $value = trim((string)$value);
        $value = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value) ?? '';
        if ($value === '' || filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return $fallback;
        }
        return function_exists('mb_substr') ? mb_substr($value, 0, 120) : substr($value, 0, 120);
    };

    $manage = (bool)($capabilities['manage'] ?? false);
    $manageSettings = (bool)($capabilities['settings'] ?? false);
    $manageMonthlyTemplates = (bool)($capabilities['monthly_templates'] ?? false);
    $export = (bool)($capabilities['export'] ?? false);

    return [
        'user' => [
            'id' => (int)($user['id'] ?? 0),
            'name' => $safeName($user['display_name'] ?? $user['name'] ?? '', 'User'),
            'role' => [
                'slug' => (string)($user['role_slug'] ?? ''),
                'name' => (string)($user['role_name'] ?? ''),
            ],
        ],
        'permissions' => [
            'view' => true,
            'manage' => $manage,
            'settings' => $manageSettings,
            'monthly_templates' => $manageMonthlyTemplates,
            'export' => $export,
            'scope_role' => (string)($capabilities['scope_role'] ?? 'agent'),
        ],
        'allowed_actions' => [
            'view_schedule' => true,
            'view_warnings' => true,
            'view_audit' => true,
            'create_assignment' => $manage,
            'update_assignment' => $manage,
            'resize_assignment' => $manage,
            'update_status' => $manage,
            'confirm_assignment' => $manage,
            'dismiss_warning' => $manage,
            'copy_last_week' => $manage,
            'replace_agent' => $manage,
            'delete_assignment' => false,
            'manage_shift_templates' => $manageSettings,
            'manage_holidays' => $manageSettings,
            'manage_coverage_rules' => $manageSettings,
            'manage_workload_settings' => $manageSettings,
            'manage_monthly_templates' => $manageMonthlyTemplates,
            'export_csv' => $export,
        ],
        'shift_definitions' => [
            [
                'key' => 'shift_1',
                'name' => 'Shift 1',
                'start_time' => '00:00',
                'end_time' => '08:00',
                'display_range' => '00:00-08:00',
                'duration_minutes' => 480,
                'is_cross_day' => false,
            ],
            [
                'key' => 'shift_2',
                'name' => 'Shift 2',
                'start_time' => '08:00',
                'end_time' => '16:00',
                'display_range' => '08:00-16:00',
                'duration_minutes' => 480,
                'is_cross_day' => false,
            ],
            [
                'key' => 'shift_3',
                'name' => 'Shift 3',
                'start_time' => '16:00',
                'end_time' => '00:00',
                'display_range' => '16:00-24:00',
                'duration_minutes' => 480,
                'is_cross_day' => true,
            ],
        ],
        'filters' => [
            'views' => ['daily', 'weekly', 'monthly'],
            'statuses' => ['assigned', 'confirmed', 'active', 'completed', 'cancelled', 'no_show', 'replaced'],
            'assignment_types' => array_map(
                static fn(array $type): array => [
                    'slug' => (string)($type['type_slug'] ?? ''),
                    'name' => (string)($type['type_name'] ?? ''),
                    'color' => (string)($type['color_label'] ?? ''),
                    'counts_as_work' => (bool)($type['count_as_work_hour'] ?? false),
                    'counts_as_overtime' => (bool)($type['count_as_overtime'] ?? false),
                ],
                $assignmentTypes
            ),
            'agents' => array_map(
                static fn(array $agent): array => [
                    'id' => (int)($agent['id'] ?? 0),
                    'name' => $safeName($agent['agent_name'] ?? '', 'Agent'),
                    'division_id' => isset($agent['division_id']) ? (int)$agent['division_id'] : null,
                    'division_name' => (string)($agent['division_name'] ?? ''),
                    'role' => (string)($agent['role_slug'] ?? ''),
                ],
                $agents
            ),
            'divisions' => array_map(
                static fn(array $division): array => [
                    'id' => (int)($division['id'] ?? 0),
                    'name' => (string)($division['name'] ?? ''),
                    'code' => (string)($division['code'] ?? ''),
                ],
                $divisions
            ),
            'shift_templates' => array_map(
                static fn(array $template): array => [
                    'id' => (int)($template['id'] ?? 0),
                    'name' => (string)($template['shift_name'] ?? ''),
                    'start_time' => substr((string)($template['start_time'] ?? ''), 0, 5),
                    'end_time' => substr((string)($template['end_time'] ?? ''), 0, 5),
                    'duration_minutes' => (int)($template['duration_minutes'] ?? 0),
                    'break_minutes' => (int)($template['default_break_minutes'] ?? 0),
                    'is_cross_day' => (bool)($template['is_cross_day'] ?? false),
                    'color' => (string)($template['color_label'] ?? ''),
                    'assignment_type' => (string)($template['default_assignment_type'] ?? ''),
                    'is_active' => (bool)($template['is_active'] ?? false),
                ],
                $templates
            ),
            'role_filter_supported' => false,
        ],
        'defaults' => [
            'view' => 'weekly',
            'start_date' => (string)($range[0] ?? ''),
            'end_date' => (string)($range[1] ?? ''),
            'timezone' => 'Asia/Jakarta',
            'ui_date_format' => 'dd-mm-yyyy',
            'api_date_format' => 'YYYY-MM-DD',
            'weekly_target_minutes' => (int)($settings['weekly_target_minutes'] ?? 2400),
            'max_weekly_minutes' => (int)($settings['max_weekly_minutes'] ?? 2880),
            'overtime_threshold_minutes' => (int)($settings['overtime_threshold_minutes'] ?? 2700),
            'minimum_rest_minutes' => (int)($settings['minimum_rest_between_shifts_minutes'] ?? 480),
            'timeline_snap_minutes' => (int)($settings['timeline_snap_minutes'] ?? 15),
            'minimum_shift_minutes' => (int)($settings['minimum_shift_minutes'] ?? 60),
        ],
        'csrf' => [
            'token' => $csrfToken,
            'header' => 'X-CSRF-Token',
        ],
    ];
}
