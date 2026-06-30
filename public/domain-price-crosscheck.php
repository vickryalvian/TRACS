<?php
/**
 * TRACS — Domain Price Crosscheck Module
 * Web interface shell with basic role-based access, month configuration, and placeholder UI sections.
 */

require_once __DIR__ . '/../core/security/csrf.php';
require_once __DIR__ . '/../core/security/error_response.php';
tracs_start_session();
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/auth/auth_check.php';
require_once __DIR__.'/../core/user_management.php';
require_once __DIR__.'/../core/access_control.php';
require_once __DIR__.'/../modules/domain-price-crosscheck/controller.php';
require_once __DIR__.'/../modules/alert-ticker/controller.php';
require_once __DIR__.'/includes/page_helpers.php';

$uid = (int)($_SESSION['user_id']??0);
$user_email = $_SESSION['user_email']??'operator@tracs.local';

tracs_require_page_permission($conn, 'domain_price.view');

$DPC = new DomainPriceCrosscheckController($conn, $uid);
$TC = new AlertTickerController($conn, $uid);
$ticker_items = $TC->formatAlertsForTicker();

$user_role_slug = $_SESSION['user_role_slug'] ?? '';
$is_intern = ($user_role_slug === 'intern');
$is_super_admin = ($user_role_slug === 'super_admin');
$can_create_month = !$is_intern && tracs_user_can($conn, 'domain_price.manage');
const DPC_TARGET_MARGIN_RATE = 0.30;
const DPC_REVIEW_INCREASE_RATE = 10.0;
const DPC_WARNING_INCREASE_RATE = 20.0;
const DPC_ROUNDING_INCREMENT = 1000;
const DPC_ALLOWED_COST_SOURCES = ['Liquid Registrar', 'Webnic Registrar', 'IDCH Internal Pricing'];
const DPC_ALLOWED_CCTLD_SOURCES = ['PANDI Registry Pricing', 'IDCH ccTLD Pricing'];

function dpc_month_name(int $month): string {
    return DateTime::createFromFormat('!m', sprintf('%02d', $month))->format('F');
}

function dpc_month_label(string $monthCode): string {
    $dt = DateTime::createFromFormat('!Y-m', $monthCode);
    return $dt ? $dt->format('F Y') : $monthCode;
}

function dpc_tld_label(?string $tld): string {
    $label = trim((string)$tld);
    if ($label === '') {
        return '';
    }
    if ($label[0] !== '.') {
        $label = '.' . $label;
    }
    return strtoupper($label);
}

function dpc_source_label(?string $sourceName): string {
    return match (trim((string)$sourceName)) {
        'PANDI Registry Pricing' => 'PANDI Registry',
        'IDCH ccTLD Pricing' => 'IDCH ccTLD',
        default => trim((string)$sourceName),
    };
}

function dpc_clean_rate($value): float {
    $clean = preg_replace('/[^\d.]/', '', (string)$value);
    return (float)$clean;
}

function dpc_is_allowed_cost_source(array $source): bool {
    $sourceName = (string)($source['source_name'] ?? '');
    return (string)($source['source_type'] ?? '') === 'registrar'
        || $sourceName === 'IDCH Internal Pricing'
        || in_array($sourceName, DPC_ALLOWED_COST_SOURCES, true);
}

function dpc_is_internal_cost_source(array $source): bool {
    return (string)($source['source_name'] ?? '') === 'IDCH Internal Pricing' || (string)($source['source_type'] ?? '') === 'internal';
}

function dpc_is_cctld_source(array $source): bool {
    return in_array((string)($source['source_name'] ?? ''), DPC_ALLOWED_CCTLD_SOURCES, true);
}

function dpc_money(?float $value): string {
    return $value === null ? '—' : 'Rp' . number_format($value, 0, ',', '.');
}

function dpc_percent(?float $value): string {
    return $value === null ? '—' : number_format($value, 2, ',', '.') . '%';
}

function dpc_round_price(float $value): float {
    return ceil($value / DPC_ROUNDING_INCREMENT) * DPC_ROUNDING_INCREMENT;
}

function dpc_round_up_decimal(float $value, int $precision = 2): float {
    $factor = 10 ** $precision;
    return ceil($value * $factor) / $factor;
}

function dpc_usd(?float $value): string {
    return $value === null ? '—' : '$' . number_format($value, 2, '.', '');
}

function dpc_usd_input_value($value): string {
    if ($value === null || $value === '') {
        return '';
    }
    $numeric = filter_var($value, FILTER_VALIDATE_FLOAT);
    return $numeric === false ? '' : number_format((float)$numeric, 2, '.', '');
}

function dpc_decimal_input_value($value, int $scale = 4): string {
    if ($value === null || $value === '') {
        return '';
    }
    $numeric = filter_var($value, FILTER_VALIDATE_FLOAT);
    if ($numeric === false) {
        return '';
    }
    $formatted = number_format((float)$numeric, $scale, '.', '');
    $trimmed = rtrim(rtrim($formatted, '0'), '.');
    return $trimmed === '' ? '0' : $trimmed;
}

function dpc_margin_delta_meta(?float $actualMarginPercent, ?float $websitePrice = null, ?float $recommendedPrice = null): array {
    if ($actualMarginPercent === null || $websitePrice === null || $recommendedPrice === null) {
        return ['class' => 'missing', 'label' => '—'];
    }

    $targetPercent = DPC_TARGET_MARGIN_RATE * 100;
    $delta = $actualMarginPercent - $targetPercent;
    $nominalDifference = $websitePrice - $recommendedPrice;
    if (abs($delta) < 0.005 || abs($nominalDifference) < 0.005) {
        return ['class' => 'on-target', 'label' => '0% · on target'];
    }

    $formatted = rtrim(rtrim(number_format(abs($delta), 2, '.', ''), '0'), '.');
    $nominal = dpc_money(abs($nominalDifference));
    return [
        'class' => $delta < 0 ? 'below-target' : 'above-target',
        'label' => ($delta > 0 ? '+' : '-') . $formatted . '% · ' . $nominal . ($delta < 0 ? ' gap' : ' buffer'),
    ];
}

function dpc_validation_current_price(array $check): ?float {
    $value = $check['website_price'] ?? $check['idch_price'] ?? null;
    return $value === null ? null : (float)$value;
}

function dpc_validation_cost_baseline(array $check): ?float {
    $value = $check['lowest_cost'] ?? $check['pandi_cost'] ?? null;
    return $value === null ? null : (float)$value;
}

function dpc_margin_status_meta(array $check): array {
    $status = (string)($check['status'] ?? 'Missing Data');
    $severity = strtolower((string)($check['severity'] ?? 'Missing'));
    $delta = dpc_margin_delta_meta(
        isset($check['margin_percent']) ? (float)$check['margin_percent'] : null,
        dpc_validation_current_price($check),
        isset($check['recommended_price']) ? (float)$check['recommended_price'] : null
    );

    $label = match ($status) {
        'Below Cost' => 'Below Cost',
        'Below Target Margin' => 'Below Target',
        'Missing Data' => 'Missing Data',
        'Registrar Cost Increased' => 'Cost Changed',
        'Recommended Source Changed' => 'Source Changed',
        default => $delta['class'] === 'above-target' ? 'Above Target' : 'Safe',
    };

    return [
        'badge_class' => in_array($severity, ['critical', 'warning', 'review', 'safe', 'missing'], true) ? $severity : 'missing',
        'label' => $label,
        'detail' => $status === 'Missing Data' ? 'Missing input' : $delta['label'],
    ];
}

function dpc_margin_sort_rank(array $check): int {
    return match ((string)($check['status'] ?? 'Missing Data')) {
        'Below Cost' => 1,
        'Missing Data' => 2,
        'Below Target Margin' => 3,
        'Registrar Cost Increased', 'Recommended Source Changed' => 4,
        default => 5,
    };
}

function dpc_website_decision_meta(array $check): array {
    $status = (string)($check['status'] ?? 'Missing Data');
    $current = dpc_validation_current_price($check);
    $recommended = isset($check['recommended_price']) ? (float)$check['recommended_price'] : null;
    $suggested = isset($check['suggested_rounded_price']) ? (float)$check['suggested_rounded_price'] : null;
    $publishIncrease = ($current !== null && $suggested !== null) ? max(0, $suggested - $current) : null;
    $buffer = ($current !== null && $recommended !== null) ? max(0, $current - $recommended) : null;

    return match ($status) {
        'Below Cost' => [
            'primary' => 'Fix below-cost price',
            'secondary' => $suggested !== null && $publishIncrease !== null
                ? 'Increase to ' . dpc_money($suggested) . ' · +' . dpc_money($publishIncrease)
                : 'Selling below cost baseline',
        ],
        'Below Target Margin' => [
            'primary' => $suggested !== null ? 'Increase to ' . dpc_money($suggested) : 'Increase website price',
            'secondary' => $publishIncrease !== null ? '+' . dpc_money($publishIncrease) . ' from current' : 'Move price to target margin',
        ],
        'Registrar Cost Increased' => [
            'primary' => 'Review cost change',
            'secondary' => 'Current price still above target',
        ],
        'Recommended Source Changed' => [
            'primary' => 'Review source change',
            'secondary' => 'Current price still above target',
        ],
        'Missing Data' => [
            'primary' => 'Complete missing data',
            'secondary' => 'Required before recommendation',
        ],
        default => [
            'primary' => 'Keep current price',
            'secondary' => $buffer !== null && $buffer > 0 ? dpc_money($buffer) . ' buffer' : 'Price is on target',
        ],
    };
}

function dpc_cctld_harga_usd(?float $baseIdr, float $exchangeRate): ?float {
    if ($baseIdr === null || $baseIdr < 0 || $exchangeRate <= 0) {
        return null;
    }
    // KURS on this page is USD -> IDR, so an IDR base price is divided by KURS before applying the 30% target margin.
    return dpc_round_up_decimal(($baseIdr / $exchangeRate) * (1 + DPC_TARGET_MARGIN_RATE), 2);
}

function dpc_pick_entry(array $matrixData, int $tldId, int $sourceId, string $priceType): ?array {
    return $matrixData[$tldId][$sourceId][$priceType] ?? null;
}

function dpc_status_key(string $status): string {
    return strtolower(str_replace(' ', '-', $status));
}

function dpc_note_status_class(?string $status): string {
    switch ((string)$status) {
        case 'Updated':
            return 'dpc-note-status-updated';
        case 'Need Review':
        case 'Waiting Finance':
        case 'Waiting Approval':
            return 'dpc-note-status-warning';
        case 'No Action':
        default:
            return 'dpc-note-status-muted';
    }
}

function dpc_note_badge_class(?string $status): string {
    switch ((string)$status) {
        case 'Updated':
            return 'b-success';
        case 'Need Review':
            return 'b-warning';
        case 'Waiting Finance':
            return 'b-info';
        case 'Waiting Approval':
            return 'b-high';
        case 'No Action':
        default:
            return 'b-low';
    }
}

function dpc_check_urgency_key(array $check): string {
    $status = (string)($check['status'] ?? '');
    $severity = (string)($check['severity'] ?? '');
    if ($status === 'Below Cost') {
        return 'immediate';
    }
    if ($status === 'Missing Data') {
        return 'missing';
    }
    if ($status === 'Below Target Margin' || $severity === 'Warning' || $severity === 'Review') {
        return 'review';
    }
    return 'monitor';
}

function dpc_build_price_check(
    array $tld,
    string $kind,
    array $sources,
    array $matrixData,
    array $matrixSpecial,
    array $previousChecks,
    array $noteMap,
    float $exchangeRate,
    ?float $previousExchangeRate
): array {
    $tldId = (int)$tld['id'];
    $costType = $kind === 'register' ? 'cost_register' : 'cost_renewal';
    $websiteType = $kind === 'register' ? 'selling_website_register' : 'selling_website_renewal';
    $label = $kind === 'register' ? 'Register' : 'Renewal';
    $candidates = [];

    foreach ($sources as $source) {
        $entry = dpc_pick_entry($matrixData, $tldId, (int)$source['id'], $costType);
        if (!$entry || (float)$entry['idr_value'] <= 0) {
            continue;
        }
        $candidates[] = [
            'source_id' => (int)$source['id'],
            'source_name' => (string)$source['source_name'],
            'idr_value' => (float)$entry['idr_value'],
            'usd_value' => (float)($entry['usd_value'] ?? 0),
            'currency' => (string)($entry['currency'] ?? ''),
        ];
    }

    usort($candidates, static fn($a, $b) => $a['idr_value'] <=> $b['idr_value']);
    $lowest = $candidates[0] ?? null;
    $next = $candidates[1] ?? null;
    $websiteEntry = $matrixSpecial[$tldId][$websiteType] ?? null;
    $websitePrice = ($websiteEntry && (float)$websiteEntry['idr_value'] > 0) ? (float)$websiteEntry['idr_value'] : null;
    $lowestCost = $lowest ? (float)$lowest['idr_value'] : null;
    $recommended = $lowestCost !== null ? $lowestCost * (1 + DPC_TARGET_MARGIN_RATE) : null;
    $rounded = $recommended !== null ? dpc_round_price($recommended) : null;
    $marginAmount = ($websitePrice !== null && $lowestCost !== null) ? $websitePrice - $lowestCost : null;
    $marginPercent = ($marginAmount !== null && $lowestCost > 0) ? ($marginAmount / $lowestCost) * 100 : null;
    $gap = ($websitePrice !== null && $recommended !== null) ? max(0, $recommended - $websitePrice) : null;
    $missing = $exchangeRate <= 0 || $lowestCost === null || $websitePrice === null;

    $status = 'Safe';
    $severity = 'Safe';
    $action = 'Keep Current Website Price';
    $priority = 7;

    if ($missing) {
        $status = 'Missing Data';
        $severity = 'Missing';
        $action = 'Complete Missing Data';
        $priority = 3;
    } elseif ($websitePrice < $lowestCost) {
        $status = 'Below Cost';
        $severity = 'Critical';
        $action = 'Increase Website Price Immediately';
        $priority = 1;
    } elseif ($websitePrice < $recommended) {
        $status = 'Below Target Margin';
        $severity = 'Warning';
        $action = 'Adjust Website Price to Target Margin';
        $priority = 2;
    }

    $previous = $previousChecks[$tldId][$kind] ?? null;
    $costChange = ($previous && $lowestCost !== null && ($previous['lowest_cost'] ?? null) !== null) ? $lowestCost - (float)$previous['lowest_cost'] : null;
    $costChangePercent = ($costChange !== null && (float)$previous['lowest_cost'] > 0) ? ($costChange / (float)$previous['lowest_cost']) * 100 : null;
    $recommendedChange = ($previous && $recommended !== null && ($previous['recommended_price'] ?? null) !== null) ? $recommended - (float)$previous['recommended_price'] : null;
    $websiteChange = ($previous && $websitePrice !== null && ($previous['website_price'] ?? null) !== null) ? $websitePrice - (float)$previous['website_price'] : null;
    $sourceChanged = $previous && $lowest && !empty($previous['source_name']) && (string)$previous['source_name'] !== (string)$lowest['source_name'];

    if ($status === 'Safe' && $costChangePercent !== null && $costChangePercent >= DPC_WARNING_INCREASE_RATE) {
        $status = 'Registrar Cost Increased';
        $severity = 'Warning';
        $action = 'Review Registrar Cost Change';
        $priority = 4;
    } elseif ($status === 'Safe' && $costChangePercent !== null && $costChangePercent >= DPC_REVIEW_INCREASE_RATE) {
        $status = 'Registrar Cost Increased';
        $severity = 'Review';
        $action = 'Review Registrar Cost Change';
        $priority = 4;
    } elseif ($status === 'Safe' && $sourceChanged) {
        $status = 'Recommended Source Changed';
        $severity = 'Review';
        $action = 'Review Source Change';
        $priority = 5;
    }

    $exchangeImpact = null;
    if ($previousExchangeRate !== null && $lowest && strtoupper($lowest['currency']) === 'USD' && (float)$lowest['usd_value'] > 0) {
        $exchangeImpact = (float)$lowest['usd_value'] * ($exchangeRate - $previousExchangeRate);
    }

    $note = $noteMap[$tldId] ?? null;
    return [
        'tld_id' => $tldId,
        'tld_name' => (string)$tld['tld_name'],
        'kind' => $kind,
        'type_label' => $label,
        'website_price' => $websitePrice,
        'lowest_cost' => $lowestCost,
        'lowest_source_id' => $lowest['source_id'] ?? null,
        'lowest_source_name' => $lowest['source_name'] ?? null,
        'next_cost' => $next['idr_value'] ?? null,
        'source_advantage' => ($next && $lowest) ? (float)$next['idr_value'] - (float)$lowest['idr_value'] : null,
        'recommended_price' => $recommended,
        'suggested_rounded_price' => $rounded,
        'margin_amount' => $marginAmount,
        'margin_percent' => $marginPercent,
        'target_margin_delta' => $marginPercent !== null ? $marginPercent - (DPC_TARGET_MARGIN_RATE * 100) : null,
        'gap_to_recommended' => $gap,
        'status' => $status,
        'severity' => $severity,
        'suggested_action' => $action,
        'priority' => $priority,
        'cost_change' => $costChange,
        'cost_change_percent' => $costChangePercent,
        'recommended_change' => $recommendedChange,
        'website_change' => $websiteChange,
        'source_changed' => $sourceChanged,
        'exchange_impact' => $exchangeImpact,
        'has_note' => $note && (!empty($note['manual_note']) || (($note['follow_up_status'] ?? 'No Action') !== 'No Action')),
        'note_status' => $note['follow_up_status'] ?? '',
    ];
}

