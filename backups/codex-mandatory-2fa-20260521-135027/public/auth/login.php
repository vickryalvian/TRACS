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
            header('Location: /login.php');
            exit;
        }

        session_regenerate_id(true);
        tracs_rotate_csrf_token();
        tracs_sync_session_user($user);
        $_SESSION['tracs_last_seen_at'] = time();
        unset($_SESSION['login_error'], $_SESSION['login_identifier'], $_SESSION['login_captcha_required'], $_SESSION['tracs_login_captcha']);

        $sets = ['last_login_at = NOW()'];
        if (tracs_column_exists($conn, 'tracs_users', 'last_activity_at')) {
            $sets[] = 'last_activity_at = NOW()';
        }
        $sql = 'UPDATE tracs_users SET ' . implode(', ', $sets) . ' WHERE id = ?';
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $userId = (int)$user['id'];
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->close();
        }

        $landing = 'index.php';
        if (tracs_table_exists($conn, 'tracs_user_preferences')) {
            $prefStmt = $conn->prepare("SELECT preference_value FROM tracs_user_preferences WHERE user_id = ? AND preference_key = 'default_landing_page' LIMIT 1");
            if ($prefStmt) {
                $prefStmt->bind_param('i', $userId);
                $prefStmt->execute();
                $prefRow = $prefStmt->get_result()->fetch_assoc();
                $prefStmt->close();
                $allowedLanding = ['index.php', 'cases.php', 'reminders.php', 'checklist.php', 'shift-reports.php', 'mom.php', 'activity.php'];
                if (in_array((string)($prefRow['preference_value'] ?? ''), $allowedLanding, true)) {
                    $landing = (string)$prefRow['preference_value'];
                }
            }
        }

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

        tracs_auth_reset_failed_login($conn, $email, $userId);
        header('Location: /' . $landing);
        exit;
    }

    $risk = tracs_auth_record_failed_login($conn, $email, $user ? (int)$user['id'] : null);
    $_SESSION['login_error'] = $risk['locked'] ? TRACS_AUTH_GENERIC_LOCKED : TRACS_AUTH_GENERIC_INVALID;
    $_SESSION['login_captcha_required'] = !empty($risk['captcha_required']);
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
