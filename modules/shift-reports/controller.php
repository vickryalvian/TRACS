<?php
/**
 * Shift Reports Module - Controller
 */

require_once __DIR__ . '/model.php';

class ShiftReportController {
    private $model;
    private $user_id;

    public function __construct($connection, $user_id) {
        $this->model = new ShiftReportModel($connection);
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

    public function create($data) {
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
}