function dpc_build_cctld_check(
    array $tld,
    string $kind,
    ?array $pandiSource,
    ?array $idchSource,
    array $matrixData,
    float $exchangeRate
): array {
    $typeMap = [
        'register' => ['price_type' => 'cost_register', 'label' => 'Register'],
        'renewal' => ['price_type' => 'cost_renewal', 'label' => 'Renewal'],
        'redemption' => ['price_type' => 'cost_transfer', 'label' => 'Redemption'],
    ];
    $meta = $typeMap[$kind] ?? $typeMap['register'];
    $tldId = (int)$tld['id'];
    $pandiEntry = $pandiSource ? dpc_pick_entry($matrixData, $tldId, (int)$pandiSource['id'], $meta['price_type']) : null;
    $idchEntry = $idchSource ? dpc_pick_entry($matrixData, $tldId, (int)$idchSource['id'], $meta['price_type']) : null;
    $pandiCost = ($pandiEntry && (float)$pandiEntry['idr_value'] > 0) ? (float)$pandiEntry['idr_value'] : null;
    $idchPrice = ($idchEntry && (float)$idchEntry['idr_value'] > 0) ? (float)$idchEntry['idr_value'] : null;
    $recommended = $pandiCost !== null ? $pandiCost * (1 + DPC_TARGET_MARGIN_RATE) : null;
    $hargaUsd = dpc_cctld_harga_usd($pandiCost, $exchangeRate);
    $marginAmount = ($idchPrice !== null && $pandiCost !== null) ? $idchPrice - $pandiCost : null;
    $marginPercent = ($marginAmount !== null && $pandiCost > 0) ? ($marginAmount / $pandiCost) * 100 : null;
    $gap = ($idchPrice !== null && $recommended !== null) ? max(0, $recommended - $idchPrice) : null;

    $status = 'Safe';
    $severity = 'Safe';
    $action = 'Keep Current IDCH ccTLD Price';
    $priority = 5;
    if ($pandiCost === null || $idchPrice === null) {
        $status = 'Missing Data';
        $severity = 'Missing';
        $action = 'Complete Missing Data';
        $priority = 3;
    } elseif ($idchPrice < $pandiCost) {
        $status = 'Below Cost';
        $severity = 'Critical';
        $action = 'Increase IDCH ccTLD Price Immediately';
        $priority = 1;
    } elseif ($idchPrice < $recommended) {
        $status = 'Below Target Margin';
        $severity = 'Warning';
        $action = 'Adjust IDCH ccTLD Price to Target Margin';
        $priority = 2;
    }

    return [
        'tld_id' => $tldId,
        'tld_name' => (string)$tld['tld_name'],
        'kind' => $kind,
        'type_label' => $meta['label'],
        'price_type' => $meta['price_type'],
        'pandi_cost' => $pandiCost,
        'harga_usd' => $hargaUsd,
        'idch_price' => $idchPrice,
        'recommended_price' => $recommended,
        'suggested_rounded_price' => $recommended !== null ? dpc_round_price($recommended) : null,
        'margin_amount' => $marginAmount,
        'margin_percent' => $marginPercent,
        'target_margin_delta' => $marginPercent !== null ? $marginPercent - (DPC_TARGET_MARGIN_RATE * 100) : null,
        'gap_to_recommended' => $gap,
        'status' => $status,
        'severity' => $severity,
        'suggested_action' => $action,
        'priority' => $priority,
        'note' => '',
    ];
}

// Fetch months and lists
$months = $DPC->getMonths();

if ($is_intern) {
    // Interns only see months assigned to them
    $filtered_months = [];
    foreach ($months as $m) {
        if ($DPC->hasInternAccess($m['id'], $user_role_slug, $uid)) {
            $filtered_months[] = $m;
        }
    }
    $months = $filtered_months;
}

$month_lookup = [];
foreach ($months as $m) {
    $month_lookup[(string)$m['month']] = $m;
}

$latest_month = $months[0] ?? null;
$latest_exchange_rate = $latest_month ? (float)$latest_month['exchange_rate_usd_idr'] : null;
$latest_month_label = $latest_month ? dpc_month_label((string)$latest_month['month']) : null;
$current_period = new DateTime('first day of this month');
$suggested_period = clone $current_period;
while (isset($month_lookup[$suggested_period->format('Y-m')])) {
    $suggested_period->modify('+1 month');
}
$current_year = (int)date('Y');
$suggested_year = (int)$suggested_period->format('Y');
$year_options = range($current_year - 2, $current_year + 2);
if (!in_array($suggested_year, $year_options, true)) {
    $year_options[] = $suggested_year;
    sort($year_options);
}

$selected_month_id = isset($_GET['month_id']) ? (int)$_GET['month_id'] : (count($months) > 0 ? (int)$months[0]['id'] : 0);

if ($selected_month_id > 0 && $is_intern && !$DPC->hasInternAccess($selected_month_id, $user_role_slug, $uid)) {
    // Fallback if they try to access via URL parameter manually
    $selected_month_id = count($months) > 0 ? (int)$months[0]['id'] : 0;
}

$active_tlds = $DPC->getActiveTlds('gtld');
$active_cctlds = $DPC->getActiveTlds('cctld');
$all_active_sources = $DPC->getActiveSources();
$registrar_sources = array_values(array_filter($all_active_sources, static fn($source) => (string)($source['source_type'] ?? '') === 'registrar'));
$active_sources = array_values(array_filter($all_active_sources, 'dpc_is_allowed_cost_source'));
$cctld_sources = array_values(array_filter($all_active_sources, 'dpc_is_cctld_source'));
$pandi_source = null;
$idch_cctld_source = null;
foreach ($cctld_sources as $source) {
    if ((string)$source['source_name'] === 'PANDI Registry Pricing') $pandi_source = $source;
    if ((string)$source['source_name'] === 'IDCH ccTLD Pricing') $idch_cctld_source = $source;
}

$month_data = null;
$entries = [];
$audit_logs = [];
$tld_notes = [];
$assigned_task = null;
$matrix_data = []; // [tld_id][source_id][price_type] => entry
$matrix_special = []; // [tld_id][price_type] => entry (no source_id)
$summary_stats = ['below_cost_count' => 0, 'low_margin_count' => 0, 'total_tlds' => 0];
$pricing_intelligence = null;
$cctld_intelligence = null;

if ($selected_month_id > 0) {
    $details = $DPC->getMonthDetails($selected_month_id);
    if ($details) {
        $month_data = $details['month'];
        $entries    = $details['entries'];
        $audit_logs = $details['audit_logs'];
        $tld_notes  = $DPC->getTldNotes($selected_month_id);
        $assigned_task = $DPC->getAssignedTask($selected_month_id);

        // Organise entries into a matrix keyed by the actual price_type enum
        foreach ($entries as $entry) {
            $tld_id = $entry['tld_id'];
            if ($entry['source_id']) {
                // Registrar / internal cost rows — indexed by [tld][source][price_type]
                $matrix_data[$tld_id][$entry['source_id']][$entry['price_type']] = $entry;
            } else {
                // Selling price rows (no source_id) — indexed by [tld][price_type]
                $matrix_special[$tld_id][$entry['price_type']] = $entry;
            }
        }

        // Computed summary stats for stat cards
        try {
            $summary_stats = $DPC->getComputedSummaryStats($selected_month_id);
        } catch (Exception $e) {
            // Non-fatal — stats will show 0 until Recalculate is triggered
        }
    }
}

