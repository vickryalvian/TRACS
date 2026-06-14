<?php
require_once __DIR__ . '/../../core/security/direct_access.php';
tracs_deny_direct_script_access(__FILE__);
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
        'in_progress'=>['b-in_progress', 'In Progress'],
        'stuck'     =>['b-stuck',   'Stuck'],
        'on_hold'   =>['b-hold',    'On Hold'],
        'completed' =>['b-resolved','Resolved'],
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
function tracs_date_display(mixed $value, string $fallback = '—'): string {
    if (!$value || !strtotime((string)$value)) return $fallback;
    return date('d-m-Y', strtotime((string)$value));
}
function tracs_date_range_picker(array $options = []): string {
    static $sequence = 0;
    $sequence++;

    $id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($options['id'] ?? 'tracsDateRange' . $sequence));
    $id = $id !== '' ? $id : 'tracsDateRange' . $sequence;
    $start = (string)($options['start'] ?? '');
    $end = (string)($options['end'] ?? '');
    $startName = (string)($options['start_name'] ?? 'start_date');
    $endName = (string)($options['end_name'] ?? 'end_date');
    $startId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($options['start_id'] ?? ''));
    $endId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($options['end_id'] ?? ''));
    $preset = (string)($options['preset'] ?? '');
    $label = (string)($options['label'] ?? 'Date range');
    $placeholder = (string)($options['placeholder'] ?? 'Select date range');
    $class = trim('tracs-date-range ' . (string)($options['class'] ?? ''));
    $autoSubmit = !empty($options['auto_submit']);

    ob_start();
    ?>
    <div
      class="<?=esc($class)?>"
      id="<?=esc($id)?>"
      data-tracs-date-range
      data-initial-start-date="<?=esc($start)?>"
      data-initial-end-date="<?=esc($end)?>"
      data-selected-preset="<?=esc($preset)?>"
      data-label="<?=esc($label)?>"
      data-placeholder="<?=esc($placeholder)?>"
      <?=$autoSubmit ? 'data-auto-submit="true"' : ''?>
    >
      <input type="hidden" <?=$startId !== '' ? 'id="'.esc($startId).'"' : ''?> name="<?=esc($startName)?>" value="<?=esc($start)?>" data-tracs-range-start>
      <input type="hidden" <?=$endId !== '' ? 'id="'.esc($endId).'"' : ''?> name="<?=esc($endName)?>" value="<?=esc($end)?>" data-tracs-range-end>
    </div>
    <?php
    return trim((string)ob_get_clean());
}

function tracs_stat_delta_meta(int $current, int $previous, string $period_label, string $polarity = 'positive'): array {
    $diff = $current - $previous;
    $pct = $previous > 0 ? (int)round(($diff / $previous) * 100) : ($current > 0 ? 100 : 0);
    $state = $diff > 0 ? 'up' : ($diff < 0 ? 'down' : 'flat');
    $tone = 'neutral';
    $direction = $state;

    if ($state !== 'flat') {
        if ($polarity === 'negative') {
            $tone = $diff < 0 ? 'good' : 'bad';
            $direction = $diff > 0 ? 'warn' : $state;
        } elseif ($polarity === 'warning') {
            $tone = $diff < 0 ? 'good' : 'warning';
        } elseif ($polarity === 'neutral') {
            $tone = 'neutral';
        } else {
            $tone = $diff > 0 ? 'good' : 'bad';
        }
    }

    $prefix = $pct > 0 ? '+' : '';
    return [
        'state' => $state,
        'tone' => $tone,
        'direction' => $direction,
        'value' => $prefix . $pct . '%',
        'detail' => $current . ' vs ' . $previous . ' ' . $period_label,
    ];
}

function tracs_stat_snapshot_meta(string $value, string $detail, string $tone = 'neutral'): array {
    return [
        'state' => 'flat',
        'tone' => preg_replace('/[^a-z0-9_-]/i', '', $tone) ?: 'neutral',
        'direction' => 'flat',
        'value' => $value,
        'detail' => $detail,
    ];
}

function tracs_render_stat_card(array $card): string {
    $trend = is_array($card['trend'] ?? null) ? $card['trend'] : tracs_stat_snapshot_meta('Live', 'Current snapshot');
    $color = preg_replace('/[^a-z0-9_-]/i', '', (string)($card['color'] ?? 'blue')) ?: 'blue';
    $key = preg_replace('/[^a-z0-9_-]/i', '', (string)($card['key'] ?? ''));
    ob_start();
    ?>
    <div class="stat-card <?=esc($color)?>" <?=$key !== '' ? 'data-stat-key="'.esc($key).'"' : ''?>>
      <div class="stat-glow"></div>
      <div class="stat-head">
        <?php if(!empty($card['icon'])): ?><span class="stat-icon"><i data-lucide="<?=esc($card['icon'])?>" class="icon-sm"></i></span><?php endif; ?>
        <span class="stat-label"><?=esc($card['label'] ?? '')?></span>
      </div>
      <div class="stat-main">
        <div class="stat-num"><?=esc((string)($card['value'] ?? '0'))?></div>
        <div class="stat-trend <?=esc($trend['direction'] ?? 'flat')?> <?=esc($trend['tone'] ?? 'neutral')?>" title="<?=esc($trend['detail'] ?? '')?>">
          <span class="stat-trend-arrow"></span>
          <span><?=esc($trend['value'] ?? 'Live')?></span>
        </div>
      </div>
      <div class="stat-compare"><?=esc($trend['detail'] ?? '')?></div>
    </div>
    <?php
    return trim((string)ob_get_clean());
}

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
