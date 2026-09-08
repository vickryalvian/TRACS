import React, { useEffect, useMemo, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';
import '../../styles/tracs-tailwind.css';
import './styles.css';
import { createApiClient } from '../../lib/apiClient';
import { Button } from '../../components/ui/Button';
import { Card } from '../../components/ui/Card';

const api = createApiClient();
const emptyFilters = { scope: 'mine', q: '', owner_user_id: '', status: '', billing_status: '', attention: '', service_type: '', service_status: '', renewal_window: '', pic: '', tax_status: '', renewal_from: '', renewal_to: '', signal: '', sort: 'attention_rank', direction: 'asc', page: '1' };
const detailTabs = ['overview', 'services', 'billing', 'renewals', 'followups', 'activity'];

function readFilters() {
  const params = new URLSearchParams(window.location.search);
  return Object.fromEntries(Object.entries(emptyFilters).map(([key, value]) => [key, params.get(key) ?? value]));
}

function clientUrl(id, tab = 'overview') {
  const params = new URLSearchParams(window.location.search);
  params.delete('id'); params.delete('tab');
  if (id) { params.set('id', id); params.set('tab', tab); }
  return `${id ? 'client-detail.php' : 'clients.php'}${params.size ? `?${params}` : ''}`;
}
const serviceTypes = ['Dedicated Server', 'Colocation', 'VPS', 'IP Transit', 'Cloud', 'Domain', 'SSL', 'Other'];
const serviceStatuses = ['active', 'monitoring', 'pending_renewal', 'suspended', 'terminated', 'inactive'];
const billingCycles = ['monthly', 'quarterly', 'semiannual', 'annual', 'one_time', 'custom'];

function icon(name, cls = 'tr:h-4 tr:w-4') {
  return <i data-lucide={name} className={cls} aria-hidden="true" />;
}

function label(value) {
  return String(value || '').replaceAll('_', ' ').replace(/\b\w/g, (m) => m.toUpperCase());
}

function money(value) {
  if (value === null || value === undefined || value === '') return '-';
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value));
}

function date(value) {
  if (!value) return '-';
  return new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(`${String(value).slice(0, 10)}T00:00:00+07:00`));
}

function daysUntil(value) {
  if (!value) return null;
  const today = new Date();
  const start = new Date(today.getFullYear(), today.getMonth(), today.getDate()).getTime();
  const target = new Date(`${String(value).slice(0, 10)}T00:00:00+07:00`).getTime();
  return Math.round((target - start) / 86400000);
}

function renewalTone(value, status) {
  if (['suspended', 'terminated'].includes(status)) return status;
  const days = daysUntil(value);
  if (days === null) return 'watch';
  if (days <= 7) return 'critical';
  if (days <= 30) return 'warning';
  return 'active';
}

function renewalLabel(value) {
  const days = daysUntil(value);
  if (days === null) return 'No renewal date';
  if (days < 0) return `${Math.abs(days)} days overdue`;
  if (days === 0) return 'Due today';
  return `${days} days left`;
}

function badgeClass(level) {
  return {
    critical: 'tr:border-tracs-danger-border tr:bg-tracs-danger-soft tr:text-tracs-danger',
    warning: 'tr:border-tracs-warning-border tr:bg-tracs-warning-soft tr:text-tracs-warning',
    due: 'tr:border-tracs-info-border tr:bg-tracs-info-soft tr:text-tracs-info',
    watch: 'tr:border-tracs-border-strong tr:bg-tracs-surface-3 tr:text-tracs-secondary',
    active: 'tr:border-tracs-success-border tr:bg-tracs-success-soft tr:text-tracs-success',
    paid: 'tr:border-tracs-success-border tr:bg-tracs-success-soft tr:text-tracs-success',
    sent: 'tr:border-tracs-info-border tr:bg-tracs-info-soft tr:text-tracs-info',
    overdue: 'tr:border-tracs-danger-border tr:bg-tracs-danger-soft tr:text-tracs-danger',
    suspended: 'tr:border-tracs-danger-border tr:bg-tracs-danger-soft tr:text-tracs-danger',
    terminated: 'tr:border-tracs-border-strong tr:bg-tracs-surface-3 tr:text-tracs-secondary',
    pending_renewal: 'tr:border-tracs-warning-border tr:bg-tracs-warning-soft tr:text-tracs-warning',
  }[level] || 'tr:border-tracs-border tr:bg-tracs-surface-2 tr:text-tracs-secondary';
}

function Badge({ children, tone }) {
  return <span className={`tr:inline-flex tr:items-center tr:rounded-tracs-sm tr:border tr:px-2 tr:py-0.5 tr:text-[11px] tr:font-semibold ${badgeClass(tone)}`}>{children}</span>;
}

function useClients(filters, enabled = true) {
  const [state, setState] = useState({ loading: true, error: '', data: { clients: [], summary: {}, attention: [] } });
  const query = useMemo(() => {
    const params = new URLSearchParams();
    Object.entries(filters).forEach(([key, value]) => value && params.set(key, value));
    return params.toString();
  }, [filters]);
  const [revision, setRevision] = useState(0);
  useEffect(() => {
    if (!enabled) return;
    const controller = new AbortController();
    setState((s) => ({ ...s, loading: true, error: '' }));
    const timer = setTimeout(async () => {
      try {
        const res = await api.request(`/api/v1/client-portfolio/clients.php?${query}`, { signal: controller.signal });
        if (!controller.signal.aborted) setState({ loading: false, error: '', data: res.data });
      } catch (error) {
        if (!controller.signal.aborted) setState((s) => ({ ...s, loading: false, error: error.message }));
      }
    }, 200);
    return () => { clearTimeout(timer); controller.abort(); };
  }, [query, revision, enabled]);
  return { ...state, refresh: () => setRevision((value) => value + 1) };
}

function useContextData() {
  const [state, setState] = useState({ loading: true, error: '', data: null });
  async function load() {
    try {
      const res = await api.request('/api/v1/client-portfolio/context.php');
      setState({ loading: false, error: '', data: res.data });
    } catch (error) {
      setState({ loading: false, error: error.message, data: null });
    }
  }
  useEffect(() => { load(); }, []);
  return state;
}

function Field({ label: title, children }) {
  return <label className="tr:flex tr:min-w-0 tr:flex-col tr:gap-1 tr:text-xs tr:font-semibold tr:text-tracs-secondary"><span>{title}</span>{children}</label>;
}

function Input(props) {
  return <input {...props} className="tr:min-h-9 tr:rounded-tracs tr:border tr:border-tracs-border tr:bg-tracs-card tr:px-3 tr:text-sm tr:text-tracs-primary tr:outline-none tr:focus:border-tracs-accent" />;
}

