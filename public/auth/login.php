<?php

require_once __DIR__ . '/../../core/security/csrf.php';
tracs_start_session();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/user_management.php';

/**
 * HANDLE LOGIN POST ONLY
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $_SESSION['login_error'] = 'Email and password are required.';
        header('Location: /login.php');
        exit;
    }

    $user = tracs_get_user_by_email($conn, $email);

    if ($user && password_verify($password, $user['password'])) {
        if (!tracs_user_can_login($user)) {
            tracs_log_user_event($conn, (int)$user['id'], 'login_blocked', 'user', (int)$user['id'], null, ['status' => $user['status'] ?? 'inactive'], 'Account inactive or suspended');
            $_SESSION['login_error'] = 'This account is inactive or suspended.';
            header('Location: /login.php');
            exit;
        }

        session_regenerate_id(true);
        tracs_sync_session_user($user);

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

        header('Location: /' . $landing);
        exit;
    }

    $_SESSION['login_error'] = 'Invalid email or password.';
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
