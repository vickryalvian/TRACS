<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../modules/currency/service.php';

header('Content-Type: application/json');

$pdo = $pdo ?? null; // pastikan ada

$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$amount = (float) ($_GET['amount'] ?? 0);

if (!$from || !$to || $amount <= 0) {
    echo json_encode(convertCurrency($conn, $from, $to, $amount));
    exit;
}

echo json_encode(convertCurrency($from, $to, $amount, $pdo)
);