<?php
/**
 * currency-history.php
 *
 * Dashboard "Currency Converter" widget backend. Read-only: returns the most
 * recent conversion ("Last Converted") plus a short recent history list, so
 * the widget never opens on a bare empty form. Does not perform a conversion
 * or write to tracs_currency_history — see currency.php for that.
 */

require '_bootstrap.php';

const CURRENCY_HISTORY_LIMIT = 5;

function currency_history_table_exists(mysqli $conn): bool {
    $res = $conn->query("SHOW TABLES LIKE 'tracs_currency_history'");
    return $res instanceof mysqli_result && $res->num_rows > 0;
}

if (!currency_history_table_exists($conn)) {
    ok(['latest' => null, 'items' => []], 'No conversion history yet');
}

$stmt = $conn->prepare("
    SELECT from_currency, to_currency, amount, result, rate, created_at
    FROM tracs_currency_history
    ORDER BY created_at DESC, id DESC
    LIMIT ?
");
if (!$stmt) {
    ok(['latest' => null, 'items' => []], 'No conversion history yet');
}
$limit = CURRENCY_HISTORY_LIMIT;
$stmt->bind_param('i', $limit);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$items = array_map(static function (array $row): array {
    return [
        'from' => (string)$row['from_currency'],
        'to' => (string)$row['to_currency'],
        'amount' => (float)$row['amount'],
        'result' => (float)$row['result'],
        'rate' => (float)$row['rate'],
        'created_at' => (string)$row['created_at'],
    ];
}, $rows);

ok(['latest' => $items[0] ?? null, 'items' => $items], 'Conversion history loaded');
