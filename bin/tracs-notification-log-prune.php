#!/usr/bin/env php
<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$options = getopt('', ['retention-days:', 'batch-size::', 'max-batches::', 'execute']);
$retentionDays = filter_var($options['retention-days'] ?? null, FILTER_VALIDATE_INT);
$batchSize = filter_var($options['batch-size'] ?? 2000, FILTER_VALIDATE_INT);
$maxBatches = filter_var($options['max-batches'] ?? 100, FILTER_VALIDATE_INT);
$execute = array_key_exists('execute', $options);

if ($retentionDays === false || $retentionDays < 1 || $retentionDays > 3650) {
    fwrite(STDERR, "Provide --retention-days=N between 1 and 3650.\n");
    exit(2);
}
if ($batchSize === false || $batchSize < 100 || $batchSize > 10000) {
    fwrite(STDERR, "--batch-size must be between 100 and 10000.\n");
    exit(2);
}
if ($maxBatches === false || $maxBatches < 1 || $maxBatches > 10000) {
    fwrite(STDERR, "--max-batches must be between 1 and 10000.\n");
    exit(2);
}

require_once __DIR__ . '/../config/database.php';

$index = $conn->query("
    SELECT 1
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tracs_notification_logs'
      AND INDEX_NAME = 'idx_tracs_notification_logs_created_at'
    LIMIT 1
");
if (!$index || !$index->fetch_row()) {
    fwrite(STDERR, "Required index idx_tracs_notification_logs_created_at is missing. Apply the reviewed retention migration first.\n");
    exit(3);
}

$cutoff = (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))
    ->modify(sprintf('-%d days', $retentionDays))
    ->format('Y-m-d H:i:s');

if (!$execute) {
    printf(
        "Dry run only. Rows older than %s would be pruned in batches of %d, up to %d batches. Add --execute after backup verification.\n",
        $cutoff,
        $batchSize,
        $maxBatches
    );
    exit(0);
}

$lock = $conn->query("SELECT GET_LOCK('tracs_notification_log_prune', 0) AS acquired");
if (!$lock || (int)($lock->fetch_assoc()['acquired'] ?? 0) !== 1) {
    fwrite(STDERR, "Another notification-log prune is already running.\n");
    exit(4);
}

$deletedTotal = 0;
$batches = 0;
$statement = $conn->prepare("
    DELETE FROM tracs_notification_logs
    WHERE created_at < ?
    ORDER BY created_at, id
    LIMIT ?
");
if (!$statement) {
    $conn->query("SELECT RELEASE_LOCK('tracs_notification_log_prune')");
    fwrite(STDERR, "Could not prepare the bounded delete.\n");
    exit(5);
}

try {
    while ($batches < $maxBatches) {
        $statement->bind_param('si', $cutoff, $batchSize);
        if (!$statement->execute()) {
            throw new RuntimeException('Notification-log prune batch failed.');
        }
        $deleted = max(0, $statement->affected_rows);
        $deletedTotal += $deleted;
        $batches++;
        printf("batch=%d deleted=%d total=%d\n", $batches, $deleted, $deletedTotal);
        if ($deleted < $batchSize) {
            break;
        }
        usleep(250000);
    }
} finally {
    $statement->close();
    $conn->query("SELECT RELEASE_LOCK('tracs_notification_log_prune')");
}

printf("complete cutoff=%s batches=%d deleted=%d\n", $cutoff, $batches, $deletedTotal);
