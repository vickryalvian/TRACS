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

function tracs_insight_php_eol(string $version): ?string {
    $parts = explode('.', $version);
    $minor = ($parts[0] ?? '0') . '.' . ($parts[1] ?? '0');
    return TRACS_PHP_EOL_DATES[$minor] ?? null;
}
