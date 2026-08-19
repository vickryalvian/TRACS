<?php
declare(strict_types=1);

function abuse_flow_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$page = file_get_contents(__DIR__ . '/../public/abuse-reports.php');
$script = file_get_contents(__DIR__ . '/../public/assets/abuse-reports.js');
$style = file_get_contents(__DIR__ . '/../public/assets/abuse-reports.css');
$bootstrap = file_get_contents(__DIR__ . '/../public/api/_bootstrap.php');
$endpoint = file_get_contents(__DIR__ . '/../public/api/abuse-report-delete.php');
$model = file_get_contents(__DIR__ . '/../modules/abuse-report/model.php');
$access = file_get_contents(__DIR__ . '/../core/access_control.php');

abuse_flow_assert(!in_array(false, [$page, $script, $style, $bootstrap, $endpoint, $model, $access], true), 'Unable to read abuse report sources.');
abuse_flow_assert(
    str_contains($page, 'id="abusePreviewModal"')
        && str_contains($page, 'id="abuseOpenRecord"')
        && str_contains($script, 'schedulePreview(toId(card.dataset.abuseId))')
        && str_contains($script, "root.addEventListener('dblclick'")
        && str_contains($script, 'openReport(toId(target.dataset.abuseId))'),
    'Card clicks must preview records while Open record and double-click load full detail.'
);
abuse_flow_assert(
    str_contains($script, "sessionStorage.getItem('tracsAbuseAdvancedOpen')")
        && str_contains($script, "sessionStorage.setItem('tracsAbuseAdvancedOpen'")
        && str_contains($page, 'data-abuse-optional="affected_ip"')
        && str_contains($script, 'function syncOptionalFields(report)')
        && str_contains($style, 'grid-template-columns: repeat(3, minmax(0, 1fr))')
        && str_contains($style, '.abuse-intake-notes'),
    'The full editor must preserve compact-layout and optional-field behavior.'
);
abuse_flow_assert(
    str_contains($script, "renderListSelect(report, 'status'")
        && str_contains($script, "renderListSelect(report, 'assigned_user_id'")
        && str_contains($script, "renderListSelect(report, 'reporter'")
        && str_contains($script, 'data-abuse-list-editor-form')
        && str_contains($script, "name=\"description\"")
        && str_contains($script, 'await jsonPost(apiUrls.update, { id, ...patch })')
        && str_contains($script, 'data-tracs-dropdown="off"')
        && str_contains($script, "event.target.closest('.abuse-list-select, .abuse-list-editor-form')")
        && substr_count($script, 'cancelScheduledPreview();') >= 2
        && !str_contains($script, 'renderAdvanceButton(report, true)')
        && str_contains($script, 'title="Edit report details"')
        && str_contains($script, "replace(/^TRACS-AR-/, '#')")
        && str_contains($style, '.abuse-list-select:focus-within')
        && !str_contains($style, '.abuse-advance-btn.is-icon-only')
        && str_contains($style, '.abuse-list-priority.is-critical')
        && str_contains($style, '.abuse-list-table th:nth-child(7)')
        && str_contains($style, '.abuse-list-select.is-status')
        && str_contains($style, '.abuse-list-editor-form')
        && str_contains($style, '.main.abuse-main')
        && str_contains($style, '.abuse-column-list::-webkit-scrollbar')
        && str_contains($style, '--abuse-pill-accent')
        && str_contains($style, '--abuse-status-accent')
        && str_contains($style, '.abuse-status-chip.is-investigating { --abuse-status-accent: var(--cyan); }')
        && preg_match('/\.abuse-indicator\s*\{[^}]*border-radius:\s*999px;/s', $style) === 1
        && preg_match('/\.abuse-tag\s*\{[^}]*border-radius:\s*999px;/s', $style) === 1
        && str_contains($page, 'class="abuse-detail-meta"'),
    'List editing must use compact controls, preserve notes editing, and share the normal update API.'
);
abuse_flow_assert(
    str_contains($page, 'id="abuseDeleteRecord"')
        && str_contains($bootstrap, "'abuse-report-delete.php' => ['POST']")
        && str_contains($endpoint, 'tracs_user_can_delete_abuse_reports')
        && str_contains($access, 'return tracs_is_supervisor_or_above($conn, $userId);')
        && str_contains($model, "'tracs_abuse_report_events', 'tracs_abuse_report_notes', 'tracs_abuse_report_evidence', 'tracs_abuse_reports'")
        && str_contains($endpoint, 'abuse_report_evidence_delete_file($file)'),
    'Delete must be role-gated and remove report children plus stored evidence.'
);

echo "TRACS abuse report preview/delete flow checks passed.\n";
