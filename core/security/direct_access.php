<?php

function tracs_deny_direct_script_access(string $file): void {
    $requested = realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $target = realpath($file);
    if ($requested === false || $target === false || $requested !== $target) {
        return;
    }

    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo 'Not found';
    exit;
}
