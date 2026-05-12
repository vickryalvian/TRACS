<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../modules/currency/service.php';

header('Content-Type: application/json');

$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$amount = (float) ($_GET['amount'] ?? 0);

if (!$from || !$to || !$amount) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid parameters'
    ]);
    exit;
}

echo json_encode(convertCurrency($from, $to, $amount, $pdo));