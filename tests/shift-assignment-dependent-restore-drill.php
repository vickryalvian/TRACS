<?php
declare(strict_types=1);

if (getenv('TRACS_TEST_DB_NAME') === false || trim((string)getenv('TRACS_TEST_DB_NAME')) === '') {
    putenv('TRACS_TEST_DB_NAME=tracs_phase24_test');
}
putenv('TRACS_TEST_INCLUDE_DELETE=1');
putenv('TRACS_TEST_INCLUDE_RESTORE=1');
putenv('TRACS_TEST_INCLUDE_DEPENDENTS=1');

require __DIR__ . '/shift-assignment-create-api-integration.php';

echo "TRACS Shift Assignment dependent delete restoration drill passed.\n";