function Select(props) {
  return <select {...props} className="tr:min-h-9 tr:rounded-tracs tr:border tr:border-tracs-border tr:bg-tracs-card tr:px-3 tr:text-sm tr:text-tracs-primary tr:outline-none tr:focus:border-tracs-accent" />;
}

function Textarea(props) {
  return <textarea {...props} className="tr:min-h-20 tr:rounded-tracs tr:border tr:border-tracs-border tr:bg-tracs-card tr:px-3 tr:py-2 tr:text-sm tr:text-tracs-primary tr:outline-none tr:focus:border-tracs-accent" />;
}

function FormPanel({ context, selected, onSaved, onCancel }) {
  const [form, setForm] = useState({
    company_name: selected?.company_name || '',
    client_code: selected?.client_code || '',
    status: selected?.status || 'active',
    owner_user_id: selected?.owner_user_id || context?.user?.id || '',
    contact_name: selected?.contacts?.[0]?.name || selected?.primary_contact_name || '',
    contact_email: selected?.contacts?.[0]?.email || '',
    contact_phone: selected?.contacts?.[0]?.phone || '',
    contact_role: selected?.contacts?.[0]?.role_title || '',
    notes: selected?.notes || '',
  });
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const canPickOwner = Boolean(context?.allowed_actions?.view_all);
  function set(key, value) { setForm((current) => ({ ...current, [key]: value })); }
  async function submit(event) {
    event.preventDefault();
    setSaving(true); setError('');
    try {
      const path = selected ? `/api/v1/client-portfolio/client.php?id=${selected.id}` : '/api/v1/client-portfolio/clients.php';
      const res = await api.request(path, { method: selected ? 'PATCH' : 'POST', body: form });
      onSaved(res.data, event.nativeEvent.submitter?.value === 'services');
    } catch (err) {
      setError(err.message);
    } finally {
      setSaving(false);
    }
  }
  return (
    <Card className="tr:p-tracs-4">
      <form className="tr:flex tr:flex-col tr:gap-tracs-3" onSubmit={submit}>
        <div className="tr:flex tr:items-start tr:justify-between tr:gap-tracs-3">
          <div><h2 id="client-form-title" className="tr:text-sm tr:font-semibold">{selected ? 'Edit Client' : 'Add Client'}</h2><p className="tr:mt-1 tr:text-xs tr:text-tracs-muted">{selected ? 'Company information and primary PIC.' : 'Start with the company and primary PIC. Add services after saving.'}</p></div>
          <Button variant="quiet" size="compact" disabled={saving} onClick={onCancel}>{icon('x')}Close</Button>
        </div>
        {error && <div className="tr:rounded-tracs tr:border tr:border-tracs-danger-border tr:bg-tracs-danger-soft tr:p-3 tr:text-xs tr:text-tracs-danger">{error}</div>}
        <div className="tr:grid tr:grid-cols-1 tr:gap-tracs-3 tr:md:grid-cols-2">
          <Field label="Company"><Input required value={form.company_name} onChange={(e) => set('company_name', e.target.value)} /></Field>
          <Field label="Client Code"><Input maxLength={40} placeholder="Assigned on save if blank" value={form.client_code || ''} onChange={(e) => set('client_code', e.target.value)} /></Field>
          <Field label="Status"><Select value={form.status} onChange={(e) => set('status', e.target.value)}><option value="active">Active</option><option value="monitoring">Monitoring</option><option value="inactive">Inactive</option></Select></Field>
          <Field label="Owner"><Select disabled={!canPickOwner} value={form.owner_user_id} onChange={(e) => set('owner_user_id', e.target.value)}>{canPickOwner ? context.users.map((u) => <option key={u.id} value={u.id}>{u.name}</option>) : <option value={context?.user?.id}>{context?.user?.name}</option>}</Select></Field>
          <Field label="PIC Name"><Input value={form.contact_name} onChange={(e) => set('contact_name', e.target.value)} /></Field>
          <Field label="PIC Email"><Input type="email" value={form.contact_email} onChange={(e) => set('contact_email', e.target.value)} /></Field>
          <Field label="PIC Phone"><Input value={form.contact_phone} onChange={(e) => set('contact_phone', e.target.value)} /></Field>
          <Field label="PIC Role"><Input value={form.contact_role} onChange={(e) => set('contact_role', e.target.value)} /></Field>
        </div>
        <Field label="Operational Notes"><Textarea value={form.notes} onChange={(e) => set('notes', e.target.value)} /></Field>
        <div className="tr:flex tr:flex-wrap tr:justify-end tr:gap-tracs-2"><Button variant="secondary" disabled={saving} onClick={onCancel}>Cancel</Button><Button disabled={saving} type="submit">{saving ? 'Saving...' : selected ? 'Save Client' : 'Save & Finish Later'}</Button>{!selected && <Button variant="primary" disabled={saving} type="submit" value="services">Save & Add Services</Button>}</div>
      </form>
    </Card>
  );
}

