<?php
declare(strict_types=1);

if (getenv('TRACS_TEST_DB_NAME') === false || trim((string)getenv('TRACS_TEST_DB_NAME')) === '') {
    putenv('TRACS_TEST_DB_NAME=tracs_phase26_test');
}
putenv('TRACS_TEST_EXPECT_DELETE=1');
putenv('TRACS_TEST_EXPECT_HARDENING=1');

require __DIR__ . '/shift-assignment-create-ui-browser-environment.php';
