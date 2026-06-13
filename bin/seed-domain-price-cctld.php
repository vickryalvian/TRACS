<?php
/**
 * Safely seed the requested ccTLD matrix values for one draft month.
 *
 * Dry run:
 *   php bin/seed-domain-price-cctld.php --month=2026-06
 *
 * Apply:
 *   php bin/seed-domain-price-cctld.php --month=2026-06 --apply
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script may only be run from the command line.\n");
    exit(1);
}

require_once __DIR__ . '/../config/database.php';

const DPC_CCTLD_SEED_ACTION = 'Seed ccTLD pricing matrix values from reference sheet';

$seedValues = [
    'PANDI Registry Pricing' => [
        'cost_register' => [
            '.AC.ID' => 35000,
            '.BIZ.ID' => 50000,
            '.CO.ID' => 210000,
            '.ID' => 182700,
            '.MY.ID' => 20000,
            '.OR.ID' => 35000,
            '.PONPES.ID' => 35000,
            '.SCH.ID' => 35000,
            '.WEB.ID' => 35000,
        ],
        'cost_renewal' => [
            '.AC.ID' => 35000,
            '.BIZ.ID' => 50000,
            '.CO.ID' => 210000,
            '.ID' => 182700,
            '.MY.ID' => 20000,
            '.OR.ID' => 35000,
            '.PONPES.ID' => 35000,
            '.SCH.ID' => 35000,
            '.WEB.ID' => 35000,
        ],
        'cost_transfer' => [
            '.AC.ID' => 105000,
            '.BIZ.ID' => 150000,
            '.CO.ID' => 630000,
            '.ID' => 548100,
            '.MY.ID' => 60000,
            '.OR.ID' => 105000,
            '.PONPES.ID' => 105000,
            '.SCH.ID' => 105000,
            '.WEB.ID' => 105000,
        ],
    ],
    'IDCH ccTLD Pricing' => [
        'cost_register' => [
            '.AC.ID' => 55000,
            '.BIZ.ID' => 55000,
            '.CO.ID' => 300000,
            '.ID' => 210000,
            '.MY.ID' => 25000,
            '.OR.ID' => 55000,
            '.PONPES.ID' => 55000,
            '.SCH.ID' => 55000,
            '.WEB.ID' => 55000,
        ],
        'cost_renewal' => [
            '.AC.ID' => 100000,
            '.BIZ.ID' => 60000,
            '.CO.ID' => 300000,
            '.ID' => 210000,
            '.MY.ID' => 25000,
            '.OR.ID' => 55000,
            '.PONPES.ID' => 55000,
            '.SCH.ID' => 55000,
            '.WEB.ID' => 55000,
        ],
    ],
];

function fail(string $message): never
{
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
}

function fetchOne(mysqli $db, string $sql, string $types = '', array $params = []): ?array
{
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException($db->error);
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function fetchAll(mysqli $db, string $sql, string $types = '', array $params = []): array
{
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException($db->error);
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function canonicalHash(array $rows): string
{
    return hash('sha256', json_encode($rows, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function sqlValue(mixed $value): string
{
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value) || is_float($value)) {
        return (string)$value;
    }
    return "'" . str_replace("'", "''", (string)$value) . "'";
}

function buildRollbackSql(string $monthCode, array $existingRows, array $missingKeys): string
{
    $lines = [
        '-- Rollback for the ccTLD matrix seed on ' . $monthCode,
        '-- Review before execution. It only targets rows included in this seed backup.',
        'START TRANSACTION;',
    ];

    foreach ($existingRows as $row) {
        $sets = [];
        foreach ([
            'currency', 'original_value', 'usd_value', 'idr_value', 'calculated_from_kurs',
            'is_lowest', 'comparison_status', 'created_by', 'created_at', 'updated_by', 'updated_at',
        ] as $column) {
            $sets[] = "`{$column}` = " . sqlValue($row[$column]);
        }
        $lines[] = 'UPDATE `domain_price_entries` SET ' . implode(', ', $sets)
            . ' WHERE `id` = ' . (int)$row['id'] . ';';
    }

    foreach ($missingKeys as $key) {
        $lines[] = sprintf(
            "DELETE FROM `domain_price_entries` WHERE `month_id` = %d AND `tld_id` = %d AND `source_id` = %d AND `price_type` = %s;",
            $key['month_id'],
            $key['tld_id'],
            $key['source_id'],
            sqlValue($key['price_type'])
        );
    }

    $lines[] = 'COMMIT;';
    return implode(PHP_EOL, $lines) . PHP_EOL;
}

$options = getopt('', ['month:', 'apply', 'actor-id:', 'backup-dir:']);
$monthCode = trim((string)($options['month'] ?? '2026-06'));
$apply = array_key_exists('apply', $options);
$requestedActorId = isset($options['actor-id']) ? (int)$options['actor-id'] : 0;
$backupRoot = (string)($options['backup-dir'] ?? (__DIR__ . '/../backups/domain-price-cctld-seed'));

if (!preg_match('/^\d{4}-\d{2}$/', $monthCode)) {
    fail('Month must use YYYY-MM format.');
}

try {
    $month = fetchOne(
        $conn,
        'SELECT id, month, status, created_by, updated_by FROM domain_price_months WHERE month = ? LIMIT 1',
        's',
        [$monthCode]
    );
    if (!$month) {
        fail("Monthly record {$monthCode} does not exist.");
    }
    if ((string)$month['status'] !== 'draft') {
        fail("Monthly record {$monthCode} is not Draft; refusing to write.");
    }

    $sourceNames = array_keys($seedValues);
    $sources = fetchAll(
        $conn,
        "SELECT id, source_name, source_type, is_active
         FROM domain_price_sources
         WHERE source_name IN ('PANDI Registry Pricing', 'IDCH ccTLD Pricing')
         ORDER BY id"
    );
    $sourceMap = [];
    foreach ($sources as $source) {
        $sourceMap[(string)$source['source_name']] = $source;
    }
    foreach ($sourceNames as $sourceName) {
        if (!isset($sourceMap[$sourceName])) {
            fail("Required source {$sourceName} does not exist.");
        }
    }

    $requestedTlds = array_keys($seedValues['PANDI Registry Pricing']['cost_register']);
    $tlds = fetchAll(
        $conn,
        "SELECT id, tld_name, tld_category, is_active
         FROM domain_price_tlds
         WHERE UPPER(tld_name) IN ('.AC.ID','.BIZ.ID','.CO.ID','.ID','.MY.ID','.OR.ID','.PONPES.ID','.SCH.ID','.WEB.ID')
         ORDER BY id"
    );
    $tldMap = [];
    foreach ($tlds as $tld) {
        $key = strtoupper(trim((string)$tld['tld_name']));
        if (isset($tldMap[$key])) {
            fail("Duplicate TLD identifier found for {$key}.");
        }
        if ((string)$tld['tld_category'] !== 'cctld') {
            fail("TLD {$key} is not classified as ccTLD.");
        }
        $tldMap[$key] = $tld;
    }
    foreach ($requestedTlds as $tldName) {
        if (!isset($tldMap[$tldName])) {
            fail("Required TLD {$tldName} does not exist.");
        }
    }

    $actorId = $requestedActorId > 0
        ? $requestedActorId
        : (int)($month['updated_by'] ?: $month['created_by']);
    $actor = fetchOne($conn, 'SELECT id, name FROM tracs_users WHERE id = ? LIMIT 1', 'i', [$actorId]);
    if (!$actor) {
        fail('A valid audit actor is required. Pass --actor-id=<user id>.');
    }

    $desired = [];
    foreach ($seedValues as $sourceName => $types) {
        foreach ($types as $priceType => $values) {
            foreach ($values as $tldName => $price) {
                $desired[] = [
                    'month_id' => (int)$month['id'],
                    'month' => $monthCode,
                    'source_id' => (int)$sourceMap[$sourceName]['id'],
                    'source_name' => $sourceName,
                    'tld_id' => (int)$tldMap[$tldName]['id'],
                    'tld_name' => $tldName,
                    'price_type' => $priceType,
                    'price' => (float)$price,
                ];
            }
        }
    }

    $allRowsBefore = fetchAll(
        $conn,
        'SELECT id, month_id, tld_id, source_id, price_type, currency, original_value, usd_value, idr_value,
                calculated_from_kurs, is_lowest, comparison_status, created_by, created_at, updated_by, updated_at
         FROM domain_price_entries ORDER BY id'
    );
    $desiredKeyMap = [];
    foreach ($desired as $item) {
        $desiredKeyMap[$item['month_id'] . ':' . $item['tld_id'] . ':' . $item['source_id'] . ':' . $item['price_type']] = true;
    }
    $unrelatedRowsBefore = array_values(array_filter(
        $allRowsBefore,
        static function (array $row) use ($desiredKeyMap): bool {
            $key = $row['month_id'] . ':' . $row['tld_id'] . ':' . $row['source_id'] . ':' . $row['price_type'];
            return !isset($desiredKeyMap[$key]);
        }
    ));
    $unrelatedHashBefore = canonicalHash($unrelatedRowsBefore);

    $idchSourceId = (int)$sourceMap['IDCH ccTLD Pricing']['id'];
    $protectedIdchRedemptionBefore = fetchAll(
        $conn,
        "SELECT e.*
         FROM domain_price_entries e
         JOIN domain_price_tlds t ON t.id = e.tld_id
         WHERE e.month_id = ? AND e.source_id = ? AND e.price_type = 'cost_transfer'
           AND UPPER(t.tld_name) IN ('.AC.ID','.BIZ.ID','.CO.ID','.ID','.MY.ID','.OR.ID','.PONPES.ID','.SCH.ID','.WEB.ID')
         ORDER BY e.id",
        'ii',
        [(int)$month['id'], $idchSourceId]
    );
    $protectedNetIdBefore = fetchAll(
        $conn,
        "SELECT e.*
         FROM domain_price_entries e
         JOIN domain_price_tlds t ON t.id = e.tld_id
         WHERE e.month_id = ? AND UPPER(t.tld_name) = '.NET.ID'
         ORDER BY e.id",
        'i',
        [(int)$month['id']]
    );

    $conn->begin_transaction();
    fetchOne($conn, 'SELECT id FROM domain_price_months WHERE id = ? FOR UPDATE', 'i', [(int)$month['id']]);

    $existingRows = [];
    $missingKeys = [];
    $inserts = [];
    $updates = [];
    $unchanged = [];
    foreach ($desired as $item) {
        $existing = fetchOne(
            $conn,
            'SELECT * FROM domain_price_entries
             WHERE month_id = ? AND tld_id = ? AND source_id = ? AND price_type = ?
             LIMIT 1 FOR UPDATE',
            'iiis',
            [$item['month_id'], $item['tld_id'], $item['source_id'], $item['price_type']]
        );
        if (!$existing) {
            $inserts[] = $item;
            $missingKeys[] = $item;
            continue;
        }
        $existingRows[] = $existing;
        $isSame = (string)$existing['currency'] === 'IDR'
            && (float)$existing['original_value'] === $item['price']
            && (float)$existing['usd_value'] === 0.0
            && (float)$existing['idr_value'] === $item['price']
            && (float)$existing['calculated_from_kurs'] === 0.0;
        if ($isSame) {
            $unchanged[] = $item;
        } else {
            $item['existing'] = $existing;
            $updates[] = $item;
        }
    }

    printf(
        "%s for %s (month_id=%d): expected=%d insert=%d update=%d unchanged=%d\n",
        $apply ? 'Apply plan' : 'Dry-run plan',
        $monthCode,
        (int)$month['id'],
        count($desired),
        count($inserts),
        count($updates),
        count($unchanged)
    );

    if (!$apply) {
        $conn->rollback();
        echo "Dry run complete. Re-run with --apply to write these scoped values.\n";
        exit(0);
    }

    if (!is_dir($backupRoot) && !mkdir($backupRoot, 0770, true) && !is_dir($backupRoot)) {
        throw new RuntimeException("Unable to create backup directory {$backupRoot}.");
    }
    $stamp = date('Ymd-His');
    $backupBase = rtrim($backupRoot, '/') . "/{$monthCode}-{$stamp}";
    $backupPayload = [
        'generated_at' => date(DATE_ATOM),
        'month' => $month,
        'actor' => ['id' => (int)$actor['id'], 'name' => (string)$actor['name']],
        'plan' => [
            'expected' => count($desired),
            'insert' => count($inserts),
            'update' => count($updates),
            'unchanged' => count($unchanged),
        ],
        'existing_affected_rows' => $existingRows,
        'missing_keys_before_seed' => $missingKeys,
        'protected_idch_redemption_rows' => $protectedIdchRedemptionBefore,
        'protected_net_id_rows' => $protectedNetIdBefore,
        'unrelated_rows_hash_before' => $unrelatedHashBefore,
    ];
    if (file_put_contents(
        $backupBase . '.json',
        json_encode($backupPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL
    ) === false) {
        throw new RuntimeException('Unable to write JSON backup.');
    }
    if (file_put_contents(
        $backupBase . '-rollback.sql',
        buildRollbackSql($monthCode, $existingRows, $missingKeys)
    ) === false) {
        throw new RuntimeException('Unable to write rollback SQL.');
    }
    echo "Backup: {$backupBase}.json\n";
    echo "Rollback: {$backupBase}-rollback.sql\n";

    $insertStmt = $conn->prepare(
        "INSERT INTO domain_price_entries
            (month_id, tld_id, source_id, price_type, currency, original_value, usd_value, idr_value,
             calculated_from_kurs, created_by, created_at)
         VALUES (?, ?, ?, ?, 'IDR', ?, 0, ?, 0, ?, NOW())"
    );
    $updateStmt = $conn->prepare(
        "UPDATE domain_price_entries
         SET currency = 'IDR', original_value = ?, usd_value = 0, idr_value = ?,
             calculated_from_kurs = 0, updated_by = ?, updated_at = NOW()
         WHERE id = ?"
    );
    if (!$insertStmt || !$updateStmt) {
        throw new RuntimeException($conn->error);
    }

    foreach ($inserts as $item) {
        $insertMonthId = (int)$item['month_id'];
        $insertTldId = (int)$item['tld_id'];
        $insertSourceId = (int)$item['source_id'];
        $insertPriceType = (string)$item['price_type'];
        $insertOriginalValue = (float)$item['price'];
        $insertIdrValue = (float)$item['price'];
        $insertStmt->bind_param(
            'iiisddi',
            $insertMonthId,
            $insertTldId,
            $insertSourceId,
            $insertPriceType,
            $insertOriginalValue,
            $insertIdrValue,
            $actorId
        );
        $insertStmt->execute();
    }
    foreach ($updates as $item) {
        $entryId = (int)$item['existing']['id'];
        $updateOriginalValue = (float)$item['price'];
        $updateIdrValue = (float)$item['price'];
        $updateStmt->bind_param('ddii', $updateOriginalValue, $updateIdrValue, $actorId, $entryId);
        $updateStmt->execute();
    }
    $insertStmt->close();
    $updateStmt->close();

    if ($inserts || $updates) {
        $details = json_encode([
            'month' => $monthCode,
            'expected_rows' => count($desired),
            'inserted_rows' => count($inserts),
            'updated_rows' => count($updates),
            'unchanged_rows' => count($unchanged),
            'protected' => ['IDCH redemption', '.NET.ID', 'all unrelated rows'],
            'backup' => basename($backupBase . '.json'),
        ], JSON_UNESCAPED_SLASHES);
        $auditStmt = $conn->prepare(
            'INSERT INTO domain_price_audit_logs
                (month_id, actor_user_id, actor_name, action, field_name, change_reason, details, ip_address, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        if (!$auditStmt) {
            throw new RuntimeException($conn->error);
        }
        $fieldName = 'ccTLD Pricing Matrix';
        $reason = DPC_CCTLD_SEED_ACTION;
        $ipAddress = '127.0.0.1';
        $auditMonthId = (int)$month['id'];
        $actorName = (string)$actor['name'];
        $auditStmt->bind_param(
            'iissssss',
            $auditMonthId,
            $actorId,
            $actorName,
            $reason,
            $fieldName,
            $reason,
            $details,
            $ipAddress
        );
        $auditStmt->execute();
        $auditStmt->close();
    }

    $allRowsAfter = fetchAll(
        $conn,
        'SELECT id, month_id, tld_id, source_id, price_type, currency, original_value, usd_value, idr_value,
                calculated_from_kurs, is_lowest, comparison_status, created_by, created_at, updated_by, updated_at
         FROM domain_price_entries ORDER BY id'
    );
    $unrelatedRowsAfter = array_values(array_filter(
        $allRowsAfter,
        static function (array $row) use ($desiredKeyMap): bool {
            $key = $row['month_id'] . ':' . $row['tld_id'] . ':' . $row['source_id'] . ':' . $row['price_type'];
            return !isset($desiredKeyMap[$key]);
        }
    ));
    if (canonicalHash($unrelatedRowsAfter) !== $unrelatedHashBefore) {
        throw new RuntimeException('Verification detected an unrelated row change.');
    }

    $protectedIdchRedemptionAfter = fetchAll(
        $conn,
        "SELECT e.*
         FROM domain_price_entries e
         JOIN domain_price_tlds t ON t.id = e.tld_id
         WHERE e.month_id = ? AND e.source_id = ? AND e.price_type = 'cost_transfer'
           AND UPPER(t.tld_name) IN ('.AC.ID','.BIZ.ID','.CO.ID','.ID','.MY.ID','.OR.ID','.PONPES.ID','.SCH.ID','.WEB.ID')
         ORDER BY e.id",
        'ii',
        [(int)$month['id'], $idchSourceId]
    );
    $protectedNetIdAfter = fetchAll(
        $conn,
        "SELECT e.*
         FROM domain_price_entries e
         JOIN domain_price_tlds t ON t.id = e.tld_id
         WHERE e.month_id = ? AND UPPER(t.tld_name) = '.NET.ID'
         ORDER BY e.id",
        'i',
        [(int)$month['id']]
    );
    if (canonicalHash($protectedIdchRedemptionAfter) !== canonicalHash($protectedIdchRedemptionBefore)) {
        throw new RuntimeException('Verification detected a change to protected IDCH Redemption rows.');
    }
    if (canonicalHash($protectedNetIdAfter) !== canonicalHash($protectedNetIdBefore)) {
        throw new RuntimeException('Verification detected a change to protected .NET.ID rows.');
    }

    $verified = 0;
    foreach ($desired as $item) {
        $row = fetchOne(
            $conn,
            'SELECT id, currency, original_value, usd_value, idr_value, calculated_from_kurs
             FROM domain_price_entries
             WHERE month_id = ? AND tld_id = ? AND source_id = ? AND price_type = ?',
            'iiis',
            [$item['month_id'], $item['tld_id'], $item['source_id'], $item['price_type']]
        );
        if (!$row
            || (string)$row['currency'] !== 'IDR'
            || (float)$row['original_value'] !== $item['price']
            || (float)$row['usd_value'] !== 0.0
            || (float)$row['idr_value'] !== $item['price']
            || (float)$row['calculated_from_kurs'] !== 0.0
        ) {
            throw new RuntimeException("Verification failed for {$item['source_name']} {$item['tld_name']} {$item['price_type']}.");
        }
        $verified++;
    }

    $duplicateRows = fetchAll(
        $conn,
        "SELECT e.month_id, e.tld_id, e.source_id, e.price_type, COUNT(*) AS row_count
         FROM domain_price_entries e
         JOIN domain_price_tlds t ON t.id = e.tld_id
         WHERE e.month_id = ?
           AND e.source_id IN (?, ?)
           AND UPPER(t.tld_name) IN ('.AC.ID','.BIZ.ID','.CO.ID','.ID','.MY.ID','.OR.ID','.PONPES.ID','.SCH.ID','.WEB.ID')
           AND e.price_type IN ('cost_register','cost_renewal','cost_transfer')
         GROUP BY e.month_id, e.tld_id, e.source_id, e.price_type
         HAVING COUNT(*) > 1",
        'iii',
        [
            (int)$month['id'],
            (int)$sourceMap['PANDI Registry Pricing']['id'],
            (int)$sourceMap['IDCH ccTLD Pricing']['id'],
        ]
    );
    if ($duplicateRows) {
        throw new RuntimeException('Verification detected duplicate ccTLD matrix keys.');
    }

    $conn->commit();

    printf(
        "Committed and verified %d/%d values. Unrelated rows, IDCH Redemption, and .NET.ID are unchanged.\n",
        $verified,
        count($desired)
    );
} catch (Throwable $error) {
    if (isset($conn) && $conn instanceof mysqli) {
        try {
            $conn->rollback();
        } catch (Throwable) {
        }
    }
    fail($error->getMessage());
}