function QuickActionForm({ selected, context, onSaved, initialKind = 'followup' }) {
  const [kind, setKind] = useState(initialKind);
  const [form, setForm] = useState({});
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  if (!context?.allowed_actions?.manage || !selected) return null;
  function set(key, value) { setForm((current) => ({ ...current, [key]: value })); }
  async function submit(event) {
    event.preventDefault();
    setSaving(true); setError('');
    try {
      const action = kind === 'service' ? 'add_service' : kind === 'addon' ? 'add_addon' : kind === 'renewal' ? 'renew_service' : kind === 'billing' ? 'add_billing' : 'add_followup';
      const res = await api.request('/api/v1/client-portfolio/actions.php', { method: 'POST', body: { ...form, action, client_id: selected.id } });
      setForm({});
      onSaved(res.data);
    } catch (err) {
      setError(err.message);
    } finally {
      setSaving(false);
    }
  }
  return (
    <Card className="tr:p-tracs-4">
      <form className="tr:flex tr:flex-col tr:gap-tracs-3" onSubmit={submit}>
        <div className="tr:flex tr:flex-col tr:gap-tracs-2 tr:sm:flex-row tr:sm:items-center tr:sm:justify-between"><h3 className="tr:text-sm tr:font-semibold">Add Operational Record</h3><Select value={kind} onChange={(e) => { setKind(e.target.value); setForm({}); }}><option value="followup">Follow-up</option><option value="service">Service</option><option value="addon">Addon</option><option value="renewal">Renew Service</option><option value="billing">Billing</option></Select></div>
        {error && <div className="tr:rounded-tracs tr:border tr:border-tracs-danger-border tr:bg-tracs-danger-soft tr:p-3 tr:text-xs tr:text-tracs-danger">{error}</div>}
        {kind === 'service' && <div className="tr:grid tr:grid-cols-1 tr:gap-tracs-3 tr:md:grid-cols-2"><Field label="Service Name"><Input required value={form.service_name || ''} onChange={(e) => set('service_name', e.target.value)} /></Field><Field label="Type"><Select value={form.service_type || ''} onChange={(e) => set('service_type', e.target.value)}><option value="">Choose type</option>{serviceTypes.map((type) => <option key={type} value={type}>{type}</option>)}</Select></Field><Field label="Reference"><Input value={form.service_reference || ''} onChange={(e) => set('service_reference', e.target.value)} /></Field><Field label="Billing Cycle"><Select value={form.billing_cycle || 'monthly'} onChange={(e) => set('billing_cycle', e.target.value)}>{billingCycles.map((cycle) => <option key={cycle} value={cycle}>{label(cycle)}</option>)}</Select></Field><Field label="Price"><Input type="number" min="0" value={form.price || ''} onChange={(e) => set('price', e.target.value)} /></Field><Field label="Billing Day"><Input type="number" min="1" max="31" value={form.billing_day || ''} onChange={(e) => set('billing_day', e.target.value)} /></Field><Field label="Start Date"><Input type="date" value={form.start_date || ''} onChange={(e) => set('start_date', e.target.value)} /></Field><Field label="Renewal Date"><Input type="date" value={form.renewal_date || ''} onChange={(e) => set('renewal_date', e.target.value)} /></Field><Field label="Status"><Select value={form.status || 'active'} onChange={(e) => set('status', e.target.value)}>{serviceStatuses.map((status) => <option key={status} value={status}>{label(status)}</option>)}</Select></Field><label className="tr:flex tr:min-h-9 tr:items-center tr:gap-2 tr:text-xs tr:font-semibold tr:text-tracs-secondary"><input type="checkbox" checked={Boolean(form.auto_renew)} onChange={(e) => set('auto_renew', e.target.checked)} /> Auto renew</label><Field label="Plan Spec"><Textarea value={form.plan_spec || ''} onChange={(e) => set('plan_spec', e.target.value)} /></Field></div>}
        {kind === 'addon' && <div className="tr:grid tr:grid-cols-1 tr:gap-tracs-3 tr:md:grid-cols-2"><Field label="Service"><Select required value={form.service_id || ''} onChange={(e) => set('service_id', e.target.value)}><option value="">Choose service</option>{selected.services?.map((s) => <option key={s.id} value={s.id}>{s.service_name}</option>)}</Select></Field><Field label="Addon Name"><Input required value={form.addon_name || ''} onChange={(e) => set('addon_name', e.target.value)} /></Field><Field label="Price"><Input type="number" min="0" value={form.price || ''} onChange={(e) => set('price', e.target.value)} /></Field><Field label="Billing Cycle"><Select value={form.billing_cycle || 'included'} onChange={(e) => set('billing_cycle', e.target.value)}><option value="included">Included</option>{billingCycles.map((cycle) => <option key={cycle} value={cycle}>{label(cycle)}</option>)}</Select></Field><Field label="Renewal Date"><Input type="date" value={form.renewal_date || ''} onChange={(e) => set('renewal_date', e.target.value)} /></Field><Field label="Status"><Select value={form.status || 'active'} onChange={(e) => set('status', e.target.value)}>{serviceStatuses.map((status) => <option key={status} value={status}>{label(status)}</option>)}</Select></Field><Field label="Notes"><Textarea value={form.notes || ''} onChange={(e) => set('notes', e.target.value)} /></Field></div>}
        {kind === 'renewal' && <div className="tr:grid tr:grid-cols-1 tr:gap-tracs-3 tr:md:grid-cols-2"><Field label="Service"><Select required value={form.service_id || ''} onChange={(e) => set('service_id', e.target.value)}><option value="">Choose service</option>{selected.services?.map((s) => <option key={s.id} value={s.id}>{s.service_name} · {date(s.renewal_date)}</option>)}</Select></Field><Field label="New Renewal Date"><Input required type="date" value={form.new_renewal_date || ''} onChange={(e) => set('new_renewal_date', e.target.value)} /></Field><Field label="New Price"><Input type="number" min="0" value={form.new_price || ''} onChange={(e) => set('new_price', e.target.value)} placeholder="Leave empty to keep current price" /></Field><Field label="Note"><Textarea value={form.note || ''} onChange={(e) => set('note', e.target.value)} /></Field></div>}
        {kind === 'billing' && <div className="tr:grid tr:grid-cols-1 tr:gap-tracs-3 tr:md:grid-cols-2"><Field label="Service"><Select value={form.service_id || ''} onChange={(e) => set('service_id', e.target.value)}><option value="">General billing</option>{selected.services?.map((s) => <option key={s.id} value={s.id}>{s.service_name}</option>)}</Select></Field><Field label="Invoice Number"><Input value={form.invoice_number || ''} onChange={(e) => set('invoice_number', e.target.value)} /></Field><Field label="Invoice Date"><Input type="date" value={form.invoice_date || ''} onChange={(e) => set('invoice_date', e.target.value)} /></Field><Field label="Due Date"><Input type="date" value={form.due_date || ''} onChange={(e) => set('due_date', e.target.value)} /></Field><Field label="Amount"><Input type="number" min="0" value={form.amount || ''} onChange={(e) => set('amount', e.target.value)} /></Field><Field label="Invoice Status"><Select value={form.invoice_status || 'upcoming'} onChange={(e) => set('invoice_status', e.target.value)}><option value="upcoming">Upcoming</option><option value="sent">Sent</option><option value="cancelled">Cancelled</option></Select></Field><Field label="Payment Status"><Select value={form.payment_status || 'waiting'} onChange={(e) => set('payment_status', e.target.value)}><option value="waiting">Waiting</option><option value="paid">Paid</option><option value="overdue">Overdue</option></Select></Field><Field label="Payment Date"><Input type="date" value={form.payment_date || ''} onChange={(e) => set('payment_date', e.target.value)} /></Field><label className="tr:flex tr:items-center tr:gap-2 tr:text-xs tr:font-semibold tr:text-tracs-secondary"><input type="checkbox" checked={Boolean(form.tax_invoice_required)} onChange={(e) => set('tax_invoice_required', e.target.checked)} /> Tax invoice required</label><Field label="Tax Invoice Number"><Input value={form.tax_invoice_number || ''} onChange={(e) => set('tax_invoice_number', e.target.value)} /></Field></div>}
        {kind === 'followup' && <div className="tr:grid tr:grid-cols-1 tr:gap-tracs-3 tr:md:grid-cols-2"><Field label="Title"><Input required value={form.title || ''} onChange={(e) => set('title', e.target.value)} placeholder="Send Invoice - PT Example" /></Field><Field label="Action Type"><Select value={form.action_type || 'general_followup'} onChange={(e) => set('action_type', e.target.value)}><option value="send_invoice">Send invoice</option><option value="check_payment">Check payment</option><option value="send_tax_invoice">Send tax invoice</option><option value="renewal">Renewal</option><option value="general_followup">General follow-up</option></Select></Field><Field label="Due At"><Input type="datetime-local" value={form.due_at || ''} onChange={(e) => set('due_at', e.target.value)} /></Field><Field label="Priority"><Select value={form.priority || 'medium'} onChange={(e) => set('priority', e.target.value)}><option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option><option value="critical">Critical</option></Select></Field><Field label="Assigned To"><Select value={form.assigned_to || context.user.id} onChange={(e) => set('assigned_to', e.target.value)}>{(context.users?.length ? context.users : [context.user]).map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}</Select></Field></div>}
        <div className="tr:flex tr:justify-end"><Button type="submit" variant="primary" disabled={saving}>{saving ? 'Saving...' : kind === 'renewal' ? 'Renew Service' : `Add ${label(kind)}`}</Button></div>
      </form>
    </Card>
  );
}

