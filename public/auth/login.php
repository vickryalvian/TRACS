<?php

require_once __DIR__ . '/../../core/security/csrf.php';
require_once __DIR__ . '/../../core/security/auth_hardening.php';
tracs_start_session();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/user_management.php';

/**
 * HANDLE LOGIN POST ONLY
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email    = tracs_auth_normalize_identifier((string)($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $_SESSION['login_identifier'] = $email;

    if (!$email || !$password) {
        $_SESSION['login_error'] = TRACS_AUTH_GENERIC_INVALID;
        header('Location: /login.php');
        exit;
    }

    $risk = tracs_auth_risk_state($conn, $email);
    if ($risk['locked']) {
        $_SESSION['login_error'] = TRACS_AUTH_GENERIC_LOCKED;
        $_SESSION['login_captcha_required'] = true;
        $_SESSION['login_show_help'] = true;
        tracs_auth_log_event($conn, 'login_blocked', 'locked', $email, null, 'temporary_lock');
        header('Location: /login.php');
        exit;
    }

    if ($risk['captcha_required']) {
        $_SESSION['login_captcha_required'] = true;
        if (!tracs_auth_verify_captcha()) {
            tracs_auth_log_event($conn, 'captcha_failed', 'failed', $email, null, 'invalid_captcha');
            $risk = tracs_auth_record_failed_login($conn, $email, null, 'invalid_captcha');
            $_SESSION['login_error'] = $risk['locked'] ? TRACS_AUTH_GENERIC_LOCKED : TRACS_AUTH_GENERIC_INVALID;
            $_SESSION['login_captcha_required'] = true;
            $_SESSION['login_show_help'] = true;
            header('Location: /login.php');
            exit;
        }
    }

    $user = tracs_get_user_by_login_identifier($conn, $email);
    $hash = (string)($user['password'] ?? TRACS_AUTH_DUMMY_HASH);
    $passwordOk = password_verify((string)$password, $hash);

    if ($user && $passwordOk) {
        if (!tracs_user_can_login($user)) {
            tracs_log_user_event($conn, (int)$user['id'], 'login_blocked', 'user', (int)$user['id'], null, ['status' => $user['status'] ?? 'inactive'], 'Account inactive or suspended');
            tracs_auth_log_event($conn, 'login_blocked', 'blocked', $email, (int)$user['id'], 'inactive_or_suspended');
            tracs_auth_record_failed_login($conn, $email, (int)$user['id'], 'inactive_or_suspended');
            $_SESSION['login_error'] = TRACS_AUTH_GENERIC_INVALID;
            $_SESSION['login_captcha_required'] = true;
            $_SESSION['login_show_help'] = true;
            header('Location: /login.php');
            exit;
        }

        if (!tracs_two_factor_ensure_schema($conn)) {
            tracs_auth_log_event($conn, 'login_blocked', 'blocked', $email, (int)$user['id'], 'two_factor_schema_missing');
            $_SESSION['login_error'] = 'Two-factor authentication is not ready. Please contact your administrator.';
            $_SESSION['login_show_help'] = true;
            header('Location: /login.php');
            exit;
        }

        $userId = (int)$user['id'];
        $user = tracs_get_user_by_id($conn, $userId) ?: $user;
        $landing = tracs_auth_user_landing($conn, $userId);

        if (password_needs_rehash((string)$user['password'], PASSWORD_DEFAULT)) {
            $rehash = password_hash((string)$password, PASSWORD_DEFAULT);
            $rehashSql = tracs_column_exists($conn, 'tracs_users', 'last_password_change_at')
                ? 'UPDATE tracs_users SET password = ?, last_password_change_at = COALESCE(last_password_change_at, NOW()) WHERE id = ?'
                : 'UPDATE tracs_users SET password = ? WHERE id = ?';
            $rehashStmt = $conn->prepare($rehashSql);
            if ($rehashStmt) {
                $rehashStmt->bind_param('si', $rehash, $userId);
                $rehashStmt->execute();
                $rehashStmt->close();
            }
        }

        session_regenerate_id(true);
        tracs_rotate_csrf_token();
        unset($_SESSION['login_error'], $_SESSION['login_identifier'], $_SESSION['login_captcha_required'], $_SESSION['tracs_login_captcha'], $_SESSION['login_show_help']);

        $mode = tracs_two_factor_user_configured($user) ? 'verify' : 'setup';
        tracs_auth_start_pending_2fa($user, $email, $landing, $mode);
        tracs_auth_reset_failed_login($conn, $email, $userId, 'password_verified');
        header('Location: /' . ($mode === 'setup' ? 'two-factor-setup.php' : 'two-factor-verify.php'));
        exit;
    }

    $risk = tracs_auth_record_failed_login($conn, $email, $user ? (int)$user['id'] : null);
    $_SESSION['login_error'] = $risk['locked'] ? TRACS_AUTH_GENERIC_LOCKED : TRACS_AUTH_GENERIC_INVALID;
    $_SESSION['login_captcha_required'] = !empty($risk['captcha_required']);
    $_SESSION['login_show_help'] = !empty($risk['captcha_required']) || !empty($risk['locked']) || ((int)($risk['failed_count'] ?? 0) > 1);
    header('Location: /login.php');
    exit;
}

/**
 * IMPORTANT:
 * Jangan redirect GET ke diri sendiri
 * Jangan render HTML di sini
 * hanya STOP supaya login.php (VIEW) yang jalan
 */
header('Location: /login.php');
exit;
