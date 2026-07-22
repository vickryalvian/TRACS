<?php

// Published active-support/security-support end dates from php.net/supported-versions.
// Review periodically — PHP publishes new minor-version EOL dates roughly yearly.
const TRACS_PHP_EOL_DATES = [
    '7.4' => '2022-11-28',
    '8.0' => '2023-11-26',
    '8.1' => '2025-12-31',
    '8.2' => '2026-12-31',
    '8.3' => '2027-12-31',
    '8.4' => '2028-12-31',
];

const TRACS_INSIGHT_PHP_EOL_WARNING_DAYS = 90;

function tracs_insight_php_eol(string $version): ?string {
    $parts = explode('.', $version);
    $minor = ($parts[0] ?? '0') . '.' . ($parts[1] ?? '0');
    return TRACS_PHP_EOL_DATES[$minor] ?? null;
}

function tracs_insight_php_support_status(string $version): array {
    $eol = tracs_insight_php_eol($version);
    if ($eol === null) {
        return ['healthy', 'Supported'];
    }
    $daysLeft = (strtotime($eol) - time()) / 86400;
    if ($daysLeft < 0) {
        return ['critical', 'End-of-Life'];
    }
    if ($daysLeft < TRACS_INSIGHT_PHP_EOL_WARNING_DAYS) {
        return ['warning', 'Approaching End-of-Life'];
    }
    return ['healthy', 'Supported'];
}
