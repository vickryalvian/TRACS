<?php

$root = dirname(__DIR__);
$source = file_get_contents($root . '/core/performance_diagnostics.php');
$database = file_get_contents($root . '/config/database.php');

function performance_contract_assert(bool $condition, string $message): void {
    if(!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

performance_contract_assert(
    str_contains($source, "TRACS_PERF_DIAGNOSTICS'] ?? '') !== '1'"),
    'Performance diagnostics must be disabled by default.'
);
performance_contract_assert(
    str_contains($source, 'TRACS_PERF_SAMPLE_RATE'),
    'Performance diagnostics must support sampling.'
);
performance_contract_assert(
    str_contains($source, "parse_url((string)(\$_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH)"),
    'Diagnostics must omit query strings from logged paths.'
);
performance_contract_assert(
    !preg_match('/sql_text|last_query|debug_backtrace|HTTP_COOKIE|HTTP_AUTHORIZATION/i', $source),
    'Diagnostics must not log SQL text, credentials, cookies, or authorization headers.'
);
performance_contract_assert(
    str_contains($database, 'tracs_enable_performance_diagnostics($conn);'),
    'Database bootstrap must register diagnostics after a successful connection.'
);

echo "PASS: Performance diagnostics are opt-in, sampled, and exclude sensitive request/query content.\n";
