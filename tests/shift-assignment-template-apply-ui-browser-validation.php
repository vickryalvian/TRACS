<?php
declare(strict_types=1);

if (getenv('TRACS_TEST_DB_NAME') === false || trim((string)getenv('TRACS_TEST_DB_NAME')) === '') {
    putenv('TRACS_TEST_DB_NAME=tracs_phase35_test');
}

require __DIR__ . '/shift-assignment-template-apply-ui-contract.php';
require __DIR__ . '/shift-assignment-template-commit-integration.php';

echo "TRACS Shift Assignment template apply UI disposable workflow validation checks passed.\n";
