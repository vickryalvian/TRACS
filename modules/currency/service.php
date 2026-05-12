<?php

function convertCurrency($conn, $from, $to, $amount)
{
    $url = "https://api.frankfurter.dev/v1/latest"
        . "?amount={$amount}&from={$from}&to={$to}";

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 10,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        return [
            'success' => false,
            'message' => 'Failed fetch exchange rate'
        ];
    }

    $data = json_decode($response, true);

    if (!isset($data['rates'][$to])) {
        return [
            'success' => false,
            'message' => 'Invalid API response'
        ];
    }

    $result = $data['rates'][$to];
    $rate = $result / $amount;

    // SAVE DB
    $conn->query("CREATE TABLE IF NOT EXISTS tracs_currency_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        from_currency VARCHAR(10),
        to_currency VARCHAR(10),
        amount DECIMAL(15,2),
        result DECIMAL(15,2),
        rate DECIMAL(15,6),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $stmt = $conn->prepare("
        INSERT INTO tracs_currency_history
        (from_currency, to_currency, amount, result, rate)
        VALUES (?, ?, ?, ?, ?)
    ");

    if ($stmt) {
        $stmt->bind_param("ssddd", $from, $to, $amount, $result, $rate);
        $stmt->execute();
        $stmt->close();
    }

    return [
        'success' => true,
        'from' => $from,
        'to' => $to,
        'amount' => $amount,
        'result' => $result,
        'rate' => $rate,
        'time' => date('Y-m-d H:i:s')
    ];
}