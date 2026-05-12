<?php
if (session_status() === PHP_SESSION_NONE) session_start();

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