function Detail({ selected, context, onEdit, onSaved }) {
  const [tab, setTab] = useState(() => {
    const value = new URLSearchParams(window.location.search).get('tab');
    return detailTabs.includes(value) ? value : 'overview';
  });
  const [error, setError] = useState('');
  useEffect(() => {
    const sync = () => { const value = new URLSearchParams(window.location.search).get('tab'); setTab(detailTabs.includes(value) ? value : 'overview'); };
    window.addEventListener('popstate', sync);
    return () => window.removeEventListener('popstate', sync);
  }, []);
  if (!selected) return <Card className="tr:p-tracs-6"><div className="tr:text-sm tr:text-tracs-secondary">Select a client to inspect operational status.</div></Card>;
  function chooseTab(value) { setTab(value); window.history.pushState({}, '', clientUrl(selected.id, value)); }
  return (
    <div className="tr:flex tr:flex-col tr:gap-tracs-4">
      <a className="clients-link" href={clientUrl()}>Back to Clients</a>
      <Card className="tr:p-0">
        <div className="tr:flex tr:flex-col tr:gap-tracs-3 tr:border-b tr:border-tracs-border tr:bg-tracs-surface-2 tr:p-tracs-4 tr:lg:flex-row tr:lg:items-start tr:lg:justify-between">
          <div><h1 className="tr:text-lg tr:font-semibold">{selected.company_name}</h1><p className="tr:mt-1 tr:text-xs tr:text-tracs-muted">{selected.client_code || 'No code'} · {label(selected.status)} · {selected.service_count} Services · {selected.owner_name}</p></div>
          <div className="tr:flex tr:flex-wrap tr:gap-tracs-2"><Badge tone={selected.attention_level}>{selected.attention_reason}</Badge>{context?.allowed_actions?.manage && <Button size="compact" onClick={onEdit}>{icon('pencil')}Edit</Button>}</div>
        </div>
        <div className="tr:grid tr:grid-cols-2 tr:gap-px tr:bg-tracs-border tr:lg:grid-cols-6">
          {[['Active Services', selected.service_count], ['Addons', selected.addon_count], ['Monthly Recurring', money(selected.mrr_amount)], ['Paid So Far', money(selected.total_paid_amount)], ['Outstanding', money(selected.outstanding_amount)], ['Renewal', date(selected.nearest_renewal_date)]].map(([k, v]) => <div key={k} className="tr:bg-tracs-card tr:p-tracs-3"><div className="tr:text-[11px] tr:text-tracs-muted">{k}</div><div className="tr:mt-1 tr:text-sm tr:font-semibold">{v}</div></div>)}
        </div>
        <nav aria-label="Client sections" className="tr:flex tr:flex-wrap tr:border-b tr:border-tracs-border tr:px-tracs-3">{detailTabs.map((t) => <button key={t} aria-current={tab === t ? 'page' : undefined} className={`tr:border-b-2 tr:px-3 tr:py-3 tr:text-xs tr:font-semibold ${tab === t ? 'tr:border-tracs-accent tr:text-tracs-accent' : 'tr:border-transparent tr:text-tracs-secondary'}`} onClick={() => chooseTab(t)}>{label(t)}</button>)}</nav>
        <div className="tr:p-tracs-4">
          {error && <p role="alert">{error}</p>}
          {tab === 'overview' && <div className="tr:grid tr:grid-cols-1 tr:gap-tracs-3 tr:md:grid-cols-2"><Info title="Primary Contact" lines={[selected.contacts?.[0]?.name, selected.contacts?.[0]?.email, selected.contacts?.[0]?.phone].filter(Boolean)} /><Info title="Operational Notes" lines={[selected.notes || 'No notes yet.']} /><Info title="Next Action" lines={[selected.next_action, selected.next_action_due_at ? date(selected.next_action_due_at) : selected.attention_reason]} /><Info title="Spend Snapshot" lines={[`Paid so far: ${money(selected.total_paid_amount)}`, `Billed total: ${money(selected.lifetime_billed_amount)}`, `Outstanding: ${money(selected.outstanding_amount)}`, `Estimated monthly recurring: ${money(selected.mrr_amount)}`]} /></div>}
          {tab === 'services' && <ServiceCards services={selected.services} />}
          {tab === 'overview' && <Contacts selected={selected} context={context} onSaved={onSaved} />}
          {tab === 'renewals' && <Rows headers={['Service', 'Renewal', 'Time remaining', 'Status']} rows={(selected.services || []).filter((s) => !['inactive', 'terminated'].includes(s.status)).sort((a, b) => (a.renewal_date || '9999').localeCompare(b.renewal_date || '9999'))} empty="No active services to renew." render={(s) => <><td>{s.service_name}</td><td>{date(s.renewal_date)}</td><td><Badge tone={renewalTone(s.renewal_date, s.status)}>{renewalLabel(s.renewal_date)}</Badge></td><td>{label(s.status)}</td></>} />}
          {tab === 'billing' && <Billing selected={selected} />}
          {tab === 'followups' && <Rows headers={['Follow-up', 'Due date', 'Priority', 'Status', 'Action']} rows={selected.followups} empty="No follow-ups yet." render={(f) => <><td>{f.title}<small>{label(f.action_type)} · {f.assignee_name || '-'}</small></td><td>{date(f.due_at)}</td><td><Badge tone={f.priority}>{label(f.priority)}</Badge></td><td><Badge tone={f.status === 'completed' ? 'active' : 'watch'}>{label(f.status)}</Badge></td><td>{f.status !== 'completed' && context?.allowed_actions?.manage ? <Button size="compact" onClick={async () => { try { setError(''); const res = await api.request('/api/v1/client-portfolio/actions.php', { method: 'POST', body: { action: 'complete_followup', followup_id: f.id } }); onSaved(res.data); } catch (err) { setError(err.message); } }}>Done</Button> : '-'}</td></>} />}
          {tab === 'activity' && <div className="tr:flex tr:flex-col tr:gap-tracs-2">{selected.activity?.length ? selected.activity.map((a) => <div key={a.id} className="tr:rounded-tracs tr:border tr:border-tracs-border tr:p-tracs-3"><div className="tr:text-sm tr:font-medium">{a.summary}</div><div className="tr:mt-1 tr:text-xs tr:text-tracs-muted">{a.actor_name || 'System'} · {date(a.created_at)}</div></div>) : <div className="tr:text-sm tr:text-tracs-muted">No activity yet.</div>}</div>}
        </div>
      </Card>
      {['services', 'billing', 'renewals', 'followups'].includes(tab) && <QuickActionForm key={tab} initialKind={{ services: 'service', billing: 'billing', renewals: 'renewal', followups: 'followup' }[tab]} selected={selected} context={context} onSaved={onSaved} />}
    </div>
  );
}