if ($month_data) {
    $note_map = [];
    foreach ($tld_notes as $note) {
        $note_map[(int)$note['tld_id']] = $note;
    }

    $previous_month = null;
    foreach ($months as $candidate) {
        if ((string)$candidate['month'] < (string)$month_data['month']) {
            $previous_month = $candidate;
            break;
        }
    }

    $previous_checks = [];
    $previous_exchange_rate = null;
    if ($previous_month) {
        $previous_exchange_rate = (float)$previous_month['exchange_rate_usd_idr'];
        $previous_details = $DPC->getMonthDetails((int)$previous_month['id']);
        $previous_matrix = [];
        $previous_special = [];
        if ($previous_details) {
            foreach ($previous_details['entries'] as $entry) {
                $tldId = (int)$entry['tld_id'];
                if ($entry['source_id']) {
                    $previous_matrix[$tldId][(int)$entry['source_id']][$entry['price_type']] = $entry;
                } else {
                    $previous_special[$tldId][$entry['price_type']] = $entry;
                }
            }
            foreach ($active_tlds as $tld) {
                foreach (['register', 'renewal'] as $kind) {
                    $prevCheck = dpc_build_price_check($tld, $kind, $active_sources, $previous_matrix, $previous_special, [], [], $previous_exchange_rate, null);
                    $previous_checks[(int)$tld['id']][$kind] = $prevCheck;
                }
            }
        }
    }

    $checks = [];
    foreach ($active_tlds as $tld) {
        foreach (['register', 'renewal'] as $kind) {
            $checks[] = dpc_build_price_check(
                $tld,
                $kind,
                $active_sources,
                $matrix_data,
                $matrix_special,
                $previous_checks,
                $note_map,
                (float)$month_data['exchange_rate_usd_idr'],
                $previous_exchange_rate
            );
        }
    }

    usort($checks, static function ($a, $b) {
        $priority = $a['priority'] <=> $b['priority'];
        if ($priority !== 0) return $priority;
        $gap = (float)($b['gap_to_recommended'] ?? 0) <=> (float)($a['gap_to_recommended'] ?? 0);
        if ($gap !== 0) return $gap;
        return [$a['tld_name'], $a['type_label']] <=> [$b['tld_name'], $b['type_label']];
    });

    $source_summary = [];
    $action_buckets = [
        'Increase Website Price Immediately' => [],
        'Adjust Website Price to Target Margin' => [],
        'Complete Missing Data' => [],
        'Review Registrar Cost Change' => [],
        'Keep Current Website Price' => [],
    ];
    $previous_summary = [
        'cost_increase' => [],
        'cost_decrease' => [],
        'recommended_increase' => [],
        'source_changed' => [],
    ];
    $exchange_impacts = [];
    $counts = [
        'below_cost' => 0,
        'below_target' => 0,
        'safe' => 0,
        'missing' => 0,
        'recommended_adjustments' => 0,
        'pending_review' => 0,
    ];
    $estimated_margin_risk = 0.0;

    foreach ($checks as $check) {
        if ($check['status'] === 'Below Cost') $counts['below_cost']++;
        if ($check['status'] === 'Below Target Margin') $counts['below_target']++;
        if ($check['status'] === 'Safe') $counts['safe']++;
        if ($check['status'] === 'Missing Data') $counts['missing']++;
        if (in_array($check['suggested_action'], ['Increase Website Price Immediately', 'Adjust Website Price to Target Margin'], true)) {
            $counts['recommended_adjustments']++;
            $estimated_margin_risk += (float)($check['gap_to_recommended'] ?? 0);
        }
        if ($check['severity'] === 'Review' || $check['status'] === 'Registrar Cost Increased' || $check['status'] === 'Recommended Source Changed') {
            $counts['pending_review']++;
        }

        if (!empty($check['lowest_source_name'])) {
            $source = $check['lowest_source_name'];
            $source_summary[$source]['source_name'] = $source;
            $source_summary[$source]['count'] = ($source_summary[$source]['count'] ?? 0) + 1;
            if ($check['source_advantage'] !== null) {
                $source_summary[$source]['advantage_total'] = ($source_summary[$source]['advantage_total'] ?? 0) + (float)$check['source_advantage'];
                $source_summary[$source]['advantage_count'] = ($source_summary[$source]['advantage_count'] ?? 0) + 1;
            }
        }

        $bucket = $check['suggested_action'];
        if ($bucket === 'Review Source Change') {
            $bucket = 'Review Registrar Cost Change';
        } elseif (!isset($action_buckets[$bucket])) {
            $bucket = 'Keep Current Website Price';
        }
        $action_buckets[$bucket][] = $check;

        if ($check['cost_change'] !== null && $check['cost_change'] > 0) $previous_summary['cost_increase'][] = $check;
        if ($check['cost_change'] !== null && $check['cost_change'] < 0) $previous_summary['cost_decrease'][] = $check;
        if ($check['recommended_change'] !== null && $check['recommended_change'] > 0) $previous_summary['recommended_increase'][] = $check;
        if ($check['source_changed']) $previous_summary['source_changed'][] = $check;
        if ($check['exchange_impact'] !== null) $exchange_impacts[] = $check;
    }

    uasort($source_summary, static fn($a, $b) => ($b['count'] ?? 0) <=> ($a['count'] ?? 0));
    usort($exchange_impacts, static fn($a, $b) => abs($b['exchange_impact'] ?? 0) <=> abs($a['exchange_impact'] ?? 0));

    $registrar_snapshot = [];
    $totalRegistrarSlots = count($active_tlds) * 2;
    foreach ($registrar_sources as $source) {
        $sourceId = (int)$source['id'];
        $coverage = 0;
        $registerCoverage = 0;
        $renewalCoverage = 0;
        foreach ($active_tlds as $tld) {
            $tldId = (int)$tld['id'];
            foreach (['cost_register' => 'register', 'cost_renewal' => 'renewal'] as $priceType => $kind) {
                $entry = $matrix_data[$tldId][$sourceId][$priceType] ?? null;
                if (!$entry || (float)($entry['idr_value'] ?? 0) <= 0) {
                    continue;
                }
                $coverage++;
                if ($kind === 'register') {
                    $registerCoverage++;
                } else {
                    $renewalCoverage++;
                }
            }
        }

        $wins = array_values(array_filter(
            $checks,
            static fn(array $check): bool => (int)($check['lowest_source_id'] ?? 0) === $sourceId
        ));
        $registerWins = count(array_filter($wins, static fn(array $check): bool => $check['kind'] === 'register'));
        $renewalWins = count($wins) - $registerWins;
        $advantagedWins = array_values(array_filter($wins, static fn(array $check): bool => $check['source_advantage'] !== null));
        $avgAdvantage = $advantagedWins
            ? array_sum(array_column($advantagedWins, 'source_advantage')) / count($advantagedWins)
            : null;
        usort($advantagedWins, static fn(array $left, array $right): int => (float)$right['source_advantage'] <=> (float)$left['source_advantage']);
        $strongest = $advantagedWins[0] ?? ($wins[0] ?? null);

        $previousWins = null;
        if ($previous_month) {
            $previousWins = 0;
            foreach ($previous_checks as $kindChecks) {
                foreach ($kindChecks as $previousCheck) {
                    if ((int)($previousCheck['lowest_source_id'] ?? 0) === $sourceId) {
                        $previousWins++;
                    }
                }
            }
        }

        $registrar_snapshot[] = [
            'source_name' => (string)$source['source_name'],
            'wins' => count($wins),
            'register_wins' => $registerWins,
            'renewal_wins' => $renewalWins,
            'coverage' => $coverage,
            'total_slots' => $totalRegistrarSlots,
            'register_coverage' => $registerCoverage,
            'renewal_coverage' => $renewalCoverage,
            'missing' => max(0, $totalRegistrarSlots - $coverage),
            'avg_advantage' => $avgAdvantage,
            'strongest' => $strongest,
            'previous_wins' => $previousWins,
            'trend' => $previousWins === null ? null : count($wins) - $previousWins,
        ];
    }
    usort($registrar_snapshot, static fn(array $left, array $right): int =>
        ($right['wins'] <=> $left['wins'])
        ?: ($right['coverage'] <=> $left['coverage'])
        ?: strcasecmp($left['source_name'], $right['source_name'])
    );

    $pricing_intelligence = [
        'checks' => $checks,
        'counts' => $counts,
        'estimated_margin_risk' => $estimated_margin_risk,
        'source_summary' => $source_summary,
        'registrar_snapshot' => $registrar_snapshot,
        'previous_month' => $previous_month,
        'previous_exchange_rate' => $previous_exchange_rate,
        'exchange_rate_diff' => $previous_exchange_rate !== null ? (float)$month_data['exchange_rate_usd_idr'] - $previous_exchange_rate : null,
        'exchange_rate_diff_pct' => ($previous_exchange_rate !== null && $previous_exchange_rate > 0) ? (((float)$month_data['exchange_rate_usd_idr'] - $previous_exchange_rate) / $previous_exchange_rate) * 100 : null,
        'exchange_impacts' => $exchange_impacts,
        'previous_summary' => $previous_summary,
        'action_buckets' => $action_buckets,
    ];

    $cctld_checks = [];
    foreach ($active_cctlds as $tld) {
        foreach (['register', 'renewal', 'redemption'] as $kind) {
            $cctld_checks[] = dpc_build_cctld_check($tld, $kind, $pandi_source, $idch_cctld_source, $matrix_data, (float)$month_data['exchange_rate_usd_idr']);
        }
    }
    usort($cctld_checks, static function ($a, $b) {
        $priority = $a['priority'] <=> $b['priority'];
        if ($priority !== 0) return $priority;
        return [$a['tld_name'], $a['type_label']] <=> [$b['tld_name'], $b['type_label']];
    });
    $cctld_counts = [
        'below_cost' => 0,
        'below_target' => 0,
        'safe' => 0,
        'missing' => 0,
        'recommended_adjustments' => 0,
    ];
    foreach ($cctld_checks as $check) {
        if ($check['status'] === 'Below Cost') $cctld_counts['below_cost']++;
        if ($check['status'] === 'Below Target Margin') $cctld_counts['below_target']++;
        if ($check['status'] === 'Safe') $cctld_counts['safe']++;
        if ($check['status'] === 'Missing Data') $cctld_counts['missing']++;
        if (in_array($check['suggested_action'], ['Increase IDCH ccTLD Price Immediately', 'Adjust IDCH ccTLD Price to Target Margin'], true)) {
            $cctld_counts['recommended_adjustments']++;
        }
    }
    $cctld_intelligence = [
        'checks' => $cctld_checks,
        'counts' => $cctld_counts,
    ];
}


$website_price_checks = array_map(static function (array $check): array {
    $check['_scope'] = 'website';
    return $check;
}, $pricing_intelligence['checks'] ?? []);
$cctld_price_checks = array_map(static function (array $check): array {
    $check['_scope'] = 'cctld';
    return $check;
}, $cctld_intelligence['checks'] ?? []);
$all_price_checks = array_merge($website_price_checks, $cctld_price_checks);

$dpc_build_action_bucket = static function (
    string $title,
    string $tone,
    string $description,
    array $items,
    string $filterValue,
    string $sortKey = '',
    string $sortDirection = 'asc',
    string $shortcutScope = 'both'
): array {
    usort($items, static function (array $left, array $right) use ($sortKey, $sortDirection): int {
        if ($sortKey === 'required-increase') {
            $comparison = (float)($left['gap_to_recommended'] ?? -1) <=> (float)($right['gap_to_recommended'] ?? -1);
            return $sortDirection === 'desc' ? -$comparison : $comparison;
        }
        $priority = (int)($left['priority'] ?? 9) <=> (int)($right['priority'] ?? 9);
        return $priority ?: ((float)($right['gap_to_recommended'] ?? 0) <=> (float)($left['gap_to_recommended'] ?? 0));
    });

    $uniqueTlds = [];
    $totalImpact = 0.0;
    foreach ($items as $item) {
        $uniqueTlds[strtolower((string)($item['tld_name'] ?? ''))] = true;
        $totalImpact += max(0, (float)($item['gap_to_recommended'] ?? 0));
    }

    $topItem = $items[0] ?? null;
    return [
        'title' => $title,
        'tone' => $tone,
        'description' => $description,
        'items' => $items,
        'row_count' => count($items),
        'tld_count' => count(array_filter(array_keys($uniqueTlds))),
        'total_impact' => $totalImpact,
        'top_item' => $topItem,
        'target_tab' => 'website-adjustment',
        'target_scope' => $shortcutScope,
        'filter_value' => $filterValue,
        'sort_key' => $sortKey,
        'sort_direction' => $sortDirection,
    ];
};

$dpc_filter_checks = static fn(array $checks, string $status): array => array_values(array_filter(
    $checks,
    static fn(array $check): bool => (string)($check['status'] ?? '') === $status
));
$dpc_high_impact_checks = array_values(array_filter(
    $website_price_checks,
    static function (array $check): bool {
        $gap = (float)($check['gap_to_recommended'] ?? 0);
        $websitePrice = (float)($check['website_price'] ?? 0);
        return $gap > 0
            && $websitePrice > 0
            && (($gap / $websitePrice) * 100) >= DPC_REVIEW_INCREASE_RATE;
    }
));
$dpc_registrar_anomaly_checks = array_values(array_filter(
    $website_price_checks,
    static fn(array $check): bool => in_array((string)($check['status'] ?? ''), ['Registrar Cost Increased', 'Recommended Source Changed'], true)
        || !empty($check['source_changed'])
));
$dpc_website_priority_checks = array_values(array_filter(
    $website_price_checks,
    static fn(array $check): bool => in_array((string)($check['status'] ?? ''), ['Below Cost', 'Below Target Margin'], true)
));

$dpc_urgency_buckets = [
    'critical' => $dpc_build_action_bucket(
        'Critical: below cost',
        'red',
        'Selling price is below the current cost baseline.',
        $dpc_filter_checks($all_price_checks, 'Below Cost'),
        'below-cost'
    ),
    'warning' => $dpc_build_action_bucket(
        'Warning: below target',
        'yellow',
        'Positive margin, but still below the configured 30% target.',
        $dpc_filter_checks($all_price_checks, 'Below Target Margin'),
        'below-target-margin'
    ),
    'missing' => $dpc_build_action_bucket(
        'Missing data',
        'muted',
        'Cost or selling price inputs are incomplete.',
        $dpc_filter_checks($all_price_checks, 'Missing Data'),
        'missing-data'
    ),
    'safe' => $dpc_build_action_bucket(
        'Safe / no action',
        'green',
        'Current pricing meets the configured target margin.',
        $dpc_filter_checks($all_price_checks, 'Safe'),
        'safe'
    ),
    'high-impact' => $dpc_build_action_bucket(
        'High impact increase',
        'yellow',
        'Required increase is at least 10% of the current website price.',
        $dpc_high_impact_checks,
        'all',
        'required-increase',
        'desc',
        'website'
    ),
    'cctld-incomplete' => $dpc_build_action_bucket(
        'ccTLD incomplete',
        'muted',
        'ccTLD checks still missing registry or IDCH pricing.',
        $dpc_filter_checks($cctld_price_checks, 'Missing Data'),
        'missing-data',
        '',
        'asc',
        'cctld'
    ),
    'registrar-anomalies' => $dpc_build_action_bucket(
        'Registrar changes / anomalies',
        'yellow',
        'Registrar cost movement or preferred source changes need review.',
        $dpc_registrar_anomaly_checks,
        'all',
        '',
        'asc',
        'website'
    ),
    'website-priority' => $dpc_build_action_bucket(
        'Website adjustment priority',
        'red',
        'Customer-facing website prices requiring immediate or target-margin adjustment.',
        $dpc_website_priority_checks,
        'all',
        'required-increase',
        'desc',
        'website'
    ),
];

$overview_counts = [
    'tlds_checked' => count($active_tlds) + count($active_cctlds),
    'safe' => ($pricing_intelligence['counts']['safe'] ?? 0) + ($cctld_intelligence['counts']['safe'] ?? 0),
    'under_margin' => ($pricing_intelligence['counts']['below_target'] ?? 0) + ($cctld_intelligence['counts']['below_target'] ?? 0),
    'loss_risk' => ($pricing_intelligence['counts']['below_cost'] ?? 0) + ($cctld_intelligence['counts']['below_cost'] ?? 0),
    'missing' => ($pricing_intelligence['counts']['missing'] ?? 0) + ($cctld_intelligence['counts']['missing'] ?? 0),
    'suggested_changes' => ($pricing_intelligence['counts']['recommended_adjustments'] ?? 0) + ($cctld_intelligence['counts']['recommended_adjustments'] ?? 0),
];

$overview_stat_cards = [
    ['label' => 'TLDs checked', 'value' => $overview_counts['tlds_checked'], 'detail' => 'gTLD + ccTLD active set', 'tone' => 'muted'],
    ['label' => 'Safe', 'value' => $overview_counts['safe'], 'detail' => 'Meets current target margin', 'tone' => 'green'],
    ['label' => 'Under target margin', 'value' => $overview_counts['under_margin'], 'detail' => 'Warning review needed', 'tone' => $overview_counts['under_margin'] > 0 ? 'yellow' : 'green'],
    ['label' => 'Loss risk', 'value' => $overview_counts['loss_risk'], 'detail' => 'Below registrar or registry cost', 'tone' => $overview_counts['loss_risk'] > 0 ? 'red' : 'green'],
    ['label' => 'Missing data', 'value' => $overview_counts['missing'], 'detail' => 'Incomplete pricing inputs', 'tone' => $overview_counts['missing'] > 0 ? 'muted' : 'green'],
    ['label' => 'Suggested price changes', 'value' => $overview_counts['suggested_changes'], 'detail' => 'Rows with adjustment actions', 'tone' => $overview_counts['suggested_changes'] > 0 ? 'red' : 'green'],
];

$priority_findings_preview = array_values(array_filter($all_price_checks, static fn($check) => ($check['status'] ?? '') !== 'Safe'));
usort($priority_findings_preview, static function ($a, $b) {
    $priority = ((int)($a['priority'] ?? 9)) <=> ((int)($b['priority'] ?? 9));
    if ($priority !== 0) return $priority;
    return (float)($b['gap_to_recommended'] ?? 0) <=> (float)($a['gap_to_recommended'] ?? 0);
});
$priority_findings_preview = array_slice($priority_findings_preview, 0, 5);
$audit_preview = array_slice($audit_logs, 0, 4);

// Handle Form Submissions (Draft creation / status changes)
$error_message = '';
$success_message = '';

