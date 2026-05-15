<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require '_bootstrap.php';
require_once __DIR__ . '/../../modules/currency/service.php';

$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$amount = (float) ($_GET['amount'] ?? 0);

if (!$from || !$to || $amount <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid parameters'
    ]);
    exit;
}

echo json_encode(convertCurrency($conn, $from, $to, $amount));
