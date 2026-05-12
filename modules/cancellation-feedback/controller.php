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

    public function __construct($db, $userId = 0) {
        $this->model = new CancellationFeedbackModel($db);
        $this->activityLogger = new ActivityLogController($db, $userId);
        $this->ticker = new TickerEventController($db);
        $this->userId = $userId;
    }

    public function createFeedback($data) {
        $id = $this->model->create($data);
        if ($id) {
            $msg = "Added new cancellation feedback for " . $data['cancelled_service'] . " — Reason: " . $data['cancellation_reason'];
            $this->activityLogger->logActivity('created', 'Cancellation Feedback', $msg, $id);
            
            // Push to ticker
            $tickerMsg = "[CS OPS] New cancellation feedback for " . $data['cancelled_service'] . " — Reason: " . $data['cancellation_reason'];
            $type = in_array($data['cancellation_reason'], ['Frequent downtime', 'DDoS / security-related instability', 'Slow server performance', 'Repeated Issue']) ? 'urgent' : 'info';
            $this->ticker->create($this->userId, $tickerMsg, $type, 'Cancellation Feedback', $id);

            return $id;
        }
        return false;
    }

    public function updateFeedback($id, $data) {
        if ($this->model->update($id, $data)) {
            $msg = "Updated cancellation feedback for " . $data['cancelled_service'];
            $this->activityLogger->logActivity('updated', 'Cancellation Feedback', $msg, $id);
            return true;
        }
        return false;
    }

    public function deleteFeedback($id) {
        $feedback = $this->model->getById($id);
        if ($feedback && $this->model->delete($id)) {
            $msg = "Deleted cancellation feedback for " . $feedback['cancelled_service'];
            $this->activityLogger->logActivity('deleted', 'Cancellation Feedback', $msg, $id);
            return true;
        }
        return false;
    }

    public function getAnalyticsData() {
        return $this->model->getAnalytics();
    }

    public function getFeedbackList($filters = [], $limit = 50, $offset = 0) {
        return $this->model->list($filters, $limit, $offset);
    }

    public function getTotalCount($filters = []) {
        return $this->model->count($filters);
    }
}
