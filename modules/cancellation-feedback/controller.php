<?php
/**
 * TRACS — Cancellation Feedback Controller
 */
require_once __DIR__ . '/model.php';
require_once __DIR__ . '/../activity-log/controller.php';
require_once __DIR__ . '/../ticker-events/controller.php';

class CancellationFeedbackController {
    private $model;
    private $activityLogger;
    private $ticker;
    private $userId;
    private $db;

    public function __construct($db, $userId = 0) {
        $this->db = $db;
        $this->model = new CancellationFeedbackModel($db);
        $this->activityLogger = new ActivityLogController($db, $userId);
        $this->ticker = new TickerEventController($db);
        $this->userId = $userId;
    }

    public function createFeedback($data) {
        $data['created_by'] = $this->userId;
        $data['created_by_name'] = function_exists('tracs_current_user_display') ? tracs_current_user_display($this->db) : '';
        $data['submitter_name'] = $data['created_by_name'] ?: 'System';
        $id = $this->model->create($data);
        if ($id) {
            $serviceText = cf_display_multi_value($data['cancelled_service']);
            $reasonText = cf_display_multi_value($data['cancellation_reason']);
            $msg = "Added new cancellation feedback for " . $serviceText . " — Reason: " . $reasonText;
            $this->activityLogger->logActivity('created', 'Cancellation Feedback', $msg, $id);
            
            // Push to ticker
            $tickerMsg = "[CS OPS] New cancellation feedback for " . $serviceText . " — Reason: " . $reasonText;
            $criticalReasons = ['Frequent downtime', 'DDoS / security-related instability', 'Slow server performance', 'Repeated Issue'];
            $type = array_intersect(cf_decode_multi_value($data['cancellation_reason']), $criticalReasons) ? 'urgent' : 'info';
            $this->ticker->create($this->userId, $tickerMsg, $type, 'Cancellation Feedback', $id);

            return $id;
        }
        return false;
    }

    public function updateFeedback($id, $data) {
        if ($this->model->update($id, $data)) {
            $msg = "Updated cancellation feedback for " . cf_display_multi_value($data['cancelled_service']);
            $this->activityLogger->logActivity('updated', 'Cancellation Feedback', $msg, $id);
            return true;
        }
        return false;
    }

    public function deleteFeedback($id) {
        $feedback = $this->model->getById($id);
        if ($feedback && $this->model->delete($id)) {
            $msg = "Deleted cancellation feedback for " . cf_display_multi_value($feedback['cancelled_service']);
            $this->activityLogger->logActivity('deleted', 'Cancellation Feedback', $msg, $id);
            return true;
        }
        return false;
    }

    public function getAnalyticsData() {
        return $this->model->getAnalytics();
    }

    // Local summary engine; keeps the page useful without an external AI dependency.
    public function buildRetentionIntelligence($feedbacks, $analytics) {
        $total = count($feedbacks);
        $criticalReasons = ['Frequent downtime', 'DDoS / security-related instability', 'Slow server performance', 'Repeated Issue', 'Issue not resolved'];
        $critical = array_values(array_filter($feedbacks, fn($f) => array_intersect(cf_decode_multi_value($f['cancellation_reason'] ?? ''), $criticalReasons)));

        $topService = $analytics['top_service']['cancelled_service'] ?? 'no dominant service yet';
        $topServiceCount = (int)($analytics['top_service']['count'] ?? 0);
        $topReason = $analytics['top_reason']['cancellation_reason'] ?? 'no dominant reason yet';
        $topReasonCount = (int)($analytics['top_reason']['count'] ?? 0);
        $summary_highlight = $total === 0 ? [] : [$topService, $topReason];
        $summary = $total === 0
            ? 'No cancellation feedback logged this month yet.'
            : [
                "{$topService} is the main affected service",
                "{$topReason} is the leading cancellation reason",
                count($critical) ? 'Retention follow-up is needed' : 'No urgent retention risk is detected',
            ];

        $insights = [];
        $insights[] = $total === 0 ? 'No monthly feedback logged yet' : "{$total} feedback records logged this month";
        $insights[] = $topServiceCount ? "{$topService} is the most cancelled service" : 'No dominant cancelled service yet';
        $insights[] = $topReasonCount ? "{$topReason} is the top cancellation reason" : 'No dominant cancellation reason yet';
        if (count($critical)) {
            $insights[] = count($critical) . ' risk-sensitive cancellation reason' . (count($critical) === 1 ? '' : 's') . ' detected';
        }

        $followups = [];
        if (count($critical)) {
            $followups[] = 'Review cancellation records linked to downtime, performance, repeated issues, or unresolved cases';
        }
        if ($topServiceCount) {
            $followups[] = "Check whether {$topService} has a recurring service or support pattern";
        }
        if ($topReasonCount) {
            $followups[] = "Validate retention actions for customers citing {$topReason}";
        }
        if (!$followups) {
            $followups[] = 'Continue collecting cancellation feedback for trend analysis';
        }

        return [
            'summary' => $summary,
            'summary_highlight' => $summary_highlight,
            'key_insights' => array_slice($insights, 0, 4),
            'followups' => array_slice($followups, 0, 3),
            'critical_count' => count($critical)
        ];
    }

    public function getFeedbackList($filters = [], $limit = 50, $offset = 0) {
        return $this->model->list($filters, $limit, $offset);
    }

    public function getTotalCount($filters = []) {
        return $this->model->count($filters);
    }
}