function dpc_is_ajax_request(): bool {
    return strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
        || str_contains(strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');
}

function dpc_action_response(bool $success, string $message, string $redirect, int $status = 200): never {
    if (dpc_is_ajax_request()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'redirect' => $redirect,
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }
    if ($success) {
        header('Location: ' . $redirect);
        exit;
    }
    throw new RuntimeException($message);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    try {
        if ($action === 'create_month' && tracs_user_can($conn, 'domain_price.manage')) {
            $period_month = (int)($_POST['period_month'] ?? 0);
            $period_year = (int)($_POST['period_year'] ?? 0);
            $month_code = trim($_POST['month_code'] ?? '');
            $use_template = !empty($_POST['use_previous_template']);
            $copy_notes = !empty($_POST['copy_template_notes']);
            if ($period_month >= 1 && $period_month <= 12 && $period_year >= 2000) {
                $month_code = sprintf('%04d-%02d', $period_year, $period_month);
            }
            $exchange_rate = dpc_clean_rate($_POST['exchange_rate'] ?? '');
            $existing_month = $DPC->getMonthByCode($month_code);
            if ($existing_month) {
                throw new Exception('A monthly record for ' . dpc_month_label($month_code) . ' already exists. Please select the existing record instead.');
            }
            $template_month_id = null;
            if ($use_template) {
                foreach ($months as $candidate) {
                    if ((string)$candidate['month'] < $month_code) {
                        $template_month_id = (int)$candidate['id'];
                        if ($exchange_rate <= 0) {
                            $exchange_rate = (float)$candidate['exchange_rate_usd_idr'];
                        }
                        break;
                    }
                }
            }
            
            $new_id = $DPC->createMonthRecord($month_code, $exchange_rate, $ip_address, $template_month_id, $copy_notes);
            if ($new_id) {
                $success_message = "Successfully created monthly record draft for {$month_code}!";
                dpc_action_response(true, $success_message, "/domain-price-crosscheck.php?month_id={$new_id}");
            } else {
                $error_message = "Failed to create monthly record.";
            }
        } elseif ($action === 'update_rate' && tracs_user_can($conn, 'domain_price.manage')) {
            $month_id = (int)($_POST['month_id'] ?? 0);
            $exchange_rate = (float)($_POST['exchange_rate'] ?? 0.0);
            $change_reason = trim($_POST['change_reason'] ?? '');
            
            if ($DPC->updateExchangeRate($month_id, $exchange_rate, $ip_address, $change_reason)) {
                $success_message = "Exchange rate updated successfully.";
                dpc_action_response(true, $success_message, "/domain-price-crosscheck.php?month_id={$month_id}");
            } else {
                $error_message = "Failed to update exchange rate (must be in draft status).";
            }
        } elseif ($action === 'submit_review' && tracs_user_can($conn, 'domain_price.manage')) {
            $month_id = (int)($_POST['month_id'] ?? 0);
            if ($DPC->submitForReview($month_id, $ip_address)) {
                $success_message = "Submitted monthly record for review!";
                dpc_action_response(true, $success_message, "/domain-price-crosscheck.php?month_id={$month_id}");
            } else {
                $error_message = "Failed to submit for review.";
            }
        } elseif ($action === 'add_tld_extension' && tracs_user_can($conn, 'domain_price.manage')) {
            $tld_name = trim($_POST['tld_name'] ?? '');
            $tld_category = trim($_POST['tld_category'] ?? 'gtld');
            $sort_order_raw = trim((string)($_POST['sort_order'] ?? ''));
            $sort_order = null;
            if ($sort_order_raw !== '') {
                $sort_order = filter_var($sort_order_raw, FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 1, 'max_range' => 999999],
                ]);
                if ($sort_order === false) {
                    throw new Exception('Sort order must be a positive number.');
                }
            }
            $DPC->addTldExtension($tld_name, $tld_category, $sort_order, $ip_address);
            $target_month = (int)($_POST['month_id'] ?? $selected_month_id);
            $redirect = "/domain-price-crosscheck.php" . ($target_month > 0 ? "?month_id={$target_month}" : "");
            dpc_action_response(true, 'Domain extension ' . dpc_tld_label($tld_name) . ' added.', $redirect);
        } elseif ($action === 'update_tld_extension' && tracs_user_can($conn, 'domain_price.manage')) {
            $tld_id = (int)($_POST['tld_id'] ?? 0);
            $tld_category = trim($_POST['tld_category'] ?? 'gtld');
            $sort_order_raw = trim((string)($_POST['sort_order'] ?? ''));
            $sort_order = null;
            if ($sort_order_raw !== '') {
                $sort_order = filter_var($sort_order_raw, FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 1, 'max_range' => 999999],
                ]);
                if ($sort_order === false) {
                    throw new Exception('Sort order must be a positive number.');
                }
            }
            $DPC->updateTldExtension($tld_id, $tld_category, $sort_order, $ip_address);
            $target_month = (int)($_POST['month_id'] ?? $selected_month_id);
            $redirect = "/domain-price-crosscheck.php" . ($target_month > 0 ? "?month_id={$target_month}" : "");
            dpc_action_response(true, "Domain extension updated.", $redirect);
        } elseif ($action === 'delete_tld_extension' && $is_super_admin) {
            $tld_id = (int)($_POST['tld_id'] ?? 0);
            $DPC->deleteTldExtension($tld_id, $ip_address);
            $target_month = (int)($_POST['month_id'] ?? $selected_month_id);
            $redirect = "/domain-price-crosscheck.php" . ($target_month > 0 ? "?month_id={$target_month}" : "");
            dpc_action_response(true, "Domain extension removed.", $redirect);
        } elseif ($action === 'add_pricing_source' && tracs_user_can($conn, 'domain_price.manage')) {
            $source_name = trim($_POST['source_name'] ?? '');
            $source_type = 'registrar';
            $sort_order_raw = trim((string)($_POST['sort_order'] ?? ''));
            $sort_order = null;
            if ($sort_order_raw !== '') {
                $sort_order = filter_var($sort_order_raw, FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 1, 'max_range' => 999999],
                ]);
                if ($sort_order === false) {
                    throw new Exception('Source order must be a positive number.');
                }
            }
            $DPC->addPricingSource($source_name, $source_type, $sort_order, $ip_address);
            $target_month = (int)($_POST['month_id'] ?? $selected_month_id);
            $redirect = "/domain-price-crosscheck.php" . ($target_month > 0 ? "?month_id={$target_month}" : "") . "#price-matrix";
            dpc_action_response(true, "Registrar source {$source_name} added.", $redirect);
        } elseif ($action === 'update_pricing_source' && tracs_user_can($conn, 'domain_price.manage')) {
            $source_id = (int)($_POST['source_id'] ?? 0);
            $source_type = 'registrar';
            $sort_order_raw = trim((string)($_POST['sort_order'] ?? ''));
            $sort_order = null;
            if ($sort_order_raw !== '') {
                $sort_order = filter_var($sort_order_raw, FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 1, 'max_range' => 999999],
                ]);
                if ($sort_order === false) {
                    throw new Exception('Source order must be a positive number.');
                }
            }
            $DPC->updatePricingSource($source_id, $source_type, $sort_order, $ip_address);
            $target_month = (int)($_POST['month_id'] ?? $selected_month_id);
            $redirect = "/domain-price-crosscheck.php" . ($target_month > 0 ? "?month_id={$target_month}" : "") . "#price-matrix";
            dpc_action_response(true, "Registrar source updated.", $redirect);
	        } elseif ($action === 'delete_pricing_source' && $is_super_admin) {
	            $source_id = (int)($_POST['source_id'] ?? 0);
	            $DPC->deletePricingSource($source_id, $ip_address);
            $target_month = (int)($_POST['month_id'] ?? $selected_month_id);
            $redirect = "/domain-price-crosscheck.php" . ($target_month > 0 ? "?month_id={$target_month}" : "") . "#price-matrix";
            dpc_action_response(true, "Registrar source disabled.", $redirect);
        } elseif ($action === 'revert_draft' && tracs_user_can($conn, 'domain_price.manage')) {
            $month_id = (int)($_POST['month_id'] ?? 0);
            if ($DPC->revertToDraft($month_id, $ip_address)) {
                $success_message = "Reverted monthly record back to Draft status.";
                dpc_action_response(true, $success_message, "/domain-price-crosscheck.php?month_id={$month_id}");
            } else {
                $error_message = "Failed to revert to Draft.";
            }
        } elseif ($action === 'duplicate_month' && tracs_user_can($conn, 'domain_price.manage')) {
            $from_month_id = (int)($_POST['from_month_id'] ?? 0);
            $month_code = trim($_POST['month_code'] ?? '');
            $exchange_rate = (float)($_POST['exchange_rate'] ?? 0.0);
            $copy_notes = !empty($_POST['copy_notes']);
            
            $new_id = $DPC->duplicatePreviousMonth($from_month_id, $month_code, $exchange_rate, $ip_address, $copy_notes);
            if ($new_id) {
                $success_message = "Successfully duplicated monthly record shell for {$month_code}!";
                dpc_action_response(true, $success_message, "/domain-price-crosscheck.php?month_id={$new_id}");
            } else {
                $error_message = "Failed to duplicate monthly record.";
            }
        } elseif ($action === 'approve_lock' && tracs_user_can($conn, 'domain_price.approve')) {
            $month_id = (int)($_POST['month_id'] ?? 0);
            $note = trim($_POST['approval_note'] ?? '');
            if ($DPC->approveAndLock($month_id, $note, $ip_address)) {
                $success_message = "Monthly snapshot approved and locked!";
                dpc_action_response(true, $success_message, "/domain-price-crosscheck.php?month_id={$month_id}");
            } else {
                $error_message = "Failed to approve record.";
            }
        } elseif ($action === 'unlock' && tracs_user_can($conn, 'domain_price.approve')) {
            $month_id = (int)($_POST['month_id'] ?? 0);
            $reason = trim($_POST['unlock_reason'] ?? '');
            if (empty($reason)) {
                $error_message = "An unlock reason is required.";
            } elseif ($DPC->unlockMonth($month_id, $reason, $ip_address)) {
                $success_message = "Monthly record unlocked for editing!";
                dpc_action_response(true, $success_message, "/domain-price-crosscheck.php?month_id={$month_id}");
            } else {
                $error_message = "Failed to unlock record.";
            }
        }
    } catch (Exception $e) {
        error_log('TRACS domain price page action failed: ' . $e->getMessage());
        $error_message = tracs_public_exception_message($e, 'The pricing action could not be completed.');
    }
    if (dpc_is_ajax_request()) {
        dpc_action_response(
            false,
            $error_message ?: 'You do not have permission to perform this action.',
            '/domain-price-crosscheck.php',
            $error_message ? 422 : 403
        );
    }
}

// Stats and metadata mapping for UI
$critical_count = 0; 
$page_title = 'Domain Price Crosscheck'; 
$active_page = 'domain_price_crosscheck';

// Load our custom scoped CSS and Javascript
$_dpc_css_v = @filemtime(__DIR__.'/assets/domain-price-crosscheck.css') ?: time();
$_dpc_js_v = @filemtime(__DIR__.'/assets/domain-price-crosscheck.js') ?: time();

include 'includes/header.php';
?>
<!-- Custom Page Assets -->
<script src="assets/domain-price-crosscheck.js?v=<?=$_dpc_js_v?>" defer></script>

