<?php
require_once __DIR__ . '/../../core/security/csrf.php';
tracs_start_session();

$uri = $_SERVER['REQUEST_URI'];

// allow login pages
if (
    strpos($uri, '/login.php') !== false ||
    strpos($uri, '/auth/login.php') !== false
) {
    return;
}

if (empty($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}
