import React, { useEffect, useMemo, useState } from 'react';
import { createRoot } from 'react-dom/client';
import '../../styles/tracs-tailwind.css';
import './styles.css';
import { Card } from '../../components/ui/Card';
import { Button } from '../../components/ui/Button';

const branches = [
  ['origin/main', '2026-06-14T23:22:01+07:00', '7037c97', 'V31 Callendar page'],
  ['origin/refactor/phase-2-react-tailwind-architecture', '2026-06-14T23:46:33+07:00', '1afc943', 'docs: plan React Tailwind architecture for TRACS refactor'],
  ['origin/refactor/phase-6-shift-assignment-api-contracts', '2026-06-15T01:31:54+07:00', 'e4eb8ec', 'chore: characterize Shift Assignment API contracts'],
  ['origin/refactor/phase-7-shift-assignment-read-api', '2026-06-15T07:08:57+07:00', '0590b8c', 'chore: add read-only Shift Assignment assignments API'],
  ['origin/refactor/phase-8-shift-assignment-react-shell', '2026-06-15T08:20:27+07:00', '1d02d16', 'feat: add read-only Shift Assignment React shell'],
  ['origin/refactor/phase-9-shift-assignment-react-preview', '2026-06-15T08:29:01+07:00', '6d543df', 'feat: add authenticated Shift Assignment React preview'],
  ['origin/refactor/phase-10-shift-preview-parity-testing', '2026-06-15T08:34:38+07:00', '70f2cce', 'test: add Shift Assignment preview parity validation'],
  ['origin/refactor/phase-11-shift-preview-internal-pilot', '2026-06-15T08:40:29+07:00', '480a217', 'chore: prepare Shift Assignment React internal pilot access'],
  ['origin/refactor/phase-12-shift-readonly-production-candidate', '2026-06-15T08:49:50+07:00', 'd7548e5', 'feat: harden Shift Assignment read-only production candidate'],
  ['origin/refactor/phase-13-shift-write-api-contracts', '2026-06-15T08:56:29+07:00', 'd3f4593', 'docs: define Shift Assignment write API contracts'],
  ['origin/refactor/phase-14-shift-create-assignment-api', '2026-06-15T09:08:34+07:00', '2d1dd81', 'feat: add controlled Shift Assignment create API'],
  ['origin/refactor/phase-15-shift-create-api-integration-testing', '2026-06-15T12:13:19+07:00', '830eaf0', 'test: add disposable DB validation for Shift Assignment create API'],
  ['origin/refactor/phase-16-shift-create-ui-pilot', '2026-06-15T12:34:22+07:00', '312db6e', 'feat: add controlled Shift Assignment create UI pilot'],
  ['origin/refactor/phase-17-shift-create-ui-staging-validation', '2026-06-15T12:45:07+07:00', 'd0affa4', 'test: validate Shift Assignment create UI pilot safely'],
  ['origin/refactor/phase-18-shift-update-assignment-api', '2026-06-15T12:54:07+07:00', 'a984729', 'feat: add controlled Shift Assignment update API'],
  ['origin/refactor/phase-19-shift-edit-ui-pilot', '2026-06-15T13:11:09+07:00', 'a1290c2', 'feat: add controlled Shift Assignment edit UI pilot'],
  ['origin/refactor/phase-20-shift-create-edit-hardening', '2026-06-15T18:46:59+07:00', 'fe67f64', 'test: harden Shift Assignment create edit pilot'],
  ['origin/refactor/phase-21-shift-delete-assignment-api', '2026-06-15T18:59:03+07:00', 'd0fbf12', 'feat: add controlled Shift Assignment delete API'],
  ['origin/refactor/phase-22-shift-delete-safeguards', '2026-06-15T19:06:25+07:00', 'f937d3d', 'docs: add Shift Assignment delete safety gate'],
  ['origin/refactor/phase-23-shift-delete-restore-drill', '2026-06-15T19:25:23+07:00', '5757729', 'test: prove Shift Assignment delete restoration drill'],
  ['origin/refactor/phase-24-shift-dependent-restore-drill', '2026-06-15T21:56:23+07:00', 'd032fdb', 'test: verify Shift Assignment dependent restore safety'],
  ['origin/refactor/phase-25-shift-delete-ui-pilot', '2026-06-16T04:18:21+07:00', '9eed15c', 'feat: add controlled Shift Assignment delete UI pilot'],
  ['origin/refactor/phase-26-shift-delete-hardening', '2026-06-16T04:45:07+07:00', 'de8b0c9', 'test: harden Shift Assignment delete pilot'],
  ['origin/refactor/phase-27-shift-template-api-contracts', '2026-06-16T05:15:32+07:00', 'd6b7516', 'docs: define Shift Assignment template API contracts'],
  ['origin/refactor/phase-28-shift-template-preview-api', '2026-06-16T05:27:49+07:00', '4dfec92', 'feat: add non-mutating Shift Assignment template preview API'],
  ['origin/refactor/phase-29-shift-template-preview-ui', '2026-06-16T05:44:58+07:00', 'd60c660', 'feat: add Shift Assignment template preview UI pilot'],
  ['origin/refactor/phase-30-shift-template-commit-contract-gate', '2026-06-20T18:32:53+07:00', '9a06808', 'docs: harden Shift Assignment template commit safety gate'],
  ['origin/refactor/phase-31-disposable-db-validation-gate', '2026-06-20T18:39:03+07:00', '79c8168', 'test: restore disposable DB validation gate'],
  ['origin/refactor/phase-32-shift-template-commit-api', '2026-06-20T18:51:39+07:00', '0535238', 'feat: add controlled Shift Assignment template commit API'],
  ['origin/refactor/phase-33-shift-template-commit-hardening', '2026-06-20T18:57:49+07:00', 'd5b5f2d', 'test: harden Shift Assignment template commit API'],
  ['origin/refactor/phase-34-shift-template-commit-ui-gate', '2026-06-20T19:03:32+07:00', '2da6ebc', 'docs: gate Shift Assignment template commit UI'],
  ['origin/refactor/phase-35-shift-template-apply-ui-pilot', '2026-06-20T19:20:55+07:00', '8a01bef', 'feat: add controlled Shift Assignment template apply UI pilot'],
  ['origin/refactor/phase-36-shift-template-apply-ui-hardening', '2026-06-20T19:40:04+07:00', 'b28ff80', 'test: harden Shift Assignment apply template UI'],
  ['origin/refactor/phase-37-auth-browser-validation-gate', '2026-06-20T20:20:38+07:00', 'e2e1388', 'test: restore Shift Assignment authenticated browser validation'],
  ['origin/refactor/phase-38-shift-copy-preview-contract', '2026-06-20T22:09:10+07:00', '1eac664', 'docs: define Shift Assignment copy preview contract'],
  ['origin/refactor/phase-39-shift-copy-preview-api', '2026-06-21T02:42:58+07:00', 'bcb8268', 'feat: add non-mutating Shift Assignment copy preview API'],
  ['origin/refactor/phase-40-shift-copy-preview-ui', '2026-06-21T03:36:46+07:00', '739b668', 'feat: add Shift Assignment copy preview UI pilot'],
  ['origin/refactor/phase-41-shift-copy-preview-ui-hardening', '2026-06-21T05:11:13+07:00', '2346e59', 'test: harden Shift Assignment copy preview UI'],
  ['origin/refactor/phase-42-shift-copy-commit-contract-gate', '2026-06-23T11:26:35+07:00', '9d37eda', 'docs: define Shift Assignment copy commit contract'],
  ['origin/refactor/phase-43-shift-copy-commit-environment-gate', '2026-06-29T13:48:26+07:00', '9297da9', 'Deployment summary and migration artifacts'],
  ['origin/feat/user-mgmt-auth-domain-ui-improvements', '2026-06-30T20:27:41+07:00', 'a68b450', 'docs: add multi-machine git sync workflow to project memory'],
  ['origin/chore/typography-windows-rendering-audit', '2026-07-01T01:12:27+07:00', 'f36e3b5', 'feat(user-management): add internal notes (supervisor-tier scope)'],
  ['origin/feat/dashboard-website-screenshot-widget', '2026-07-01T05:45:54+07:00', '81f5f25', 'docs(deploy): log Website Screenshot widget production deploy'],
  ['origin/feat/task-monitoring-mom-permission-revision', '2026-07-02T14:58:46+07:00', '3efa5c9', 'docs(deploy): log actions-header/view-details/toast deploy + prod data-loss incident'],
  ['origin/fix/monitoring-actions-filter-row-admin-rename', '2026-07-02T18:25:54+07:00', '7079daa', 'docs(deploy): log kebab trim, title-link, single-row filter, admin rename'],
  ['origin/fix/checklist-unsaved-changes-false-positive', '2026-07-02T23:43:32+07:00', '7216bdd', 'fix(alert-ticker): make announcements a shared public feed with live sync'],
  ['origin/design/ui-consistency-audit', '2026-07-03T13:02:42+07:00', '796a75d', 'docs(deploy): log continuous background ICMP monitoring deploy'],
  ['origin/design/sidebar-hover-expand', '2026-07-03T20:02:55+07:00', '2937ef3', 'docs(deploy): log sidebar visual-polish deploy'],
  ['origin/design/mom-audit-redesign', '2026-07-03T23:37:18+07:00', 'e0cb038', 'style(calendar): remove back-to-dashboard button next to page title'],
  ['origin/fix/monitoring-task-assignment-routing-and-modal', '2026-07-12T22:45:46+07:00', '378cbbe', 'docs(deploy): log task monitoring update'],
  ['origin/feature/realtime-form-workflow', '2026-07-14T18:30:53+07:00', '27547c0', 'feat: add realtime form workflows'],
  ['origin/codex/dashboard-tabs-calendar-checkbox-audit', '2026-07-15T18:59:51+07:00', 'cd80a48', 'docs: document latest deployment in summary'],
  ['origin/fix/intern-user-creation-audit', '2026-07-21T10:41:23+07:00', '2b4c271', 'docs(deploy): log no-reload save flow audit deployment'],
  ['origin/fix/cases-status-access-audit', '2026-07-21T11:22:45+07:00', '9e4831f', 'fix(cases): open status transitions to every cases.view holder'],
  ['origin/feat/dashboard-quick-tools-widgets', '2026-07-28T13:06:52+07:00', '7d2f8d9', 'Move billing balance into health grid'],
  ['origin/codex/abuse-reports-task-monitoring', '2026-08-28T22:27:26+07:00', '1197f87', 'Update domain transfer filters and registrar UI'],
  ['origin/codex/infrastructure-configurator-phase-2', '2026-08-31T09:19:17+07:00', '18c0cd9', 'Add abuse report delete actions'],
  ['origin/feat/client-portfolio-mvp', '2026-09-02T08:53:48+07:00', '045cf9d', 'Update configurator route and compact clients dashboard'],
].map(([name, createdAt, hash, subject], index) => ({
  id: name.replace(/^origin\//, '').replaceAll('/', '-'),
  name,
  createdAt,
  hash,
  subject,
  index,
  family: familyFor(name, subject),
  type: typeFor(name, subject),
}));

const familyMeta = {
  baseline: { label: 'Baseline', lane: 0, color: '#344054' },
  architecture: { label: 'Architecture', lane: 1, color: '#2563eb' },
  shift: { label: 'Shift assignment', lane: 2, color: '#0891b2' },
  operations: { label: 'Operations', lane: 3, color: '#16a34a' },
  fixes: { label: 'Fixes and recovery', lane: 4, color: '#dc2626' },
  design: { label: 'Design system', lane: 5, color: '#7c3aed' },
  codex: { label: 'Codex workstreams', lane: 6, color: '#c2410c' },
  clients: { label: 'Client portfolio', lane: 7, color: '#0f766e' },
};

function familyFor(name, subject) {
  const text = `${name} ${subject}`.toLowerCase();
  if (name === 'origin/main') return 'baseline';
  if (text.includes('react-tailwind') || text.includes('architecture')) return 'architecture';
  if (text.includes('shift')) return 'shift';
  if (text.includes('client')) return 'clients';
  if (name.includes('/fix/') || text.includes('incident') || text.includes('recovery')) return 'fixes';
  if (name.includes('/design/') || text.includes('style(') || text.includes('typography')) return 'design';
  if (name.includes('/codex/')) return 'codex';
  return 'operations';
}

function typeFor(name, subject) {
  const text = `${name} ${subject}`.toLowerCase();
  if (text.includes('test:') || text.includes('validation') || text.includes('audit') || text.includes('hardening')) return 'validation';
  if (text.includes('docs') || text.includes('contract') || text.includes('gate') || text.includes('summary')) return 'contract';
  if (name.includes('/fix/') || text.includes('fix(') || text.includes('incident')) return 'fix';
  if (name.includes('/design/') || text.includes('style(')) return 'design';
  if (text.includes('feat') || text.includes('mvp')) return 'feature';
  return 'ops';
}

function icon(name, cls = 'tr:h-4 tr:w-4') {
  return <i data-lucide={name} className={cls} aria-hidden="true" />;
}

function formatDate(value) {
  return new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric', timeZone: 'Asia/Jakarta' }).format(new Date(value));
}

function daysBetween(a, b) {
  return Math.round((new Date(b).getTime() - new Date(a).getTime()) / 86400000);
}

function hoursBetween(a, b) {
  return (new Date(b).getTime() - new Date(a).getTime()) / 3600000;
}

function shortName(name) {
  return name.replace(/^origin\//, '').replace(/^refactor\//, '');
}

function branchLabel(branch) {
  const phase = branch.name.match(/phase-(\d+)/)?.[1];
  return phase ? `P${phase}` : shortName(branch.name).split('/').at(-1).slice(0, 12);
}

function buildInsights(items) {
  const byFamily = Object.keys(familyMeta).map((key) => ({
    key,
    label: familyMeta[key].label,
    count: items.filter((branch) => branch.family === key).length,
  })).filter((item) => item.count > 0).sort((a, b) => b.count - a.count);
  const gaps = items.slice(1).map((branch, index) => ({
    from: items[index],
    to: branch,
    hours: hoursBetween(items[index].createdAt, branch.createdAt),
  })).sort((a, b) => b.hours - a.hours);
  const phaseBranches = items.filter((branch) => branch.name.includes('/phase-'));
  const validation = items.filter((branch) => branch.type === 'validation').length;
  const contracts = items.filter((branch) => branch.type === 'contract').length;
  return {
    byFamily,
    phaseBranches,
    validation,
    contracts,
    longestGap: gaps[0],
    totalDays: daysBetween(items[0].createdAt, items.at(-1).createdAt),
    first: items[0],
    latest: items.at(-1),
  };
}

function NetworkGraph({ items, selectedId, onSelect }) {
  const width = Math.max(1040, branches.length * 54);
  const height = 480;
  const xFor = (index) => 120 + index * ((width - 180) / Math.max(1, branches.length - 1));
  const yFor = (family) => 48 + familyMeta[family].lane * 50;
  const visibleIds = new Set(items.map((item) => item.id));
  const lines = items.slice(1).map((branch, index) => [items[index], branch]);

  return (
    <div className="branch-network-scroll" aria-label="Chronological branch graph">
      <svg className="branch-network-map" viewBox={`0 0 ${width} ${height}`} role="img" aria-labelledby="branch-network-title">
        <title id="branch-network-title">TRACS branch network from first branch to latest branch</title>
        {Object.entries(familyMeta).map(([key, meta]) => (
          <g key={key} opacity={items.some((item) => item.family === key) ? 1 : 0.24}>
            <line x1="34" x2={width - 34} y1={yFor(key)} y2={yFor(key)} className="branch-network-lane" />
            <text x="14" y={yFor(key) - 11} className="branch-network-lane-label">{meta.label}</text>
          </g>
        ))}
        {lines.map(([from, to]) => {
          const longGap = hoursBetween(from.createdAt, to.createdAt) > 120;
          return (
            <path
              key={`${from.id}-${to.id}`}
              d={`M ${xFor(from.index)} ${yFor(from.family)} C ${xFor(from.index) + 24} ${yFor(from.family)}, ${xFor(to.index) - 24} ${yFor(to.family)}, ${xFor(to.index)} ${yFor(to.family)}`}
              className={longGap ? 'branch-network-link is-gap' : 'branch-network-link'}
              opacity={visibleIds.has(from.id) && visibleIds.has(to.id) ? 1 : 0.2}
            />
          );
        })}
        {branches.map((branch) => {
          const isVisible = visibleIds.has(branch.id);
          const selected = selectedId === branch.id;
          const meta = familyMeta[branch.family];
          return (
            <g
              key={branch.id}
              className="branch-node"
              transform={`translate(${xFor(branch.index)} ${yFor(branch.family)})`}
              opacity={isVisible ? 1 : 0.16}
              role="button"
              tabIndex={isVisible ? 0 : -1}
              aria-label={`Inspect ${shortName(branch.name)}`}
              onClick={() => isVisible && onSelect(branch)}
              onKeyDown={(event) => {
                if (!isVisible || !['Enter', ' '].includes(event.key)) return;
                event.preventDefault();
                onSelect(branch);
              }}
            >
              <circle r="15" className="branch-node-hit" />
              <circle r={selected ? 10 : 7} fill={meta.color} className={selected ? 'is-selected' : ''} />
              <text y="-16" textAnchor="middle" className="branch-node-label">{branchLabel(branch)}</text>
            </g>
          );
        })}
      </svg>
    </div>
  );
}

function BranchDetail({ branch }) {
  return (
    <Card className="branch-detail tr:p-tracs-4">
      <div className="tr:flex tr:items-start tr:justify-between tr:gap-tracs-3">
        <div className="tr:min-w-0">
          <h2 className="tr:text-base tr:font-semibold tr:leading-tight">{shortName(branch.name)}</h2>
          <p className="tr:mt-1 tr:text-xs tr:text-tracs-muted">{formatDate(branch.createdAt)} at {new Date(branch.createdAt).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Jakarta' })}</p>
        </div>
        <span className={`branch-type is-${branch.type}`}>{branch.type}</span>
      </div>
      <p className="tr:mt-tracs-3 tr:text-sm tr:leading-6 tr:text-tracs-secondary">{branch.subject}</p>
      <div className="tr:mt-tracs-4 tr:grid tr:grid-cols-2 tr:gap-tracs-2 tr:text-xs">
        <div><span>Commit</span><strong>{branch.hash}</strong></div>
        <div><span>Family</span><strong>{familyMeta[branch.family].label}</strong></div>
      </div>
    </Card>
  );
}

function BranchNetworkApp() {
  const [family, setFamily] = useState('all');
  const [selected, setSelected] = useState(branches.at(-1));
  const items = useMemo(() => family === 'all' ? branches : branches.filter((branch) => branch.family === family), [family]);
  const insights = useMemo(() => buildInsights(branches), []);

  useEffect(() => { window.lucide?.createIcons(); });
  useEffect(() => {
    if (!items.some((branch) => branch.id === selected?.id)) setSelected(items[0] || branches.at(-1));
  }, [items, selected?.id]);

  return (
    <main className="tracs-react-root branch-network-shell">
      <section className="branch-network-head">
        <div>
          <h1>TRACS Branch Network</h1>
          <p>Chronological branch map from {formatDate(insights.first.createdAt)} to {formatDate(insights.latest.createdAt)}, ordered left to right from the first visible branch to the latest branch.</p>
        </div>
        <div className="branch-network-stamps" aria-label="Repository summary">
          <span><strong>{branches.length}</strong> branches</span>
          <span><strong>{insights.totalDays}</strong> days covered</span>
          <span><strong>{insights.phaseBranches.length}</strong> phase branches</span>
        </div>
      </section>

      <section className="branch-network-toolbar" aria-label="Branch filters">
        <div className="branch-filter-label">{icon('filter')} Family filter</div>
        <div className="branch-filter-buttons">
          <Button size="compact" variant={family === 'all' ? 'primary' : 'secondary'} onClick={() => setFamily('all')}>All</Button>
          {Object.entries(familyMeta).filter(([key]) => branches.some((branch) => branch.family === key)).map(([key, meta]) => (
            <Button key={key} size="compact" variant={family === key ? 'primary' : 'secondary'} onClick={() => setFamily(key)}>{meta.label}</Button>
          ))}
        </div>
      </section>

      <div className="branch-network-grid">
        <Card className="branch-map-panel tr:p-tracs-3">
          {items.length ? <NetworkGraph items={items} selectedId={selected?.id} onSelect={setSelected} /> : <div className="branch-empty">No branch family matches this filter.</div>}
        </Card>
        <aside className="branch-side">
          <BranchDetail branch={selected} />
          <Card className="branch-insights tr:p-tracs-4">
            <h2>{icon('scan-line')}Version System Insight</h2>
            <ul>
              <li><strong>Phased refactor spine.</strong> The phase branches form the dominant backbone, with API contracts, preview UI, gates, drills, and hardening split into traceable steps.</li>
              <li><strong>Safety before mutation.</strong> {insights.validation} validation or hardening branches and {insights.contracts} contract or gate branches show the team repeatedly boxed risk before write flows shipped.</li>
              <li><strong>Longest quiet gap.</strong> {Math.round(insights.longestGap.hours / 24)} days passed between {shortName(insights.longestGap.from.name)} and {shortName(insights.longestGap.to.name)}, marking the shift from July operations into late August Codex workstreams.</li>
              <li><strong>Current edge.</strong> The latest branch moves from infrastructure and abuse-report work into the client portfolio MVP, so the product frontier is now customer and service visibility.</li>
            </ul>
          </Card>
        </aside>
      </div>

      <section className="branch-family-strip" aria-label="Branch family distribution">
        {insights.byFamily.map((item) => (
          <div key={item.key} style={{ '--family-color': familyMeta[item.key].color }}>
            <span>{item.label}</span>
            <strong>{item.count}</strong>
          </div>
        ))}
      </section>
    </main>
  );
}

const root = document.getElementById('tracs-branch-network-root');
if (root) createRoot(root).render(<React.StrictMode><BranchNetworkApp /></React.StrictMode>);
