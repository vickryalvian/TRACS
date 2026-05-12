<?php
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
function rem_status_class(string $s): string {
    return match($s){'Overdue'=>'rem-ov','Today'=>'rem-today',default=>'rem-future'};
}
