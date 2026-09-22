<?php
declare(strict_types=1);

/**
 * Opt-in request diagnostics for production-shaped benchmarks.
 * Disabled unless TRACS_PERF_DIAGNOSTICS is exactly "1".
 */
function tracs_enable_performance_diagnostics(mysqli $conn): void {
    if ((string)($_ENV['TRACS_PERF_DIAGNOSTICS'] ?? '') !== '1' || PHP_SAPI === 'cli') {
        return;
    }

    $sampleRate = (float)($_ENV['TRACS_PERF_SAMPLE_RATE'] ?? 1);
    $sampleRate = max(0.0, min(1.0, $sampleRate));
    if ($sampleRate <= 0.0 || ($sampleRate < 1.0 && random_int(1, 10000) > (int)round($sampleRate * 10000))) {
        return;
    }

    $startedAt = hrtime(true);
    $requestId = bin2hex(random_bytes(8));
    $initialStats = function_exists('mysqli_get_connection_stats')
        ? (mysqli_get_connection_stats($conn) ?: [])
        : [];

    if (!headers_sent()) {
        header('X-TRACS-Request-ID: ' . $requestId);
    }

    register_shutdown_function(static function () use ($conn, $startedAt, $requestId, $initialStats): void {
        $durationMs = round((hrtime(true) - $startedAt) / 1_000_000, 2);
        $finalStats = function_exists('mysqli_get_connection_stats')
            ? (mysqli_get_connection_stats($conn) ?: [])
            : [];
        $queryCount = max(0,
            tracs_performance_stat_delta($initialStats, $finalStats, 'result_set_queries')
            + tracs_performance_stat_delta($initialStats, $finalStats, 'non_result_set_queries')
        );
        $responseBytes = ob_get_level() > 0 ? ob_get_length() : false;
        $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
        $status = http_response_code();

        if (!headers_sent()) {
            header('Server-Timing: app;dur=' . number_format($durationMs, 2, '.', ''));
            header('X-TRACS-SQL-Queries: ' . $queryCount);
        }

        error_log('[TRACS_PERF] ' . json_encode([
            'request_id' => $requestId,
            'method' => strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            'path' => $path,
            'status' => $status,
            'duration_ms' => $durationMs,
            'sql_queries' => $queryCount,
            'response_bytes' => $responseBytes === false ? null : $responseBytes,
            'peak_memory_bytes' => memory_get_peak_usage(true),
        ], JSON_UNESCAPED_SLASHES));
    });
}

function tracs_performance_stat_delta(array $before, array $after, string $key): int {
    return (int)($after[$key] ?? 0) - (int)($before[$key] ?? 0);
}
