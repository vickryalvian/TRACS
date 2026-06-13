<?php
require_once __DIR__ . '/../../../core/security/direct_access.php';
tracs_deny_direct_script_access(__FILE__);
/**
 * Domain Price Crosscheck analytical dashboard modules.
 * Expects parent page variables and helper functions to be in scope.
 */
?>
    <?php if ($pricing_intelligence): ?>
      <?php
        $dpcCombinedChecks = array_merge($website_price_checks, $cctld_price_checks);
        $dpcIncreaseChecks = array_values(array_filter(
          $dpcCombinedChecks,
          static fn(array $check): bool => in_array((string)($check['status'] ?? ''), ['Below Cost', 'Below Target Margin'], true)
        ));
        foreach ($dpcIncreaseChecks as &$dpcIncreaseCheck) {
          $currentPrice = dpc_validation_current_price($dpcIncreaseCheck);
          $suggestedPrice = isset($dpcIncreaseCheck['suggested_rounded_price']) ? (float)$dpcIncreaseCheck['suggested_rounded_price'] : null;
          $dpcIncreaseCheck['_publish_increase'] = $currentPrice !== null && $suggestedPrice !== null
            ? max(0, $suggestedPrice - $currentPrice)
            : 0.0;
        }
        unset($dpcIncreaseCheck);
        usort($dpcIncreaseChecks, static fn(array $left, array $right): int =>
          ((float)$right['_publish_increase'] <=> (float)$left['_publish_increase'])
          ?: ((int)($left['priority'] ?? 9) <=> (int)($right['priority'] ?? 9))
        );
        $dpcHighestIncrease = $dpcIncreaseChecks[0] ?? null;
        $dpcGtldIncreaseCount = count(array_filter($dpcIncreaseChecks, static fn(array $check): bool => ($check['_scope'] ?? '') === 'website'));
        $dpcCctldIncreaseCount = count($dpcIncreaseChecks) - $dpcGtldIncreaseCount;
        $dpcMissingChecks = array_values(array_filter(
          $dpcCombinedChecks,
          static fn(array $check): bool => (string)($check['status'] ?? '') === 'Missing Data'
        ));
        $dpcBelowCostChecks = array_values(array_filter(
          $dpcCombinedChecks,
          static fn(array $check): bool => (string)($check['status'] ?? '') === 'Below Cost'
        ));
        $dpcSuggestedChangeTotal = array_sum(array_map(
          static fn(array $check): float => (float)($check['_publish_increase'] ?? 0),
          $dpcIncreaseChecks
        ));
        $dpcHighRiskChecks = array_values(array_filter(
          $dpcCombinedChecks,
          static fn(array $check): bool =>
            in_array((string)($check['status'] ?? ''), ['Below Cost', 'Missing Data', 'Registrar Cost Increased', 'Recommended Source Changed'], true)
            || (float)($check['gap_to_recommended'] ?? 0) > 0
        ));
        usort($dpcHighRiskChecks, static fn(array $left, array $right): int =>
          ((int)($left['priority'] ?? 9) <=> (int)($right['priority'] ?? 9))
          ?: ((float)($right['gap_to_recommended'] ?? 0) <=> (float)($left['gap_to_recommended'] ?? 0))
        );
        $dpcHighestRisk = $dpcHighRiskChecks[0] ?? null;
      ?>
      <section class="dpc-module-panel dpc-intelligence-panel" id="intelligence-summary" data-dpc-panel="intelligence-summary" role="tabpanel" hidden>
        <section class="panel dpc-priority-dashboard">
          <div class="panel-head">
            <span class="panel-title">
              <i data-lucide="shield-alert" class="icon-sm"></i>
              Decision Priorities
            </span>
            <span class="panel-meta">gTLD and ccTLD validation combined</span>
          </div>
          <div class="dpc-priority-summary-grid">
            <article class="dpc-priority-summary-card yellow">
              <div class="dpc-priority-summary-head">
                <span>Website Adjustment Priority</span>
                <strong><?=count($dpcIncreaseChecks)?></strong>
              </div>
              <p><?=$dpcGtldIncreaseCount?> gTLD · <?=$dpcCctldIncreaseCount?> ccTLD rows need a price increase.</p>
              <?php if ($dpcHighestIncrease): ?>
                <div class="dpc-priority-example">
                  <small>Largest increase</small>
                  <strong><?=esc(dpc_tld_label($dpcHighestIncrease['tld_name']))?> <?=esc($dpcHighestIncrease['type_label'])?> · +<?=dpc_money($dpcHighestIncrease['_publish_increase'])?></strong>
                  <span>Increase to <?=dpc_money($dpcHighestIncrease['suggested_rounded_price'])?></span>
                </div>
              <?php else: ?>
                <div class="empty-small">No price increases are required.</div>
              <?php endif; ?>
              <div class="dpc-priority-actions">
                <?php foreach (array_slice($dpcIncreaseChecks, 0, 3) as $check): ?>
                  <span><?=esc(dpc_tld_label($check['tld_name']))?> <?=esc($check['type_label'])?> → <?=dpc_money($check['suggested_rounded_price'])?></span>
                <?php endforeach; ?>
              </div>
            </article>

            <article class="dpc-priority-summary-card <?=$dpcHighRiskChecks ? 'red' : 'green'?>">
              <div class="dpc-priority-summary-head">
                <span>High Risk</span>
                <strong><?=count($dpcHighRiskChecks)?></strong>
              </div>
              <p><?=count($dpcBelowCostChecks)?> below cost · <?=count($dpcMissingChecks)?> missing · largest margin and cost-change risks included.</p>
              <?php if ($dpcHighestRisk): ?>
                <div class="dpc-priority-example">
                  <small>Highest risk</small>
                  <strong><?=esc(dpc_tld_label($dpcHighestRisk['tld_name']))?> <?=esc($dpcHighestRisk['type_label'])?></strong>
                  <span><?=esc($dpcHighestRisk['suggested_action'])?></span>
                </div>
              <?php else: ?>
                <div class="empty-small">No below-cost rows in current record.</div>
              <?php endif; ?>
            </article>

            <article class="dpc-priority-summary-card muted">
              <div class="dpc-priority-summary-head">
                <span>Missing Data</span>
                <strong><?=count($dpcMissingChecks)?></strong>
              </div>
              <p>Rows without enough cost or selling-price data to produce a recommendation.</p>
              <button type="button" class="btn btn-ghost btn-sm"
                      data-dpc-open-tab="website-adjustment"
                      data-dpc-filter-shortcut="both"
                      data-dpc-filter-value="missing-data">Review missing rows</button>
            </article>

            <article class="dpc-priority-summary-card blue">
              <div class="dpc-priority-summary-head">
                <span>Suggested Price Changes</span>
                <strong><?=dpc_money($dpcSuggestedChangeTotal)?></strong>
              </div>
              <p>Total rounded publish-price increase across rows currently requiring action.</p>
              <button type="button" class="btn btn-ghost btn-sm"
                      data-dpc-open-tab="website-adjustment"
                      data-dpc-filter-shortcut="both"
                      data-dpc-filter-value="below-target-margin"
                      data-dpc-sort-key="required-increase"
                      data-dpc-sort-direction="desc">Open priority pricing</button>
            </article>
          </div>
        </section>

        <div class="dpc-intelligence-dashboard">
        <section class="panel dpc-analysis-panel">
          <div class="panel-head">
            <span class="panel-title">
              <i data-lucide="brain-circuit" class="icon-sm"></i>
              Pricing Intelligence Summary
            </span>
            <span class="panel-meta">Target margin <?=number_format(DPC_TARGET_MARGIN_RATE * 100, 0)?>% · rounded to Rp<?=number_format(DPC_ROUNDING_INCREMENT, 0, ',', '.')?></span>
          </div>

          <div class="dpc-exec-grid">
            <?php
              $execCards = [
                ['TLD Checks', count($pricing_intelligence['checks']), 'Register and renewal checks', 'muted'],
                ['Estimated Margin Risk', dpc_money($pricing_intelligence['estimated_margin_risk']), 'Total gap to recommended price', $pricing_intelligence['estimated_margin_risk'] > 0 ? 'yellow' : 'green'],
                ['Pending Review', $pricing_intelligence['counts']['pending_review'], 'Cost increase or source-change checks', $pricing_intelligence['counts']['pending_review'] ? 'yellow' : 'green'],
                ['Lowest Registrars', count(array_filter($pricing_intelligence['registrar_snapshot'] ?? [], static fn(array $source): bool => $source['wins'] > 0)), 'Active registrars winning current checks', 'blue'],
              ];
            ?>
            <?php foreach ($execCards as $card): ?>
              <div class="dpc-intel-card <?=$card[3]?>">
                <span><?=$card[0]?></span>
                <strong><?=$card[1]?></strong>
                <small><?=$card[2]?></small>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="dpc-analysis-grid">
            <section class="dpc-intel-section">
              <div class="dpc-section-head">
                <h3>Exchange Rate Impact</h3>
              </div>
              <?php if (!$pricing_intelligence['previous_month']): ?>
                <div class="empty-small">No previous month exchange rate data available.</div>
              <?php else: ?>
                <?php
                  $rateDiff = (float)($pricing_intelligence['exchange_rate_diff'] ?? 0);
                  $rateTone = $rateDiff > 0 ? 'red' : ($rateDiff < 0 ? 'green' : 'muted');
                ?>
                <div class="dpc-rate-impact">
                  <div><span>Previous</span><strong>Rp<?=number_format((float)$pricing_intelligence['previous_exchange_rate'], 2)?></strong></div>
                  <div><span>Current</span><strong>Rp<?=number_format((float)$month_data['exchange_rate_usd_idr'], 2)?></strong></div>
                  <div class="<?=$rateTone?>"><span>Difference</span><strong><?=dpc_money($pricing_intelligence['exchange_rate_diff'])?> / <?=dpc_percent($pricing_intelligence['exchange_rate_diff_pct'])?></strong></div>
                </div>
                <?php if (empty($pricing_intelligence['exchange_impacts'])): ?>
                  <div class="empty-small">No USD-based lowest registrar cost is available for impact analysis.</div>
                <?php else: ?>
                  <div class="dpc-mini-list">
                    <?php foreach (array_slice($pricing_intelligence['exchange_impacts'], 0, 5) as $impact): ?>
                      <span><?=esc(dpc_tld_label($impact['tld_name']))?> <?=$impact['type_label']?> · <?=dpc_money($impact['exchange_impact'])?></span>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              <?php endif; ?>
            </section>

            <section class="dpc-intel-section">
              <div class="dpc-section-head">
                <h3>Previous Month Change Summary</h3>
              </div>
              <?php if (!$pricing_intelligence['previous_month']): ?>
                <div class="empty-small">No previous month data available.</div>
              <?php else: ?>
                <div class="dpc-change-grid">
                  <div><strong><?=count($pricing_intelligence['previous_summary']['cost_increase'])?></strong><span>Cost increases</span></div>
                  <div><strong><?=count($pricing_intelligence['previous_summary']['cost_decrease'])?></strong><span>Cost decreases</span></div>
                  <div><strong><?=count($pricing_intelligence['previous_summary']['recommended_increase'])?></strong><span>Recommended price increases</span></div>
                  <div><strong><?=count($pricing_intelligence['previous_summary']['source_changed'])?></strong><span>Source changes</span></div>
                </div>
                <div class="dpc-mini-list">
                  <?php foreach (array_slice($pricing_intelligence['previous_summary']['source_changed'], 0, 5) as $changed): ?>
                    <span><?=esc(dpc_tld_label($changed['tld_name']))?> <?=$changed['type_label']?> source changed to <?=esc($changed['lowest_source_name'] ?? '—')?></span>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </section>

          </div>

          <section class="dpc-intel-section dpc-action-buckets-panel dpc-intelligence-buckets">
            <div class="dpc-section-head">
              <h3>
              <i data-lucide="list-checks" class="icon-sm"></i>
              Action Buckets
              </h3>
              <span>Current decision queue</span>
            </div>
            <div class="dpc-action-bucket-grid">
              <?php foreach ($dpc_urgency_buckets as $bucket): ?>
                <article class="dpc-action-bucket <?=$bucket['tone']?>">
                  <div class="dpc-action-bucket-head">
                    <div>
                      <strong><?=esc($bucket['title'])?></strong>
                      <span><?=esc($bucket['description'])?></span>
                    </div>
                    <em><?=$bucket['row_count']?></em>
                  </div>
                  <div class="dpc-action-bucket-stats">
                    <span><small>Rows</small><strong><?=$bucket['row_count']?></strong></span>
                    <span><small>TLDs</small><strong><?=$bucket['tld_count']?></strong></span>
                    <span><small>Increase</small><strong><?=dpc_money($bucket['total_impact'])?></strong></span>
                  </div>
                  <?php if ($bucket['top_item']): ?>
                    <div class="dpc-action-bucket-example">
                      <small>Highest priority</small>
                      <strong><?=esc(dpc_tld_label($bucket['top_item']['tld_name']))?> <?=esc($bucket['top_item']['type_label'])?></strong>
                      <span><?=esc($bucket['top_item']['suggested_action'])?></span>
                    </div>
                  <?php else: ?>
                    <div class="empty-small">No affected rows.</div>
                  <?php endif; ?>
                  <button type="button"
                          class="btn btn-ghost btn-sm dpc-action-bucket-link"
                          data-dpc-open-tab="<?=esc($bucket['target_tab'])?>"
                          data-dpc-filter-shortcut="<?=esc($bucket['target_scope'])?>"
                          data-dpc-filter-value="<?=esc($bucket['filter_value'])?>"
                          <?php if ($bucket['sort_key'] !== ''): ?>
                            data-dpc-sort-key="<?=esc($bucket['sort_key'])?>"
                            data-dpc-sort-direction="<?=esc($bucket['sort_direction'])?>"
                          <?php endif; ?>>
                    View affected rows
                  </button>
                </article>
              <?php endforeach; ?>
            </div>
          </section>
        </section>

        <aside class="panel dpc-registrar-snapshot-panel">
          <div class="panel-head">
            <span class="panel-title">
              <i data-lucide="server-cog" class="icon-sm"></i>
              Registrar Snapshot
            </span>
            <span class="panel-meta"><?=count($pricing_intelligence['registrar_snapshot'] ?? [])?> active</span>
          </div>
          <?php if (empty($pricing_intelligence['registrar_snapshot'])): ?>
            <div class="empty-small">No active registrar pricing is available for this record.</div>
          <?php else: ?>
            <div class="dpc-registrar-snapshot-list">
              <?php foreach ($pricing_intelligence['registrar_snapshot'] as $source): ?>
                <?php
                  $trend = $source['trend'];
                  $trendClass = $trend === null ? 'muted' : ($trend > 0 ? 'positive' : ($trend < 0 ? 'negative' : 'muted'));
                  $trendLabel = $trend === null ? 'No previous-month comparison' : (($trend > 0 ? '+' : '') . $trend . ' wins vs previous month');
                ?>
                <article class="dpc-registrar-snapshot-card <?=$source['missing'] > 0 ? 'is-incomplete' : ''?>">
                  <div class="dpc-registrar-snapshot-head">
                    <div>
                      <strong><?=esc($source['source_name'])?></strong>
                      <span><?=$source['wins']?> lowest-price wins</span>
                    </div>
                    <em class="<?=$source['missing'] > 0 ? 'warning' : 'complete'?>">
                      <?=$source['coverage']?>/<?=$source['total_slots']?>
                    </em>
                  </div>
                  <div class="dpc-registrar-snapshot-metrics">
                    <span><small>Avg advantage</small><strong><?=dpc_money($source['avg_advantage'])?></strong></span>
                    <span><small>Missing values</small><strong><?=$source['missing']?></strong></span>
                  </div>
                  <div class="dpc-registrar-strength">
                    <span>Register <strong><?=$source['register_wins']?> wins</strong> · <?=$source['register_coverage']?>/<?=count($active_tlds)?> coverage</span>
                    <span>Renewal <strong><?=$source['renewal_wins']?> wins</strong> · <?=$source['renewal_coverage']?>/<?=count($active_tlds)?> coverage</span>
                  </div>
                  <?php if ($source['strongest']): ?>
                    <div class="dpc-registrar-example">
                      <small>Strongest example</small>
                      <strong><?=esc(dpc_tld_label($source['strongest']['tld_name']))?> <?=esc($source['strongest']['type_label'])?></strong>
                      <?php if ($source['strongest']['source_advantage'] !== null): ?>
                        <span><?=dpc_money($source['strongest']['source_advantage'])?> cheaper than next source</span>
                      <?php endif; ?>
                    </div>
                  <?php endif; ?>
                  <div class="dpc-registrar-trend <?=$trendClass?>"><?=esc($trendLabel)?></div>
                  <?php if ($source['missing'] > 0): ?>
                    <div class="dpc-registrar-warning">Complete <?=$source['missing']?> missing registrar value<?=$source['missing'] === 1 ? '' : 's'?>.</div>
                  <?php endif; ?>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </aside>
        </div>
      </section>

      <section class="dpc-module-panel dpc-validation-panel" id="website-adjustment" data-dpc-panel="website-adjustment" role="tabpanel" hidden>
        <div class="dpc-validation-stack">
        <div class="dpc-cctld-check-layout dpc-validation-cctld">
          <section class="panel dpc-cctld-check-table-panel">
            <div class="panel-head">
              <span class="panel-title">
                <i data-lucide="globe-2" class="icon-sm"></i>
                ccTLD Price Validation
              </span>
              <span class="panel-meta"><?=count($cctld_intelligence['checks'] ?? [])?> rows</span>
            </div>
            <div class="dpc-summary-filters" aria-label="ccTLD check filters">
              <?php foreach (['all' => 'All', 'below-cost' => 'Below Cost', 'below-target-margin' => 'Below Target Margin', 'missing-data' => 'Missing Data', 'safe' => 'Safe'] as $filter => $label): ?>
                <button type="button" class="btn btn-ghost btn-sm <?=$filter === 'all' ? 'active' : ''?>" data-dpc-filter="cctld" data-dpc-filter-value="<?=$filter?>"><?=$label?></button>
              <?php endforeach; ?>
            </div>
            <div class="table-container dpc-adjustment-scroll">
              <table class="table-dense dpc-adjustment-table dpc-cctld-check-table" data-dpc-sortable-table>
                <thead>
                  <tr>
                    <th data-dpc-sort-type="text" data-dpc-sort-key="tld">TLD</th>
                    <th data-dpc-sort-type="text" data-dpc-sort-key="type">Type</th>
                    <th data-dpc-sort-type="number" data-dpc-sort-key="current-price">Current IDCH Price</th>
                    <th data-dpc-sort-type="number" data-dpc-sort-key="cost-baseline">Cost Baseline</th>
                    <th class="dpc-margin-status-head" data-dpc-sort-type="number" data-dpc-sort-key="margin-status">
                      <span>Margin Status</span>
                      <button type="button"
                              class="dpc-secondary-sort"
                              data-dpc-sort-secondary="sortDelta"
                              data-dpc-sort-type="percent"
                              title="Sort by margin delta">Delta</button>
                      <button type="button"
                              class="dpc-secondary-sort"
                              data-dpc-sort-secondary="sortGap"
                              data-dpc-sort-type="number"
                              data-dpc-sort-default="desc"
                              title="Sort by required increase">Gap</button>
                    </th>
                    <th data-dpc-sort-type="number" data-dpc-sort-key="recommended-price">Recommended Price</th>
                    <th class="dpc-suggested-price-head" data-dpc-sort-type="number" data-dpc-sort-key="suggested-price">Suggested Price</th>
                    <th class="dpc-decision-head" data-dpc-sort-type="number" data-dpc-sort-key="decision">Decision</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach (($cctld_intelligence['checks'] ?? []) as $check): ?>
                    <?php
                      $marginStatus = dpc_margin_status_meta($check);
                      $decision = dpc_website_decision_meta($check);
                      $marginRank = dpc_margin_sort_rank($check);
                      $gap = (float)($check['gap_to_recommended'] ?? 0);
                      $healthSort = ($marginRank * 1000000000000) - $gap;
                    ?>
                    <tr data-dpc-filter-scope="cctld" data-dpc-filter-status="<?=dpc_status_key($check['status'])?>" data-summary-severity="<?=strtolower($check['severity'])?>">
                      <td><?=esc(dpc_tld_label($check['tld_name']))?></td>
                      <td><?=esc($check['type_label'])?></td>
                      <td data-sort-value="<?=esc((string)($check['idch_price'] ?? ''))?>"><?=dpc_money($check['idch_price'])?></td>
                      <td data-sort-value="<?=esc((string)($check['pandi_cost'] ?? ''))?>" title="<?=esc($check['harga_usd'] !== null ? 'Approx. ' . dpc_usd($check['harga_usd']) . ' at current KURS' : 'PANDI Registry baseline')?>"><?=dpc_money($check['pandi_cost'])?></td>
                      <td data-sort-value="<?=esc((string)$healthSort)?>"
                          data-sort-delta="<?=esc((string)($check['target_margin_delta'] ?? ''))?>"
                          data-sort-gap="<?=esc((string)($check['gap_to_recommended'] ?? ''))?>">
                        <div class="dpc-margin-status" title="Target margin: <?=number_format(DPC_TARGET_MARGIN_RATE * 100, 0)?>%">
                          <span class="dpc-severity dpc-severity-<?=$marginStatus['badge_class']?>"><?=esc($marginStatus['label'])?></span>
                          <small><?=esc($marginStatus['detail'])?></small>
                        </div>
                      </td>
                      <td data-sort-value="<?=esc((string)($check['recommended_price'] ?? ''))?>">
                        <div class="dpc-price-explainer">
                          <strong><?=dpc_money($check['recommended_price'])?></strong>
                          <small>exact 30% target</small>
                        </div>
                      </td>
                      <td class="dpc-suggested-price-cell" data-sort-value="<?=esc((string)($check['suggested_rounded_price'] ?? ''))?>">
                        <div class="dpc-price-explainer">
                          <strong><?=dpc_money($check['suggested_rounded_price'])?></strong>
                          <small>rounded publish price</small>
                        </div>
                      </td>
                      <td class="dpc-decision-cell" data-sort-value="<?=esc((string)$marginRank)?>" data-required-increase="<?=esc((string)($check['gap_to_recommended'] ?? ''))?>">
                        <div class="dpc-decision">
                          <strong><?=esc($decision['primary'])?></strong>
                          <small><?=esc($decision['secondary'])?></small>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </section>

          <aside class="panel dpc-cctld-priority-panel">
            <div class="panel-head">
              <span class="panel-title">
                <i data-lucide="alert-circle" class="icon-sm"></i>
                Priority Findings
              </span>
            </div>
            <?php
              $cctldPriority = array_values(array_filter(
                ($cctld_intelligence['checks'] ?? []),
                static fn($check) => in_array(($check['status'] ?? ''), ['Below Cost', 'Missing Data', 'Below Target Margin'], true)
              ));
              usort($cctldPriority, static fn($a, $b) =>
                (dpc_margin_sort_rank($a) <=> dpc_margin_sort_rank($b))
                ?: ((float)($b['gap_to_recommended'] ?? 0) <=> (float)($a['gap_to_recommended'] ?? 0))
              );
            ?>
            <?php if (empty($cctldPriority)): ?>
              <div class="empty-small">No priority ccTLD findings.</div>
            <?php else: ?>
              <div class="dpc-finding-column">
                <?php foreach (array_slice($cctldPriority, 0, 8) as $check): ?>
                  <?php
                    $marginStatus = dpc_margin_status_meta($check);
                    $decision = dpc_website_decision_meta($check);
                  ?>
                  <article class="dpc-finding-row">
                    <div class="dpc-finding-main">
                      <strong><?=esc(dpc_tld_label($check['tld_name']))?> <?=$check['type_label']?></strong>
                      <span class="dpc-severity dpc-severity-<?=$marginStatus['badge_class']?>"><?=esc($marginStatus['label'])?></span>
                    </div>
                    <div class="dpc-finding-metrics">
                      <span>Cost Baseline <strong><?=dpc_money($check['pandi_cost'])?></strong></span>
                      <span>Current Price <strong><?=dpc_money($check['idch_price'])?></strong></span>
                      <span>Recommended <strong><?=dpc_money($check['recommended_price'])?></strong></span>
                      <span>Suggested <strong><?=dpc_money($check['suggested_rounded_price'])?></strong></span>
                    </div>
                    <div class="dpc-finding-action"><?=esc($decision['primary'])?></div>
                  </article>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </aside>
        </div>

        <section class="panel dpc-adjustment-panel dpc-validation-website">
          <div class="panel-head">
            <span class="panel-title">
              <i data-lucide="badge-dollar-sign" class="icon-sm"></i>
              gTLD Price Validation
            </span>
            <span class="panel-meta">Recommended = exact 30% target · Suggested = rounded up to next Rp<?=number_format(DPC_ROUNDING_INCREMENT, 0, ',', '.')?></span>
          </div>
          <div class="dpc-summary-filters" aria-label="Website adjustment filters">
            <?php foreach (['all' => 'All', 'below-cost' => 'Below Cost', 'below-target-margin' => 'Below Target Margin', 'missing-data' => 'Missing Data', 'safe' => 'Safe'] as $filter => $label): ?>
              <button type="button" class="btn btn-ghost btn-sm <?=$filter === 'all' ? 'active' : ''?>" data-dpc-filter="website" data-dpc-filter-value="<?=$filter?>"><?=$label?></button>
            <?php endforeach; ?>
          </div>
          <div class="table-container dpc-adjustment-scroll">
            <table class="table-dense dpc-adjustment-table dpc-website-adjustment-table" data-dpc-sortable-table>
              <thead>
                <tr>
                  <th data-dpc-sort-type="text" data-dpc-sort-key="tld">TLD</th>
                  <th data-dpc-sort-type="text" data-dpc-sort-key="type">Type</th>
                  <th data-dpc-sort-type="number" data-dpc-sort-key="website-price">Current Website Price</th>
                  <th data-dpc-sort-type="number" data-dpc-sort-key="registrar-cost">Lowest Registrar Cost</th>
                  <th class="dpc-margin-status-head" data-dpc-sort-type="number" data-dpc-sort-key="margin-status">
                    <span>Margin Status</span>
                    <button type="button"
                            class="dpc-secondary-sort"
                            data-dpc-sort-secondary="sortDelta"
                            data-dpc-sort-type="percent"
                            title="Sort by margin delta">Delta</button>
                    <button type="button"
                            class="dpc-secondary-sort"
                            data-dpc-sort-secondary="sortGap"
                            data-dpc-sort-type="number"
                            data-dpc-sort-default="desc"
                            title="Sort by required increase">Gap</button>
                  </th>
                  <th data-dpc-sort-type="number" data-dpc-sort-key="recommended-price">Recommended Price</th>
                  <th class="dpc-suggested-price-head" data-dpc-sort-type="number" data-dpc-sort-key="suggested-price">Suggested Price</th>
                  <th class="dpc-decision-head" data-dpc-sort-type="number" data-dpc-sort-key="decision">Decision</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($pricing_intelligence['checks'] as $check): ?>
                  <?php
                    $marginStatus = dpc_margin_status_meta($check);
                    $decision = dpc_website_decision_meta($check);
                    $marginRank = dpc_margin_sort_rank($check);
                    $gap = (float)($check['gap_to_recommended'] ?? 0);
                    $healthSort = ($marginRank * 1000000000000) - $gap;
                  ?>
                  <tr data-dpc-filter-scope="website" data-dpc-filter-status="<?=dpc_status_key($check['status'])?>">
                    <td><?=esc(dpc_tld_label($check['tld_name']))?></td>
                    <td><?=esc($check['type_label'])?></td>
                    <td data-sort-value="<?=esc((string)($check['website_price'] ?? ''))?>"><?=dpc_money($check['website_price'])?></td>
                    <td data-sort-value="<?=esc((string)($check['lowest_cost'] ?? ''))?>"><?=dpc_money($check['lowest_cost'])?></td>
                    <td data-sort-value="<?=esc((string)$healthSort)?>"
                        data-sort-delta="<?=esc((string)($check['target_margin_delta'] ?? ''))?>"
                        data-sort-gap="<?=esc((string)($check['gap_to_recommended'] ?? ''))?>">
                      <div class="dpc-margin-status" title="Target margin: <?=number_format(DPC_TARGET_MARGIN_RATE * 100, 0)?>%">
                        <span class="dpc-severity dpc-severity-<?=$marginStatus['badge_class']?>"><?=esc($marginStatus['label'])?></span>
                        <small><?=esc($marginStatus['detail'])?></small>
                      </div>
                    </td>
                    <td data-sort-value="<?=esc((string)($check['recommended_price'] ?? ''))?>">
                      <div class="dpc-price-explainer">
                        <strong><?=dpc_money($check['recommended_price'])?></strong>
                        <small>exact 30% target</small>
                      </div>
                    </td>
                    <td class="dpc-suggested-price-cell" data-sort-value="<?=esc((string)($check['suggested_rounded_price'] ?? ''))?>">
                      <div class="dpc-price-explainer">
                        <strong><?=dpc_money($check['suggested_rounded_price'])?></strong>
                        <small>rounded publish price</small>
                      </div>
                    </td>
                    <td class="dpc-decision-cell" data-sort-value="<?=esc((string)$marginRank)?>" data-required-increase="<?=esc((string)($check['gap_to_recommended'] ?? ''))?>">
                      <div class="dpc-decision">
                        <strong><?=esc($decision['primary'])?></strong>
                        <small><?=esc($decision['secondary'])?></small>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>
        </div>
      </section>
    <?php endif; ?>