function Info({ title, lines }) {
  return <div className="tr:rounded-tracs tr:border tr:border-tracs-border tr:bg-tracs-surface-2 tr:p-tracs-3"><div className="tr:text-xs tr:font-semibold tr:text-tracs-secondary">{title}</div>{lines.map((line, i) => <div key={i} className="tr:mt-1 tr:text-sm">{line}</div>)}</div>;
}

function ServiceCards({ services = [] }) {
  if (!services.length) return <div className="tr:rounded-tracs tr:border tr:border-dashed tr:border-tracs-border tr:p-tracs-4 tr:text-sm tr:text-tracs-muted">No services tracked yet.</div>;
  return (
    <div className="tr:grid tr:grid-cols-1 tr:gap-tracs-3">
      {services.map((service) => {
        const tone = renewalTone(service.renewal_date, service.status);
        return (
          <div key={service.id} className="service-card tr:rounded-tracs tr:border tr:border-tracs-border tr:bg-tracs-card tr:p-tracs-3">
            <div className="tr:flex tr:flex-col tr:gap-tracs-2 tr:md:flex-row tr:md:items-start tr:md:justify-between">
              <div className="tr:min-w-0">
                <div className="tr:flex tr:flex-wrap tr:items-center tr:gap-tracs-2">
                  {icon('server', 'tr:h-4 tr:w-4 tr:text-tracs-secondary')}
                  <h3 className="tr:text-sm tr:font-semibold">{service.service_name}</h3>
                  <Badge tone={service.status}>{label(service.status)}</Badge>
                  <Badge tone={tone}>{renewalLabel(service.renewal_date)}</Badge>
                </div>
                <p className="tr:mt-1 tr:text-xs tr:text-tracs-muted">{[service.service_type, service.service_reference].filter(Boolean).join(' · ') || 'No service type set'}</p>
                {service.plan_spec && <p className="tr:mt-2 tr:text-xs tr:text-tracs-secondary">{service.plan_spec}</p>}
              </div>
              <div className="tr:text-left tr:text-sm tr:font-semibold tr:md:text-right">
                <div>{money(service.price)}</div>
                <div className="tr:mt-1 tr:text-xs tr:font-medium tr:text-tracs-muted">{label(service.billing_cycle)}{Number(service.auto_renew) === 1 ? ' · Auto renew' : ''}</div>
              </div>
            </div>
            <div className="tr:mt-tracs-3 tr:flex tr:flex-wrap tr:gap-tracs-2">
              {(service.addons || []).length ? service.addons.map((addon) => <span key={addon.id} className="addon-chip tr:inline-flex tr:min-h-7 tr:items-center tr:gap-1 tr:rounded-tracs-sm tr:border tr:border-tracs-border tr:bg-tracs-surface-2 tr:px-2 tr:text-xs tr:text-tracs-secondary">{addon.name}<span className="tr:text-tracs-muted">{money(addon.price)} · {label(addon.billing_cycle)}</span></span>) : <span className="tr:text-xs tr:text-tracs-muted">No addons attached.</span>}
            </div>
            <div className="tr:mt-tracs-3 tr:grid tr:grid-cols-1 tr:gap-2 tr:border-t tr:border-tracs-border tr:pt-tracs-3 tr:text-xs tr:text-tracs-muted tr:md:grid-cols-3">
              <div>Start: {date(service.start_date)}</div>
              <div>Renewal: {date(service.renewal_date)}</div>
              <div>Billing day: {service.billing_day || '-'}</div>
            </div>
          </div>
        );
      })}
    </div>
  );
}

function Rows({ rows = [], headers = [], empty, render }) {
  const [page, setPage] = useState(1);
  const lastPage = Math.max(1, Math.ceil(rows.length / 25));
  const current = Math.min(page, lastPage);
  if (!rows.length) return <div className="tr:rounded-tracs tr:border tr:border-dashed tr:border-tracs-border tr:p-tracs-4 tr:text-sm tr:text-tracs-muted">{empty}</div>;
  return <><div className="clients-table-scroll tr:overflow-x-auto" tabIndex={0}><table className="tr:w-full tr:min-w-[680px] tr:text-left tr:text-sm">{headers.length > 0 && <thead><tr>{headers.map((title) => <th key={title} scope="col">{title}</th>)}</tr></thead>}<tbody>{rows.slice((current - 1) * 25, current * 25).map((row) => <tr key={row.id} className="tr:border-b tr:border-tracs-border last:tr:border-0">{render(row)}</tr>)}</tbody></table></div>{lastPage > 1 && <div className="clients-pagination"><Button disabled={current === 1} onClick={() => setPage(current - 1)}>Previous</Button><span>Page {current} of {lastPage}</span><Button disabled={current === lastPage} onClick={() => setPage(current + 1)}>Next</Button></div>}</>;
}

function Drawer({ children, onClose, titleId }) {
  const ref = useRef(null);
  const previousRef = useRef(null);
  function canClose() {
    return !ref.current?.querySelector('button[type="submit"]:disabled');
  }
  function requestClose() {
    onClose();
    setTimeout(() => previousRef.current?.focus(), 0);
  }
  useEffect(() => {
    const dialog = ref.current;
    const previous = document.activeElement;
    previousRef.current = previous;
    const overflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    dialog.showModal();
    return () => { dialog.close(); document.body.style.overflow = overflow; previous?.focus(); };
  }, []);
  return <dialog ref={ref} className="clients-drawer tracs-react-root clients-react-shell" aria-labelledby={titleId} onClick={(event) => {
    if (event.target === event.currentTarget && canClose()) requestClose();
  }} onCancel={(event) => {
    event.preventDefault();
    if (canClose()) requestClose();
  }}>{children}</dialog>;
}

