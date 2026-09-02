import React, { useEffect, useMemo, useState } from 'react';
import { createRoot } from 'react-dom/client';
import '../../styles/tracs-tailwind.css';
import './styles.css';
import { createApiClient } from '../../lib/apiClient';
import { Button } from '../../components/ui/Button';
import { Card } from '../../components/ui/Card';

const api = createApiClient();
const emptyFilters = { scope: 'mine', q: '', owner_user_id: '', status: '', billing_status: '', attention: '', service_type: '', service_status: '', renewal_window: '' };
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

function useClients(filters) {
  const [state, setState] = useState({ loading: true, error: '', data: { clients: [], summary: {}, attention: [] } });
  const query = useMemo(() => {
    const params = new URLSearchParams();
    Object.entries(filters).forEach(([key, value]) => value && params.set(key, value));
    return params.toString();
  }, [filters]);
  async function load() {
    setState((s) => ({ ...s, loading: true, error: '' }));
    try {
      const res = await api.request(`/api/v1/client-portfolio/clients.php${query ? `?${query}` : ''}`);
      setState({ loading: false, error: '', data: res.data });
    } catch (error) {
      setState((s) => ({ ...s, loading: false, error: error.message }));
    }
  }
  useEffect(() => { load(); }, [query]);
  return { ...state, refresh: load };
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
        <div className="tr:flex tr:items-start tr:justify-between tr:gap-tracs-3">
          <div><h2 className="tr:text-sm tr:font-semibold">Client Information</h2><p className="tr:mt-1 tr:text-xs tr:text-tracs-muted">Profile, primary PIC, owner, and operational notes.</p></div>
          <Button variant="quiet" size="compact" onClick={onCancel}>{icon('x')}Close</Button>
        </div>
        {error && <div className="tr:rounded-tracs tr:border tr:border-tracs-danger-border tr:bg-tracs-danger-soft tr:p-3 tr:text-xs tr:text-tracs-danger">{error}</div>}
        <div className="tr:grid tr:grid-cols-1 tr:gap-tracs-3 tr:md:grid-cols-2">
          <Field label="Company"><Input required value={form.company_name} onChange={(e) => set('company_name', e.target.value)} /></Field>
          <Field label="Client Code"><Input value={form.client_code || ''} onChange={(e) => set('client_code', e.target.value)} /></Field>
          <Field label="Status"><Select value={form.status} onChange={(e) => set('status', e.target.value)}><option value="active">Active</option><option value="monitoring">Monitoring</option><option value="inactive">Inactive</option></Select></Field>
          <Field label="Owner"><Select disabled={!canPickOwner} value={form.owner_user_id} onChange={(e) => set('owner_user_id', e.target.value)}>{canPickOwner ? context.users.map((u) => <option key={u.id} value={u.id}>{u.name}</option>) : <option value={context?.user?.id}>{context?.user?.name}</option>}</Select></Field>
          <Field label="PIC Name"><Input value={form.contact_name} onChange={(e) => set('contact_name', e.target.value)} /></Field>
          <Field label="PIC Email"><Input type="email" value={form.contact_email} onChange={(e) => set('contact_email', e.target.value)} /></Field>
          <Field label="PIC Phone"><Input value={form.contact_phone} onChange={(e) => set('contact_phone', e.target.value)} /></Field>
          <Field label="PIC Role"><Input value={form.contact_role} onChange={(e) => set('contact_role', e.target.value)} /></Field>
        </div>
        <Field label="Operational Notes"><Textarea value={form.notes} onChange={(e) => set('notes', e.target.value)} /></Field>
        <div className="tr:flex tr:justify-end tr:gap-tracs-2"><Button variant="secondary" onClick={onCancel}>Cancel</Button><Button variant="primary" disabled={saving} type="submit">{saving ? 'Saving...' : 'Save Client'}</Button></div>
      </form>
    </Card>
  );
}

