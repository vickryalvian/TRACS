<?php
/**
 * TRACS — Cancellation Feedback Model
 */
if (!function_exists('cf_allowed_services')) {
    function cf_allowed_services(): array {
        return [
            'Domain', 'Cloud Hosting cPanel', 'Wordpress Hosting', 'Reseller Hosting cPanel',
            'Website Instant', 'Cloud VPS', 'VPS Pro', 'VPS Rocket', 'VPS AMD Extreme',
            'SSL Comodo', 'Managed VPS WHM', 'Cyberpanel VPS', 'Email & Collaboration (Zimbra)',
            'Dedicated Server', 'Baremetal Server', 'Colocation Server', 'Object Storage',
            'Cloud Storage Drive', 'License', 'Kubernetes', 'Reseller Hosting Plesk', 'Cloud Hosting Plesk'
        ];
    }

    function cf_allowed_reasons(): array {
        return [
            'Service No Longer Required', 'Document activation requirements', 'Missing required features',
            'Frequent downtime', 'Slow server performance', 'Network latency / packet loss',
            'Resource limits', 'DDoS / security-related instability', 'Slow Response Time',
            'Issue not resolved', 'Repeated Issue', 'Price Increase', 'Cheaper Competitor Found',
            'Billing/Payment method issue', 'Service Expansion (Upgrade / New Order)', 'Unknown/No Feedback'
        ];
    }

    function cf_allowed_resolutions(): array {
        return [
            'End of Billing Periode', 'Refund to Credit Balance', 'Refund to Bank Account / Paypal / CC'
        ];
    }

    function cf_decode_multi_value(mixed $value): array {
        if (is_array($value)) {
            $items = $value;
        } else {
            $raw = trim((string)$value);
            if ($raw === '') return [];
            $decoded = json_decode($raw, true);
            $items = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [$raw];
        }

        $out = [];
        foreach ($items as $item) {
            $item = trim((string)$item);
            if ($item !== '' && !in_array($item, $out, true)) $out[] = $item;
        }
        return $out;
    }

    function cf_filter_allowed_values(mixed $value, array $allowed): array {
        return array_values(array_filter(
            cf_decode_multi_value($value),
            fn($item) => in_array($item, $allowed, true)
        ));
    }

    function cf_encode_multi_value(array $values): string {
        return json_encode(array_values($values), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    function cf_display_multi_value(mixed $value): string {
        return implode(', ', cf_decode_multi_value($value));
    }
}

class CancellationFeedbackModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function create($data) {
        $sql = "INSERT INTO tracs_cancellation_feedback 
                (submitter_name, cancelled_service, cancellation_reason, additional_details, whmcs_reference, email_address, payment_resolution, created_by, created_by_name) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("sssssssis", 
            $data['submitter_name'], 
            $data['cancelled_service'], 
            $data['cancellation_reason'], 
            $data['additional_details'], 
            $data['whmcs_reference'], 
            $data['email_address'], 
            $data['payment_resolution'],
            $data['created_by'],
            $data['created_by_name']
        );
        
        if ($stmt->execute()) {
            return $this->db->insert_id;
        }
        return false;
    }

    public function getById($id) {
        $sql = "
            SELECT f.*,
                   COALESCE(NULLIF(f.created_by_name,''), NULLIF(u.name,''), u.email, 'System') AS creator_name,
                   COALESCE(NULLIF(f.created_by_name,''), NULLIF(u.name,''), u.email, NULLIF(f.submitter_name,''), 'System') AS submitter_display
            FROM tracs_cancellation_feedback f
            LEFT JOIN tracs_users u ON f.created_by = u.id
            WHERE f.id = ?
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    public function list($filters = [], $limit = 50, $offset = 0) {
        $sql = "
            SELECT f.*,
                   COALESCE(NULLIF(f.created_by_name,''), NULLIF(u.name,''), u.email, 'System') AS creator_name,
                   COALESCE(NULLIF(f.created_by_name,''), NULLIF(u.name,''), u.email, NULLIF(f.submitter_name,''), 'System') AS submitter_display
            FROM tracs_cancellation_feedback f
            LEFT JOIN tracs_users u ON f.created_by = u.id
            WHERE 1=1
        ";
        $params = [];
        $types = "";

        if (!empty($filters['q'])) {
            $sql .= " AND (f.email_address LIKE ? OR f.whmcs_reference LIKE ? OR f.submitter_name LIKE ? OR f.cancelled_service LIKE ? OR f.cancellation_reason LIKE ? OR f.additional_details LIKE ? OR f.created_by_name LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
            $q = "%" . $filters['q'] . "%";
            $params = array_merge($params, [$q, $q, $q, $q, $q, $q, $q, $q, $q]);
            $types .= "sssssssss";
        }

        if (!empty($filters['service'])) {
            $sql .= " AND (f.cancelled_service = ? OR f.cancelled_service LIKE ?)";
            $params[] = $filters['service'];
            $params[] = '%"' . $filters['service'] . '"%';
            $types .= "ss";
        }

        if (!empty($filters['reason'])) {
            $sql .= " AND (f.cancellation_reason = ? OR f.cancellation_reason LIKE ?)";
            $params[] = $filters['reason'];
            $params[] = '%"' . $filters['reason'] . '"%';
            $types .= "ss";
        }

        if (!empty($filters['resolution'])) {
            $sql .= " AND f.payment_resolution = ?";
            $params[] = $filters['resolution'];
            $types .= "s";
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND f.created_at >= ?";
            $params[] = $filters['date_from'] . " 00:00:00";
            $types .= "s";
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND f.created_at <= ?";
            $params[] = $filters['date_to'] . " 23:59:59";
            $types .= "s";
        }

        $sql .= " ORDER BY f.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";

        $stmt = $this->db->prepare($sql);
        if ($params) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function count($filters = []) {
        $sql = "
            SELECT COUNT(*) as total
            FROM tracs_cancellation_feedback f
            LEFT JOIN tracs_users u ON f.created_by = u.id
            WHERE 1=1
        ";
        $params = [];
        $types = "";

        if (!empty($filters['q'])) {
            $sql .= " AND (f.email_address LIKE ? OR f.whmcs_reference LIKE ? OR f.submitter_name LIKE ? OR f.cancelled_service LIKE ? OR f.cancellation_reason LIKE ? OR f.additional_details LIKE ? OR f.created_by_name LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
            $q = "%" . $filters['q'] . "%";
            $params = array_merge($params, [$q, $q, $q, $q, $q, $q, $q, $q, $q]);
            $types .= "sssssssss";
        }

        // ... repeat filters as above ...
        if (!empty($filters['service'])) {
            $sql .= " AND (f.cancelled_service = ? OR f.cancelled_service LIKE ?)";
            $params[] = $filters['service'];
            $params[] = '%"' . $filters['service'] . '"%';
            $types .= "ss";
        }
        if (!empty($filters['reason'])) {
            $sql .= " AND (f.cancellation_reason = ? OR f.cancellation_reason LIKE ?)";
            $params[] = $filters['reason'];
            $params[] = '%"' . $filters['reason'] . '"%';
            $types .= "ss";
        }
        if (!empty($filters['resolution'])) { $sql .= " AND f.payment_resolution = ?"; $params[] = $filters['resolution']; $types .= "s"; }
        if (!empty($filters['date_from'])) { $sql .= " AND f.created_at >= ?"; $params[] = $filters['date_from'] . " 00:00:00"; $types .= "s"; }
        if (!empty($filters['date_to'])) { $sql .= " AND f.created_at <= ?"; $params[] = $filters['date_to'] . " 23:59:59"; $types .= "s"; }

        $stmt = $this->db->prepare($sql);
        if ($params) { $stmt->bind_param($types, ...$params); }
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc()['total'];
    }

    public function update($id, $data) {
        $sql = "UPDATE tracs_cancellation_feedback SET 
                cancelled_service = ?, cancellation_reason = ?, 
                additional_details = ?, whmcs_reference = ?, email_address = ?, 
                payment_resolution = ? 
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ssssssi", 
            $data['cancelled_service'], 
            $data['cancellation_reason'], 
            $data['additional_details'], 
            $data['whmcs_reference'], 
            $data['email_address'], 
            $data['payment_resolution'],
            $id
        );
        
        return $stmt->execute();
    }

    public function delete($id) {
        $sql = "DELETE FROM tracs_cancellation_feedback WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    public function getAnalytics($month = null) {
        if (!$month) $month = date('Y-m');
        
        $analytics = [];

        $sql = "SELECT cancelled_service, cancellation_reason
                FROM tracs_cancellation_feedback
                WHERE DATE_FORMAT(created_at, '%Y-%m') = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("s", $month);
        $stmt->execute();
        $result = $stmt->get_result();
        $serviceCounts = [];
        $reasonCounts = [];
        while ($row = $result->fetch_assoc()) {
            foreach (cf_decode_multi_value($row['cancelled_service'] ?? '') as $service) {
                $serviceCounts[$service] = ($serviceCounts[$service] ?? 0) + 1;
            }
            foreach (cf_decode_multi_value($row['cancellation_reason'] ?? '') as $reason) {
                $reasonCounts[$reason] = ($reasonCounts[$reason] ?? 0) + 1;
            }
        }
        arsort($serviceCounts);
        arsort($reasonCounts);
        $topService = array_key_first($serviceCounts);
        $topReason = array_key_first($reasonCounts);
        $analytics['top_service'] = $topService ? ['cancelled_service' => $topService, 'count' => $serviceCounts[$topService]] : null;
        $analytics['top_reason'] = $topReason ? ['cancellation_reason' => $topReason, 'count' => $reasonCounts[$topReason]] : null;

        // Most Used Resolution
        $sql = "SELECT payment_resolution, COUNT(*) as count 
                FROM tracs_cancellation_feedback 
                WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
                GROUP BY payment_resolution 
                ORDER BY count DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("s", $month);
        $stmt->execute();
        $analytics['top_resolution'] = $stmt->get_result()->fetch_assoc();

        return $analytics;
    }
}