function Contacts({ selected, context, onSaved }) {
  const [editing, setEditing] = useState(null);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  function set(key, value) { setEditing((current) => ({ ...current, [key]: value })); }
  async function save(event) {
    event.preventDefault(); setSaving(true); setError('');
    try {
      const res = await api.request('/api/v1/client-portfolio/actions.php', { method: 'POST', body: { ...editing, action: 'save_contact', client_id: selected.id, contact_id: editing.id } });
      onSaved(res.data); setEditing(null);
    } catch (err) { setError(err.message); } finally { setSaving(false); }
  }
  return <section className="tr:mt-tracs-4">
    <div className="clients-section-heading"><h2>PICs</h2>{context.allowed_actions.manage && <Button onClick={() => { setEditing({ name: '', email: '', phone: '', role_title: '', is_primary: false }); setError(''); }}>Add PIC</Button>}</div>
    <Rows headers={['Name', 'Email', 'Phone', 'Role', 'Actions']} rows={selected.contacts} empty="No PICs added yet." render={(contact) => <><td>{contact.name}{Number(contact.is_primary) === 1 && <small>Primary PIC</small>}</td><td>{contact.email || '-'}</td><td>{contact.phone || '-'}</td><td>{contact.role_title || '-'}</td><td>{context.allowed_actions.manage && <Button size="compact" onClick={() => { setEditing({ ...contact, is_primary: Number(contact.is_primary) === 1 }); setError(''); }}>Edit PIC</Button>}</td></>} />
    {editing && <Drawer onClose={() => setEditing(null)} titleId="pic-form-title"><form className="clients-contact-form" onSubmit={save}>
      <div className="clients-section-heading"><h2 id="pic-form-title">{editing.id ? 'Edit PIC' : 'Add PIC'}</h2><Button disabled={saving} onClick={() => setEditing(null)}>Close</Button></div>
      {error && <p role="alert">{error}</p>}
      <Field label="Name"><Input required maxLength={150} value={editing.name} onChange={(e) => set('name', e.target.value)} /></Field>
      <Field label="Email"><Input type="email" maxLength={190} value={editing.email || ''} onChange={(e) => set('email', e.target.value)} /></Field>
      <Field label="Phone"><Input maxLength={80} value={editing.phone || ''} onChange={(e) => set('phone', e.target.value)} /></Field>
      <Field label="Role"><Input maxLength={120} placeholder="Billing, Operations, Legal…" value={editing.role_title || ''} onChange={(e) => set('role_title', e.target.value)} /></Field>
      <label><input type="checkbox" checked={editing.is_primary} onChange={(e) => set('is_primary', e.target.checked)} /> Primary PIC</label>
      <Button variant="primary" type="submit" disabled={saving}>{saving ? 'Saving...' : 'Save PIC'}</Button>
    </form></Drawer>}
  </section>;
}

function Billing({ selected }) {
  const [payment, setPayment] = useState('');
  const [tax, setTax] = useState('');
  const rows = (selected.billing || []).filter((row) => (!payment || row.payment_status === payment) && (!tax || (Number(row.tax_invoice_required) === 1 && (tax === 'sent' ? Boolean(row.tax_invoice_sent_at) : !row.tax_invoice_sent_at))));
  return <div className="tr:flex tr:flex-col tr:gap-tracs-3">
    <div className="tr:flex tr:flex-wrap tr:gap-tracs-2"><Field label="Payment status"><Select value={payment} onChange={(e) => setPayment(e.target.value)}><option value="">All payments</option>{['waiting', 'paid', 'overdue'].map((v) => <option key={v} value={v}>{label(v)}</option>)}</Select></Field><Field label="Tax invoice"><Select value={tax} onChange={(e) => setTax(e.target.value)}><option value="">All tax invoices</option><option value="pending">Pending</option><option value="sent">Sent</option></Select></Field></div>
    <Rows key={payment + tax} headers={['Invoice / service', 'Invoice date', 'Due date', 'Amount', 'Invoice status', 'Payment', 'Tax invoice']} rows={rows} empty={selected.billing?.length ? 'No invoices match these filters.' : 'No billing records yet.'} render={(b) => <><td>{b.invoice_number || '-'}<small>{b.service_name || 'General billing'}</small></td><td>{date(b.invoice_date)}</td><td>{date(b.due_date)}</td><td>{money(b.amount)}</td><td>{label(b.invoice_status)}</td><td><Badge tone={b.payment_status}>{label(b.payment_status)}</Badge></td><td>{Number(b.tax_invoice_required) === 1 ? (b.tax_invoice_sent_at ? 'Sent' : 'Pending') : '-'}</td></>} />
  </div>;
}

function StatStrip({ summary, filters, setFilters }) {
  const signals = [
    ['Action Required', summary.action_required || 0, { attention: 'attention', signal: '' }, filters.attention === 'attention'],
    ['Invoice This Week', summary.invoice_this_week || 0, { attention: '', signal: 'invoice' }, filters.signal === 'invoice'],
    ['Services Renewing ≤30 Days', summary.renewal_soon || 0, { attention: '', signal: 'renewal' }, filters.signal === 'renewal'],
  ];
  return <div className="clients-stat-strip">
    <div className="clients-stat-groups">
      <section><h2>Attention signals</h2><div className="clients-stat-grid">{signals.map(([title, value, filter, active]) => <button key={title} className="client-stat-cell" aria-pressed={active} onClick={() => setFilters({ ...filters, ...filter, page: '1' })}><strong>{value}</strong><span>{title}</span></button>)}</div></section>
      <section><h2>Financial snapshot</h2><div className="clients-stat-grid">{[['Paid So Far', summary.total_paid_amount], ['Outstanding', summary.outstanding_amount], ['Est. Monthly Recurring', summary.mrr_amount]].map(([title, value]) => <div key={title} className="client-stat-cell"><strong>{money(value ?? 0)}</strong><span>{title}</span></div>)}</div></section>
    </div>
    <p className="clients-stat-note">Current filters · Waiting payment: {summary.waiting_payment || 0} · Tax invoice pending: {summary.tax_invoice_pending || 0}</p>
  </div>;
}