<main class="main tracs-domain-price-crosscheck">
<div class="main-inner">

  <!-- TOPBAR -->
  <div class="topbar dpc-page-header">
    <div class="topbar-left">
      <div class="page-title">Domain Price Crosscheck</div>
      <div class="page-sub">Compare monthly domain registrar costs, selling website and PAAS prices</div>
    </div>
    
    <div class="topbar-right dpc-header-actions">
      <?php if ($month_data && tracs_user_can($conn, 'domain_price.manage')): ?>
      <button class="btn btn-secondary" onclick="openDuplicateMonthModal(<?=$month_data['id']?>, '<?=$month_data['month']?>', <?=$month_data['exchange_rate_usd_idr']?>)">
        <i data-lucide="copy" class="icon-sm"></i>
        Duplicate Month
      </button>
      <?php endif; ?>
      <?php if ($can_create_month): ?>
      <button class="btn btn-primary" onclick="openNewMonthModal()">
        <i data-lucide="plus-circle" class="icon-sm"></i>
        New Monthly Record
      </button>
      <?php endif; ?>
    </div>
  </div>

  <!-- Messages -->
  <?php if (!empty($error_message)): ?>
  <div class="tracs-alert alert-danger dpc-page-alert">
    <i data-lucide="alert-circle" class="icon-sm"></i>
    <span><?=esc($error_message)?></span>
  </div>
  <?php endif; ?>
  <?php if (!empty($success_message)): ?>
  <div class="tracs-alert alert-success dpc-page-alert">
    <i data-lucide="check-circle" class="icon-sm"></i>
    <span><?=esc($success_message)?></span>
  </div>
  <?php endif; ?>

  <!-- TOOLBAR ROW -->
  <div class="dpc-toolbar" data-unsaved-bar-before>
    <div class="dpc-toolbar-controls">
      <div class="month-selector-card">
        <form method="get" class="dpc-select-form">
          <label for="month_id_select">Monthly Record</label>
          <select name="month_id" id="month_id_select" class="form-select" data-tracs-native onchange="this.form.submit()">
            <?php if (empty($months)): ?>
              <option value="">No monthly records available</option>
            <?php else: ?>
              <?php foreach ($months as $m): ?>
                <option value="<?=$m['id']?>" <?=$selected_month_id === (int)$m['id'] ? 'selected' : ''?>>
                  <?=esc(dpc_month_label((string)$m['month']))?> (<?=esc(ucfirst(str_replace('_', ' ', (string)$m['status'])))?>)
                </option>
              <?php endforeach; ?>
            <?php endif; ?>
          </select>
        </form>
      </div>

    <?php if ($month_data): ?>
    <div class="month-status-pill-wrap">
      <span class="status-kicker">Status</span>
      <?php 
        $status_class = 'b-active';
        if ($month_data['status'] === 'approved') $status_class = 'b-done';
        if ($month_data['status'] === 'pending_review') $status_class = 'b-high';
      ?>
      <span class="badge <?=$status_class?>"><?=strtoupper($month_data['status'] === 'pending_review' ? 'IN PROGRESS' : $month_data['status'])?></span>
      
      <span class="status-kicker">Exchange Rate</span>
      <span class="rate-badge">1 USD = Rp<?=number_format($month_data['exchange_rate_usd_idr'], 2)?></span>

      <?php if ($month_data['status'] === 'draft' && tracs_user_can($conn, 'domain_price.manage')): ?>
        <button class="btn btn-ghost btn-sm" onclick="openUpdateRateModal(<?=$month_data['id']?>, <?=$month_data['exchange_rate_usd_idr']?>)">
          <i data-lucide="edit-3" class="icon-xs"></i> Edit Rate
        </button>
      <?php endif; ?>
    </div>
    <?php if (!$is_intern): ?>
    <div class="month-actions-group">
      <?php if ($month_data['status'] === 'draft' && tracs_user_can($conn, 'domain_price.manage')): ?>
        <form method="post" onsubmit="return tracsConfirmSubmit(this, {type:'warning', title:'Submit for review', message:'Submit this monthly record for review? This will lock basic editing.', confirmText:'Submit', destructive:false});">
          <?= csrf_input() ?>
          <input type="hidden" name="action" value="submit_review">
          <input type="hidden" name="month_id" value="<?=$month_data['id']?>">
          <button type="submit" class="btn btn-primary">
            <i data-lucide="send" class="icon-sm"></i>
            Submit for Review
          </button>
        </form>
      <?php elseif ($month_data['status'] === 'pending_review'): ?>
        <?php if (tracs_user_can($conn, 'domain_price.manage')): ?>
          <form method="post" onsubmit="return tracsConfirmSubmit(this, {type:'warning', title:'Revert to draft', message:'Revert this monthly record to Draft status?', confirmText:'Revert', destructive:false});">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="revert_draft">
            <input type="hidden" name="month_id" value="<?=$month_data['id']?>">
            <button type="submit" class="btn btn-warning">
              <i data-lucide="rotate-ccw" class="icon-sm"></i>
              Revert to Draft
            </button>
          </form>
        <?php endif; ?>
        <?php if (tracs_user_can($conn, 'domain_price.approve')): ?>
          <button class="btn btn-success" onclick="openApproveModal(<?=$month_data['id']?>)">
            <i data-lucide="check-square" class="icon-sm"></i>
            Approve & Lock
          </button>
        <?php endif; ?>
      <?php elseif ($month_data['status'] === 'approved' && tracs_user_can($conn, 'domain_price.approve')): ?>
        <button class="btn btn-danger" onclick="openUnlockModal(<?=$month_data['id']?>)">
          <i data-lucide="lock-open" class="icon-sm"></i>
          Unlock for Editing
        </button>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <details class="report-export-menu dpc-export-menu">
      <summary class="btn btn-ghost btn-icon report-export-trigger" title="More actions" aria-label="More actions" data-tooltip="More actions">
        <i data-lucide="more-vertical" class="icon-sm"></i>
      </summary>
      <div class="report-export-popover dpc-actions-popover" role="menu" aria-label="Domain price actions">
        <?php if (!$is_intern && tracs_user_can($conn, 'domain_price.manage')): ?>
          <button type="button" class="btn btn-ghost dpc-more-action-btn" role="menuitem" onclick="openRegistrarManagementModal(); this.closest('details')?.removeAttribute('open');">
            <i data-lucide="building-2" class="icon-sm"></i>
            Manage Registrars
          </button>
          <button type="button" class="btn btn-ghost dpc-more-action-btn" role="menuitem" onclick="openExtensionModal(); this.closest('details')?.removeAttribute('open');">
            <i data-lucide="sliders-horizontal" class="icon-sm"></i>
            Matrix Settings
          </button>
        <?php endif; ?>
        <button type="button" class="btn btn-ghost dpc-more-action-btn" role="menuitem" onclick="openExportModal(); this.closest('details')?.removeAttribute('open');">
          <i data-lucide="download" class="icon-sm"></i>
          Export CSV
        </button>
      </div>
    </details>
    <?php endif; ?>
    </div>
  </div>

  <?php if (!$month_data): ?>
    <!-- EMPTY STATE -->
    <div class="empty panel">
      <div class="empty-ic"><i data-lucide="trending-up"></i></div>
      <div class="empty-t">No monthly pricing records yet</div>
      <div class="empty-s">Create your first monthly record to start comparing registrar cost, IDCH Website Pricing, and PAAS Pricing.</div>
      <?php if ($can_create_month): ?>
      <button class="btn btn-primary dpc-empty-cta" onclick="openNewMonthModal()">
        <i data-lucide="plus-circle" class="icon-sm"></i>
        Create Monthly Record
      </button>
      <?php endif; ?>
    </div>
  <?php else: ?>

    <div class="dpc-module-shell" data-dpc-dashboard>
      <div class="dpc-section-tabs" role="tablist" aria-label="Domain price modules">
        <button type="button" class="active" data-dpc-tab="overview" role="tab" aria-selected="true">Overview</button>
        <button type="button" data-dpc-tab="price-matrix" role="tab" aria-selected="false">Price Matrix</button>
        <button type="button" data-dpc-tab="website-adjustment" role="tab" aria-selected="false">Website Price Adjustment</button>
        <button type="button" data-dpc-tab="intelligence-summary" role="tab" aria-selected="false">Intelligence Summary</button>
        <button type="button" data-dpc-tab="audit-trail" role="tab" aria-selected="false">Audit Trail</button>
        <button type="button" data-dpc-tab="notes-followups" role="tab" aria-selected="false">Notes &amp; Follow-ups</button>
      </div>

      <section class="dpc-module-panel dpc-overview is-active" id="overview" data-dpc-panel="overview" role="tabpanel">
        <div class="dpc-overview-top">
          <section class="panel dpc-record-overview">
            <div class="panel-head">
              <div>
                <span class="panel-title">
                  <i data-lucide="layout-dashboard" class="icon-sm"></i>
                  Domain Crosscheck Dashboard
                </span>
                <div class="panel-meta">Active month overview before deeper pricing work.</div>
              </div>
              <span class="rate-badge">1 USD = Rp<?=number_format((float)$month_data['exchange_rate_usd_idr'], 2)?></span>
            </div>
            <div class="dpc-record-summary">
              <div>
                <span>Current Month</span>
                <strong><?=esc(dpc_month_label((string)$month_data['month']))?></strong>
              </div>
              <div>
                <span>Record Status</span>
                <strong><span class="badge <?=$status_class?>"><?=strtoupper($month_data['status'] === 'pending_review' ? 'IN PROGRESS' : $month_data['status'])?></span></strong>
              </div>
              <div>
                <span>Last Updated</span>
                <strong><?=esc(date('d M Y H:i', strtotime($month_data['updated_at'])))?></strong>
              </div>
            </div>
          </section>

          <section class="panel dpc-quick-links">
            <div class="panel-head">
              <span class="panel-title">
                <i data-lucide="navigation" class="icon-sm"></i>
                Quick Links
              </span>
            </div>
            <div class="dpc-quick-link-grid">
              <?php
                $quickLinks = [
                  ['price-matrix', 'Price Matrix', 'Input and save monthly pricing', 'table'],
                  ['website-adjustment', 'Website Price Adjustment', 'Website and ccTLD price validation', 'badge-dollar-sign'],
                  ['intelligence-summary', 'Intelligence Summary', 'Margin and registrar analysis', 'brain-circuit'],
                  ['audit-trail', 'Audit Trail', 'Review recorded pricing actions', 'history'],
                  ['notes-followups', 'Notes & Follow-ups', 'Manual notes and status', 'message-square'],
                ];
              ?>
              <?php foreach ($quickLinks as $link): ?>
                <button type="button" class="dpc-quick-card" data-dpc-open-tab="<?=$link[0]?>">
                  <i data-lucide="<?=$link[3]?>" class="icon-sm"></i>
                  <span><?=esc($link[1])?></span>
                  <small><?=esc($link[2])?></small>
                </button>
              <?php endforeach; ?>
            </div>
          </section>
        </div>

        <div class="dpc-overview-stats" aria-label="Domain crosscheck summary stats">
          <?php foreach ($overview_stat_cards as $card): ?>
            <article class="dpc-overview-stat <?=$card['tone']?>">
              <span><?=esc($card['label'])?></span>
              <strong><?=esc((string)$card['value'])?></strong>
              <small><?=esc($card['detail'])?></small>
            </article>
          <?php endforeach; ?>
        </div>

        <div class="dpc-overview-grid">
          <section class="panel dpc-preview-panel">
            <div class="panel-head">
              <span class="panel-title">
                <i data-lucide="alert-triangle" class="icon-sm"></i>
                Priority Findings
              </span>
              <button type="button" class="btn btn-ghost btn-sm" data-dpc-open-tab="website-adjustment">Open</button>
            </div>
            <?php if (empty($priority_findings_preview)): ?>
              <div class="empty-small">No priority findings for this month.</div>
            <?php else: ?>
              <div class="dpc-preview-list">
                <?php foreach ($priority_findings_preview as $finding): ?>
                  <article class="dpc-preview-row">
                    <div>
                      <strong><?=esc(dpc_tld_label($finding['tld_name']))?> <?=esc($finding['type_label'])?></strong>
                      <span><?=esc($finding['suggested_action'])?></span>
                    </div>
                    <span class="dpc-severity dpc-severity-<?=strtolower($finding['severity'])?>"><?=esc($finding['status'])?></span>
                  </article>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </section>

          <section class="panel dpc-preview-panel">
            <div class="panel-head">
              <span class="panel-title">
                <i data-lucide="history" class="icon-sm"></i>
                Latest Audit Activity
              </span>
              <button type="button" class="btn btn-ghost btn-sm" data-dpc-open-tab="audit-trail">Open</button>
            </div>
            <?php if (empty($audit_preview)): ?>
              <div class="empty-small">No logged actions for this record yet.</div>
            <?php else: ?>
              <div class="dpc-audit-preview-list">
                <?php foreach ($audit_preview as $log): ?>
                  <div>
                    <strong><?=esc($log['actor_name'])?></strong>
                    <span><?=esc($log['action'])?> · <?=esc(date('d M H:i', strtotime($log['created_at'])))?></span>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </section>
        </div>
      </section>

      <section class="dpc-module-panel" id="price-matrix" data-dpc-panel="price-matrix" role="tabpanel" hidden>
    <div class="panel dpc-matrix-panel" id="gtld-pricing">
      <div class="panel-head">
        <span class="panel-title">
          <i data-lucide="table" class="icon-sm"></i>
          gTLD Pricing Matrix
        </span>
        <div class="panel-right dpc-matrix-actions">
          <?php if ($month_data['status'] !== 'approved'): ?>
            <button class="btn btn-secondary btn-sm dpc-action-btn" id="btnRecalculate" title="Updates margin, recommended price, and risk status using the latest saved pricing data.">
              <i data-lucide="calculator" class="icon-xs"></i> Recalculate Summary
            </button>
            <?php if (!$is_intern && tracs_user_can($conn, 'domain_price.manage')): ?>
              <button class="btn btn-primary btn-sm dpc-action-btn" id="btnSaveMatrix">
                <i data-lucide="save" class="icon-xs"></i> Save Metrics
              </button>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
      <div class="table-container dpc-matrix-scroll">
        <table class="table-dense dpc-matrix-table dpc-gtld-table" data-dpc-matrix-sort>
          <thead>
            <tr>
              <th class="sticky-col group-header-col">SOURCE</th>
              <th class="sticky-col type-col">Type</th>
              <?php foreach ($active_tlds as $tld): ?>
                <th class="tld-col" data-dpc-matrix-column="<?=$tld['id']?>" data-dpc-matrix-label="<?=esc(dpc_tld_label($tld['tld_name']))?>"><?=esc(dpc_tld_label($tld['tld_name']))?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <!-- Registrars (register + renewal rows) -->
            <?php foreach ($active_sources as $source): ?>
              <?php if (!dpc_is_internal_cost_source($source)): ?>
                <!-- Register Cost (USD input) -->
                <tr class="row-source-usd">
                  <td class="sticky-col group-header-col dpc-group-label" rowspan="4">
                    <strong><?=esc($source['source_name'])?></strong>
                  </td>
                  <td class="sticky-col type-col">Register (USD)</td>
                  <?php foreach ($active_tlds as $tld):
                    $val = $matrix_data[$tld['id']][$source['id']]['cost_register'] ?? null;
                    $usd = $val ? dpc_usd_input_value($val['usd_value']) : '';
                  ?>
                    <td data-dpc-matrix-column="<?=$tld['id']?>">
                      <div class="dpc-input-wrap usd-input dpc-registrar-price"
                           data-tld="<?=$tld['id']?>" data-source="<?=$source['id']?>" data-type="cost_register" data-currency="USD"
                           data-source-name="<?=esc($source['source_name'])?>" data-source-order="<?=esc((string)$source['sort_order'])?>">
                        <span class="currency-prefix">$</span>
                        <input type="number" step="0.01" class="form-input matrix-input"
                               data-tld="<?=$tld['id']?>" data-source="<?=$source['id']?>" data-type="cost_register" data-currency="USD"
                               data-source-name="<?=esc($source['source_name'])?>" data-source-order="<?=esc((string)$source['sort_order'])?>"
                               value="<?=esc($usd)?>" <?=$month_data['status'] === 'approved' ? 'disabled' : ''?>>
                      </div>
                    </td>
                  <?php endforeach; ?>
                </tr>
                <!-- Register Cost (IDR auto-calc) -->
                <tr class="row-source-idr">
                  <td class="sticky-col type-col auto-calc-col">
                    <span class="dpc-idr-label">Register (IDR)</span>
                  </td>
                  <?php foreach ($active_tlds as $tld):
                    $val = $matrix_data[$tld['id']][$source['id']]['cost_register'] ?? null;
                    $idr = $val ? number_format($val['idr_value'], 0, '', '') : '';
                  ?>
                    <td class="auto-calc cell-idr" data-dpc-matrix-column="<?=$tld['id']?>" data-live-idr data-tld="<?=$tld['id']?>" data-source="<?=$source['id']?>" data-type="cost_register">
                      <?= $idr ? 'Rp ' . number_format($idr, 0, ',', '.') : '<span class="dpc-empty-value">—</span>' ?>
                    </td>
                  <?php endforeach; ?>
                </tr>
                <!-- Renewal Cost (USD input) -->
                <tr class="row-source-usd">
                  <td class="sticky-col type-col">Renewal (USD)</td>
                  <?php foreach ($active_tlds as $tld):
                    $val = $matrix_data[$tld['id']][$source['id']]['cost_renewal'] ?? null;
                    $usd = $val ? dpc_usd_input_value($val['usd_value']) : '';
                  ?>
                    <td data-dpc-matrix-column="<?=$tld['id']?>">
                      <div class="dpc-input-wrap usd-input dpc-registrar-price"
                           data-tld="<?=$tld['id']?>" data-source="<?=$source['id']?>" data-type="cost_renewal" data-currency="USD"
                           data-source-name="<?=esc($source['source_name'])?>" data-source-order="<?=esc((string)$source['sort_order'])?>">
                        <span class="currency-prefix">$</span>
                        <input type="number" step="0.01" class="form-input matrix-input"
                               data-tld="<?=$tld['id']?>" data-source="<?=$source['id']?>" data-type="cost_renewal" data-currency="USD"
                               data-source-name="<?=esc($source['source_name'])?>" data-source-order="<?=esc((string)$source['sort_order'])?>"
                               value="<?=esc($usd)?>" <?=$month_data['status'] === 'approved' ? 'disabled' : ''?>>
                      </div>
                    </td>
                  <?php endforeach; ?>
                </tr>
                <!-- Renewal Cost (IDR auto-calc) -->
                <tr class="row-source-idr">
                  <td class="sticky-col type-col auto-calc-col">
                    <span class="dpc-idr-label">Renewal (IDR)</span>
                  </td>
                  <?php foreach ($active_tlds as $tld):
                    $val = $matrix_data[$tld['id']][$source['id']]['cost_renewal'] ?? null;
                    $idr = $val ? number_format($val['idr_value'], 0, '', '') : '';
                  ?>
                    <td class="auto-calc cell-idr" data-dpc-matrix-column="<?=$tld['id']?>" data-live-idr data-tld="<?=$tld['id']?>" data-source="<?=$source['id']?>" data-type="cost_renewal">
                      <?= $idr ? 'Rp ' . number_format($idr, 0, ',', '.') : '<span class="dpc-empty-value">—</span>' ?>
                    </td>
                  <?php endforeach; ?>
                </tr>
              <?php endif; ?>
            <?php endforeach; ?>

            <tr class="matrix-divider"><td colspan="<?=count($active_tlds) + 2?>"></td></tr>

            <!-- IDCH Internal cost rows -->
            <?php foreach ($active_sources as $source): ?>
              <?php if (dpc_is_internal_cost_source($source)): ?>
                <tr class="row-internal-idr">
                  <td class="sticky-col group-header-col dpc-group-label" rowspan="2">
                    <strong><?=esc($source['source_name'])?></strong>
                  </td>
                  <td class="sticky-col type-col">Register (IDR)</td>
                  <?php foreach ($active_tlds as $tld):
                    $val = $matrix_data[$tld['id']][$source['id']]['cost_register'] ?? null;
                    $idr = $val ? dpc_decimal_input_value($val['original_value'] ?? $val['idr_value']) : '';
                  ?>
                    <td data-dpc-matrix-column="<?=$tld['id']?>">
                      <div class="dpc-input-wrap idr-input dpc-internal-pricing-wrap">
                        <span class="currency-prefix">Rp</span>
                        <input type="text" inputmode="decimal" autocomplete="off" class="form-input matrix-input dpc-internal-pricing-input"
                               data-tld="<?=$tld['id']?>" data-source="<?=$source['id']?>" data-type="cost_register" data-currency="IDR"
                               data-internal-pricing data-saved-value="<?=esc($idr)?>"
                               value="<?=esc($idr)?>" <?=$month_data['status'] === 'approved' ? 'disabled' : ''?>>
                      </div>
                    </td>
                  <?php endforeach; ?>
                </tr>
                <tr class="row-internal-idr">
                  <td class="sticky-col type-col">Renewal (IDR)</td>
                  <?php foreach ($active_tlds as $tld):
                    $val = $matrix_data[$tld['id']][$source['id']]['cost_renewal'] ?? null;
                    $idr = $val ? dpc_decimal_input_value($val['original_value'] ?? $val['idr_value']) : '';
                  ?>
                    <td data-dpc-matrix-column="<?=$tld['id']?>">
                      <div class="dpc-input-wrap idr-input dpc-internal-pricing-wrap">
                        <span class="currency-prefix">Rp</span>
                        <input type="text" inputmode="decimal" autocomplete="off" class="form-input matrix-input dpc-internal-pricing-input"
                               data-tld="<?=$tld['id']?>" data-source="<?=$source['id']?>" data-type="cost_renewal" data-currency="IDR"
                               data-internal-pricing data-saved-value="<?=esc($idr)?>"
                               value="<?=esc($idr)?>" <?=$month_data['status'] === 'approved' ? 'disabled' : ''?>>
                      </div>
                    </td>
                  <?php endforeach; ?>
                </tr>
              <?php endif; ?>
            <?php endforeach; ?>

            <tr class="matrix-divider"><td colspan="<?=count($active_tlds) + 2?>"></td></tr>

            <!-- IDCH Website Pricing (register + renewal) -->
            <tr class="row-website-price">
              <td class="sticky-col group-header-col dpc-group-label" rowspan="2">
                <strong>IDCH Website Pricing</strong>
              </td>
              <td class="sticky-col type-col">Register (IDR)</td>
	              <?php foreach ($active_tlds as $tld):
	                $val = $matrix_special[$tld['id']]['selling_website_register'] ?? null;
	                $idr = $val ? dpc_decimal_input_value($val['original_value'] ?? $val['idr_value']) : '';
	              ?>
                <td data-dpc-matrix-column="<?=$tld['id']?>">
                  <div class="dpc-input-wrap idr-input selling-input dpc-website-price-wrap"
                       data-website-price-cell data-tld="<?=$tld['id']?>" data-type="cost_register">
                    <span class="currency-prefix">Rp</span>
	                    <input type="text" inputmode="decimal" autocomplete="off" class="form-input matrix-input"
                           data-tld="<?=$tld['id']?>" data-source="" data-type="selling_website_register" data-currency="IDR"
                           value="<?=esc($idr)?>" <?=$month_data['status'] === 'approved' ? 'disabled' : ''?>>
                  </div>
                </td>
              <?php endforeach; ?>
            </tr>
            <tr class="row-website-price">
              <td class="sticky-col type-col">Renewal (IDR)</td>
	              <?php foreach ($active_tlds as $tld):
	                $val = $matrix_special[$tld['id']]['selling_website_renewal'] ?? null;
	                $idr = $val ? dpc_decimal_input_value($val['original_value'] ?? $val['idr_value']) : '';
	              ?>
                <td data-dpc-matrix-column="<?=$tld['id']?>">
                  <div class="dpc-input-wrap idr-input selling-input dpc-website-price-wrap"
                       data-website-price-cell data-tld="<?=$tld['id']?>" data-type="cost_renewal">
                    <span class="currency-prefix">Rp</span>
	                    <input type="text" inputmode="decimal" autocomplete="off" class="form-input matrix-input"
                           data-tld="<?=$tld['id']?>" data-source="" data-type="selling_website_renewal" data-currency="IDR"
                           value="<?=esc($idr)?>" <?=$month_data['status'] === 'approved' ? 'disabled' : ''?>>
                  </div>
                </td>
              <?php endforeach; ?>
            </tr>
            <!-- PAAS Pricing (register + renewal) -->
            <tr class="row-paas-price">
              <td class="sticky-col group-header-col dpc-group-label" rowspan="2">
                <strong>PAAS Pricing</strong>
              </td>
              <td class="sticky-col type-col">Register (IDR)</td>
	              <?php foreach ($active_tlds as $tld):
	                $val = $matrix_special[$tld['id']]['selling_paas_register'] ?? null;
	                $idr = $val ? dpc_decimal_input_value($val['original_value'] ?? $val['idr_value']) : '';
	              ?>
                <td data-dpc-matrix-column="<?=$tld['id']?>">
                  <div class="dpc-input-wrap idr-input selling-input">
                    <span class="currency-prefix">Rp</span>
	                    <input type="text" inputmode="decimal" autocomplete="off" class="form-input matrix-input"
                           data-tld="<?=$tld['id']?>" data-source="" data-type="selling_paas_register" data-currency="IDR"
                           value="<?=esc($idr)?>" <?=$month_data['status'] === 'approved' ? 'disabled' : ''?>>
                  </div>
                </td>
              <?php endforeach; ?>
            </tr>
            <tr class="row-paas-price">
              <td class="sticky-col type-col">Renewal (IDR)</td>
	              <?php foreach ($active_tlds as $tld):
	                $val = $matrix_special[$tld['id']]['selling_paas_renewal'] ?? null;
	                $idr = $val ? dpc_decimal_input_value($val['original_value'] ?? $val['idr_value']) : '';
	              ?>
                <td data-dpc-matrix-column="<?=$tld['id']?>">
                  <div class="dpc-input-wrap idr-input selling-input">
                    <span class="currency-prefix">Rp</span>
	                    <input type="text" inputmode="decimal" autocomplete="off" class="form-input matrix-input"
                           data-tld="<?=$tld['id']?>" data-source="" data-type="selling_paas_renewal" data-currency="IDR"
                           value="<?=esc($idr)?>" <?=$month_data['status'] === 'approved' ? 'disabled' : ''?>>
                  </div>
                </td>
              <?php endforeach; ?>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <section class="panel dpc-matrix-panel dpc-cctld-panel" id="cctld-pricing">
      <div class="panel-head">
        <span class="panel-title">
          <i data-lucide="table-2" class="icon-sm"></i>
          ccTLD Pricing Matrix
        </span>
        <span class="panel-meta">PANDI Registry vs IDCH ccTLD · target margin <?=number_format(DPC_TARGET_MARGIN_RATE * 100, 0)?>%</span>
      </div>
      <div class="table-container dpc-cctld-scroll">
        <table class="table-dense dpc-matrix-table dpc-cctld-table" data-dpc-matrix-sort>
          <thead>
            <tr>
              <th class="sticky-col group-header-col">SOURCE</th>
              <th class="sticky-col type-col">Type</th>
              <?php foreach ($active_cctlds as $tld): ?>
                <th class="tld-col" data-dpc-matrix-column="<?=$tld['id']?>" data-dpc-matrix-label="<?=esc(dpc_tld_label($tld['tld_name']))?>"><?=esc(dpc_tld_label($tld['tld_name']))?></th>
              <?php endforeach; ?>
              <th class="dpc-notes-col">Notes</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($active_cctlds) || !$pandi_source || !$idch_cctld_source): ?>
              <tr><td colspan="<?=count($active_cctlds) + 3?>" class="empty-small">ccTLD pricing sources are not configured yet.</td></tr>
            <?php else: ?>
              <?php
                $cctldRows = [
                  ['source' => $pandi_source, 'label' => dpc_source_label($pandi_source['source_name']), 'note' => 'Registry cost baseline'],
                  ['source' => $idch_cctld_source, 'label' => dpc_source_label($idch_cctld_source['source_name']), 'note' => 'IDCH selling price check'],
                ];
                $cctldTypes = [
                  ['key' => 'cost_register', 'label' => 'Register'],
                  ['key' => 'cost_renewal', 'label' => 'Renewal'],
                  ['key' => 'cost_transfer', 'label' => 'Redemption'],
                ];
              ?>
              <?php foreach ($cctldRows as $groupIndex => $group): ?>
                <?php foreach ($cctldTypes as $typeIndex => $type): ?>
                  <tr class="<?=$groupIndex === 0 ? 'row-cctld-registry' : 'row-cctld-idch'?>">
                    <?php if ($typeIndex === 0): ?>
                      <td class="sticky-col group-header-col dpc-group-label" rowspan="3"><strong><?=esc($group['label'])?></strong></td>
                    <?php endif; ?>
                    <td class="sticky-col type-col"><?=esc($type['label'])?> (IDR)</td>
	                    <?php foreach ($active_cctlds as $tld):
	                      $val = $matrix_data[$tld['id']][$group['source']['id']][$type['key']] ?? null;
	                      $idr = $val ? dpc_decimal_input_value($val['original_value'] ?? $val['idr_value']) : '';
	                    ?>
                      <td data-dpc-matrix-column="<?=$tld['id']?>">
                        <div class="dpc-input-wrap idr-input dpc-cctld-price-wrap"
                             data-cctld-price-cell
                             data-cctld-role="<?=$groupIndex === 0 ? 'baseline' : 'current'?>"
                             data-tld="<?=$tld['id']?>"
                             data-type="<?=$type['key']?>">
                          <span class="currency-prefix">Rp</span>
	                          <input type="text" inputmode="decimal" autocomplete="off" class="form-input matrix-input"
                                 data-tld="<?=$tld['id']?>" data-source="<?=$group['source']['id']?>" data-type="<?=$type['key']?>" data-currency="IDR"
                                 value="<?=esc($idr)?>" <?=$month_data['status'] === 'approved' ? 'disabled' : ''?>>
                        </div>
                      </td>
                    <?php endforeach; ?>
                    <td class="dpc-row-note"><?=esc($typeIndex === 0 ? $group['note'] : 'No price changes')?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
      </section>

    <?php include __DIR__ . '/includes/domain-price-crosscheck/pricing-summary.php'; ?>

    <!-- TLD NOTES PANEL -->
    <section class="dpc-module-panel" id="notes-followups" data-dpc-panel="notes-followups" role="tabpanel" hidden>
    <div class="panel dpc-notes-panel" id="tld-notes">
      <div class="panel-head">
        <span class="panel-title">
          <i data-lucide="message-square" class="icon-sm"></i>
          TLD Notes & Follow-ups
        </span>
      </div>
      <div class="panel-body dpc-notes-body">
        <?php if ($month_data['status'] !== 'approved' && tracs_user_can($conn, 'domain_price.manage')): ?>
        <form id="tldNotesForm" class="dpc-notes-form">
          <input type="hidden" name="month_id" value="<?=$month_data['id']?>">
          <div class="form-group">
            <label for="note_tld_select">TLD</label>
            <select name="tld_id" id="note_tld_select" class="form-select dpc-note-tld-select" data-tracs-native required>
              <option value="">-- Select TLD --</option>
              <optgroup label="gTLD">
                <?php foreach ($active_tlds as $tld): ?>
                  <option value="<?=$tld['id']?>"><?=esc(dpc_tld_label($tld['tld_name']))?></option>
                <?php endforeach; ?>
              </optgroup>
              <optgroup label="ccTLD">
                <?php foreach ($active_cctlds as $tld): ?>
                  <option value="<?=$tld['id']?>"><?=esc(dpc_tld_label($tld['tld_name']))?></option>
                <?php endforeach; ?>
              </optgroup>
            </select>
          </div>
          <div class="form-group dpc-note-text-field">
            <label for="note_manual_input">Manual Note (Short)</label>
            <input type="text" name="manual_note" id="note_manual_input" class="form-input" placeholder="e.g. Discussing with registrar for discount">
          </div>
          <div class="form-group">
            <label for="note_status_select">Follow-up Status</label>
            <select name="follow_up_status" id="note_status_select" class="form-select" data-tracs-native>
              <option value="No Action">No Action</option>
              <option value="Need Review">Need Review</option>
              <option value="Waiting Finance">Waiting Finance</option>
              <option value="Waiting Approval">Waiting Approval</option>
              <option value="Updated">Updated</option>
            </select>
          </div>
          <button type="submit" class="btn btn-primary" id="btnSaveNote">Save Note</button>
        </form>
        <?php elseif ($month_data['status'] === 'approved'): ?>
        <div class="tracs-alert alert-info dpc-panel-alert">
          <i data-lucide="lock" class="icon-sm"></i>
          <span>This monthly record is locked. Notes cannot be edited.</span>
        </div>
        <?php endif; ?>

        <div class="dpc-notes-list">
          <?php if (empty($tld_notes)): ?>
            <div class="empty-small">No TLD notes recorded yet.</div>
          <?php else: ?>
            <table class="table-dense dpc-notes-table" data-dpc-sortable-table>
              <thead>
                <tr>
                  <th data-dpc-sort-type="text" data-dpc-sort-key="tld">TLD</th>
                  <th data-dpc-sort-type="text" data-dpc-sort-key="status">Follow-up Status</th>
                  <th data-dpc-sort-type="text" data-dpc-sort-key="note">Note</th>
                  <th data-dpc-sort-type="date" data-dpc-sort-key="updated">Updated By</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($tld_notes as $note): ?>
                  <tr>
                    <td><strong><?=esc(dpc_tld_label($note['tld_name']))?></strong></td>
                    <td>
                      <span class="badge <?=dpc_note_badge_class($note['follow_up_status'])?> <?=dpc_note_status_class($note['follow_up_status'])?>"><?=esc($note['follow_up_status'])?></span>
                    </td>
                    <td><?=esc($note['manual_note'])?></td>
                    <td class="dpc-note-updated" data-sort-value="<?=esc((string)strtotime($note['updated_at']))?>">
                      <?=esc($note['updater_name'])?><br><?=date('d M y H:i', strtotime($note['updated_at']))?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      </div>
    </div>
    </section>

    <section class="dpc-module-panel" id="audit-trail" data-dpc-panel="audit-trail" role="tabpanel" hidden>
    <div class="dpc-lower-layout" id="record-operations">
      <!-- AUDIT LOGS SECTION -->
      <section class="panel dpc-audit-panel">
        <div class="panel-head">
          <span class="panel-title">
            <i data-lucide="shield-check" class="icon-sm"></i>
            Operational Audit Trail
          </span>
          <span class="panel-meta"><?=count($audit_logs)?> actions</span>
        </div>
        
        <div class="dpc-audit-scroll">
          <?php if (empty($audit_logs)): ?>
            <div class="empty-small">No logged actions for this record yet.</div>
          <?php else: ?>
            <div class="audit-timeline">
              <?php foreach ($audit_logs as $log): ?>
                <div class="timeline-row">
                  <div class="timeline-dot"></div>
                  <div class="timeline-content">
                    <div class="timeline-meta">
                      <strong><?=esc($log['actor_name'])?></strong>
                      <span>· <?=esc($log['action'])?></span>
                      <time class="timeline-time"><?=esc(date('d M Y H:i', strtotime($log['created_at'])))?></time>
                    </div>
                    <div class="timeline-details"><?=esc($log['details'])?></div>
                    <div class="timeline-ip">IP: <?=esc($log['ip_address'])?></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </section>

      <!-- META DETAILS PANEL -->
      <section class="panel dpc-meta-panel">
        <div class="panel-head">
          <span class="panel-title">
            <i data-lucide="info" class="icon-sm"></i>
            Record Details
          </span>
        </div>
        
        <table class="dpc-meta-table">
          <tr>
            <th>Month / Year</th>
            <td><?=esc($month_data['month'])?> / <?=esc($month_data['year'])?></td>
          </tr>
          <tr>
            <th>Created By</th>
            <td><?=esc($month_data['creator_name'] ?: 'System')?></td>
          </tr>
          <tr>
            <th>Created At</th>
            <td><?=esc(date('d M Y H:i', strtotime($month_data['created_at'])))?></td>
          </tr>
          <tr>
            <th>Last Updated</th>
            <td><?=esc(date('d M Y H:i', strtotime($month_data['updated_at'])))?></td>
          </tr>
          <?php if ($month_data['updated_by']): ?>
            <tr>
              <th>Updated By</th>
              <td><?=esc($month_data['updater_name'] ?: 'System')?></td>
            </tr>
          <?php endif; ?>
          <?php if ($month_data['submitted_by']): ?>
            <tr>
              <th>Submitted By</th>
              <td><?=esc($month_data['submitter_name'])?></td>
            </tr>
            <tr>
              <th>Submitted At</th>
              <td><?=esc(date('d M Y H:i', strtotime($month_data['submitted_at'])))?></td>
            </tr>
          <?php endif; ?>
          <?php if ($month_data['approved_by']): ?>
            <tr>
              <th>Approved By</th>
              <td><?=esc($month_data['approver_name'])?></td>
            </tr>
            <tr>
              <th>Approved At</th>
              <td><?=esc(date('d M Y H:i', strtotime($month_data['approved_at'])))?></td>
            </tr>
            <?php if (!empty($month_data['approval_note'])): ?>
              <tr>
                <th>Approval Note</th>
                <td><p class="approval-note-para"><?=esc($month_data['approval_note'])?></p></td>
              </tr>
            <?php endif; ?>
          <?php endif; ?>
          <?php if ($month_data['unlocked_by']): ?>
            <tr>
              <th>Last Unlocked By</th>
              <td><?=esc($month_data['unlocked_by'])?></td>
            </tr>
            <tr>
              <th>Unlock Reason</th>
              <td><p class="approval-note-para"><?=esc($month_data['unlock_reason'])?></p></td>
            </tr>
          <?php endif; ?>
        </table>

        <div class="panel-head dpc-task-head">
          <span class="panel-title">
            <i data-lucide="clipboard-list" class="icon-sm"></i>
            Task Assignment
          </span>
        </div>
        <?php if ($assigned_task): ?>
          <table class="dpc-meta-table">
            <tr>
              <th>Assigned To</th>
              <td><?=esc($assigned_task['assigned_name'])?></td>
            </tr>
            <tr>
              <th>Due Date</th>
              <td><?=esc(date('d M Y', strtotime($assigned_task['due_date'])))?></td>
            </tr>
            <tr>
              <th>Status</th>
              <td>
                <?php if ($assigned_task['is_completed']): ?>
                  <span class="badge b-success">Completed</span>
                <?php else: ?>
                  <span class="badge b-warning">In Progress</span>
                <?php endif; ?>
              </td>
            </tr>
          </table>
        <?php else: ?>
          <div class="empty-small dpc-task-empty">No task assigned for this month.</div>
          <?php if (!$is_intern && tracs_user_can($conn, 'domain_price.manage')): ?>
            <button class="btn btn-outline dpc-full-width-btn" onclick="openAssignTaskModal(<?=$month_data['id']?>)">
              <i data-lucide="user-plus" class="icon-sm"></i> Assign Task
            </button>
          <?php endif; ?>
        <?php endif; ?>

      </section>
    </div>
    </section>
    </div>

  <?php endif; ?>

