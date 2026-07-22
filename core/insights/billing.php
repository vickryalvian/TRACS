<?php

const TRACS_INSIGHT_BILLING_CACHE_TTL = 300;
const TRACS_INSIGHT_BILLING_WARNING_THRESHOLD = 100000;
const TRACS_INSIGHT_BILLING_CRITICAL_THRESHOLD = 25000;

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
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.idcloudhost.com/v1/payment/billing_account/list',
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
        error_log('TRACS billing insight: IDCloudHost request failed (HTTP ' . $httpCode . ') ' . $curlError);
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

    $status = $restricted ? 'critical'
        : ($balance >= TRACS_INSIGHT_BILLING_WARNING_THRESHOLD ? 'healthy'
        : ($balance >= TRACS_INSIGHT_BILLING_CRITICAL_THRESHOLD ? 'warning' : 'critical'));

    $statusText = $restricted ? ('Account restricted (' . $restrictionLevel . ')') : ucfirst($status);

    return [
        'key' => 'billing',
        'title' => 'Billing',
        'icon' => 'credit-card',
        'type' => 'kv',
        'status' => $status,
        'score_input' => $status === 'healthy' ? 100.0 : ($status === 'warning' ? 60.0 : 20.0),
        'items' => [
            ['label' => 'Billing Balance', 'value' => tracs_insight_billing_format_rupiah($balance)],
            ['label' => 'Status', 'value' => $statusText],
            ['label' => 'Last Updated', 'value' => date('H:i') . ' WIB'],
        ],
    ];
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
        'type' => 'kv',
        'status' => 'unavailable',
        'score_input' => null,
        'items' => [
            ['label' => 'Billing Balance', 'value' => 'Unavailable'],
            ['label' => 'Status', 'value' => $message],
            ['label' => 'Last Updated', 'value' => date('H:i') . ' WIB'],
        ],
    ];
}
