<?php
/**
 * Shared TRACS shift schedule helpers.
 *
 * Default shift starts are 00:00, 08:00, and 16:00 local server time. They can
 * be overridden with TRACS_SHIFT_CHANGE_TIMES="00:00,08:00,16:00".
 */

function tracs_shift_start_times(): array {
    $raw = (string)($_ENV['TRACS_SHIFT_CHANGE_TIMES'] ?? getenv('TRACS_SHIFT_CHANGE_TIMES') ?: '00:00,08:00,16:00');
    $parts = array_values(array_filter(array_map('trim', explode(',', $raw)), fn($value) => preg_match('/^\d{2}:\d{2}$/', $value)));
    if (count($parts) < 3) {
        $parts = ['00:00', '08:00', '16:00'];
    }
    return array_slice($parts, 0, 3);
}

function tracs_shift_windows_for_date(?string $date = null): array {
    $date = $date ?: date('Y-m-d');
    [$s1, $s2, $s3] = tracs_shift_start_times();
    $nextDate = date('Y-m-d', strtotime($date . ' +1 day'));
    return [
        'Shift 1' => ['start' => "{$date} {$s1}:00", 'end' => "{$date} {$s2}:00"],
        'Shift 2' => ['start' => "{$date} {$s2}:00", 'end' => "{$date} {$s3}:00"],
        'Shift 3' => ['start' => "{$date} {$s3}:00", 'end' => "{$nextDate} {$s1}:00"],
    ];
}

function tracs_detect_shift(?DateTimeInterface $now = null): string {
    $now = $now ?: new DateTimeImmutable('now');
    $date = $now->format('Y-m-d');
    $tz = $now->getTimezone();
    $ts = $now->getTimestamp();
    foreach (tracs_shift_windows_for_date($date) as $shift => $window) {
        $start = (new DateTimeImmutable($window['start'], $tz))->getTimestamp();
        $end = (new DateTimeImmutable($window['end'], $tz))->getTimestamp();
        if ($ts >= $start && $ts < $end) {
            return $shift;
        }
    }

    $yesterday = $now->modify('-1 day')->format('Y-m-d');
    $shift3 = tracs_shift_windows_for_date($yesterday)['Shift 3'];
    $start = (new DateTimeImmutable($shift3['start'], $tz))->getTimestamp();
    $end = (new DateTimeImmutable($shift3['end'], $tz))->getTimestamp();
    if ($ts >= $start && $ts < $end) {
        return 'Shift 3';
    }

    return 'Shift 1';
}

function tracs_current_shift_window(?string $shift = null, ?string $date = null): array {
    $shift = tracs_normalize_shift_name($shift) ?: tracs_detect_shift();
    $date = $date ?: date('Y-m-d');
    $windows = tracs_shift_windows_for_date($date);
    $window = $windows[$shift] ?? $windows['Shift 1'];
    return [
        'shift_name' => $shift,
        'start' => $window['start'],
        'end' => $window['end'],
    ];
}

function tracs_next_shift_change(?DateTimeInterface $now = null): array {
    $now = $now ?: new DateTimeImmutable('now');
    $current = tracs_detect_shift($now);
    $date = $now->format('Y-m-d');
    $tz = $now->getTimezone();
    $windows = tracs_shift_windows_for_date($date);
    $changeAt = new DateTimeImmutable($windows[$current]['end'], $tz);
    if ($changeAt <= $now) {
        $tomorrow = $now->modify('+1 day')->format('Y-m-d');
        $changeAt = new DateTimeImmutable(tracs_shift_windows_for_date($tomorrow)['Shift 1']['start'], $tz);
    }
    $next = match ($current) {
        'Shift 1' => 'Shift 2',
        'Shift 2' => 'Shift 3',
        default => 'Shift 1',
    };
    return [
        'current_shift' => $current,
        'next_shift' => $next,
        'change_at' => $changeAt,
        'seconds_until' => max(0, $changeAt->getTimestamp() - $now->getTimestamp()),
    ];
}

function tracs_normalize_shift_name(?string $shift): ?string {
    $shift = strtolower(trim((string)$shift));
    return match ($shift) {
        'shift 1', '1', 's1' => 'Shift 1',
        'shift 2', '2', 's2' => 'Shift 2',
        'shift 3', '3', 's3' => 'Shift 3',
        default => null,
    };
}
