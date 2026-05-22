<?php
/**
 * TRACS authentication hardening helpers.
 */

const TRACS_AUTH_GENERIC_INVALID = 'Invalid login credentials.';
const TRACS_AUTH_GENERIC_LOCKED = 'Too many attempts. Please try again later.';
const TRACS_AUTH_HELP_MESSAGE = 'If you are having trouble logging in, please contact your administrator for further assistance.';
const TRACS_AUTH_DUMMY_HASH = '$2y$10$uG/.8q0jbJc8f9V6mI8GBeKyUQvubVw7aJ3d3AjzbHMDEZdyx1XmS';

function tracs_auth_env(string $key, mixed $default = null): mixed {
    $value = $_ENV[$key] ?? getenv($key);
    return ($value === false || $value === null || $value === '') ? $default : $value;
}

function tracs_auth_config_int(string $key, int $default, int $min = 0, int $max = 1440): int {
    $value = (int)tracs_auth_env($key, $default);
    return max($min, min($max, $value));
}

function tracs_auth_max_attempts(): int {
    return tracs_auth_config_int('TRACS_LOGIN_MAX_FAILED_ATTEMPTS', 5, 3, 50);
}

function tracs_auth_window_minutes(): int {
    return tracs_auth_config_int('TRACS_LOGIN_WINDOW_MINUTES', 15, 1, 1440);
}

function tracs_auth_lock_minutes(): int {
    return tracs_auth_config_int('TRACS_LOGIN_LOCK_MINUTES', 15, 1, 1440);
}

function tracs_auth_captcha_after(): int {
    return tracs_auth_config_int('TRACS_LOGIN_CAPTCHA_AFTER', 3, 1, 50);
}

function tracs_auth_idle_timeout_seconds(): int {
    return tracs_auth_config_int('TRACS_SESSION_IDLE_TIMEOUT_MINUTES', 60, 5, 1440) * 60;
}

function tracs_auth_turnstile_enabled(): bool {
    return (string)tracs_auth_env('TRACS_CAPTCHA_PROVIDER', 'internal') === 'turnstile'
        && (string)tracs_auth_env('TRACS_TURNSTILE_SITE_KEY', '') !== ''
        && (string)tracs_auth_env('TRACS_TURNSTILE_SECRET_KEY', '') !== '';
}

function tracs_auth_normalize_identifier(string $identifier): string {
    return strtolower(trim(substr($identifier, 0, 255)));
}

function tracs_auth_identifier_hash(string $identifier): string {
    return hash('sha256', tracs_auth_normalize_identifier($identifier));
}

function tracs_auth_ip_in_cidr(string $ip, string $cidr): bool {
    if (!str_contains($cidr, '/')) {
        return hash_equals($cidr, $ip);
    }
    [$subnet, $bits] = explode('/', $cidr, 2);
    $ipBin = @inet_pton($ip);
    $subnetBin = @inet_pton($subnet);
    $bits = (int)$bits;
    if ($ipBin === false || $subnetBin === false || $bits < 0) {
        return false;
    }
    $bytes = intdiv($bits, 8);
    $remainder = $bits % 8;
    if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
        return false;
    }
    if ($remainder === 0) {
        return true;
    }
    $mask = (0xff << (8 - $remainder)) & 0xff;
    return (ord($ipBin[$bytes]) & $mask) === (ord($subnetBin[$bytes]) & $mask);
}

function tracs_auth_remote_addr_is_trusted(): bool {
    $remote = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    $trusted = trim((string)tracs_auth_env('TRACS_TRUSTED_PROXIES', ''));
    if ($remote === '' || $trusted === '') {
        return false;
    }
    foreach (array_filter(array_map('trim', explode(',', $trusted))) as $proxy) {
        if (tracs_auth_ip_in_cidr($remote, $proxy)) {
            return true;
        }
    }
    return false;
}