</div>
</main>

<script>
window.DPC_MONTH_RECORDS = <?=json_encode(array_map(static function ($m) {
  return [
    'id' => (int)$m['id'],
    'month' => (string)$m['month'],
    'label' => dpc_month_label((string)$m['month']),
    'exchange_rate' => (float)$m['exchange_rate_usd_idr'],
  ];
}, $months), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)?>;
window.DPC_CREATE_DEFAULTS = <?=json_encode([
  'month' => (int)$suggested_period->format('n'),
  'year' => $suggested_year,
  'month_code' => $suggested_period->format('Y-m'),
  'label' => $suggested_period->format('F Y'),
  'exchange_rate' => $latest_exchange_rate,
], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)?>;
window.DPC_CURRENT_EXCHANGE_RATE = <?=json_encode($month_data ? (float)$month_data['exchange_rate_usd_idr'] : null)?>;
window.DPC_SAVED_EXCHANGE_RATE = window.DPC_CURRENT_EXCHANGE_RATE;
window.DPC_TARGET_MARGIN_MULTIPLIER = <?=json_encode(1 + DPC_TARGET_MARGIN_RATE)?>;
window.DPC_MATRIX_EDITABLE = <?=json_encode((bool)($month_data && $month_data['status'] === 'draft'))?>;
window.DPC_TLD_NOTES = <?=json_encode(array_reduce($tld_notes, static function ($carry, $note) {
  $carry[(string)$note['tld_id']] = [
    'manual_note' => (string)($note['manual_note'] ?? ''),
    'follow_up_status' => (string)($note['follow_up_status'] ?? 'No Action'),
  ];
  return $carry;
}, []), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)?>;
</script>

