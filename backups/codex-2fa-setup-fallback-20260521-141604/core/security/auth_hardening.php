<?php
/**
 * TRACS authentication hardening helpers.
 */

const TRACS_AUTH_GENERIC_INVALID = 'Invalid login credentials.';
const TRACS_AUTH_GENERIC_LOCKED = 'Too many attempts. Please try again later.';
const TRACS_AUTH_HELP_MESSAGE = 'If you are having trouble logging in, please contact your administrator for further assistance.';
const TRACS_AUTH_DUMMY_HASH = '$2y$10$uG/.8q0jbJc8f9V6mI8GBeKyUQvubVw7aJ3d3AjzbHMDEZdyx1XmS';
const TRACS_2FA_GENERIC_INVALID = 'Invalid or expired verification code.';
const TRACS_2FA_SESSION_EXPIRED = 'Your verification session expired. Please sign in again.';

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

function tracs_two_factor_issuer(): string {
    $issuer = trim((string)tracs_auth_env('TRACS_2FA_ISSUER', 'TRACS'));
    $issuer = preg_replace('/[\x00-\x1F\x7F]+/', '', $issuer) ?? 'TRACS';
    return substr($issuer !== '' ? $issuer : 'TRACS', 0, 64);
}

function tracs_two_factor_pending_timeout_seconds(): int {
    return tracs_auth_config_int('TRACS_2FA_TIMEOUT_MINUTES', 10, 2, 60) * 60;
}

function tracs_two_factor_max_attempts(): int {
    return tracs_auth_config_int('TRACS_2FA_MAX_FAILED_ATTEMPTS', 5, 3, 20);
}

function tracs_two_factor_lock_seconds(): int {
    return tracs_auth_config_int('TRACS_2FA_LOCK_MINUTES', 15, 1, 1440) * 60;
}

function tracs_two_factor_valid_window_steps(): int {
    return tracs_auth_config_int('TRACS_2FA_VALID_WINDOW_STEPS', 1, 0, 2);
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

function tracs_auth_reset_failed_login(mysqli $conn, string $identifier, int $userId, string $eventType = 'password_verified'): void {
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
    tracs_auth_log_event($conn, $eventType, 'success', $identifier, $userId);
}

function tracs_two_factor_schema_ready(mysqli $conn): bool {
    return function_exists('tracs_column_exists')
        && tracs_column_exists($conn, 'tracs_users', 'two_factor_enabled')
        && tracs_column_exists($conn, 'tracs_users', 'two_factor_secret')
        && tracs_column_exists($conn, 'tracs_users', 'two_factor_confirmed_at')
        && tracs_column_exists($conn, 'tracs_users', 'two_factor_reset_required')
        && tracs_column_exists($conn, 'tracs_users', 'two_factor_failed_attempts')
        && tracs_column_exists($conn, 'tracs_users', 'two_factor_locked_until');
}

function tracs_two_factor_user_configured(array $user): bool {
    return !empty($user['two_factor_enabled'])
        && trim((string)($user['two_factor_secret'] ?? '')) !== ''
        && empty($user['two_factor_reset_required'])
        && !empty($user['two_factor_confirmed_at']);
}

function tracs_two_factor_base32_encode(string $bytes): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    $encoded = '';
    $length = strlen($bytes);
    for ($i = 0; $i < $length; $i++) {
        $bits .= str_pad(decbin(ord($bytes[$i])), 8, '0', STR_PAD_LEFT);
    }
    for ($i = 0; $i < strlen($bits); $i += 5) {
        $chunk = substr($bits, $i, 5);
        if (strlen($chunk) < 5) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
        }
        $encoded .= $alphabet[bindec($chunk)];
    }
    return $encoded;
}

function tracs_two_factor_base32_decode(string $secret): string {
    $secret = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $secret) ?? '');
    $alphabet = array_flip(str_split('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'));
    $bits = '';
    for ($i = 0; $i < strlen($secret); $i++) {
        if (!isset($alphabet[$secret[$i]])) {
            continue;
        }
        $bits .= str_pad(decbin($alphabet[$secret[$i]]), 5, '0', STR_PAD_LEFT);
    }
    $decoded = '';
    for ($i = 0; $i + 8 <= strlen($bits); $i += 8) {
        $decoded .= chr(bindec(substr($bits, $i, 8)));
    }
    return $decoded;
}