function FilterBar({ filters, setFilters, context }) {
  const [open, setOpen] = useState(false);
  const canViewAll = Boolean(context.allowed_actions.view_all);
  const ownerValue = filters.scope === 'mine' ? 'mine' : filters.owner_user_id ? `owner:${filters.owner_user_id}` : 'all';
  function set(key, value) { setFilters({ ...filters, [key]: value, page: '1' }); }
  function setOwner(value) { setFilters({ ...filters, scope: value === 'mine' ? 'mine' : 'all', owner_user_id: value.startsWith('owner:') ? value.slice(6) : '', page: '1' }); }
  const active = Object.entries(filters).filter(([key, value]) => value && value !== emptyFilters[key] && !['sort', 'direction', 'page', 'owner_user_id'].includes(key));
  const titles = { q: 'Search', scope: 'Owner scope', status: 'Status', service_type: 'Service', service_status: 'Service status', renewal_window: 'Renewal days', billing_status: 'Payment', tax_status: 'Tax invoice', pic: 'PIC', renewal_from: 'Renewal from', renewal_to: 'Renewal to', attention: 'Attention', signal: 'Signal' };
  return <div className="clients-filter-row">
    <div className="clients-primary-filters">
      <Field label="Search"><Input placeholder="Client, code, or PIC" value={filters.q} onChange={(e) => set('q', e.target.value)} /></Field>
      <Field label="Status"><Select value={filters.status} onChange={(e) => set('status', e.target.value)}><option value="">Any status</option>{['active', 'monitoring', 'inactive'].map((v) => <option key={v} value={v}>{label(v)}</option>)}</Select></Field>
      <Field label="Owner"><Select disabled={!canViewAll} value={ownerValue} onChange={(e) => setOwner(e.target.value)}><option value="mine">My Clients</option>{canViewAll && <option value="all">All Owners</option>}{canViewAll && context.users.map((u) => <option key={u.id} value={`owner:${u.id}`}>{u.name}</option>)}</Select></Field>
      <Field label="Renewal window"><Select value={filters.renewal_window} onChange={(e) => set('renewal_window', e.target.value)}><option value="">Any renewal</option>{[7, 30, 90].map((days) => <option key={days} value={days}>Within {days} days</option>)}</Select></Field>
      <Button onClick={() => setOpen(!open)} aria-expanded={open} aria-controls="client-more-filters">More Filters</Button>
      <Button onClick={() => { setFilters({ ...emptyFilters }); setOpen(false); }}>Clear filters</Button>
    </div>
    {open && <div id="client-more-filters" className="clients-more-filters">
      <Field label="Service type"><Select value={filters.service_type} onChange={(e) => set('service_type', e.target.value)}><option value="">Any service</option>{serviceTypes.map((v) => <option key={v}>{v}</option>)}</Select></Field>
      <Field label="Service status"><Select value={filters.service_status} onChange={(e) => set('service_status', e.target.value)}><option value="">Any service status</option>{serviceStatuses.map((v) => <option key={v} value={v}>{label(v)}</option>)}</Select></Field>
      <Field label="PIC"><Input value={filters.pic} onChange={(e) => set('pic', e.target.value)} /></Field>
      <Field label="Payment state"><Select value={filters.billing_status} onChange={(e) => set('billing_status', e.target.value)}><option value="">Any payment</option>{['waiting', 'paid', 'overdue'].map((v) => <option key={v} value={v}>{label(v)}</option>)}</Select></Field>
      <Field label="Tax invoice status"><Select value={filters.tax_status} onChange={(e) => set('tax_status', e.target.value)}><option value="">Any tax invoice</option><option value="pending">Pending</option><option value="sent">Sent</option></Select></Field>
      <Field label="Attention"><Select value={filters.attention} onChange={(e) => set('attention', e.target.value)}><option value="">Any attention</option>{['attention', 'critical', 'warning', 'due', 'watch'].map((v) => <option key={v} value={v}>{v === 'attention' ? 'Needs attention' : label(v)}</option>)}</Select></Field>
      <Field label="Renewal from"><Input type="date" max={filters.renewal_to || undefined} value={filters.renewal_from} onChange={(e) => set('renewal_from', e.target.value)} /></Field>
      <Field label="Renewal to"><Input type="date" min={filters.renewal_from || undefined} value={filters.renewal_to} onChange={(e) => set('renewal_to', e.target.value)} /></Field>
    </div>}
    {(active.length > 0 || filters.owner_user_id) && <div className="clients-active-filters" aria-label="Active filters">{active.map(([key, value]) => <Button key={key} size="compact" aria-label={`Remove ${titles[key]} filter`} onClick={() => key === 'scope' ? setOwner('mine') : set(key, emptyFilters[key])}>{titles[key]}: {label(value)} {icon('x')}</Button>)}{filters.owner_user_id && <Button size="compact" onClick={() => setOwner('all')}>Owner: {context.users.find((u) => String(u.id) === filters.owner_user_id)?.name || filters.owner_user_id} {icon('x')}</Button>}</div>}
  </div>;
}

function AttentionStrip({ clients, filters, setFilters }) {
  const count = clients.data.summary?.action_required || 0;
  const attention = clients.data.attention || [];
  return <section className={`clients-attention-area ${attention.length ? '' : 'is-empty'}`} aria-label="Needs attention">
    <div className="clients-attention-heading">
      <h2>Needs Attention</h2>
      {count > 0 && <Button size="compact" onClick={() => setFilters({ ...filters, attention: 'attention', signal: '', page: '1' })}>View all ({count})</Button>}
    </div>
    <div className="clients-attention-strip">
      {attention.length ? attention.map((c) => <a className="client-row" href={clientUrl(c.id, c.next_action === 'Review renewal' ? 'renewals' : 'billing')} key={c.id}><strong>{c.company_name}</strong><span>{c.attention_reason}</span><small>{c.next_action_due_at ? date(c.next_action_due_at) : 'No due date'} · {c.next_action}</small></a>) : <p>{clients.loading ? 'Checking attention signals…' : 'No clients need attention in this view.'}</p>}
    </div>
  </section>;
}

