<?php
/**
 * TRACS — Domain Price Crosscheck Module
 * Web interface shell with basic role-based access, month configuration, and placeholder UI sections.
 */

require_once __DIR__ . '/../core/security/csrf.php';
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

function dpc_clean_rate($value): float {
    $clean = preg_replace('/[^\d.]/', '', (string)$value);
    return (float)$clean;
}

function dpc_is_allowed_cost_source(array $source): bool {
    return in_array((string)($source['source_name'] ?? ''), DPC_ALLOWED_COST_SOURCES, true);
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
    return $value === null ? '—' : number_format($value, 2) . '%';
}

function dpc_round_price(float $value): float {
    return ceil($value / DPC_ROUNDING_INCREMENT) * DPC_ROUNDING_INCREMENT;
}

function dpc_pick_entry(array $matrixData, int $tldId, int $sourceId, string $priceType): ?array {
    return $matrixData[$tldId][$sourceId][$priceType] ?? null;
}

function dpc_status_key(string $status): string {
    return strtolower(str_replace(' ', '-', $status));
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
    array $matrixData
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
        'idch_price' => $idchPrice,
        'recommended_price' => $recommended,
        'suggested_rounded_price' => $recommended !== null ? dpc_round_price($recommended) : null,
        'margin_amount' => $marginAmount,
        'margin_percent' => $marginPercent,
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

    $pricing_intelligence = [
        'checks' => $checks,
        'counts' => $counts,
        'estimated_margin_risk' => $estimated_margin_risk,
        'source_summary' => $source_summary,
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
            $cctld_checks[] = dpc_build_cctld_check($tld, $kind, $pandi_source, $idch_cctld_source, $matrix_data);
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


// Handle Form Submissions (Draft creation / status changes)
$error_message = '';
$success_message = '';

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
                header("Location: domain_price_crosscheck.php?month_id=" . $new_id);
                exit();
            } else {
                $error_message = "Failed to create monthly record.";
            }
        } elseif ($action === 'update_rate' && tracs_user_can($conn, 'domain_price.manage')) {
            $month_id = (int)($_POST['month_id'] ?? 0);
            $exchange_rate = (float)($_POST['exchange_rate'] ?? 0.0);
            $change_reason = trim($_POST['change_reason'] ?? '');
            
            if ($DPC->updateExchangeRate($month_id, $exchange_rate, $ip_address, $change_reason)) {
                $success_message = "Exchange rate updated successfully.";
                header("Location: domain_price_crosscheck.php?month_id=" . $month_id);
                exit();
            } else {
                $error_message = "Failed to update exchange rate (must be in draft status).";
            }
        } elseif ($action === 'submit_review' && tracs_user_can($conn, 'domain_price.manage')) {
            $month_id = (int)($_POST['month_id'] ?? 0);
            if ($DPC->submitForReview($month_id, $ip_address)) {
                $success_message = "Submitted monthly record for review!";
                header("Location: domain_price_crosscheck.php?month_id=" . $month_id);
                exit();
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
            header("Location: domain_price_crosscheck.php" . ($target_month > 0 ? "?month_id={$target_month}" : ""));
            exit();
        } elseif ($action === 'revert_draft' && tracs_user_can($conn, 'domain_price.manage')) {
            $month_id = (int)($_POST['month_id'] ?? 0);
            if ($DPC->revertToDraft($month_id, $ip_address)) {
                $success_message = "Reverted monthly record back to Draft status.";
                header("Location: domain_price_crosscheck.php?month_id=" . $month_id);
                exit();
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
                header("Location: domain_price_crosscheck.php?month_id=" . $new_id);
                exit();
            } else {
                $error_message = "Failed to duplicate monthly record.";
            }
        } elseif ($action === 'approve_lock' && tracs_user_can($conn, 'domain_price.approve')) {
            $month_id = (int)($_POST['month_id'] ?? 0);
            $note = trim($_POST['approval_note'] ?? '');
            if ($DPC->approveAndLock($month_id, $note, $ip_address)) {
                $success_message = "Monthly snapshot approved and locked!";
                header("Location: domain_price_crosscheck.php?month_id=" . $month_id);
                exit();
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
                header("Location: domain_price_crosscheck.php?month_id=" . $month_id);
                exit();
            } else {
                $error_message = "Failed to unlock record.";
            }
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
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
<link rel="stylesheet" href="assets/domain-price-crosscheck.css?v=<?=$_dpc_css_v?>">
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
  <div class="dpc-toolbar">
    <div class="dpc-toolbar-controls">
      <div class="month-selector-card">
        <form method="get" class="dpc-select-form">
          <label for="month_id_select">Monthly Record</label>
          <select name="month_id" id="month_id_select" class="form-select" onchange="this.form.submit()">
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
        <form method="post" onsubmit="return confirm('Are you sure you want to submit this monthly record for review? It will lock basic editing.');">
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
          <form method="post" onsubmit="return confirm('Are you sure you want to revert this monthly record to Draft status?');">
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
      <form method="get" action="/api/export-domain-price-crosscheck.php" class="report-export-popover dpc-export-popover" id="dpcMonthlyExportForm">
        <div class="report-export-title">
          <i data-lucide="download" class="icon-xs"></i>
          Export CSV
        </div>
        <?php if (!$is_intern && tracs_user_can($conn, 'domain_price.manage')): ?>
          <button type="button" class="btn btn-ghost dpc-more-action-btn" onclick="openExtensionModal(); this.closest('details')?.removeAttribute('open');">
            <i data-lucide="globe-2" class="icon-sm"></i>
            Manage Extensions
          </button>
        <?php endif; ?>
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
        <button type="submit" class="btn btn-primary"><i data-lucide="download" class="icon-sm"></i>Download CSV</button>
      </form>
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

    <!-- MAIN MATRIX -->
    <div class="dpc-section-tabs" aria-label="Domain price sections">
      <a class="active" href="#gtld-pricing">gTLD Pricing</a>
      <a href="#cctld-pricing">ccTLD Pricing</a>
      <a href="#pricing-summary">Summary / Findings</a>
      <a href="#tld-notes">Notes</a>
      <a href="#record-operations">Audit / Metadata</a>
    </div>

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
                <i data-lucide="save" class="icon-xs"></i> Save Matrix
              </button>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
      <div class="table-container dpc-matrix-scroll">
        <table class="table-dense dpc-matrix-table dpc-gtld-table">
          <thead>
            <tr>
              <th class="sticky-col group-header-col">Source Group</th>
              <th class="sticky-col type-col">Type</th>
              <?php foreach ($active_tlds as $tld): ?>
                <th class="tld-col"><?=esc($tld['tld_name'])?></th>
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
                    $usd = $val ? $val['usd_value'] : '';
                  ?>
                    <td>
                      <div class="dpc-input-wrap usd-input">
                        <span class="currency-prefix">$</span>
                        <input type="number" step="0.01" class="form-input matrix-input"
                               data-tld="<?=$tld['id']?>" data-source="<?=$source['id']?>" data-type="cost_register" data-currency="USD"
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
                    <td class="auto-calc cell-idr">
                      <?= $idr ? 'Rp ' . number_format($idr, 0, ',', '.') : '<span class="dpc-empty-value">—</span>' ?>
                    </td>
                  <?php endforeach; ?>
                </tr>
                <!-- Renewal Cost (USD input) -->
                <tr class="row-source-usd">
                  <td class="sticky-col type-col">Renewal (USD)</td>
                  <?php foreach ($active_tlds as $tld):
                    $val = $matrix_data[$tld['id']][$source['id']]['cost_renewal'] ?? null;
                    $usd = $val ? $val['usd_value'] : '';
                  ?>
                    <td>
                      <div class="dpc-input-wrap usd-input">
                        <span class="currency-prefix">$</span>
                        <input type="number" step="0.01" class="form-input matrix-input"
                               data-tld="<?=$tld['id']?>" data-source="<?=$source['id']?>" data-type="cost_renewal" data-currency="USD"
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
                    <td class="auto-calc cell-idr">
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
                    $idr = $val ? number_format($val['idr_value'], 0, '', '') : '';
                  ?>
                    <td>
                      <div class="dpc-input-wrap idr-input">
                        <span class="currency-prefix">Rp</span>
                        <input type="number" class="form-input matrix-input"
                               data-tld="<?=$tld['id']?>" data-source="<?=$source['id']?>" data-type="cost_register" data-currency="IDR"
                               value="<?=esc($idr)?>" <?=$month_data['status'] === 'approved' ? 'disabled' : ''?>>
                      </div>
                    </td>
                  <?php endforeach; ?>
                </tr>
                <tr class="row-internal-idr">
                  <td class="sticky-col type-col">Renewal (IDR)</td>
                  <?php foreach ($active_tlds as $tld):
                    $val = $matrix_data[$tld['id']][$source['id']]['cost_renewal'] ?? null;
                    $idr = $val ? number_format($val['idr_value'], 0, '', '') : '';
                  ?>
                    <td>
                      <div class="dpc-input-wrap idr-input">
                        <span class="currency-prefix">Rp</span>
                        <input type="number" class="form-input matrix-input"
                               data-tld="<?=$tld['id']?>" data-source="<?=$source['id']?>" data-type="cost_renewal" data-currency="IDR"
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
                $idr = $val ? number_format($val['idr_value'], 0, '', '') : '';
              ?>
                <td>
                  <div class="dpc-input-wrap idr-input selling-input">
                    <span class="currency-prefix">Rp</span>
                    <input type="number" class="form-input matrix-input"
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
                $idr = $val ? number_format($val['idr_value'], 0, '', '') : '';
              ?>
                <td>
                  <div class="dpc-input-wrap idr-input selling-input">
                    <span class="currency-prefix">Rp</span>
                    <input type="number" class="form-input matrix-input"
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
                $idr = $val ? number_format($val['idr_value'], 0, '', '') : '';
              ?>
                <td>
                  <div class="dpc-input-wrap idr-input selling-input">
                    <span class="currency-prefix">Rp</span>
                    <input type="number" class="form-input matrix-input"
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
                $idr = $val ? number_format($val['idr_value'], 0, '', '') : '';
              ?>
                <td>
                  <div class="dpc-input-wrap idr-input selling-input">
                    <span class="currency-prefix">Rp</span>
                    <input type="number" class="form-input matrix-input"
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
        <span class="panel-meta">PANDI Registry Pricing vs IDCH ccTLD Pricing · target margin <?=number_format(DPC_TARGET_MARGIN_RATE * 100, 0)?>%</span>
      </div>
      <div class="table-container dpc-cctld-scroll">
        <table class="table-dense dpc-matrix-table dpc-cctld-table">
          <thead>
            <tr>
              <th class="sticky-col month-col">Month</th>
              <th class="sticky-col group-header-col">Source</th>
              <th class="sticky-col type-col">Type</th>
              <?php foreach ($active_cctlds as $tld): ?>
                <th class="tld-col"><?=esc(strtoupper($tld['tld_name']))?></th>
              <?php endforeach; ?>
              <th class="dpc-notes-col">Notes</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($active_cctlds) || !$pandi_source || !$idch_cctld_source): ?>
              <tr><td colspan="<?=count($active_cctlds) + 4?>" class="empty-small">ccTLD seed data is not installed yet.</td></tr>
            <?php else: ?>
              <?php
                $cctldRows = [
                  ['source' => $pandi_source, 'label' => 'PANDI Registry Pricing', 'note' => 'Registry cost baseline'],
                  ['source' => $idch_cctld_source, 'label' => 'IDCH ccTLD Pricing', 'note' => 'IDCH selling price check'],
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
                    <?php if ($groupIndex === 0 && $typeIndex === 0): ?>
                      <td class="sticky-col month-col" rowspan="6"><?=esc(dpc_month_label((string)$month_data['month']))?></td>
                    <?php endif; ?>
                    <?php if ($typeIndex === 0): ?>
                      <td class="sticky-col group-header-col" rowspan="3"><strong><?=esc($group['label'])?></strong></td>
                    <?php endif; ?>
                    <td class="sticky-col type-col"><?=esc($type['label'])?> (IDR)</td>
                    <?php foreach ($active_cctlds as $tld):
                      $val = $matrix_data[$tld['id']][$group['source']['id']][$type['key']] ?? null;
                      $idr = $val ? number_format($val['idr_value'], 0, '', '') : '';
                    ?>
                      <td>
                        <div class="dpc-input-wrap idr-input">
                          <span class="currency-prefix">Rp</span>
                          <input type="number" class="form-input matrix-input"
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

    <?php include __DIR__ . '/includes/domain-price-crosscheck/pricing-summary.php'; ?>

    <!-- TLD NOTES PANEL -->
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
            <label>TLD</label>
            <select name="tld_id" id="note_tld_select" class="form-select dpc-note-tld-select" required>
              <option value="">-- Select TLD --</option>
              <?php foreach ($active_tlds as $tld): ?>
                <option value="<?=$tld['id']?>"><?=$tld['tld_name']?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group dpc-note-text-field">
            <label>Manual Note (Short)</label>
            <input type="text" name="manual_note" id="note_manual_input" class="form-input" placeholder="e.g. Discussing with registrar for discount">
          </div>
          <div class="form-group">
            <label>Follow-up Status</label>
            <select name="follow_up_status" id="note_status_select" class="form-select">
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
            <table class="table-dense dpc-notes-table">
              <thead>
                <tr>
                  <th>TLD</th>
                  <th>Follow-up Status</th>
                  <th>Note</th>
                  <th>Updated By</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($tld_notes as $note): ?>
                  <tr>
                    <td><strong><?=$note['tld_name']?></strong></td>
                    <td>
                      <?php
                        $bClass = 'b-default';
                        if ($note['follow_up_status'] === 'Need Review') $bClass = 'b-warning';
                        if ($note['follow_up_status'] === 'Waiting Finance') $bClass = 'b-info';
                        if ($note['follow_up_status'] === 'Waiting Approval') $bClass = 'b-high';
                        if ($note['follow_up_status'] === 'Updated') $bClass = 'b-success';
                      ?>
                      <span class="badge <?=$bClass?>"><?=$note['follow_up_status']?></span>
                    </td>
                    <td><?=esc($note['manual_note'])?></td>
                    <td class="dpc-note-updated">
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
</script>

<!-- MODALS -->

<!-- 1. Create New Monthly Record Modal -->
<div id="newMonthModal" class="dpc-modal">
  <div class="dpc-modal-content">
    <div class="dpc-modal-header">
      <h3>Create Monthly Record Draft</h3>
      <button class="modal-close-btn" onclick="closeNewMonthModal()"><i data-lucide="x" class="icon-sm"></i></button>
    </div>
    <form method="post" class="dpc-modal-form" id="newMonthForm">
      <?= csrf_input() ?>
      <input type="hidden" name="action" value="create_month">
      <input type="hidden" name="month_code" id="month_code_input" value="<?=esc($suggested_period->format('Y-m'))?>">
      
      <div class="dpc-modal-grid">
        <div class="form-group">
          <label for="period_month_select">Month</label>
          <select name="period_month" id="period_month_select" class="form-select" required>
            <?php for ($m = 1; $m <= 12; $m++): ?>
              <option value="<?=$m?>" <?=$m === (int)$suggested_period->format('n') ? 'selected' : ''?>><?=esc(dpc_month_name($m))?></option>
            <?php endfor; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="period_year_select">Year</label>
          <select name="period_year" id="period_year_select" class="form-select" required>
            <?php foreach ($year_options as $year): ?>
              <option value="<?=$year?>" <?=$year === $suggested_year ? 'selected' : ''?>><?=$year?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label for="exchange_rate_input">Exchange Rate (USD to IDR)</label>
        <input type="text" inputmode="decimal" name="exchange_rate" id="exchange_rate_input" placeholder="Example: 17500" class="form-input" required data-raw-value="<?=esc($latest_exchange_rate ? (string)$latest_exchange_rate : '')?>" value="<?=esc($latest_exchange_rate ? (string)(int)$latest_exchange_rate : '')?>">
        <small>Used to convert USD registrar cost into IDR for this monthly record.</small>
      </div>

      <div class="dpc-option-card <?=$latest_month ? '' : 'is-disabled'?>">
        <label class="dpc-check-row">
          <input type="checkbox" name="use_previous_template" id="use_previous_template_input" value="1" <?=$latest_month ? 'checked' : 'disabled'?>>
          <span>
            <strong>Use previous month pricing as template</strong>
            <small>Copies the previous month pricing as editable draft data for faster monthly comparison. Review the exchange rate before saving.</small>
          </span>
        </label>
        <label class="dpc-check-row dpc-copy-notes-row">
          <input type="checkbox" name="copy_template_notes" id="copy_template_notes_input" value="1" <?=$latest_month ? '' : 'disabled'?>>
          <span>
            <strong>Copy notes</strong>
            <small>Optional. Manual notes are copied only when this is selected.</small>
          </span>
        </label>
        <div class="dpc-template-hint">
          <?=$latest_month ? 'Template source: ' . esc($latest_month_label) : 'No previous monthly record is available yet.'?>
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
        <select name="priority" class="form-select">
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

<!-- Domain Extension Modal -->
<div id="extensionModal" class="dpc-modal">
  <div class="dpc-modal-content dpc-extension-modal-content">
    <div class="dpc-modal-header">
      <h3>Manage Domain Extensions</h3>
      <button class="modal-close-btn" onclick="closeExtensionModal()"><i data-lucide="x" class="icon-sm"></i></button>
    </div>
    <div class="dpc-modal-form dpc-extension-modal-body">
      <?php if ($month_data && !$is_intern && tracs_user_can($conn, 'domain_price.manage')): ?>
      <!-- Layout audit: extension creation is intentionally hidden behind More Actions so the main pricing workflow stays focused. -->
      <form method="post" class="dpc-extension-form" data-extension-form>
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="add_tld_extension">
        <input type="hidden" name="month_id" value="<?= (int)$month_data['id'] ?>">
        <div class="form-group">
          <label for="dpcExtensionName">Extension</label>
          <input id="dpcExtensionName" type="text" name="tld_name" class="form-input" placeholder=".app or .web.id" maxlength="30" pattern="^\.?[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)*$" autocomplete="off" required>
        </div>
        <div class="form-group">
          <label for="dpcExtensionCategory">Category</label>
          <select id="dpcExtensionCategory" name="tld_category" class="form-select" required>
            <option value="gtld">gTLD Pricing Matrix</option>
            <option value="cctld">ccTLD Pricing Matrix</option>
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
      <?php endif; ?>
      <div class="dpc-extension-lists">
        <div class="dpc-extension-list-card">
          <div class="dpc-extension-list-head">
            <strong>gTLD</strong>
            <span class="dpc-extension-count"><?=count($active_tlds)?> active</span>
          </div>
          <div class="dpc-extension-chips">
            <?php foreach ($active_tlds as $tld): ?>
              <span data-extension-name="<?=esc(strtolower((string)$tld['tld_name']))?>"><?=esc($tld['tld_name'])?></span>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="dpc-extension-list-card">
          <div class="dpc-extension-list-head">
            <strong>ccTLD</strong>
            <span class="dpc-extension-count"><?=count($active_cctlds)?> active</span>
          </div>
          <div class="dpc-extension-chips">
            <?php foreach ($active_cctlds as $tld): ?>
              <span data-extension-name="<?=esc(strtolower((string)$tld['tld_name']))?>"><?=esc(strtoupper($tld['tld_name']))?></span>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <div class="dpc-modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeExtensionModal()">Close</button>
      </div>
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
  <div class="dpc-modal-content">
    <div class="dpc-modal-header">
      <h3>Duplicate Monthly Record</h3>
      <button class="modal-close-btn" onclick="closeDuplicateMonthModal()"><i data-lucide="x" class="icon-sm"></i></button>
    </div>
    <form method="post" class="dpc-modal-form">
      <?= csrf_input() ?>
      <input type="hidden" name="action" value="duplicate_month">
      <input type="hidden" name="from_month_id" id="duplicate_from_month_id">
      
      <div class="form-group">
        <label for="duplicate_month_code_input">New Month Code (YYYY-MM)</label>
        <input type="text" name="month_code" id="duplicate_month_code_input" placeholder="e.g. <?=date('Y-m')?>" class="form-input" required pattern="^\d{4}-\d{2}$">
        <small>Format: YYYY-MM.</small>
      </div>

      <div class="form-group">
        <label for="duplicate_exchange_rate_input">Exchange Rate (USD to IDR)</label>
        <input type="number" step="0.01" name="exchange_rate" id="duplicate_exchange_rate_input" placeholder="e.g. 16250.00" class="form-input" required min="1">
        <small>Previous month exchange rate is suggested as a starting point. Review it before creating the draft.</small>
      </div>

      <div class="dpc-option-card">
        <label class="dpc-check-row">
          <input type="checkbox" checked disabled>
          <span>
            <strong>Copy previous month pricing as editable template</strong>
            <small>Price entries are copied into the new Draft record. Approval metadata, audit logs, and task state are not copied.</small>
          </span>
        </label>
        <label class="dpc-check-row dpc-copy-notes-row">
          <input type="checkbox" name="copy_notes" value="1">
          <span>
            <strong>Copy notes</strong>
            <small>Optional. Manual notes are copied only when selected.</small>
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
