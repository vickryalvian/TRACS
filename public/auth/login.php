<?php

require_once __DIR__ . '/../../core/security/csrf.php';
tracs_start_session();
require_once __DIR__ . '/../../config/database.php';

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

    $stmt = $conn->prepare('SELECT id, email, password FROM tracs_users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user && password_verify($password, $user['password'])) {

        session_regenerate_id(true);
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_email'] = $user['email'];

        header('Location: /index.php');
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
