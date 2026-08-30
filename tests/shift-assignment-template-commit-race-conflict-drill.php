<?php
declare(strict_types=1);

if (getenv('TRACS_TEST_DB_NAME') === false || trim((string)getenv('TRACS_TEST_DB_NAME')) === '') {
    putenv('TRACS_TEST_DB_NAME=tracs_phase33_race_test');
}

require __DIR__ . '/shift-assignment-template-commit-integration.php';

echo "TRACS Shift Assignment template commit race conflict drill passed.\n";
