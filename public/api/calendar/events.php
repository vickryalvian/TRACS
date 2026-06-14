<?php

require_once __DIR__ . '/_common.php';
calendar_require_method('GET');

$start = calendar_valid_date($_GET['start'] ?? '');
$end = calendar_valid_date($_GET['end'] ?? '');
if ($start === null || $end === null || $start > $end) {
    calendar_validation_fail(['range' => 'A valid start and end date are required.']);
}
$days = (new DateTimeImmutable($start))->diff(new DateTimeImmutable($end))->days;
if ($days > 400) {
    calendar_validation_fail(['range' => 'Calendar requests are limited to 400 days.']);
}

$service = new CalendarService($conn, $uid, $authUser);
ok($service->getEvents($start, $end), 'Calendar events loaded.');
