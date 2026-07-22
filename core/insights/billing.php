<?php

const TRACS_INSIGHT_BILLING_CACHE_TTL = 300;
const TRACS_INSIGHT_BILLING_WARNING_THRESHOLD = 100000;
const TRACS_INSIGHT_BILLING_CRITICAL_THRESHOLD = 25000;
const TRACS_INSIGHT_BILLING_DAYS_CRITICAL = 3;
const TRACS_INSIGHT_BILLING_DAYS_WARNING = 14;

function tracs_insight_billing(mysqli $conn, array $monitoring, array $context = []): array {
    $apiKey = trim((string)($_ENV['IDCLOUDHOST_API_KEY'] ?? ''));
    if ($apiKey === '') {
        return tracs_insight_billing_unavailable('Billing API key is not configured.');
    }

    $now = time();
    $cache = $_SESSION['tracs_insight_billing_cache'] ?? null;
    if (is_array($cache) && isset($cache['ts'], $cache['data']) && ($now - (int)$cache['ts']) < TRACS_INSIGHT_BILLING_CACHE_TTL) {
        return $cache['data'];
    }

    $result = tracs_insight_billing_fetch($apiKey);
    $_SESSION['tracs_insight_billing_cache'] = ['ts' => $now, 'data' => $result];
    return $result;
}

function tracs_insight_billing_fetch(string $apiKey): array {
    $response = tracs_insight_billing_call($apiKey, 'https://api.idcloudhost.com/v1/payment/billing_account/list');
    if ($response === null) {
        return tracs_insight_billing_unavailable('Billing balance is temporarily unavailable.');
    }

    $account = tracs_insight_billing_select_account(json_decode($response, true));
    if ($account === null) {
        error_log('TRACS billing insight: unrecognized IDCloudHost response shape: ' . substr($response, 0, 500));
        return tracs_insight_billing_unavailable('Billing balance response was not recognized.');
    }

    $balance = is_numeric($account['credit_amount'] ?? null)
        ? (float)$account['credit_amount']
        : (float)($account['running_totals']['credit_available'] ?? 0);
    $restrictionLevel = strtoupper(trim((string)($account['restriction_level'] ?? '')));
    $restricted = $restrictionLevel !== '' && $restrictionLevel !== 'CLEAR';

    $usage = $restricted ? null : tracs_insight_billing_usage($apiKey, (int)($account['id'] ?? 0));
    $daysRemaining = $usage !== null && $usage['hourly_rate'] > 0
        ? (int)floor($balance / ($usage['hourly_rate'] * 24))
        : null;

    $status = tracs_insight_billing_status($restricted, $daysRemaining, $balance);
    $statusLabel = $restricted ? ('Account restricted (' . $restrictionLevel . ')') : ucfirst($status);

    return [
        'key' => 'billing',
        'title' => 'Billing',
        'icon' => 'credit-card',
        'type' => 'billing',
        'status' => $status,
        'items' => [
            'balance_display' => tracs_insight_billing_format_rupiah($balance),
            'days_remaining' => $daysRemaining,
            'monthly_spend_display' => $usage !== null ? tracs_insight_billing_format_rupiah($usage['monthly_spend']) : null,
            'status_label' => $statusLabel,
            'last_updated' => date('H:i') . ' WIB',
            'resources' => $usage['resources'] ?? [],
            'recommendation' => tracs_insight_billing_recommendation($restricted, $restrictionLevel, $status, $daysRemaining),
        ],
    ];
}

function tracs_insight_billing_status(bool $restricted, ?int $daysRemaining, float $balance): string {
    if ($restricted) {
        return 'critical';
    }
    if ($daysRemaining !== null) {
        if ($daysRemaining <= TRACS_INSIGHT_BILLING_DAYS_CRITICAL) {
            return 'critical';
        }
        return $daysRemaining <= TRACS_INSIGHT_BILLING_DAYS_WARNING ? 'warning' : 'healthy';
    }
    if ($balance >= TRACS_INSIGHT_BILLING_WARNING_THRESHOLD) {
        return 'healthy';
    }
    return $balance >= TRACS_INSIGHT_BILLING_CRITICAL_THRESHOLD ? 'warning' : 'critical';
}