function tracs_two_factor_generate_secret(): string {
    return tracs_two_factor_base32_encode(random_bytes(20));
}

function tracs_two_factor_format_secret(string $secret): string {
    return trim(chunk_split(strtoupper($secret), 4, ' '));
}

function tracs_two_factor_encryption_key(): string {
    global $db_pass, $db_name;
    $configured = trim((string)tracs_auth_env('TRACS_2FA_SECRET_KEY', ''));
    if ($configured !== '') {
        if (str_starts_with($configured, 'base64:')) {
            $decoded = base64_decode(substr($configured, 7), true);
            if (is_string($decoded) && $decoded !== '') {
                return hash('sha256', $decoded, true);
            }
        }
        return hash('sha256', $configured, true);
    }

    $fallback = implode('|', [
        (string)tracs_auth_env('DB_PASS', (string)($db_pass ?? '')),
        (string)tracs_auth_env('DB_NAME', (string)($db_name ?? '')),
        (string)tracs_auth_env('APP_NAME', 'TRACS'),
    ]);
    return hash('sha256', $fallback, true);
}

function tracs_two_factor_encrypt_secret(string $secret): string {
    $key = tracs_two_factor_encryption_key();
    if (function_exists('sodium_crypto_secretbox')) {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($secret, $nonce, $key);
        return 'sodium:v1:' . base64_encode($nonce) . ':' . base64_encode($cipher);
    }

    if (function_exists('openssl_encrypt')) {
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($secret, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if (is_string($cipher) && $tag !== '') {
            return 'gcm:v1:' . base64_encode($iv) . ':' . base64_encode($tag) . ':' . base64_encode($cipher);
        }
    }

    throw new RuntimeException('Two-factor secret encryption is unavailable.');
}

function tracs_two_factor_decrypt_secret(string $stored): ?string {
    $stored = trim($stored);
    if ($stored === '') {
        return null;
    }
    $parts = explode(':', $stored);
    $key = tracs_two_factor_encryption_key();

    if (($parts[0] ?? '') === 'sodium' && ($parts[1] ?? '') === 'v1' && count($parts) === 4 && function_exists('sodium_crypto_secretbox_open')) {
        $nonce = base64_decode($parts[2], true);
        $cipher = base64_decode($parts[3], true);
        if (!is_string($nonce) || !is_string($cipher)) {
            return null;
        }
        $plain = sodium_crypto_secretbox_open($cipher, $nonce, $key);
        return is_string($plain) ? $plain : null;
    }

    if (($parts[0] ?? '') === 'gcm' && ($parts[1] ?? '') === 'v1' && count($parts) === 5 && function_exists('openssl_decrypt')) {
        $iv = base64_decode($parts[2], true);
        $tag = base64_decode($parts[3], true);
        $cipher = base64_decode($parts[4], true);
        if (!is_string($iv) || !is_string($tag) || !is_string($cipher)) {
            return null;
        }
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        return is_string($plain) ? $plain : null;
    }

    return null;
}

function tracs_two_factor_hotp(string $secret, int $counter, int $digits = 6): string {
    $key = tracs_two_factor_base32_decode($secret);
    if ($key === '') {
        return '';
    }
    $binaryCounter = pack('N*', 0) . pack('N*', $counter);
    $hash = hash_hmac('sha1', $binaryCounter, $key, true);
    $offset = ord($hash[strlen($hash) - 1]) & 0x0f;
    $value = ((ord($hash[$offset]) & 0x7f) << 24)
        | ((ord($hash[$offset + 1]) & 0xff) << 16)
        | ((ord($hash[$offset + 2]) & 0xff) << 8)
        | (ord($hash[$offset + 3]) & 0xff);
    return str_pad((string)($value % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
}

function tracs_two_factor_verify_code(string $secret, string $code, ?int $time = null): bool {
    $code = preg_replace('/\D+/', '', $code) ?? '';
    if (!preg_match('/^\d{6}$/', $code)) {
        return false;
    }
    $time = $time ?? time();
    $counter = intdiv($time, 30);
    $window = tracs_two_factor_valid_window_steps();
    for ($step = -$window; $step <= $window; $step++) {
        $expected = tracs_two_factor_hotp($secret, $counter + $step);
        if ($expected !== '' && hash_equals($expected, $code)) {
            return true;
        }
    }
    return false;
}

function tracs_two_factor_account_label(array $user): string {
    $label = trim((string)($user['email'] ?? $user['username'] ?? $user['display_name'] ?? 'user'));
    $label = preg_replace('/[\x00-\x1F\x7F]+/', '', $label) ?? 'user';
    return substr($label !== '' ? $label : 'user', 0, 80);
}

function tracs_two_factor_otpauth_uri(array $user, string $secret): string {
    $issuer = tracs_two_factor_issuer();
    $account = tracs_two_factor_account_label($user);
    $label = rawurlencode($issuer . ':' . $account);
    return 'otpauth://totp/' . $label
        . '?secret=' . rawurlencode($secret)
        . '&issuer=' . rawurlencode($issuer)
        . '&algorithm=SHA1&digits=6&period=30';
}

function tracs_auth_allowed_landing(string $landing): string {
    $landing = ltrim($landing, '/');
    $allowed = ['index.php', 'cases.php', 'reminders.php', 'checklist.php', 'shift-reports.php', 'mom.php', 'activity.php', 'tasks.php', 'monitoring.php', 'domains.php', 'finance.php'];
    return in_array($landing, $allowed, true) ? $landing : 'index.php';
}

function tracs_auth_user_landing(mysqli $conn, int $userId): string {
    $landing = 'index.php';
    if (function_exists('tracs_table_exists') && tracs_table_exists($conn, 'tracs_user_preferences')) {
        $prefStmt = $conn->prepare("SELECT preference_value FROM tracs_user_preferences WHERE user_id = ? AND preference_key = 'default_landing_page' LIMIT 1");
        if ($prefStmt) {
            $prefStmt->bind_param('i', $userId);
            $prefStmt->execute();
            $prefRow = $prefStmt->get_result()->fetch_assoc();
            $prefStmt->close();
            $landing = tracs_auth_allowed_landing((string)($prefRow['preference_value'] ?? 'index.php'));
        }
    }
    return $landing;
}

function tracs_auth_clear_pending_2fa(): void {
    tracs_start_session();
    unset(
        $_SESSION['tracs_pre_2fa_user_id'],
        $_SESSION['tracs_pre_2fa_identifier'],
        $_SESSION['tracs_pre_2fa_started_at'],
        $_SESSION['tracs_pre_2fa_expires_at'],
        $_SESSION['tracs_pre_2fa_landing'],
        $_SESSION['tracs_pre_2fa_mode'],
        $_SESSION['tracs_pending_2fa_secret'],
        $_SESSION['tracs_pending_2fa_setup_logged']
    );
}

function tracs_auth_start_pending_2fa(array $user, string $identifier, string $landing, string $mode): void {
    tracs_start_session();
    unset($_SESSION['user_id'], $_SESSION['user_email'], $_SESSION['user_name'], $_SESSION['user_role_slug'], $_SESSION['user_role_name'], $_SESSION['user_division_id'], $_SESSION['tracs_auth_state']);
    $_SESSION['tracs_pre_2fa_user_id'] = (int)$user['id'];
    $_SESSION['tracs_pre_2fa_identifier'] = tracs_auth_normalize_identifier($identifier);
    $_SESSION['tracs_pre_2fa_started_at'] = time();
    $_SESSION['tracs_pre_2fa_expires_at'] = time() + tracs_two_factor_pending_timeout_seconds();
    $_SESSION['tracs_pre_2fa_landing'] = tracs_auth_allowed_landing($landing);
    $_SESSION['tracs_pre_2fa_mode'] = $mode === 'setup' ? 'setup' : 'verify';
}

function tracs_auth_pending_user_id(): int {
    tracs_start_session();
    return (int)($_SESSION['tracs_pre_2fa_user_id'] ?? 0);
}

function tracs_auth_pending_identifier(): string {
    tracs_start_session();
    return (string)($_SESSION['tracs_pre_2fa_identifier'] ?? '');
}

function tracs_auth_pending_landing(): string {
    tracs_start_session();
    return tracs_auth_allowed_landing((string)($_SESSION['tracs_pre_2fa_landing'] ?? 'index.php'));
}

function tracs_auth_pending_mode(): string {
    tracs_start_session();
    return (string)($_SESSION['tracs_pre_2fa_mode'] ?? '');
}

function tracs_auth_pending_expired(): bool {
    tracs_start_session();
    return tracs_auth_pending_user_id() <= 0 || (int)($_SESSION['tracs_pre_2fa_expires_at'] ?? 0) < time();
}

function tracs_auth_pending_redirect_path(mysqli $conn): string {
    $userId = tracs_auth_pending_user_id();
    if ($userId <= 0) {
        return '/login.php';
    }
    $mode = tracs_auth_pending_mode();
    if ($mode === 'setup') {
        return '/two-factor-setup.php';
    }
    if ($mode === 'verify') {
        return '/two-factor-verify.php';
    }
    if (function_exists('tracs_get_user_by_id')) {
        $user = tracs_get_user_by_id($conn, $userId);
        return ($user && tracs_two_factor_user_configured($user)) ? '/two-factor-verify.php' : '/two-factor-setup.php';
    }
    return '/login.php';
}

function tracs_is_fully_authenticated(): bool {
    tracs_start_session();
    return !empty($_SESSION['user_id']) && (string)($_SESSION['tracs_auth_state'] ?? '') === 'full';
}

function tracs_two_factor_record_failure(mysqli $conn, int $userId, string $identifier = ''): array {
    $state = ['locked' => false, 'failed_attempts' => 1];
    if (!tracs_two_factor_schema_ready($conn)) {
        tracs_auth_log_event($conn, 'two_factor_failed', 'failed', $identifier, $userId, 'invalid_or_expired_code');
        return $state;
    }

    $stmt = $conn->prepare("
        UPDATE tracs_users
        SET two_factor_failed_attempts = COALESCE(two_factor_failed_attempts, 0) + 1,
            updated_at = NOW()
        WHERE id = ?
    ");
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
    }

    $failed = 1;
    $read = $conn->prepare('SELECT two_factor_failed_attempts FROM tracs_users WHERE id = ? LIMIT 1');
    if ($read) {
        $read->bind_param('i', $userId);
        $read->execute();
        $failed = (int)($read->get_result()->fetch_assoc()['two_factor_failed_attempts'] ?? 1);
        $read->close();
    }
    $state['failed_attempts'] = $failed;

    if ($failed >= tracs_two_factor_max_attempts()) {
        $lockSeconds = tracs_two_factor_lock_seconds();
        $lock = $conn->prepare('UPDATE tracs_users SET two_factor_locked_until = DATE_ADD(NOW(), INTERVAL ? SECOND), updated_at = NOW() WHERE id = ?');
        if ($lock) {
            $lock->bind_param('ii', $lockSeconds, $userId);
            $lock->execute();
            $lock->close();
        }
        $state['locked'] = true;
        tracs_auth_log_event($conn, 'two_factor_lock', 'locked', $identifier, $userId, 'temporary_lock');
    }

    tracs_auth_log_event($conn, 'two_factor_failed', 'failed', $identifier, $userId, 'invalid_or_expired_code');
    return $state;
}

function tracs_two_factor_reset_attempts(mysqli $conn, int $userId): void {
    if (!tracs_two_factor_schema_ready($conn)) {
        return;
    }
    $stmt = $conn->prepare('UPDATE tracs_users SET two_factor_failed_attempts = 0, two_factor_locked_until = NULL, two_factor_last_verified_at = NOW(), updated_at = NOW() WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
    }
}

function tracs_two_factor_locked(array $user): bool {
    $lockedUntil = (string)($user['two_factor_locked_until'] ?? '');
    return $lockedUntil !== '' && strtotime($lockedUntil) > time();
}

function tracs_auth_complete_full_login(mysqli $conn, array $user, string $identifier = '', ?string $landing = null): void {
    tracs_start_session();
    $userId = (int)$user['id'];
    $landing = tracs_auth_allowed_landing($landing ?? tracs_auth_pending_landing());
    session_regenerate_id(true);
    tracs_rotate_csrf_token();
    if (function_exists('tracs_sync_session_user')) {
        tracs_sync_session_user($user);
    } else {
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_email'] = (string)($user['email'] ?? '');
        $_SESSION['user_name'] = (string)($user['display_name'] ?? $user['name'] ?? $user['email'] ?? 'User');
    }
    $_SESSION['tracs_auth_state'] = 'full';
    $_SESSION['tracs_2fa_verified_at'] = time();
    $_SESSION['tracs_last_seen_at'] = time();
    tracs_auth_clear_pending_2fa();
    unset($_SESSION['login_error'], $_SESSION['login_identifier'], $_SESSION['login_captcha_required'], $_SESSION['tracs_login_captcha'], $_SESSION['login_show_help']);

    $sets = ['last_login_at = NOW()'];
    if (function_exists('tracs_column_exists') && tracs_column_exists($conn, 'tracs_users', 'last_activity_at')) {
        $sets[] = 'last_activity_at = NOW()';
    }
    $sql = 'UPDATE tracs_users SET ' . implode(', ', $sets) . ' WHERE id = ?';
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
    }

    tracs_two_factor_reset_attempts($conn, $userId);
    tracs_auth_log_event($conn, 'two_factor_verified', 'success', $identifier, $userId);
    tracs_auth_log_event($conn, 'login_success', 'success', $identifier, $userId, 'two_factor_completed');
    header('Location: /' . $landing);
    exit;
}

function tracs_two_factor_confirm_setup(mysqli $conn, int $userId, string $secret): void {
    $encrypted = tracs_two_factor_encrypt_secret($secret);
    $stmt = $conn->prepare("
        UPDATE tracs_users
        SET two_factor_enabled = 1,
            two_factor_secret = ?,
            two_factor_confirmed_at = NOW(),
            two_factor_reset_required = 0,
            two_factor_failed_attempts = 0,
            two_factor_locked_until = NULL,
            two_factor_last_verified_at = NOW(),
            updated_at = NOW()
        WHERE id = ?
    ");
    if (!$stmt) {
        throw new RuntimeException('Unable to save two-factor setup.');
    }
    $stmt->bind_param('si', $encrypted, $userId);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('Unable to save two-factor setup.');
    }
    $stmt->close();
}

function tracs_two_factor_reset_for_user(mysqli $conn, int $userId): void {
    if (!tracs_two_factor_schema_ready($conn)) {
        throw new RuntimeException('Two-factor schema is not installed.');
    }
    $stmt = $conn->prepare("
        UPDATE tracs_users
        SET two_factor_enabled = 0,
            two_factor_secret = NULL,
            two_factor_confirmed_at = NULL,
            two_factor_reset_required = 1,
            two_factor_failed_attempts = 0,
            two_factor_locked_until = NULL,
            two_factor_last_verified_at = NULL,
            updated_at = NOW()
        WHERE id = ?
    ");
    if (!$stmt) {
        throw new RuntimeException('Unable to reset two-factor authentication.');
    }
    $stmt->bind_param('i', $userId);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('Unable to reset two-factor authentication.');
    }
    $stmt->close();
}

function tracs_qr_gf_mul(int $x, int $y): int {
    $z = 0;
    for ($i = 7; $i >= 0; $i--) {
        $z = (($z << 1) ^ (($z >> 7) * 0x11D)) & 0xFF;
        if ((($y >> $i) & 1) !== 0) {
            $z ^= $x;
        }
    }
    return $z;
}

function tracs_qr_rs_generator(int $degree): array {
    $result = [1];
    $root = 1;
    for ($i = 0; $i < $degree; $i++) {
        $next = array_fill(0, count($result) + 1, 0);
        foreach ($result as $j => $coef) {
            $next[$j] ^= $coef;
            $next[$j + 1] ^= tracs_qr_gf_mul($coef, $root);
        }
        $result = $next;
        $root = tracs_qr_gf_mul($root, 0x02);
    }
    return $result;
}

function tracs_qr_rs_remainder(array $data, int $degree): array {
    $generator = tracs_qr_rs_generator($degree);
    $result = array_fill(0, $degree, 0);
    foreach ($data as $byte) {
        $factor = $byte ^ $result[0];
        array_shift($result);
        $result[] = 0;
        for ($i = 0; $i < $degree; $i++) {
            $result[$i] ^= tracs_qr_gf_mul($generator[$i + 1], $factor);
        }
    }
    return $result;
}

function tracs_qr_version_info(int $version): int {
    $rem = $version;
    for ($i = 0; $i < 12; $i++) {
        $rem = ($rem << 1) ^ ((($rem >> 11) & 1) * 0x1F25);
    }
    return ($version << 12) | $rem;
}

function tracs_qr_format_info(int $mask): int {
    $data = (1 << 3) | $mask; // Error correction L.
    $rem = $data;
    for ($i = 0; $i < 10; $i++) {
        $rem = ($rem << 1) ^ ((($rem >> 9) & 1) * 0x537);
    }
    return (($data << 10) | $rem) ^ 0x5412;
}

function tracs_qr_mask_bit(int $mask, int $x, int $y): bool {
    return match ($mask) {
        0 => (($x + $y) % 2) === 0,
        1 => ($y % 2) === 0,
        2 => ($x % 3) === 0,
        3 => (($x + $y) % 3) === 0,
        default => (($x + $y) % 2) === 0,
    };
}

function tracs_qr_set(array &$matrix, array &$function, int $x, int $y, bool $dark, bool $isFunction = true): void {
    if (!isset($matrix[$y][$x])) {
        return;
    }
    $matrix[$y][$x] = $dark;
    if ($isFunction) {
        $function[$y][$x] = true;
    }
}

function tracs_qr_draw_finder(array &$matrix, array &$function, int $x, int $y): void {
    $size = count($matrix);
    for ($dy = -1; $dy <= 7; $dy++) {
        for ($dx = -1; $dx <= 7; $dx++) {
            $xx = $x + $dx;
            $yy = $y + $dy;
            if ($xx < 0 || $xx >= $size || $yy < 0 || $yy >= $size) {
                continue;
            }
            $dark = ($dx >= 0 && $dx <= 6 && $dy >= 0 && $dy <= 6)
                && ($dx === 0 || $dx === 6 || $dy === 0 || $dy === 6 || ($dx >= 2 && $dx <= 4 && $dy >= 2 && $dy <= 4));
            tracs_qr_set($matrix, $function, $xx, $yy, $dark, true);
        }
    }
}

function tracs_qr_draw_alignment(array &$matrix, array &$function, int $x, int $y): void {
    for ($dy = -2; $dy <= 2; $dy++) {
        for ($dx = -2; $dx <= 2; $dx++) {
            $dark = max(abs($dx), abs($dy)) !== 1;
            tracs_qr_set($matrix, $function, $x + $dx, $y + $dy, $dark, true);
        }
    }
}

function tracs_qr_alignment_positions(int $version): array {
    return [
        1 => [],
        2 => [6, 18],
        3 => [6, 22],
        4 => [6, 26],
        5 => [6, 30],
        6 => [6, 34],
        7 => [6, 22, 38],
        8 => [6, 24, 42],
        9 => [6, 26, 46],
        10 => [6, 28, 50],
    ][$version] ?? [];
}

function tracs_qr_block_specs(int $version): array {
    return [
        1 => ['data' => 19, 'ec' => 7, 'blocks' => [19]],
        2 => ['data' => 34, 'ec' => 10, 'blocks' => [34]],
        3 => ['data' => 55, 'ec' => 15, 'blocks' => [55]],
        4 => ['data' => 80, 'ec' => 20, 'blocks' => [80]],
        5 => ['data' => 108, 'ec' => 26, 'blocks' => [108]],
        6 => ['data' => 136, 'ec' => 18, 'blocks' => [68, 68]],
        7 => ['data' => 156, 'ec' => 20, 'blocks' => [78, 78]],
        8 => ['data' => 194, 'ec' => 24, 'blocks' => [97, 97]],
        9 => ['data' => 232, 'ec' => 30, 'blocks' => [116, 116]],
        10 => ['data' => 274, 'ec' => 18, 'blocks' => [68, 68, 69, 69]],
    ][$version] ?? ['data' => 274, 'ec' => 18, 'blocks' => [68, 68, 69, 69]];
}

function tracs_qr_data_codewords(string $text, int $version): array {
    $spec = tracs_qr_block_specs($version);
    $bytes = array_values(unpack('C*', $text) ?: []);
    $bits = '0100' . str_pad(decbin(count($bytes)), $version <= 9 ? 8 : 16, '0', STR_PAD_LEFT);
    foreach ($bytes as $byte) {
        $bits .= str_pad(decbin($byte), 8, '0', STR_PAD_LEFT);
    }
    $capacityBits = $spec['data'] * 8;
    $bits .= str_repeat('0', min(4, max(0, $capacityBits - strlen($bits))));
    while (strlen($bits) % 8 !== 0) {
        $bits .= '0';
    }
    $codewords = [];
    for ($i = 0; $i < strlen($bits); $i += 8) {
        $codewords[] = bindec(substr($bits, $i, 8));
    }
    for ($pad = 0; count($codewords) < $spec['data']; $pad++) {
        $codewords[] = ($pad % 2 === 0) ? 0xEC : 0x11;
    }
    return $codewords;
}

function tracs_qr_interleaved_codewords(array $dataCodewords, int $version): array {
    $spec = tracs_qr_block_specs($version);
    $blocks = [];
    $offset = 0;
    foreach ($spec['blocks'] as $length) {
        $data = array_slice($dataCodewords, $offset, $length);
        $offset += $length;
        $blocks[] = ['data' => $data, 'ec' => tracs_qr_rs_remainder($data, $spec['ec'])];
    }

    $result = [];
    $maxData = max($spec['blocks']);
    for ($i = 0; $i < $maxData; $i++) {
        foreach ($blocks as $block) {
            if (isset($block['data'][$i])) {
                $result[] = $block['data'][$i];
            }
        }
    }
    for ($i = 0; $i < $spec['ec']; $i++) {
        foreach ($blocks as $block) {
            $result[] = $block['ec'][$i];
        }
    }
    return $result;
}

function tracs_qr_matrix(string $text, int $mask = 0): array {
    $length = strlen($text);
    $version = 1;
    for ($v = 1; $v <= 10; $v++) {
        $spec = tracs_qr_block_specs($v);
        $countBits = $v <= 9 ? 8 : 16;
        if (4 + $countBits + ($length * 8) <= $spec['data'] * 8) {
            $version = $v;
            break;
        }
    }
    $size = 21 + 4 * ($version - 1);
    $matrix = array_fill(0, $size, array_fill(0, $size, false));
    $function = array_fill(0, $size, array_fill(0, $size, false));

    tracs_qr_draw_finder($matrix, $function, 0, 0);
    tracs_qr_draw_finder($matrix, $function, $size - 7, 0);
    tracs_qr_draw_finder($matrix, $function, 0, $size - 7);

    for ($i = 0; $i < $size; $i++) {
        if (!$function[6][$i]) {
            tracs_qr_set($matrix, $function, $i, 6, $i % 2 === 0, true);
        }
        if (!$function[$i][6]) {
            tracs_qr_set($matrix, $function, 6, $i, $i % 2 === 0, true);
        }
    }

    $align = tracs_qr_alignment_positions($version);
    foreach ($align as $x) {
        foreach ($align as $y) {
            if (($x === 6 && $y === 6) || ($x === 6 && $y === $size - 7) || ($x === $size - 7 && $y === 6)) {
                continue;
            }
            tracs_qr_draw_alignment($matrix, $function, $x, $y);
        }
    }

    tracs_qr_set($matrix, $function, 8, $size - 8, true, true);
    $format = tracs_qr_format_info($mask);
    for ($i = 0; $i <= 5; $i++) tracs_qr_set($matrix, $function, 8, $i, (($format >> $i) & 1) !== 0, true);
    tracs_qr_set($matrix, $function, 8, 7, (($format >> 6) & 1) !== 0, true);
    tracs_qr_set($matrix, $function, 8, 8, (($format >> 7) & 1) !== 0, true);
    tracs_qr_set($matrix, $function, 7, 8, (($format >> 8) & 1) !== 0, true);
    for ($i = 9; $i < 15; $i++) tracs_qr_set($matrix, $function, 14 - $i, 8, (($format >> $i) & 1) !== 0, true);
    for ($i = 0; $i < 8; $i++) tracs_qr_set($matrix, $function, $size - 1 - $i, 8, (($format >> $i) & 1) !== 0, true);
    for ($i = 8; $i < 15; $i++) tracs_qr_set($matrix, $function, 8, $size - 15 + $i, (($format >> $i) & 1) !== 0, true);

    if ($version >= 7) {
        $versionInfo = tracs_qr_version_info($version);
        for ($i = 0; $i < 18; $i++) {
            $bit = (($versionInfo >> $i) & 1) !== 0;
            $a = $size - 11 + ($i % 3);
            $b = intdiv($i, 3);
            tracs_qr_set($matrix, $function, $a, $b, $bit, true);
            tracs_qr_set($matrix, $function, $b, $a, $bit, true);
        }
    }

    $codewords = tracs_qr_interleaved_codewords(tracs_qr_data_codewords($text, $version), $version);
    $bits = '';
    foreach ($codewords as $codeword) {
        $bits .= str_pad(decbin($codeword), 8, '0', STR_PAD_LEFT);
    }

    $bitIndex = 0;
    $upward = true;
    for ($right = $size - 1; $right >= 1; $right -= 2) {
        if ($right === 6) {
            $right--;
        }
        for ($vert = 0; $vert < $size; $vert++) {
            $y = $upward ? $size - 1 - $vert : $vert;
            for ($j = 0; $j < 2; $j++) {
                $x = $right - $j;
                if ($function[$y][$x]) {
                    continue;
                }
                $dark = $bitIndex < strlen($bits) && $bits[$bitIndex] === '1';
                if (tracs_qr_mask_bit($mask, $x, $y)) {
                    $dark = !$dark;
                }
                $matrix[$y][$x] = $dark;
                $bitIndex++;
            }
        }
        $upward = !$upward;
    }

    return $matrix;
}

function tracs_two_factor_qr_svg(string $text, int $scale = 5, int $border = 4): string {
    $matrix = tracs_qr_matrix($text, 0);
    $size = count($matrix);
    $dimension = ($size + $border * 2) * $scale;
    $paths = [];
    for ($y = 0; $y < $size; $y++) {
        for ($x = 0; $x < $size; $x++) {
            if (!empty($matrix[$y][$x])) {
                $paths[] = 'M' . (($x + $border) * $scale) . ' ' . (($y + $border) * $scale) . 'h' . $scale . 'v' . $scale . 'h-' . $scale . 'z';
            }
        }
    }
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $dimension . ' ' . $dimension . '" role="img" aria-label="Authenticator app setup QR code">'
        . '<rect width="100%" height="100%" fill="#fff"/>'
        . '<path d="' . implode('', $paths) . '" fill="#111827"/>'
        . '</svg>';
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
