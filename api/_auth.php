<?php
declare(strict_types=1);

namespace TRACS\Api;

require_once __DIR__ . '/../core/security/direct_access.php';
\tracs_deny_direct_script_access(__FILE__);
require_once __DIR__ . '/../core/security/csrf.php';
require_once __DIR__ . '/../core/user_management.php';
require_once __DIR__ . '/_response.php';
require_once __DIR__ . '/_logging.php';

function current_user(?\mysqli $conn = null): ?array
{
    \tracs_start_session();

    if (!\tracs_is_fully_authenticated()) {
        return null;
    }

    $userId = (int)($_SESSION['user_id'] ?? 0);
    if ($userId <= 0 || !$conn instanceof \mysqli) {
        return null;
    }

    $user = \tracs_get_user_by_id($conn, $userId);
    return $user && \tracs_user_can_login($user) ? $user : null;
}

function require_auth(?\mysqli $conn = null): array
{
    \tracs_start_session();

    if (!\tracs_is_fully_authenticated()) {
        if ($conn instanceof \mysqli && \tracs_auth_pending_user_id() > 0) {
            $pendingExpired = \tracs_auth_pending_expired();
            $reason = $pendingExpired
                ? 'pending_two_factor_expired'
                : 'pending_two_factor';
            \tracs_auth_log_event(
                $conn,
                'suspicious_access_attempt',
                'blocked',
                \tracs_auth_pending_identifier(),
                \tracs_auth_pending_user_id(),
                $reason
            );
            if ($pendingExpired) {
                \tracs_auth_clear_pending_2fa();
            }
        }
        json_error('Authentication is required.', 401);
    }

    $lastSeen = (int)($_SESSION['tracs_last_seen_at'] ?? time());
    if ((time() - $lastSeen) > \tracs_auth_idle_timeout_seconds()) {
        $expiredUserId = (int)($_SESSION['user_id'] ?? 0);
        \tracs_auth_destroy_current_session();
        if ($conn instanceof \mysqli) {
            \tracs_auth_log_event(
                $conn,
                'session_timeout',
                'expired',
                '',
                $expiredUserId ?: null,
                'idle_timeout'
            );
        }
        json_error('Your session has expired. Please sign in again.', 401);
    }

    if (!$conn instanceof \mysqli) {
        write_error_log('Authenticated API bootstrap is missing a database connection.');
        json_error('The server could not complete the request.', 500);
    }

    $user = current_user($conn);
    if ($user === null) {
        json_error('This account is inactive or unavailable.', 403);
    }

    $_SESSION['tracs_last_seen_at'] = time();
    \tracs_sync_session_user($user);
    \tracs_touch_user_activity($conn, (int)$user['id']);

    return $user;
}
