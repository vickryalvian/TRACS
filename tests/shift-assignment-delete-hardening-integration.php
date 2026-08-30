<?php
declare(strict_types=1);

if (getenv('TRACS_TEST_DB_NAME') === false || trim((string)getenv('TRACS_TEST_DB_NAME')) === '') {
    putenv('TRACS_TEST_DB_NAME=tracs_phase26_test');
}
putenv('TRACS_TEST_INCLUDE_DELETE=1');
putenv('TRACS_TEST_INCLUDE_RESTORE=1');
putenv('TRACS_TEST_INCLUDE_DEPENDENTS=1');
putenv('TRACS_TEST_PHASE26_MATRIX=1');

require __DIR__ . '/shift-assignment-create-api-integration.php';

echo "TRACS Shift Assignment Phase 26 create/edit/delete regression gate passed.\n";
