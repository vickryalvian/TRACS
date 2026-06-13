<?php

function tracs_public_error_message(string $message, string $fallback = 'The request could not be completed.'): string {
    $message = trim(preg_replace('/[\x00-\x1F\x7F]+/', ' ', $message) ?? '');
    if ($message === '') {
        return $fallback;
    }

    $sensitive = [
        '/\b(?:SQLSTATE|mysqli|PDOException|database server|query failed|syntax error|constraint fails|duplicate entry)\b/i',
        '/\b(?:SELECT|INSERT|UPDATE|DELETE|ALTER|DROP|CREATE)\s+[`A-Za-z0-9_*(]/i',
        '/\b(?:password|passwd|secret|token|authorization|cookie|set-cookie|private key|DB_PASS)\b/i',
        '#(?:^|\s)/(?:home|var|srv|etc|opt|usr|Users|private|tmp)/#i',
        '/\bStack trace\b|\bthrown in\b|\bon line \d+\b/i',
    ];
    foreach ($sensitive as $pattern) {
        if (preg_match($pattern, $message)) {
            return $fallback;
        }
    }

    return mb_substr($message, 0, 240);
}

function tracs_public_exception_message(Throwable $error, string $fallback = 'The request could not be completed.'): string {
    if ($error instanceof mysqli_sql_exception || $error instanceof PDOException) {
        return $fallback;
    }
    return tracs_public_error_message($error->getMessage(), $fallback);
}