<!-- MODALS -->

<!-- 1. Create New Monthly Record Modal -->
<div id="newMonthModal" class="dpc-modal">
  <div class="dpc-modal-content dpc-new-month-modal-content">
    <div class="dpc-modal-header">
      <h3>Create Monthly Record</h3>
      <button class="modal-close-btn" onclick="closeNewMonthModal()"><i data-lucide="x" class="icon-sm"></i></button>
    </div>
    <form method="post" class="dpc-modal-form" id="newMonthForm">
      <?= csrf_input() ?>
      <input type="hidden" name="action" value="create_month">
      <input type="hidden" name="month_code" id="month_code_input" value="<?=esc($suggested_period->format('Y-m'))?>">
      
      <div class="dpc-modal-grid">
        <div class="form-group">
          <label for="period_month_select">Month</label>
          <select name="period_month" id="period_month_select" class="form-select" data-tracs-native required>
            <?php for ($m = 1; $m <= 12; $m++): ?>
              <option value="<?=$m?>" <?=$m === (int)$suggested_period->format('n') ? 'selected' : ''?>><?=esc(dpc_month_name($m))?></option>
            <?php endfor; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="period_year_select">Year</label>
          <select name="period_year" id="period_year_select" class="form-select" data-tracs-native required>
            <?php foreach ($year_options as $year): ?>
              <option value="<?=$year?>" <?=$year === $suggested_year ? 'selected' : ''?>><?=$year?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label for="exchange_rate_input">KURS USD to IDR</label>
        <input type="text" inputmode="decimal" name="exchange_rate" id="exchange_rate_input" placeholder="Example: 17500" class="form-input" required data-raw-value="<?=esc($latest_exchange_rate ? (string)$latest_exchange_rate : '')?>" value="<?=esc($latest_exchange_rate ? (string)(int)$latest_exchange_rate : '')?>">
        <small>Used for USD registrar cost previews and saved matrix calculations.</small>
      </div>

      <div class="dpc-option-card <?=$latest_month ? '' : 'is-disabled'?>">
        <label class="dpc-check-row">
          <input type="checkbox" name="use_previous_template" id="use_previous_template_input" value="1" <?=$latest_month ? 'checked' : 'disabled'?>>
          <span>
            <strong>Start from previous month pricing</strong>
            <small>Copies registrar and selling prices into an editable draft. Review KURS before saving.</small>
          </span>
        </label>
        <label class="dpc-check-row dpc-copy-notes-row">
          <input type="checkbox" name="copy_template_notes" id="copy_template_notes_input" value="1" <?=$latest_month ? '' : 'disabled'?>>
          <span>
            <strong>Also copy notes</strong>
            <small>Optional. Follow-up notes are copied only when selected.</small>
          </span>
        </label>
        <div class="dpc-template-hint">
          <?=$latest_month ? 'Template source · ' . esc($latest_month_label) : 'No previous monthly record is available yet.'?>
        </div>
      </div>

      <div class="dpc-period-preview" aria-live="polite">
        <div>
          <span>Selected Period</span>
          <strong id="selected_period_preview"><?=esc($suggested_period->format('F Y'))?></strong>
        </div>
        <div>
          <span>Month Code</span>
          <strong id="month_code_preview"><?=esc($suggested_period->format('Y-m'))?></strong>
        </div>
      </div>
      <div class="dpc-duplicate-warning" id="new_month_duplicate_warning" hidden></div>

      <div class="dpc-modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeNewMonthModal()">Cancel</button>
        <button type="submit" class="btn btn-primary" id="btnCreateMonthDraft">Create Draft</button>
      </div>
    </form>
  </div>
</div>

<!-- Assign Task Modal -->
<div id="assignTaskModal" class="dpc-modal">
  <div class="dpc-modal-content">
    <div class="dpc-modal-header">
      <h3>Assign Task</h3>
      <button class="modal-close-btn" onclick="closeAssignTaskModal()"><i data-lucide="x" class="icon-sm"></i></button>
    </div>
    <form id="assignTaskForm" class="dpc-modal-form">
      <input type="hidden" name="month_id" id="assign_task_month_id">
      
      <div class="form-group">
        <label>Assign To (User ID)</label>
        <input type="number" name="assigned_to" class="form-input" required placeholder="User ID (e.g. 1)">
      </div>

      <div class="form-group">
        <label>Due Date</label>
        <input type="date" name="due_date" class="form-input" required>
      </div>

      <div class="form-group">
        <label>Priority</label>
        <select name="priority" class="form-select" data-tracs-native>
          <option value="low">Low</option>
          <option value="medium" selected>Medium</option>
          <option value="high">High</option>
          <option value="critical">Critical</option>
        </select>
      </div>

      <div class="dpc-modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeAssignTaskModal()">Cancel</button>
        <button type="submit" class="btn btn-primary" id="btnAssignTaskSave">Assign Task</button>
      </div>
    </form>
  </div>
</div>

<?php if ($month_data): ?>
<!-- Export CSV Modal -->
<div id="exportCsvModal" class="dpc-modal">
  <div class="dpc-modal-content dpc-export-modal-content">
    <div class="dpc-modal-header">
      <h3>Export CSV</h3>
      <button class="modal-close-btn" onclick="closeExportModal()"><i data-lucide="x" class="icon-sm"></i></button>
    </div>
    <form method="get" action="/api/export-domain-price-crosscheck.php" class="dpc-modal-form dpc-export-form" id="dpcMonthlyExportForm">
      <div class="dpc-export-mode" role="radiogroup" aria-label="Export range">
        <label class="dpc-export-option">
          <input type="radio" name="export_scope" value="single" checked>
          <span>Single month</span>
        </label>
        <label class="dpc-export-option">
          <input type="radio" name="export_scope" value="range">
          <span>Month range</span>
        </label>
      </div>
      <label data-dpc-export-single>Month<input type="month" name="month" class="form-input" value="<?=esc((string)$month_data['month'])?>"></label>
      <div class="dpc-export-range" data-dpc-export-range hidden>
        <label>From Month<input type="month" name="from_month" class="form-input" value="<?=esc((string)$month_data['month'])?>" disabled></label>
        <label>To Month<input type="month" name="to_month" class="form-input" value="<?=esc((string)$month_data['month'])?>" disabled></label>
      </div>
      <div class="dpc-export-validation" data-dpc-export-validation role="alert" hidden></div>
      <div class="dpc-modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeExportModal()">Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-lucide="download" class="icon-sm"></i>Download CSV</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- Price Matrix Settings Modal -->
