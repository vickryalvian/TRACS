<?php
/**
 * TRACS — Database Configuration
 * Edit credentials below before deployment
 */

 $db_host = $_ENV['DB_HOST'] ?? 'localhost';
 $db_user = $_ENV['DB_USER'] ?? 'root';
 $db_pass = $_ENV['DB_PASS'] ?? '';
 $db_name = $_ENV['DB_NAME'] ?? 'tracs';
 $db_port = (int)($_ENV['DB_PORT'] ?? 3306);
 
 $conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);

if ($conn->connect_error) {
    // In production: log to file, show generic error
    error_log('TRACS DB Error: ' . $conn->connect_error);
    if (php_sapi_name() === 'cli') {
        die('DB connection failed: ' . $conn->connect_error . PHP_EOL);
    }
    http_response_code(503);
    die('Service temporarily unavailable. Please try again later.');
}

$conn->set_charset('utf8mb4');
$conn->query("SET time_zone = '+07:00'"); // WIB / Asia/Jakarta
date_default_timezone_set('Asia/Jakarta');