function QuickActionForm({ selected, context, onSaved }) {
  const [kind, setKind] = useState('followup');
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
  const [tab, setTab] = useState('overview');
  if (!selected) return <Card className="tr:p-tracs-6"><div className="tr:text-sm tr:text-tracs-secondary">Select a client to inspect operational status.</div></Card>;
  const tabs = ['overview', 'services', 'billing', 'followups', 'activity'];
  return (
    <div className="tr:flex tr:flex-col tr:gap-tracs-4">
      <Card className="tr:p-0">
        <div className="tr:flex tr:flex-col tr:gap-tracs-3 tr:border-b tr:border-tracs-border tr:bg-tracs-surface-2 tr:p-tracs-4 tr:lg:flex-row tr:lg:items-start tr:lg:justify-between">
          <div><h1 className="tr:text-lg tr:font-semibold">{selected.company_name}</h1><p className="tr:mt-1 tr:text-xs tr:text-tracs-muted">{label(selected.status)} · {selected.service_count} Services · {selected.owner_name}</p></div>
          <div className="tr:flex tr:flex-wrap tr:gap-tracs-2"><Badge tone={selected.attention_level}>{selected.attention_reason}</Badge>{context?.allowed_actions?.manage && <Button size="compact" onClick={onEdit}>{icon('pencil')}Edit</Button>}</div>
        </div>
        <div className="tr:grid tr:grid-cols-2 tr:gap-px tr:bg-tracs-border tr:lg:grid-cols-6">
          {[['Active Services', selected.service_count], ['Addons', selected.addon_count], ['Monthly Recurring', money(selected.mrr_amount)], ['Paid So Far', money(selected.total_paid_amount)], ['Outstanding', money(selected.outstanding_amount)], ['Renewal', date(selected.nearest_renewal_date)]].map(([k, v]) => <div key={k} className="tr:bg-tracs-card tr:p-tracs-3"><div className="tr:text-[11px] tr:text-tracs-muted">{k}</div><div className="tr:mt-1 tr:text-sm tr:font-semibold">{v}</div></div>)}
        </div>
        <div className="tr:flex tr:flex-wrap tr:border-b tr:border-tracs-border tr:px-tracs-3">{tabs.map((t) => <button key={t} className={`tr:border-b-2 tr:px-3 tr:py-3 tr:text-xs tr:font-semibold ${tab === t ? 'tr:border-tracs-accent tr:text-tracs-accent' : 'tr:border-transparent tr:text-tracs-secondary'}`} onClick={() => setTab(t)}>{label(t)}</button>)}</div>
        <div className="tr:p-tracs-4">
          {tab === 'overview' && <div className="tr:grid tr:grid-cols-1 tr:gap-tracs-3 tr:md:grid-cols-2"><Info title="Primary Contact" lines={[selected.contacts?.[0]?.name, selected.contacts?.[0]?.email, selected.contacts?.[0]?.phone].filter(Boolean)} /><Info title="Operational Notes" lines={[selected.notes || 'No notes yet.']} /><Info title="Next Action" lines={[selected.next_action, selected.next_action_due_at ? date(selected.next_action_due_at) : selected.attention_reason]} /><Info title="Spend Snapshot" lines={[`Paid so far: ${money(selected.total_paid_amount)}`, `Billed total: ${money(selected.lifetime_billed_amount)}`, `Outstanding: ${money(selected.outstanding_amount)}`, `Estimated monthly recurring: ${money(selected.mrr_amount)}`]} /></div>}
          {tab === 'services' && <ServiceCards services={selected.services} />}
          {tab === 'billing' && <Rows rows={selected.billing} empty="No billing records yet." render={(b) => <><td>{b.invoice_number || '-'}<small>{b.service_name || 'General billing'}</small></td><td>{date(b.invoice_date)}</td><td>{date(b.due_date)}</td><td>{money(b.amount)}</td><td><Badge tone={b.payment_status}>{label(b.payment_status)}</Badge></td><td>{b.tax_invoice_required === '1' || b.tax_invoice_required === 1 ? (b.tax_invoice_sent_at ? 'Sent' : 'Pending') : '-'}</td></>} />}
          {tab === 'followups' && <Rows rows={selected.followups} empty="No follow-ups yet." render={(f) => <><td>{f.title}<small>{label(f.action_type)} · {f.assignee_name || '-'}</small></td><td>{date(f.due_at)}</td><td><Badge tone={f.priority}>{label(f.priority)}</Badge></td><td><Badge tone={f.status === 'completed' ? 'active' : 'watch'}>{label(f.status)}</Badge></td><td>{f.status !== 'completed' && context?.allowed_actions?.manage ? <Button size="compact" onClick={async () => { const res = await api.request('/api/v1/client-portfolio/actions.php', { method: 'POST', body: { action: 'complete_followup', followup_id: f.id } }); onSaved(res.data); }}>Done</Button> : '-'}</td></>} />}
          {tab === 'activity' && <div className="tr:flex tr:flex-col tr:gap-tracs-2">{selected.activity?.length ? selected.activity.map((a) => <div key={a.id} className="tr:rounded-tracs tr:border tr:border-tracs-border tr:p-tracs-3"><div className="tr:text-sm tr:font-medium">{a.summary}</div><div className="tr:mt-1 tr:text-xs tr:text-tracs-muted">{a.actor_name || 'System'} · {date(a.created_at)}</div></div>) : <div className="tr:text-sm tr:text-tracs-muted">No activity yet.</div>}</div>}
        </div>
      </Card>
      <QuickActionForm selected={selected} context={context} onSaved={onSaved} />
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

function Rows({ rows = [], empty, render }) {
  if (!rows.length) return <div className="tr:rounded-tracs tr:border tr:border-dashed tr:border-tracs-border tr:p-tracs-4 tr:text-sm tr:text-tracs-muted">{empty}</div>;
  return <div className="clients-table-scroll tr:overflow-x-auto"><table className="tr:w-full tr:min-w-[680px] tr:text-left tr:text-sm"><tbody>{rows.map((row) => <tr key={row.id} className="tr:border-b tr:border-tracs-border last:tr:border-0">{render(row)}</tr>)}</tbody></table></div>;
}

function ClientsApp() {
  const context = useContextData();
  const [filters, setFilters] = useState({ ...emptyFilters });
  const clients = useClients(filters);
  const [selected, setSelected] = useState(null);
  const [editing, setEditing] = useState(false);
  const [creating, setCreating] = useState(false);
  const selectedIdFromUrl = new URLSearchParams(window.location.search).get('id');

  useEffect(() => { window.lucide?.createIcons(); });
  useEffect(() => {
    const first = clients.data.clients?.find((c) => String(c.id) === String(selectedIdFromUrl)) || clients.data.clients?.[0] || null;
    if (!selected && first) loadDetail(first.id);
  }, [clients.data.clients]);

  async function loadDetail(id) {
    const res = await api.request(`/api/v1/client-portfolio/client.php?id=${id}`);
    setSelected(res.data);
    setEditing(false);
    setCreating(false);
    window.history.replaceState({}, '', id ? `client-detail.php?id=${id}` : 'clients.php');
  }

  async function afterSaved(client) {
    setSelected(client);
    setEditing(false);
    setCreating(false);
    clients.refresh();
  }

  const summary = clients.data.summary || {};
  const canManage = Boolean(context.data?.allowed_actions?.manage);
  return (
    <main className="tracs-react-root clients-react-shell tr:flex tr:flex-col tr:gap-tracs-4">
      <div className="tr:flex tr:flex-col tr:gap-tracs-3 tr:lg:flex-row tr:lg:items-end tr:lg:justify-between">
        <div><h1 className="tr:text-xl tr:font-semibold">Clients</h1><p className="tr:mt-1 tr:text-sm tr:text-tracs-muted">Owned portfolios, billing signals, invoice follow-ups, and renewal attention.</p></div>
        {canManage && <Button variant="primary" onClick={() => { setCreating(true); setEditing(false); }}>{icon('plus-circle')}Add Client</Button>}
      </div>
      {context.loading || clients.loading ? <Card>Loading client portfolio...</Card> : context.error || clients.error ? <Card className="tr:border-tracs-danger-border tr:text-tracs-danger">{context.error || clients.error}</Card> : !context.data?.schema_ready ? <Card>Run <code>config/migrations/2026_09_01_client_portfolio_mvp.sql</code>, then reload Clients.</Card> : (
        <>
          <div className="tr:grid tr:grid-cols-2 tr:gap-tracs-3 tr:lg:grid-cols-4 tr:xl:grid-cols-8">{[['Action Required', summary.action_required], ['Invoice This Week', summary.invoice_this_week], ['Waiting Payment', summary.waiting_payment], ['Tax Invoice Pending', summary.tax_invoice_pending], ['Renewal <= 30 Days', summary.renewal_soon], ['Paid So Far', money(summary.total_paid_amount)], ['Outstanding', money(summary.outstanding_amount)], ['Est. Monthly Recurring', money(summary.mrr_amount)]].map(([k, v]) => <Card key={k} className="tr:p-tracs-3"><div className="tr:text-lg tr:font-semibold">{v || 0}</div><div className="tr:mt-1 tr:text-xs tr:text-tracs-muted">{k}</div></Card>)}</div>
          <Card className="tr:p-tracs-3"><div className="tr:grid tr:grid-cols-1 tr:gap-tracs-2 tr:lg:grid-cols-[minmax(180px,1fr)_145px_145px_145px_145px_145px_145px_145px_120px]"><Input placeholder="Search client, code, PIC, email" value={filters.q} onChange={(e) => setFilters({ ...filters, q: e.target.value })} /><Select value={filters.scope} onChange={(e) => setFilters({ ...filters, scope: e.target.value, owner_user_id: e.target.value === 'mine' ? '' : filters.owner_user_id })}><option value="mine">My Clients</option>{context.data.allowed_actions.view_all && <option value="all">All Clients</option>}</Select>{context.data.allowed_actions.view_all && <Select value={filters.owner_user_id} onChange={(e) => setFilters({ ...filters, scope: 'all', owner_user_id: e.target.value })}><option value="">Any owner</option>{context.data.users.map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}</Select>}<Select value={filters.status} onChange={(e) => setFilters({ ...filters, status: e.target.value })}><option value="">Any client status</option><option value="active">Active</option><option value="monitoring">Monitoring</option><option value="inactive">Inactive</option></Select><Select value={filters.service_type} onChange={(e) => setFilters({ ...filters, service_type: e.target.value })}><option value="">Any service</option>{serviceTypes.map((type) => <option key={type} value={type}>{type}</option>)}</Select><Select value={filters.service_status} onChange={(e) => setFilters({ ...filters, service_status: e.target.value })}><option value="">Any service status</option>{serviceStatuses.map((status) => <option key={status} value={status}>{label(status)}</option>)}</Select><Select value={filters.renewal_window} onChange={(e) => setFilters({ ...filters, renewal_window: e.target.value })}><option value="">Any renewal</option><option value="7">Renewal &lt;= 7 days</option><option value="30">Renewal &lt;= 30 days</option><option value="90">Renewal &lt;= 90 days</option></Select><Select value={filters.attention} onChange={(e) => setFilters({ ...filters, attention: e.target.value })}><option value="">Any attention</option><option value="attention">Needs attention</option><option value="critical">Critical</option><option value="warning">Warning</option><option value="due">Due</option><option value="watch">Watch</option></Select><Button onClick={() => setFilters({ ...emptyFilters })}>{icon('rotate-ccw')}Reset</Button></div></Card>
          <div className="tr:grid tr:grid-cols-1 tr:gap-tracs-4 tr:xl:grid-cols-[390px_minmax(0,1fr)]">
            <div className="tr:flex tr:flex-col tr:gap-tracs-4">
              <Card className="tr:p-0"><div className="tr:border-b tr:border-tracs-border tr:p-tracs-3 tr:text-sm tr:font-semibold">Needs Attention</div><div className="tr:flex tr:flex-col">{clients.data.attention?.length ? clients.data.attention.map((c) => <button key={c.id} className="client-row tr:border-b tr:border-tracs-border tr:p-tracs-3 tr:text-left last:tr:border-0" onClick={() => loadDetail(c.id)}><div className="tr:flex tr:items-center tr:justify-between tr:gap-2"><strong>{c.company_name}</strong><Badge tone={c.attention_level}>{c.attention_reason}</Badge></div><div className="tr:mt-1 tr:text-xs tr:text-tracs-muted">{c.next_action} · {c.next_action_due_at ? date(c.next_action_due_at) : 'No date'}</div></button>) : <div className="tr:p-tracs-4 tr:text-sm tr:text-tracs-muted">No client needs immediate attention.</div>}</div></Card>
              <Card className="tr:p-0"><div className="tr:border-b tr:border-tracs-border tr:p-tracs-3 tr:text-sm tr:font-semibold">{clients.data.clients?.length || 0} Clients</div><div className="tr:flex tr:flex-col">{clients.data.clients?.length ? clients.data.clients.map((c) => <button key={c.id} className={`client-row tr:border-b tr:border-tracs-border tr:p-tracs-3 tr:text-left last:tr:border-0 ${selected?.id === c.id ? 'is-selected' : ''}`} onClick={() => loadDetail(c.id)}><div className="tr:flex tr:items-center tr:justify-between tr:gap-2"><strong>{c.company_name}</strong><Badge tone={c.status}>{label(c.status)}</Badge></div><div className="tr:mt-1 tr:text-xs tr:text-tracs-muted">{c.service_count} services · {c.addon_count} addons · {c.primary_contact_name || 'No PIC'}</div><div className="tr:mt-2 tr:grid tr:grid-cols-1 tr:gap-1 tr:text-xs tr:md:grid-cols-2"><span>Paid {money(c.total_paid_amount)}</span><span>Outstanding {money(c.outstanding_amount)}</span><span>MRR {money(c.mrr_amount)}</span><span>{c.next_action}</span></div></button>) : <div className="tr:p-tracs-6 tr:text-sm tr:text-tracs-muted">No clients tracked yet. Add a client to start tracking services, billing schedules, invoice follow-ups, and tax invoice reminders.</div>}</div></Card>
            </div>
            {creating ? <FormPanel context={context.data} onSaved={afterSaved} onCancel={() => setCreating(false)} /> : editing ? <FormPanel context={context.data} selected={selected} onSaved={afterSaved} onCancel={() => setEditing(false)} /> : <Detail selected={selected} context={context.data} onEdit={() => setEditing(true)} onSaved={afterSaved} />}
          </div>
        </>
      )}
    </main>
  );
}

const root = document.getElementById('tracs-clients-root');
if (root) createRoot(root).render(<React.StrictMode><ClientsApp /></React.StrictMode>);