function tracs_insight_billing_recommendation(bool $restricted, string $restrictionLevel, string $status, ?int $daysRemaining): ?string {
    if ($restricted) {
        return 'Account is restricted (' . $restrictionLevel . '). Contact IDCloudHost support to resolve this before it affects the server.';
    }
    if ($daysRemaining !== null && $status !== 'healthy') {
        return 'At the current usage rate, balance may run out in about ' . $daysRemaining . ' day' . ($daysRemaining === 1 ? '' : 's') . '. Top up soon to avoid an interruption.';
    }
    if ($status !== 'healthy') {
        return 'Billing balance is low. Consider topping up.';
    }
    return null;
}

function tracs_insight_billing_usage(string $apiKey, int $accountId): ?array {
    if ($accountId <= 0) {
        return null;
    }
    $response = tracs_insight_billing_call(
        $apiKey,
        'https://api.idcloudhost.com/v1/charging/usage?billing_account_id=' . $accountId
    );
    if ($response === null) {
        return null;
    }
    $rows = json_decode($response, true);
    if (!is_array($rows) || !tracs_insight_is_list($rows)) {
        return null;
    }

    $hourlyRate = 0.0;
    $monthlySpend = 0.0;
    $resources = [];
    foreach ($rows as $row) {
        if (!is_array($row) || !empty($row['_technical'])) {
            continue;
        }
        $price = is_numeric($row['price'] ?? null) ? (float)$row['price'] : 0.0;
        $cost = is_numeric($row['cost'] ?? null) ? (float)$row['cost'] : 0.0;
        $hourlyRate += $price;
        $monthlySpend += $cost;
        $description = trim((string)($row['description'] ?? ''));
        if ($description !== '') {
            $resources[] = [
                'label' => $description,
                'value' => tracs_insight_billing_format_rupiah($price) . '/hour',
            ];
        }
    }

    return [
        'hourly_rate' => $hourlyRate,
        'monthly_spend' => $monthlySpend,
        'resources' => $resources,
    ];
}

function tracs_insight_billing_call(string $apiKey, string $url): ?string {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['apikey: ' . $apiKey],
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
        error_log('TRACS billing insight: IDCloudHost request failed (' . $url . ', HTTP ' . $httpCode . ') ' . $curlError);
        return null;
    }
    return $response;
}

function tracs_insight_billing_select_account(mixed $data): ?array {
    if (!is_array($data)) {
        return null;
    }
    $accounts = isset($data['data']) && is_array($data['data']) ? $data['data'] : $data;
    if (!tracs_insight_is_list($accounts)) {
        $accounts = [$accounts];
    }
    $accounts = array_values(array_filter(
        $accounts,
        static fn($row) => is_array($row) && empty($row['is_deleted'])
    ));
    if (!$accounts) {
        return null;
    }
    foreach ($accounts as $account) {
        if (!empty($account['is_default'])) {
            return $account;
        }
    }
    return $accounts[0];
}

function tracs_insight_is_list(array $arr): bool {
    return $arr === [] || array_keys($arr) === range(0, count($arr) - 1);
}

function tracs_insight_billing_format_rupiah(float $amount): string {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

function tracs_insight_billing_unavailable(string $message): array {
    return [
        'key' => 'billing',
        'title' => 'Billing',
        'icon' => 'credit-card',
        'type' => 'billing',
        'status' => 'unavailable',
        'items' => [
            'balance_display' => 'Unavailable',
            'days_remaining' => null,
            'monthly_spend_display' => null,
            'status_label' => $message,
            'last_updated' => date('H:i') . ' WIB',
            'resources' => [],
            'recommendation' => null,
        ],
    ];
}