<div id="extensionModal" class="dpc-modal">
  <div class="dpc-modal-content dpc-extension-modal-content dpc-matrix-settings-modal">
    <div class="dpc-modal-header">
      <h3>Manage Price Matrix</h3>
      <button class="modal-close-btn" onclick="closeExtensionModal()"><i data-lucide="x" class="icon-sm"></i></button>
    </div>
    <div class="dpc-modal-form dpc-extension-modal-body dpc-matrix-settings-body">
      <?php if ($month_data && !$is_intern && tracs_user_can($conn, 'domain_price.manage')): ?>
      <section class="dpc-matrix-config-card dpc-source-config-card">
        <div class="dpc-config-card-head">
          <div>
            <strong>Registrar Sources</strong>
            <span>Active registrar sources become USD input rows in the Price Matrix.</span>
          </div>
          <span class="dpc-extension-count"><?=count($registrar_sources)?> active</span>
        </div>
	        <div class="dpc-config-card-help">Liquid and Webnic stay as the default active registrars. IDCH Internal Pricing uses the lowest active registrar price; Matrix Order breaks ties. Disabling another source only hides it from active input rows and keeps historical records intact.</div>
        <form method="post" class="dpc-source-form" data-source-form>
          <?= csrf_input() ?>
          <input type="hidden" name="action" value="add_pricing_source">
          <input type="hidden" name="month_id" value="<?= (int)$month_data['id'] ?>">
          <div class="form-group">
            <label for="dpcSourceName">Source Name</label>
            <input id="dpcSourceName" type="text" name="source_name" class="form-input" placeholder="Example Registrar" maxlength="100" autocomplete="off" required>
          </div>
          <div class="form-group">
            <label for="dpcSourceSort">Matrix Order</label>
            <input id="dpcSourceSort" type="number" name="sort_order" class="form-input" min="1" max="999999" step="1" inputmode="numeric" placeholder="Optional">
          </div>
          <button type="submit" class="btn btn-primary">
            <i data-lucide="plus-circle" class="icon-sm"></i>
            Add Registrar
          </button>
        </form>
        <div class="dpc-source-row-head" aria-hidden="true">
          <span>Source</span>
          <span>Type</span>
          <span>Matrix Order</span>
          <span>Actions</span>
        </div>
        <div class="dpc-source-rows">
          <?php if (empty($registrar_sources)): ?>
            <div class="empty-small">No active registrar sources.</div>
          <?php endif; ?>
          <?php foreach ($registrar_sources as $source): ?>
            <form method="post" class="dpc-source-edit-row" data-source-edit-form>
              <?= csrf_input() ?>
              <input type="hidden" name="month_id" value="<?= (int)($month_data['id'] ?? 0) ?>">
              <input type="hidden" name="source_id" value="<?= (int)$source['id'] ?>">
              <span class="dpc-source-name" data-source-name="<?=esc(strtolower((string)$source['source_name']))?>"><?=esc($source['source_name'])?></span>
              <span class="dpc-source-type-badge">Registrar</span>
              <input type="number" name="sort_order" class="form-input" min="1" max="999999" step="1" value="<?=esc((string)$source['sort_order'])?>" aria-label="Matrix order for <?=esc($source['source_name'])?>" title="Matrix Order: lower numbers appear first" data-source-order-input data-source-id="<?= (int)$source['id'] ?>">
              <div class="dpc-source-row-actions">
                <button type="submit" name="action" value="update_pricing_source" class="btn btn-ghost btn-sm" title="Save source"><i data-lucide="save" class="icon-xs"></i></button>
                <?php if ($is_super_admin && !in_array((string)$source['source_name'], ['Liquid Registrar', 'Webnic Registrar'], true)): ?>
                  <button type="submit" name="action" value="delete_pricing_source" class="btn btn-danger btn-sm" data-delete-source="<?=esc($source['source_name'])?>" title="Disable source"><i data-lucide="trash-2" class="icon-xs"></i></button>
                <?php elseif (in_array((string)$source['source_name'], ['Liquid Registrar', 'Webnic Registrar'], true)): ?>
                  <span class="dpc-default-source-badge">Default</span>
                <?php endif; ?>
              </div>
            </form>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="dpc-matrix-config-card">
        <div class="dpc-config-card-head">
          <div>
            <strong>Domain Extensions</strong>
            <span>Add gTLD or ccTLD columns to the matrix.</span>
          </div>
          <span class="dpc-extension-count"><?=count($active_tlds) + count($active_cctlds)?> active</span>
        </div>
        <form method="post" class="dpc-extension-form dpc-extension-create-card" data-extension-form>
          <?= csrf_input() ?>
          <input type="hidden" name="action" value="add_tld_extension">
          <input type="hidden" name="month_id" value="<?= (int)$month_data['id'] ?>">
          <div class="dpc-extension-create-head">
            <strong>Add Extension</strong>
            <span>Choose where the extension should appear after saving.</span>
          </div>
          <div class="form-group">
            <label for="dpcExtensionName">Extension</label>
            <input id="dpcExtensionName" type="text" name="tld_name" class="form-input" placeholder=".APP or .WEB.ID" maxlength="30" pattern="^\.?[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)*$" autocomplete="off" required>
          </div>
          <div class="form-group dpc-extension-category-field">
            <label for="dpcExtensionCategory">Category</label>
            <select id="dpcExtensionCategory" name="tld_category" class="form-select" data-tracs-native required>
              <option value="gtld">gTLD</option>
              <option value="cctld">ccTLD</option>
            </select>
          </div>
          <div class="form-group">
            <label for="dpcExtensionSort">Sort Order</label>
            <input id="dpcExtensionSort" type="number" name="sort_order" class="form-input" min="1" max="999999" step="1" inputmode="numeric" placeholder="Optional">
          </div>
          <button type="submit" class="btn btn-primary">
            <i data-lucide="plus-circle" class="icon-sm"></i>
            Add Extension
          </button>
        </form>
      </section>
      <?php endif; ?>
      <div class="dpc-extension-lists">
        <div class="dpc-extension-list-card">
          <div class="dpc-extension-list-head">
            <strong>gTLD</strong>
            <span class="dpc-extension-count"><?=count($active_tlds)?> active</span>
          </div>
          <div class="dpc-extension-list-help">Display Order controls the matrix position. Lower numbers appear first.</div>
          <div class="dpc-extension-row-head" aria-hidden="true">
            <span>Extension</span>
            <span>Category</span>
            <span>Display Order</span>
            <span>Actions</span>
          </div>
          <div class="dpc-extension-rows">
            <?php foreach ($active_tlds as $tld): ?>
              <form method="post" class="dpc-extension-edit-row" data-extension-edit-form>
                <?= csrf_input() ?>
                <input type="hidden" name="month_id" value="<?= (int)($month_data['id'] ?? 0) ?>">
                <input type="hidden" name="tld_id" value="<?= (int)$tld['id'] ?>">
                <input type="hidden" name="tld_category" value="gtld">
                <span class="dpc-extension-name" data-extension-name="<?=esc(strtolower((string)$tld['tld_name']))?>"><?=esc(dpc_tld_label($tld['tld_name']))?></span>
                <span class="dpc-extension-category-badge">gTLD</span>
                <input type="number" name="sort_order" class="form-input" min="1" max="999999" step="1" value="<?=esc((string)$tld['sort_order'])?>" aria-label="Display order for <?=esc(dpc_tld_label($tld['tld_name']))?>" title="Display Order: lower numbers appear first" <?=$month_data && !$is_intern && tracs_user_can($conn, 'domain_price.manage') ? '' : 'disabled'?>>
                <?php if ($month_data && !$is_intern && tracs_user_can($conn, 'domain_price.manage')): ?>
                  <div class="dpc-extension-row-actions">
                    <button type="submit" name="action" value="update_tld_extension" class="btn btn-ghost btn-sm" title="Save extension"><i data-lucide="save" class="icon-xs"></i></button>
                    <?php if ($is_super_admin): ?>
                      <button type="submit" name="action" value="delete_tld_extension" class="btn btn-danger btn-sm" data-delete-extension="<?=esc(dpc_tld_label($tld['tld_name']))?>" title="Delete extension"><i data-lucide="trash-2" class="icon-xs"></i></button>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              </form>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="dpc-extension-list-card">
          <div class="dpc-extension-list-head">
            <strong>ccTLD</strong>
            <span class="dpc-extension-count"><?=count($active_cctlds)?> active</span>
          </div>
          <div class="dpc-extension-list-help">Display Order controls the matrix position. Lower numbers appear first.</div>
          <div class="dpc-extension-row-head" aria-hidden="true">
            <span>Extension</span>
            <span>Category</span>
            <span>Display Order</span>
            <span>Actions</span>
          </div>
          <div class="dpc-extension-rows">
            <?php foreach ($active_cctlds as $tld): ?>
              <form method="post" class="dpc-extension-edit-row" data-extension-edit-form>
                <?= csrf_input() ?>
                <input type="hidden" name="month_id" value="<?= (int)($month_data['id'] ?? 0) ?>">
                <input type="hidden" name="tld_id" value="<?= (int)$tld['id'] ?>">
                <input type="hidden" name="tld_category" value="cctld">
                <span class="dpc-extension-name" data-extension-name="<?=esc(strtolower((string)$tld['tld_name']))?>"><?=esc(dpc_tld_label($tld['tld_name']))?></span>
                <span class="dpc-extension-category-badge">ccTLD</span>
                <input type="number" name="sort_order" class="form-input" min="1" max="999999" step="1" value="<?=esc((string)$tld['sort_order'])?>" aria-label="Display order for <?=esc(dpc_tld_label($tld['tld_name']))?>" title="Display Order: lower numbers appear first" <?=$month_data && !$is_intern && tracs_user_can($conn, 'domain_price.manage') ? '' : 'disabled'?>>
                <?php if ($month_data && !$is_intern && tracs_user_can($conn, 'domain_price.manage')): ?>
                  <div class="dpc-extension-row-actions">
                    <button type="submit" name="action" value="update_tld_extension" class="btn btn-ghost btn-sm" title="Save extension"><i data-lucide="save" class="icon-xs"></i></button>
                    <?php if ($is_super_admin): ?>
                      <button type="submit" name="action" value="delete_tld_extension" class="btn btn-danger btn-sm" data-delete-extension="<?=esc(dpc_tld_label($tld['tld_name']))?>" title="Delete extension"><i data-lucide="trash-2" class="icon-xs"></i></button>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              </form>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
    <div class="dpc-modal-footer dpc-extension-modal-footer">
      <button type="button" class="btn btn-ghost" onclick="closeExtensionModal()">Close</button>
    </div>
  </div>
</div>

<!-- 2. Update Exchange Rate Modal -->
<div id="updateRateModal" class="dpc-modal">
  <div class="dpc-modal-content">
    <div class="dpc-modal-header">
      <h3>Update USD to IDR Exchange Rate</h3>
      <button class="modal-close-btn" onclick="closeUpdateRateModal()"><i data-lucide="x" class="icon-sm"></i></button>
    </div>
    <form method="post" class="dpc-modal-form">
      <?= csrf_input() ?>
      <input type="hidden" name="action" value="update_rate">
      <input type="hidden" name="month_id" id="update_rate_month_id">
      
      <div class="form-group">
        <label for="update_rate_input">Exchange Rate (USD to IDR)</label>
        <input type="number" step="0.01" name="exchange_rate" id="update_rate_input" class="form-input" required min="1">
      </div>

      <div class="form-group">
        <label for="update_rate_reason_input">Reason for Change (Required)</label>
        <textarea name="change_reason" id="update_rate_reason_input" placeholder="e.g. Updating to current market transaction rate." class="form-input" rows="2" required></textarea>
      </div>

      <div class="dpc-modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeUpdateRateModal()">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- 3. Approve and Lock Modal -->
<div id="approveModal" class="dpc-modal">
  <div class="dpc-modal-content">
    <div class="dpc-modal-header">
      <h3>Approve & Lock Monthly Snapshot</h3>
      <button class="modal-close-btn" onclick="closeApproveModal()"><i data-lucide="x" class="icon-sm"></i></button>
    </div>
    <form method="post" class="dpc-modal-form">
      <?= csrf_input() ?>
      <input type="hidden" name="action" value="approve_lock">
      <input type="hidden" name="month_id" id="approve_month_id">
      
      <div class="form-group">
        <label for="approval_note_input">Approval Review Note (Optional)</label>
        <textarea name="approval_note" id="approval_note_input" placeholder="e.g. All pricing verified against registrar dashboards." class="form-input" rows="3"></textarea>
      </div>

      <div class="dpc-modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeApproveModal()">Cancel</button>
        <button type="submit" class="btn btn-success">Approve & Lock</button>
      </div>
    </form>
  </div>
</div>

<!-- 4. Unlock Modal -->
<div id="unlockModal" class="dpc-modal">
  <div class="dpc-modal-content">
    <div class="dpc-modal-header text-danger">
      <h3>Unlock approved Monthly Snapshot</h3>
      <button class="modal-close-btn" onclick="closeUnlockModal()"><i data-lucide="x" class="icon-sm"></i></button>
    </div>
    <form method="post" class="dpc-modal-form">
      <?= csrf_input() ?>
      <input type="hidden" name="action" value="unlock">
      <input type="hidden" name="month_id" id="unlock_month_id">
      
      <div class="form-group">
        <label for="unlock_reason_input" style="color: var(--red);">Reason for Unlocking (Required)</label>
        <textarea name="unlock_reason" id="unlock_reason_input" placeholder="e.g. Pricing correction needed for Liquid Registrar .com renewal." class="form-input" rows="3" required></textarea>
        <small style="color: var(--tx3);">Unlocking this month changes status back to draft, allowing data edits.</small>
      </div>

      <div class="dpc-modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeUnlockModal()">Cancel</button>
        <button type="submit" class="btn btn-danger">Confirm Unlock</button>
      </div>
    </form>
  </div>
</div>

<!-- 5. Duplicate Month Modal -->
<div id="duplicateMonthModal" class="dpc-modal">
  <div class="dpc-modal-content dpc-duplicate-month-modal-content">
    <div class="dpc-modal-header">
      <h3>Duplicate Monthly Record</h3>
      <button class="modal-close-btn" onclick="closeDuplicateMonthModal()"><i data-lucide="x" class="icon-sm"></i></button>
    </div>
    <form method="post" class="dpc-modal-form">
      <?= csrf_input() ?>
      <input type="hidden" name="action" value="duplicate_month">
      <input type="hidden" name="from_month_id" id="duplicate_from_month_id">
      
      <div class="form-group">
        <label for="duplicate_month_code_input">New Record Month</label>
        <input type="text" name="month_code" id="duplicate_month_code_input" placeholder="e.g. <?=date('Y-m')?>" class="form-input" required pattern="^\d{4}-\d{2}$">
        <small>Use YYYY-MM, for example 2026-06.</small>
      </div>

      <div class="form-group">
        <label for="duplicate_exchange_rate_input">KURS USD to IDR</label>
        <input type="number" step="0.01" name="exchange_rate" id="duplicate_exchange_rate_input" placeholder="e.g. 16250.00" class="form-input" required min="1">
        <small>Suggested from the previous month. Review before creating the draft.</small>
      </div>

      <div class="dpc-option-card">
        <label class="dpc-check-row">
          <input type="checkbox" checked disabled>
          <span>
            <strong>Copy pricing into editable draft</strong>
            <small>Registrar and selling prices are copied. Approval status, audit logs, and task state stay with the source month.</small>
          </span>
        </label>
        <label class="dpc-check-row dpc-copy-notes-row">
          <input type="checkbox" name="copy_notes" value="1">
          <span>
            <strong>Also copy notes</strong>
            <small>Optional. Copies manual follow-up notes to the new draft.</small>
          </span>
        </label>
      </div>

      <div class="dpc-modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeDuplicateMonthModal()">Cancel</button>
        <button type="submit" class="btn btn-primary">Duplicate Month</button>
      </div>
    </form>
  </div>
</div>

<?php include 'includes/footer.php';?>
