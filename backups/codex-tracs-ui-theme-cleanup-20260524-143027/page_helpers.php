<?php
require_once __DIR__ . '/../../core/creator_tracking.php';

/* Shared formatting helpers for all pages */
function prio_bar(string $p): string {
    return match($p){
        'critical'=>'critical','high'=>'high','medium'=>'medium',default=>'low'
    };
}
function prio_badge(string $p): string {
    return match($p){
        'critical'=>'b-critical','high'=>'b-high','medium'=>'b-medium',default=>'b-low'
    };
}
function status_badge(string $s): array {
    return match($s){
        'active'    =>['b-active',  'Active'],
        'stuck'     =>['b-stuck',   'Stuck'],
        'completed' =>['b-done',    'Done'],
        default     =>['b-pending', 'Pending']
    };
}
function safe_dt(mixed $v, string $fmt='M d'): string {
    if(!$v||!strtotime((string)$v))return '—';
    return date($fmt, strtotime((string)$v));
}
function safe_dt_local(mixed $v): string {
    if(!$v||!strtotime((string)$v))return '';
    return date('Y-m-d\TH:i', strtotime((string)$v));
}
function esc(mixed $v): string { return htmlspecialchars((string)($v??''), ENT_QUOTES,'UTF-8'); }
function tracs_highlight_summary(mixed $summary, mixed $highlight): string {
    $summary = (string)($summary ?? '');
    $highlights = is_array($highlight) ? $highlight : [$highlight];
    $highlights = array_values(array_filter(array_map(
        fn($item) => trim((string)($item ?? '')),
        $highlights
    )));

    if ($summary === '' || !$highlights) {
        return esc($summary);
    }

    $matches = [];
    foreach ($highlights as $item) {
        $pos = stripos($summary, $item);
        if ($pos !== false) {
            $matches[] = ['pos' => $pos, 'len' => strlen($item)];
        }
    }

    if (!$matches) {
        return esc($summary);
    }

    usort($matches, fn($a, $b) => $a['pos'] <=> $b['pos']);

    $html = '';
    $cursor = 0;
    foreach ($matches as $match) {
        if ($match['pos'] < $cursor) {
            continue;
        }
        $html .= esc(substr($summary, $cursor, $match['pos'] - $cursor));
        $html .= '<span class="summary-highlight">' . esc(substr($summary, $match['pos'], $match['len'])) . '</span>';
        $cursor = $match['pos'] + $match['len'];
    }

    return $html . esc(substr($summary, $cursor));
}
function tracs_highlight_lines(mixed $lines, mixed $highlight): string {
    $lines = is_array($lines) ? $lines : [(string)($lines ?? '')];
    $lines = array_values(array_filter(array_map(
        fn($line) => trim((string)($line ?? '')),
        $lines
    )));
    if (!$lines) {
        return '';
    }

    return '<ul class="summary-bullets"><li>'
        . implode('</li><li>', array_map(fn($line) => tracs_highlight_summary($line, $highlight), $lines))
        . '</li></ul>';
}
function rem_status_class(string $s): string {
    return match($s){'Overdue'=>'rem-ov','Today'=>'rem-today',default=>'rem-future'};
}
function reminder_completed_at(array $reminder): ?string {
    foreach (['completed_at', 'archived_at', 'updated_at'] as $key) {
        if (!empty($reminder[$key]) && strtotime((string)$reminder[$key]) !== false) {
            return (string)$reminder[$key];
        }
    }
    return null;
}
function reminder_visible_in_checklist(array $reminder): bool {
    if (empty($reminder['is_completed'])) {
        return true;
    }
    $completed_at = reminder_completed_at($reminder);
    return $completed_at !== null && strtotime($completed_at) >= strtotime('-24 hours');
}