function ClientTable({ data, filters, setFilters, loading, canManage, onAdd, highlighted }) {
  const columns = [['company_name', 'Company'], ['client_code', 'Code'], ['owner_name', 'Owner'], ['status', 'Status'], ['primary_contact_name', 'Primary PIC'], ['nearest_renewal_date', 'Next Renewal'], ['outstanding_amount', 'Outstanding (Rp)'], ['attention_rank', 'Attention']];
  const total = data.total ?? 0;
  const page = data.page ?? 1;
  const pages = Math.max(1, Math.ceil(total / (data.page_size || 25)));
  function sort(key) { setFilters({ ...filters, sort: key, direction: filters.sort === key && filters.direction === 'asc' ? 'desc' : 'asc', page: '1' }); }
  return <Card className="tr:p-0" aria-busy={loading}>
    <div className="clients-section-heading"><h2>{total} {total === 1 ? 'Client' : 'Clients'}</h2><span role="status">{loading ? 'Updating clients…' : `Page ${page} of ${pages}`}</span></div>
    {data.clients?.length ? <div className="clients-table-scroll" tabIndex={0} aria-label="Client roster"><table className="clients-roster"><caption className="tr:sr-only">Client roster. Select a company to view its details.</caption><thead><tr>{columns.map(([key, title]) => <th key={key} scope="col" aria-sort={filters.sort === key ? (filters.direction === 'desc' ? 'descending' : 'ascending') : 'none'}><button onClick={() => sort(key)}>{title}{filters.sort === key && icon(filters.direction === 'asc' ? 'chevron-up' : 'chevron-down')}</button></th>)}</tr></thead><tbody>{data.clients.map((c) => <tr key={c.id} className={String(highlighted) === String(c.id) ? 'is-selected' : ''} onClick={(event) => { if (!event.target.closest('a,button') && !window.getSelection()?.toString()) window.location.assign(clientUrl(c.id)); }}><td><a className="clients-link" href={clientUrl(c.id)}>{c.company_name}</a><small>{c.service_count} services</small></td><td>{c.client_code || '-'}</td><td>{c.owner_name}</td><td><Badge tone={c.status}>{label(c.status)}</Badge></td><td>{c.primary_contact_name || 'No PIC'}</td><td>{date(c.nearest_renewal_date)}</td><td className="clients-money">{money(c.outstanding_amount)}</td><td>{c.attention_level !== 'normal' ? <Badge tone={c.attention_level}>{c.attention_reason}</Badge> : '-'}</td></tr>)}</tbody></table></div> : !loading && <div className="clients-empty"><h3>{data.available_total === 0 ? 'No clients tracked yet' : 'No clients match your filters'}</h3><p>{data.available_total === 0 ? 'Add your first client, then set up their services and billing.' : 'Try another search or clear the filters to see your clients.'}</p>{data.available_total === 0 ? canManage && <Button variant="primary" onClick={onAdd}>Add Client</Button> : <Button onClick={() => setFilters({ ...emptyFilters, ...(filters.scope === 'mine' ? { scope: 'all' } : {}) })}>Clear filters</Button>}</div>}
    {pages > 1 && <div className="clients-pagination"><Button disabled={loading || page === 1} onClick={() => setFilters({ ...filters, page: String(page - 1) })}>Previous</Button><span>{(page - 1) * 25 + 1}–{Math.min(page * 25, total)} of {total}</span><Button disabled={loading || page >= pages} onClick={() => setFilters({ ...filters, page: String(page + 1) })}>Next</Button></div>}
  </Card>;
}

function ClientsApp() {
  const context = useContextData();
  const [filters, setFilters] = useState(readFilters);
  const isDetail = window.location.pathname.endsWith('/client-detail.php') || new URLSearchParams(window.location.search).has('id');
  const clients = useClients(filters, !isDetail && Boolean(context.data?.schema_ready));
  const [selected, setSelected] = useState(null);
  const [detailError, setDetailError] = useState('');
  const [editing, setEditing] = useState(false);
  const [creating, setCreating] = useState(false);
  const [highlighted, setHighlighted] = useState('');
  const [notice, setNotice] = useState('');
  const selectedId = new URLSearchParams(window.location.search).get('id');

  useEffect(() => { window.lucide?.createIcons(); });
  useEffect(() => {
    const sync = () => setFilters(readFilters());
    window.addEventListener('popstate', sync);
    return () => window.removeEventListener('popstate', sync);
  }, []);
  useEffect(() => {
    if (isDetail) return;
    const params = new URLSearchParams();
    Object.entries(filters).forEach(([key, value]) => { if (value && value !== emptyFilters[key]) params.set(key, value); });
    window.history.replaceState({}, '', `clients.php${params.size ? `?${params}` : ''}`);
  }, [filters, isDetail]);
  useEffect(() => {
    if (!isDetail || !context.data?.schema_ready) return;
    const controller = new AbortController();
    setDetailError('');
    if (!/^[1-9]\d*$/.test(selectedId || '')) { setDetailError('Client not found. Return to Clients to choose a record.'); return; }
    api.request(`/api/v1/client-portfolio/client.php?id=${selectedId}`, { signal: controller.signal })
      .then((res) => { if (!controller.signal.aborted) setSelected(res.data); })
      .catch((err) => { if (!controller.signal.aborted) setDetailError(err.message); });
    return () => controller.abort();
  }, [isDetail, selectedId, context.data?.schema_ready]);

  function afterSaved(client, services = false) {
    setEditing(false); setCreating(false);
    if (services) { window.location.assign(clientUrl(client.id, 'services')); return; }
    if (isDetail) { setSelected(client); setNotice('Client saved.'); }
    else { setHighlighted(client.id); setNotice(`${client.company_name} saved.`); clients.refresh(); }
  }
  const canManage = Boolean(context.data?.allowed_actions?.manage);
  const ready = !context.loading && !context.error && context.data?.schema_ready;
  return <div className="tracs-react-root clients-react-shell tr:flex tr:flex-col tr:gap-tracs-4">
    {!isDetail && <div className="clients-page-heading"><div><h1 className="tr:text-xl tr:font-semibold">Clients</h1><p className="tr:mt-1 tr:text-sm tr:text-tracs-muted">Find clients, review billing, and follow up on renewals.</p></div>{canManage && ready && <Button variant="primary" onClick={() => setCreating(true)}>{icon('plus-circle')}Add Client</Button>}</div>}
    {notice && <div role="status">{notice}{highlighted && <a className="clients-link tr:ml-2" href={clientUrl(highlighted)}>Go to client</a>}</div>}
    {context.loading ? <Card>Loading client portfolio…</Card> : context.error ? <Card><p role="alert">{context.error}</p><Button onClick={() => window.location.reload()}>Retry</Button></Card> : !context.data?.schema_ready ? <Card>Client Portfolio setup is incomplete. Contact your administrator.</Card> : isDetail ? detailError ? <Card><p role="alert">{detailError}</p><a className="clients-link" href={clientUrl()}>Back to Clients</a><Button onClick={() => window.location.reload()}>Retry</Button></Card> : selected ? <Detail selected={selected} context={context.data} onEdit={() => setEditing(true)} onSaved={afterSaved} /> : <Card>Loading client details…</Card> : <>
      <section className="clients-toolbar" aria-label="Client controls">
        <StatStrip summary={clients.data.summary || {}} filters={filters} setFilters={setFilters} />
        <FilterBar filters={filters} setFilters={setFilters} context={context.data} />
      </section>
      <AttentionStrip clients={clients} filters={filters} setFilters={setFilters} />
      {clients.error && <Card><p role="alert">{clients.error}</p><Button onClick={clients.refresh}>Retry</Button></Card>}
      <ClientTable data={clients.data} filters={filters} setFilters={setFilters} loading={clients.loading} canManage={canManage} onAdd={() => setCreating(true)} highlighted={highlighted} />
    </>}
    {(creating || editing) && ready && <Drawer onClose={() => { setCreating(false); setEditing(false); }} titleId="client-form-title"><FormPanel context={context.data} selected={editing ? selected : null} onSaved={afterSaved} onCancel={() => { setCreating(false); setEditing(false); }} /></Drawer>}
  </div>;
}

const root = document.getElementById('tracs-clients-root');
if (root) createRoot(root).render(<React.StrictMode><ClientsApp /></React.StrictMode>);