function tracs_auth_client_ip(): string {
    if (tracs_auth_remote_addr_is_trusted()) {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR'] as $key) {
            $value = trim((string)($_SERVER[$key] ?? ''));
            if ($value === '') {
                continue;
            }
            foreach (array_map('trim', explode(',', $value)) as $candidate) {
                if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                    return substr($candidate, 0, 45);
                }
            }
        }
    }
    $remote = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    return filter_var($remote, FILTER_VALIDATE_IP) ? substr($remote, 0, 45) : '';
}

function tracs_auth_log_event(mysqli $conn, string $eventType, string $result, string $identifier = '', ?int $userId = null, ?string $reason = null): void {
    $ip = tracs_auth_client_ip();
    $agent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
    $identifier = tracs_auth_normalize_identifier($identifier);
    $reason = $reason ? substr($reason, 0, 255) : null;

    if (function_exists('tracs_table_exists') && tracs_table_exists($conn, 'tracs_auth_events')) {
        $stmt = $conn->prepare("
            INSERT INTO tracs_auth_events
              (event_type, result, user_id, identifier, ip_address, user_agent, reason, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        if ($stmt) {
            $stmt->bind_param('ssissss', $eventType, $result, $userId, $identifier, $ip, $agent, $reason);
            $stmt->execute();
            $stmt->close();
        }
        return;
    }

    error_log('TRACS auth event: ' . json_encode([
        'event_type' => $eventType,
        'result' => $result,
        'user_id' => $userId,
        'identifier' => $identifier,
        'ip_address' => $ip,
        'reason' => $reason,
    ], JSON_UNESCAPED_SLASHES));
}

function tracs_auth_risk_state(mysqli $conn, string $identifier): array {
    $state = ['locked' => false, 'captcha_required' => false, 'locked_until' => null, 'failed_count' => 0];
    if (!function_exists('tracs_table_exists') || !tracs_table_exists($conn, 'tracs_login_attempts')) {
        return $state;
    }

    $hash = tracs_auth_identifier_hash($identifier);
    $ip = tracs_auth_client_ip();
    $window = tracs_auth_window_minutes();
    $max = tracs_auth_max_attempts();
    $captchaAfter = tracs_auth_captcha_after();

    $stmt = $conn->prepare("
        SELECT
          COALESCE(SUM(CASE WHEN identifier_hash = ? THEN failed_attempts ELSE 0 END), 0) AS identifier_fails,
          COALESCE(SUM(CASE WHEN ip_address = ? THEN failed_attempts ELSE 0 END), 0) AS ip_fails,
          MAX(locked_until) AS locked_until,
          MAX(captcha_required_until) AS captcha_until
        FROM tracs_login_attempts
        WHERE (identifier_hash = ? OR ip_address = ?)
          AND (last_failed_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
               OR locked_until > NOW()
               OR captcha_required_until > NOW())
    ");
    if (!$stmt) {
        return $state;
    }
    $stmt->bind_param('ssssi', $hash, $ip, $hash, $ip, $window);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();

    $fails = max((int)($row['identifier_fails'] ?? 0), (int)($row['ip_fails'] ?? 0));
    $lockedUntil = (string)($row['locked_until'] ?? '');
    $captchaUntil = (string)($row['captcha_until'] ?? '');
    $now = time();

    $state['failed_count'] = $fails;
    $state['locked_until'] = $lockedUntil ?: null;
    $state['locked'] = $lockedUntil !== '' && strtotime($lockedUntil) > $now;
    $state['captcha_required'] = $state['locked']
        || $fails >= $captchaAfter
        || ($captchaUntil !== '' && strtotime($captchaUntil) > $now);
    return $state;
}

function tracs_auth_record_failed_login(mysqli $conn, string $identifier, ?int $userId, string $reason = 'invalid_credentials'): array {
    $state = ['locked' => false, 'captcha_required' => false, 'failed_count' => 1];
    if (!function_exists('tracs_table_exists') || !tracs_table_exists($conn, 'tracs_login_attempts')) {
        tracs_auth_log_event($conn, 'login_failed', 'failed', $identifier, $userId, $reason);
        return $state;
    }

    $identifier = tracs_auth_normalize_identifier($identifier);
    $hash = tracs_auth_identifier_hash($identifier);
    $ip = tracs_auth_client_ip();
    $agent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
    $window = tracs_auth_window_minutes();

    $stmt = $conn->prepare("
        INSERT INTO tracs_login_attempts
          (identifier_hash, identifier_display, ip_address, user_id, failed_attempts, first_failed_at, last_failed_at, last_result, user_agent, created_at, updated_at)
        VALUES (?, ?, ?, ?, 1, NOW(), NOW(), 'failed', ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
          user_id = VALUES(user_id),
          failed_attempts = IF(last_failed_at < DATE_SUB(NOW(), INTERVAL {$window} MINUTE), 1, failed_attempts + 1),
          first_failed_at = IF(last_failed_at < DATE_SUB(NOW(), INTERVAL {$window} MINUTE), NOW(), first_failed_at),
          last_failed_at = NOW(),
          last_result = 'failed',
          user_agent = VALUES(user_agent),
          updated_at = NOW()
    ");
    if ($stmt) {
        $stmt->bind_param('sssis', $hash, $identifier, $ip, $userId, $agent);
        $stmt->execute();
        $stmt->close();
    }

    $state = tracs_auth_risk_state($conn, $identifier);
    if ($state['failed_count'] >= tracs_auth_max_attempts()) {
        $lockMinutes = tracs_auth_lock_minutes();
        $captchaMinutes = max($lockMinutes, tracs_auth_window_minutes());
        $lockStmt = $conn->prepare("
            UPDATE tracs_login_attempts
            SET locked_until = DATE_ADD(NOW(), INTERVAL ? MINUTE),
                captcha_required_until = DATE_ADD(NOW(), INTERVAL ? MINUTE),
                last_result = 'locked',
                updated_at = NOW()
            WHERE identifier_hash = ? OR ip_address = ?
        ");
        if ($lockStmt) {
            $lockStmt->bind_param('iiss', $lockMinutes, $captchaMinutes, $hash, $ip);
            $lockStmt->execute();
            $lockStmt->close();
        }
        $state = tracs_auth_risk_state($conn, $identifier);
        tracs_auth_log_event($conn, 'login_lock', 'locked', $identifier, $userId, 'temporary_lock');
    } elseif ($state['failed_count'] >= tracs_auth_captcha_after()) {
        $captchaMinutes = tracs_auth_window_minutes();
        $captchaStmt = $conn->prepare("
            UPDATE tracs_login_attempts
            SET captcha_required_until = DATE_ADD(NOW(), INTERVAL ? MINUTE),
                updated_at = NOW()
            WHERE identifier_hash = ? OR ip_address = ?
        ");
        if ($captchaStmt) {
            $captchaStmt->bind_param('iss', $captchaMinutes, $hash, $ip);
            $captchaStmt->execute();
            $captchaStmt->close();
        }
        $state = tracs_auth_risk_state($conn, $identifier);
        tracs_auth_log_event($conn, 'captcha_challenge', 'shown', $identifier, $userId, 'failed_attempt_threshold');
    }

    tracs_auth_log_event($conn, 'login_failed', 'failed', $identifier, $userId, $reason);
    return $state;
}

function tracs_auth_reset_failed_login(mysqli $conn, string $identifier, int $userId): void {
    if (function_exists('tracs_table_exists') && tracs_table_exists($conn, 'tracs_login_attempts')) {
        $hash = tracs_auth_identifier_hash($identifier);
        $ip = tracs_auth_client_ip();
        $stmt = $conn->prepare("
            UPDATE tracs_login_attempts
            SET failed_attempts = 0,
                locked_until = NULL,
                captcha_required_until = NULL,
                last_result = 'success',
                user_id = ?,
                updated_at = NOW()
            WHERE identifier_hash = ? OR ip_address = ?
        ");
        if ($stmt) {
            $stmt->bind_param('iss', $userId, $hash, $ip);
            $stmt->execute();
            $stmt->close();
        }
    }
    tracs_auth_log_event($conn, 'login_success', 'success', $identifier, $userId);
}

function tracs_auth_generate_internal_captcha(): array {
    tracs_start_session();
    $a = random_int(2, 9);
    $b = random_int(2, 9);
    $_SESSION['tracs_login_captcha'] = [
        'answer' => (string)($a + $b),
        'expires_at' => time() + (tracs_auth_window_minutes() * 60),
    ];
    return ['question' => "{$a} + {$b} ="];
}

function tracs_auth_internal_captcha_question(): string {
    tracs_start_session();
    $captcha = $_SESSION['tracs_login_captcha'] ?? null;
    if (!is_array($captcha) || (int)($captcha['expires_at'] ?? 0) < time() || empty($captcha['question'])) {
        $captcha = tracs_auth_generate_internal_captcha();
        $_SESSION['tracs_login_captcha']['question'] = $captcha['question'];
    }
    return (string)($_SESSION['tracs_login_captcha']['question'] ?? '');
}

function tracs_auth_verify_internal_captcha(string $answer): bool {
    tracs_start_session();
    $captcha = $_SESSION['tracs_login_captcha'] ?? null;
    if (!is_array($captcha) || (int)($captcha['expires_at'] ?? 0) < time()) {
        return false;
    }
    $ok = hash_equals((string)($captcha['answer'] ?? ''), trim($answer));
    unset($_SESSION['tracs_login_captcha']);
    return $ok;
}

function tracs_auth_verify_turnstile(string $token): bool {
    if (!tracs_auth_turnstile_enabled() || trim($token) === '') {
        return false;
    }
    $payload = http_build_query([
        'secret' => (string)tracs_auth_env('TRACS_TURNSTILE_SECRET_KEY', ''),
        'response' => $token,
        'remoteip' => tracs_auth_client_ip(),
    ]);
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $payload,
            'timeout' => 4,
        ],
    ]);
    $response = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $context);
    if (!is_string($response) || $response === '') {
        return false;
    }
    $json = json_decode($response, true);
    return !empty($json['success']);
}

function tracs_auth_verify_captcha(): bool {
    if (tracs_auth_turnstile_enabled()) {
        return tracs_auth_verify_turnstile((string)($_POST['cf-turnstile-response'] ?? ''));
    }
    return tracs_auth_verify_internal_captcha((string)($_POST['captcha_answer'] ?? ''));
}

function tracs_auth_recent_events(mysqli $conn, int $limit = 50): array {
    if (!function_exists('tracs_table_exists') || !tracs_table_exists($conn, 'tracs_auth_events')) {
        return [];
    }
    $limit = max(10, min(100, $limit));
    $sql = "
        SELECT e.*, COALESCE(NULLIF(u.name,''), u.email) AS user_name
        FROM tracs_auth_events e
        LEFT JOIN tracs_users u ON u.id = e.user_id
        ORDER BY e.created_at DESC
        LIMIT {$limit}
    ";
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function tracs_auth_locked_attempts(mysqli $conn, int $limit = 50): array {
    if (!function_exists('tracs_table_exists') || !tracs_table_exists($conn, 'tracs_login_attempts')) {
        return [];
    }
    $limit = max(10, min(100, $limit));
    $result = $conn->query("
        SELECT identifier_display, ip_address, user_id, failed_attempts, locked_until, captcha_required_until, last_failed_at, updated_at
        FROM tracs_login_attempts
        WHERE locked_until > NOW() OR captcha_required_until > NOW() OR failed_attempts > 0
        ORDER BY COALESCE(locked_until, captcha_required_until, last_failed_at) DESC
        LIMIT {$limit}
    ");
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}
