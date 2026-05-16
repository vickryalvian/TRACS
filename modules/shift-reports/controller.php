<?php
/**
 * Shift Reports Module - Controller
 */

require_once __DIR__ . '/model.php';
require_once __DIR__ . '/ShiftActivityService.php';

class ShiftReportController {
    private $model;
    private $activity;
    private $user_id;
    private $conn;

    public function __construct($connection, $user_id) {
        $this->conn = $connection;
        $this->model = new ShiftReportModel($connection);
        $this->activity = new ShiftActivityService($connection, (int)$user_id);
        $this->user_id = $user_id;
    }

    public function getTodayByShift() {
        $reports = $this->model->getTodayReports();
        $grouped = [];
        foreach ($reports as $r) {
            $shift = $r['shift_name'];
            if (!isset($grouped[$shift])) {
                $grouped[$shift] = [];
            }
            $grouped[$shift][] = $r;
        }
        return $grouped;
    }

    public function getTodayStats() {
        $reports = $this->model->getTodayReports();
        $active = 0;
        $resolved = 0;
        $critical = 0;
        
        foreach ($reports as $r) {
            if ($r['status'] === 'active') {
                $active++;
                if ($r['priority'] === 'critical') {
                    $critical++;
                }
            } else {
                $resolved++;
            }
        }
        
        return [
            'total' => count($reports),
            'active' => $active,
            'resolved' => $resolved,
            'critical' => $critical
        ];
    }

    public function getHistory($filters = [], $limit = 50, $offset = 0) {
        return $this->model->getHistory($filters, $limit, $offset);
    }

    // Local summary engine; keep this return shape stable for optional external AI later.
    public function buildOperationalIntelligence($today_reports, $yesterday_reports, $month_reports, $recent_reports) {
        $today_total = count($today_reports);
        $yesterday_active = count(array_filter($yesterday_reports, fn($r) => ($r['status'] ?? '') === 'active'));
        $month_total = count($month_reports);
        $critical_active = count(array_filter($recent_reports, fn($r) => ($r['status'] ?? '') === 'active' && ($r['priority'] ?? '') === 'critical'));

        $category_counts = [];
        foreach ($month_reports as $report) {
            $category = $this->detectCategory($report);
            $category_counts[$category] = ($category_counts[$category] ?? 0) + 1;
        }
        arsort($category_counts);

        $top_category = $category_counts ? array_key_first($category_counts) : 'General Ops';
        $recurring = array_filter($category_counts, fn($count) => $count >= 2);
        $recurring_label = $recurring ? implode(', ', array_slice(array_keys($recurring), 0, 3)) : 'no repeated category pattern yet';

        $summary_highlight = $critical_active > 0
            ? "{$critical_active} critical active issue" . ($critical_active === 1 ? '' : 's')
            : $top_category;
        $summary = $critical_active > 0
            ? "{$summary_highlight} need attention; {$today_total} report" . ($today_total === 1 ? '' : 's') . " logged today."
            : "{$today_total} report" . ($today_total === 1 ? '' : 's') . " logged today; {$top_category} is the main monthly pattern.";

        $insights = [];
        $insights[] = $today_total === 0 ? 'No reports logged today' : "{$today_total} reports logged today";
        $insights[] = $yesterday_active === 0 ? 'No active issue carried from yesterday' : "{$yesterday_active} active issue" . ($yesterday_active === 1 ? '' : 's') . ' carried from yesterday';
        $insights[] = "{$top_category} is the most frequent category this month";
        if ($recurring) {
            $insights[] = 'Recurring pattern detected: ' . $recurring_label;
        }

        $followups = [];
        if ($yesterday_active > 0) {
            $followups[] = 'Review unresolved handover from yesterday';
        }
        if ($critical_active > 0) {
            $followups[] = 'Confirm whether the critical issue is still active';
        }
        if ($today_total === 0) {
            $followups[] = 'Confirm current shift has no new handover item';
        }
        if (!$followups) {
            $followups[] = 'Continue monitoring active handover items';
        }

        return [
            'summary' => $summary,
            'summary_highlight' => $summary_highlight,
            'key_insights' => array_slice($insights, 0, 4),
            'followups' => array_slice($followups, 0, 3),
            'critical_active' => $critical_active
        ];
    }

    public function create($data) {
        if (function_exists('tracs_ensure_creator_columns')) {
            tracs_ensure_creator_columns($this->conn, 'tracs_shift_reports', 'created_by');
        }
        if (function_exists('tracs_current_user_display')) {
            $data['created_by_name'] = tracs_current_user_display($this->conn);
        }
        return $this->model->create($data, $this->user_id);
    }

    public function update($id, $data) {
        return $this->model->update($id, $data);
    }

    public function resolve($id) {
        return $this->model->resolve($id);
    }

    public function delete($id) {
        return $this->model->delete($id);
    }

    public function getById($id) {
        return $this->model->getById($id);
    }

    public function detectCurrentShift(): string {
        return $this->activity->detectCurrentShift();
    }

    public function getCurrentHandover(?string $shift = null, ?string $date = null): array {
        return $this->activity->buildCurrentHandover($shift, $date);
    }

    public function logShiftActivity(string $type, int $reference_id, string $title, ?string $description = null, string $status = 'info'): bool {
        return $this->activity->logActivity($type, $reference_id, $title, $description, $status);
    }

    private function detectCategory($report) {
        $categoryKeywords = [
            'Network' => ['network','latency','packet','routing','connectivity','isp','downtime'],
            'Hosting' => ['hosting','server','cpanel','plesk','vps','disk','storage','cpu','memory'],
            'Domain' => ['domain','dns','nameserver','epp','transfer'],
            'Email' => ['email','mail','smtp','zimbra','imap','mx'],
            'Security' => ['ddos','abuse','malware','security','attack','firewall'],
            'Billing' => ['billing','invoice','payment','refund','credit'],
            'Backup' => ['backup','restore','snapshot'],
        ];
        $text = strtolower(($report['title'] ?? '') . ' ' . ($report['details'] ?? ''));

        foreach ($categoryKeywords as $category => $words) {
            foreach ($words as $word) {
                if (str_contains($text, $word)) {
                    return $category;
                }
            }
        }

        return 'General Ops';
    }
}
