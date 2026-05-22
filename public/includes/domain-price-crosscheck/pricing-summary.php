<?php
/**
 * Domain Price Crosscheck pricing intelligence summary section.
 * Expects parent page variables and helper functions to be in scope.
 */
?>
    <?php if ($pricing_intelligence): ?>
    <!-- PRICING INTELLIGENCE SUMMARY -->
    <section class="panel dpc-intelligence-panel" id="pricing-summary">
      <div class="panel-head">
        <span class="panel-title">
          <i data-lucide="brain-circuit" class="icon-sm"></i>
          Pricing Intelligence Summary
        </span>
        <span class="panel-meta">Target margin <?=number_format(DPC_TARGET_MARGIN_RATE * 100, 0)?>% · rounded up to Rp<?=number_format(DPC_ROUNDING_INCREMENT, 0, ',', '.')?></span>
      </div>

      <div class="dpc-exec-grid">
        <?php
          $execCards = [
            ['Total TLDs Checked', count($active_tlds), 'Active TLDs in matrix', 'blue'],
            ['Below Cost', $pricing_intelligence['counts']['below_cost'], 'Website price below registrar cost', $pricing_intelligence['counts']['below_cost'] ? 'red' : 'green'],
            ['Below Target Margin', $pricing_intelligence['counts']['below_target'], 'Below 30% target margin', $pricing_intelligence['counts']['below_target'] ? 'yellow' : 'green'],
            ['Safe', $pricing_intelligence['counts']['safe'], 'Meets recommended website price', 'green'],
            ['Missing Data', $pricing_intelligence['counts']['missing'], 'Cost, rate, or website price missing', $pricing_intelligence['counts']['missing'] ? 'yellow' : 'blue'],
            ['Recommended Website Adjustments', $pricing_intelligence['counts']['recommended_adjustments'], 'Price checks needing adjustment', $pricing_intelligence['counts']['recommended_adjustments'] ? 'red' : 'green'],
            ['Estimated Margin Risk', dpc_money($pricing_intelligence['estimated_margin_risk']), 'Total gap to recommended price', $pricing_intelligence['estimated_margin_risk'] > 0 ? 'yellow' : 'green'],
            ['Pending Review', $pricing_intelligence['counts']['pending_review'], 'Cost increase or source-change checks', $pricing_intelligence['counts']['pending_review'] ? 'purple' : 'blue'],
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

      <?php if ($cctld_intelligence): ?>
      <div class="dpc-cctld-summary">
        <div class="dpc-section-head">
          <h3>ccTLD Summary</h3>
          <span>PANDI Registry Pricing vs IDCH ccTLD Pricing</span>
        </div>
        <div class="dpc-exec-grid dpc-exec-grid-compact">
          <?php
            $cctldCards = [
              ['Total ccTLDs Checked', count($active_cctlds), 'Default Indonesian ccTLD set', 'blue'],
              ['ccTLD Below Cost', $cctld_intelligence['counts']['below_cost'], 'IDCH ccTLD price below PANDI cost', $cctld_intelligence['counts']['below_cost'] ? 'red' : 'green'],
              ['ccTLD Below Target Margin', $cctld_intelligence['counts']['below_target'], 'Below 30% target margin', $cctld_intelligence['counts']['below_target'] ? 'yellow' : 'green'],
              ['ccTLD Safe', $cctld_intelligence['counts']['safe'], 'Meets recommended price', 'green'],
              ['ccTLD Missing Data', $cctld_intelligence['counts']['missing'], 'PANDI or IDCH ccTLD price missing', $cctld_intelligence['counts']['missing'] ? 'yellow' : 'blue'],
              ['ccTLD Recommended Adjustments', $cctld_intelligence['counts']['recommended_adjustments'], 'ccTLD prices needing adjustment', $cctld_intelligence['counts']['recommended_adjustments'] ? 'red' : 'green'],
            ];
          ?>
          <?php foreach ($cctldCards as $card): ?>
            <div class="dpc-intel-card <?=$card[3]?>">
              <span><?=$card[0]?></span>
              <strong><?=$card[1]?></strong>
              <small><?=$card[2]?></small>
            </div>
          <?php endforeach; ?>
        </div>
        <!-- Layout audit: long ccTLD detail rows are collapsed by default to keep the summary scan-friendly. -->
        <details class="dpc-collapsible">
          <summary class="dpc-collapsible-summary">
            <span>ccTLD Check Table</span>
            <small><?=count($cctld_intelligence['checks'])?> rows</small>
          </summary>
          <div class="table-container">
            <table class="table-dense dpc-adjustment-table">
              <thead>
                <tr>
                  <th>TLD</th>
                  <th>Type</th>
                  <th>PANDI Registry Cost</th>
                  <th>IDCH ccTLD Price</th>
                  <th>Recommended Price</th>
                  <th>Margin</th>
                  <th>Gap</th>
                  <th>Status</th>
                  <th>Suggested Action</th>
                  <th>Notes</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach (array_slice($cctld_intelligence['checks'], 0, 30) as $check): ?>
                  <tr data-summary-status="<?=dpc_status_key($check['status'])?>" data-summary-severity="<?=strtolower($check['severity'])?>">
                    <td><?=esc(strtoupper($check['tld_name']))?></td>
                    <td><?=esc($check['type_label'])?></td>
                    <td><?=dpc_money($check['pandi_cost'])?></td>
                    <td><?=dpc_money($check['idch_price'])?></td>
                    <td><?=dpc_money($check['recommended_price'])?></td>
                    <td><?=dpc_money($check['margin_amount'])?> / <?=dpc_percent($check['margin_percent'])?></td>
                    <td><?=dpc_money($check['gap_to_recommended'])?></td>
                    <td><span class="dpc-severity dpc-severity-<?=strtolower($check['severity'])?>"><?=esc($check['status'])?></span></td>
                    <td><?=esc($check['suggested_action'])?></td>
                    <td><?=esc($check['status'] === 'Safe' ? 'No price changes' : 'Review ccTLD pricing')?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </details>
      </div>
      <?php endif; ?>

      <div class="dpc-summary-filters" aria-label="Pricing summary filters">
        <?php foreach (['all' => 'All', 'critical' => 'Critical', 'below-cost' => 'Below Cost', 'below-target-margin' => 'Below Target Margin', 'missing-data' => 'Missing Data', 'registrar-cost-increased' => 'Price Increased', 'recommended-source-changed' => 'Source Changed', 'safe' => 'Safe'] as $filter => $label): ?>
          <button type="button" class="btn btn-ghost btn-sm <?=$filter === 'all' ? 'active' : ''?>" data-summary-filter="<?=$filter?>"><?=$label?></button>
        <?php endforeach; ?>
      </div>

      <div class="dpc-intel-layout">
        <section class="dpc-intel-section">
          <div class="dpc-section-head">
            <h3>Priority Findings</h3>
            <span><?=count($pricing_intelligence['checks'])?> checks</span>
          </div>
          <div class="dpc-findings-list">
            <?php foreach ($pricing_intelligence['checks'] as $check): ?>
              <?php
                $filterStatus = strtolower(str_replace(' ', '-', $check['status']));
                $filterSeverity = strtolower($check['severity']);
              ?>
              <article class="dpc-finding-row" data-summary-status="<?=$filterStatus?>" data-summary-severity="<?=$filterSeverity?>">
                <div class="dpc-finding-main">
                  <div>
                    <strong><?=esc(strtoupper($check['tld_name']))?> <?=$check['type_label']?></strong>
                    <?php if ($check['has_note']): ?><span class="dpc-note-chip"><?=esc($check['note_status'] ?: 'Has Note')?></span><?php endif; ?>
                  </div>
                  <span class="dpc-severity dpc-severity-<?=strtolower($check['severity'])?>"><?=esc($check['status'])?></span>
                </div>
                <div class="dpc-finding-metrics">
                  <span>Current Website Price <strong><?=dpc_money($check['website_price'])?></strong></span>
                  <span>Lowest Registrar Cost <strong><?=dpc_money($check['lowest_cost'])?></strong></span>
                  <span>Recommended Website Price <strong><?=dpc_money($check['recommended_price'])?></strong></span>
                  <span>Margin <strong><?=dpc_money($check['margin_amount'])?> / <?=dpc_percent($check['margin_percent'])?></strong></span>
                  <span>Gap <strong><?=dpc_money($check['gap_to_recommended'])?></strong></span>
                </div>
                <div class="dpc-finding-action"><?=esc($check['suggested_action'])?></div>
              </article>
            <?php endforeach; ?>
          </div>
        </section>

        <section class="dpc-intel-section">
          <div class="dpc-section-head">
            <h3>Registrar Source Summary</h3>
            <span>Lowest source counts</span>
          </div>
          <?php if (empty($pricing_intelligence['source_summary'])): ?>
            <div class="empty-small">No registrar cost data available.</div>
          <?php else: ?>
            <div class="dpc-source-list">
              <?php foreach ($pricing_intelligence['source_summary'] as $source): ?>
                <?php $avgAdvantage = !empty($source['advantage_count']) ? ($source['advantage_total'] / $source['advantage_count']) : null; ?>
                <div class="dpc-source-row">
                  <strong><?=esc($source['source_name'])?></strong>
                  <span><?=$source['count']?> lowest checks</span>
                  <small>Avg advantage vs next source: <?=dpc_money($avgAdvantage)?></small>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <div class="dpc-section-head dpc-subhead">
            <h3>Exchange Rate Impact</h3>
          </div>
          <?php if (!$pricing_intelligence['previous_month']): ?>
            <div class="empty-small">No previous month exchange rate data available.</div>
          <?php else: ?>
            <div class="dpc-rate-impact">
              <div><span>Previous</span><strong>Rp<?=number_format($pricing_intelligence['previous_exchange_rate'], 2)?></strong></div>
              <div><span>Current</span><strong>Rp<?=number_format((float)$month_data['exchange_rate_usd_idr'], 2)?></strong></div>
              <div><span>Difference</span><strong><?=dpc_money($pricing_intelligence['exchange_rate_diff'])?> / <?=dpc_percent($pricing_intelligence['exchange_rate_diff_pct'])?></strong></div>
            </div>
            <?php if (empty($pricing_intelligence['exchange_impacts'])): ?>
              <div class="empty-small">No USD-based lowest registrar cost is available for impact analysis.</div>
            <?php else: ?>
              <div class="dpc-mini-list">
                <?php foreach (array_slice($pricing_intelligence['exchange_impacts'], 0, 5) as $impact): ?>
                  <span><?=esc(strtoupper($impact['tld_name']))?> <?=$impact['type_label']?> · <?=dpc_money($impact['exchange_impact'])?></span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          <?php endif; ?>
        </section>
      </div>

      <!-- Layout audit: adjustment details stay available without forcing another full-height table into the page flow. -->
      <details class="dpc-intel-section dpc-collapsible dpc-adjustment-section">
        <summary class="dpc-collapsible-summary">
          <span>Recommended Website Price Adjustment</span>
          <small>Uses next Rp<?=number_format(DPC_ROUNDING_INCREMENT, 0, ',', '.')?> increment</small>
        </summary>
        <div class="table-container">
          <table class="table-dense dpc-adjustment-table">
            <thead>
              <tr>
                <th>TLD</th>
                <th>Type</th>
                <th>Current IDCH Website Price</th>
                <th>Lowest Registrar Cost</th>
                <th>Target Margin</th>
                <th>Recommended Website Price</th>
                <th>Required Increase</th>
                <th>Suggested Rounded Website Price</th>
                <th>Status</th>
                <th>Suggested Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($pricing_intelligence['checks'] as $check): ?>
                <?php if (!in_array($check['status'], ['Below Cost', 'Below Target Margin', 'Missing Data'], true)) continue; ?>
                <tr data-summary-status="<?=strtolower(str_replace(' ', '-', $check['status']))?>" data-summary-severity="<?=strtolower($check['severity'])?>">
                  <td><?=esc(strtoupper($check['tld_name']))?></td>
                  <td><?=esc($check['type_label'])?></td>
                  <td><?=dpc_money($check['website_price'])?></td>
                  <td><?=dpc_money($check['lowest_cost'])?></td>
                  <td><?=number_format(DPC_TARGET_MARGIN_RATE * 100, 0)?>%</td>
                  <td><?=dpc_money($check['recommended_price'])?></td>
                  <td><?=dpc_money($check['gap_to_recommended'])?></td>
                  <td><?=dpc_money($check['suggested_rounded_price'])?></td>
                  <td><span class="dpc-severity dpc-severity-<?=strtolower($check['severity'])?>"><?=esc($check['status'])?></span></td>
                  <td><?=esc($check['suggested_action'])?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </details>

      <div class="dpc-intel-layout">
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
                <span><?=esc(strtoupper($changed['tld_name']))?> <?=$changed['type_label']?> source changed to <?=esc($changed['lowest_source_name'] ?? '—')?></span>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>

        <section class="dpc-intel-section">
          <div class="dpc-section-head">
            <h3>Action Buckets</h3>
          </div>
          <div class="dpc-bucket-list">
            <?php foreach ($pricing_intelligence['action_buckets'] as $bucketName => $bucketItems): ?>
              <details <?=$bucketItems ? 'open' : ''?>>
                <summary><span><?=esc($bucketName)?></span><strong><?=count($bucketItems)?></strong></summary>
                <?php if (!$bucketItems): ?>
                  <div class="empty-small">No items.</div>
                <?php else: ?>
                  <div class="dpc-mini-list">
                    <?php foreach (array_slice($bucketItems, 0, 12) as $item): ?>
                      <span><?=esc(strtoupper($item['tld_name']))?> <?=$item['type_label']?></span>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </details>
            <?php endforeach; ?>
          </div>
        </section>
      </div>
    </section>
    <?php endif; ?>
