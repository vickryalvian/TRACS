<?php
declare(strict_types=1);

require __DIR__ . '/shift-assignment-template-preview-ui-pilot.php';

if (getenv('TRACS_TEST_DB_NAME') === false || trim((string)getenv('TRACS_TEST_DB_NAME')) === '') {
    putenv('TRACS_TEST_DB_NAME=tracs_phase29_test');
}

require __DIR__ . '/shift-assignment-template-preview-integration.php';

echo "TRACS Shift Assignment template preview UI disposable validation checks passed.\n";
