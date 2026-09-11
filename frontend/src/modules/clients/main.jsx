import React, { useEffect, useMemo, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { Plus, RotateCcw, Server, SlidersHorizontal, X, ChevronDown, ChevronUp } from 'lucide-react';
import '../../styles/tracs-tailwind.css';
import './styles.css';
import '../../../../assets/react/calendar/styles.css';
import { TracsModal } from '../../../../assets/react/calendar/components/TracsModal';
import { useCalendarData } from '../../../../assets/react/calendar/hooks/useCalendarData';
import { jakartaToday } from '../../../../assets/react/calendar/utils/date';
import { EventDetailPanel } from '../../../../assets/react/calendar/components/EventDetailPanel';
import { ClientCalendar } from './ClientCalendar';
import { createApiClient } from '../../lib/apiClient';
import { Button } from '../../components/ui/Button';
import { Card } from '../../components/ui/Card';

const api = createApiClient();
const emptyFilters = { scope: 'mine', q: '', owner_user_id: '', status: '', billing_status: '', attention: '', service_type: '', service_status: '', renewal_window: '' };
const serviceTypes = ['Dedicated Server', 'Colocation', 'VPS', 'IP Transit', 'Cloud', 'Domain', 'SSL', 'Other'];
const serviceStatuses = ['active', 'monitoring', 'pending_renewal', 'suspended', 'terminated', 'inactive'];
const billingCycles = ['monthly', 'quarterly', 'semiannual', 'annual', 'one_time', 'custom'];

function icon(name, cls = 'tr:h-4 tr:w-4') {
  const Icon = { plus: Plus, 'rotate-ccw': RotateCcw, server: Server, 'sliders-horizontal': SlidersHorizontal, x: X, 'chevron-down': ChevronDown, 'chevron-up': ChevronUp }[name];
  return Icon ? <Icon className={cls} aria-hidden="true" /> : null;
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
  const sequence = useRef(0);
  async function load() {
    const requestId = ++sequence.current;
    setState((s) => ({ ...s, loading: true, error: '' }));
    try {
      const res = await api.request(`/api/v1/client-portfolio/clients.php${query ? `?${query}` : ''}`);
      if (requestId !== sequence.current) return;
      setState({ loading: false, error: '', data: res.data });
    } catch (error) {
      if (requestId !== sequence.current) return;
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
    <div>
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
        {!selected && <>
          <h3 className="client-section-title">Service Information</h3>
          <div className="client-form-grid">
            <Field label="Service Name (optional)"><Input value={form.service_name || ''} onChange={e => set('service_name', e.target.value)} /></Field>
            <Field label="Service Type"><Select value={form.service_type || ''} onChange={e => set('service_type', e.target.value)}><option value="">Choose type</option>{serviceTypes.map(t => <option key={t}>{t}</option>)}</Select></Field>
            <Field label="Billing Cycle"><Select value={form.billing_cycle || 'monthly'} onChange={e => set('billing_cycle', e.target.value)}>{billingCycles.map(t => <option key={t} value={t}>{label(t)}</option>)}</Select></Field>
            <Field label="Price"><Input type="number" min="0" step="0.01" value={form.price || ''} onChange={e => set('price', e.target.value)} /></Field>
            <Field label="Start Date"><Input type="date" value={form.start_date || ''} onChange={e => set('start_date', e.target.value)} /></Field>
            <Field label="Renewal Date"><Input type="date" value={form.renewal_date || ''} onChange={e => set('renewal_date', e.target.value)} /></Field>
          </div>
          <h3 className="client-section-title">Billing / Administration</h3>
          <div className="client-form-grid">
            <Field label="Invoice Date"><Input type="date" value={form.invoice_date || ''} onChange={e => set('invoice_date', e.target.value)} /></Field>
            <Field label="Payment Due Date"><Input type="date" min={form.invoice_date || undefined} value={form.due_date || ''} onChange={e => set('due_date', e.target.value)} /></Field>
            <Field label="Invoice Amount"><Input type="number" min="0" step="0.01" value={form.amount || ''} onChange={e => set('amount', e.target.value)} /></Field>
            <Field label="Billing Day"><Input type="number" min="1" max="31" value={form.billing_day || ''} onChange={e => set('billing_day', e.target.value)} /></Field>
            <label><input type="checkbox" checked={Boolean(form.tax_invoice_required)} onChange={e => set('tax_invoice_required', e.target.checked)} /> Tax invoice required</label>
            {form.tax_invoice_required && <Field label="Send Tax Invoice On"><Input type="date" required value={form.tax_invoice_due_date || ''} onChange={e => set('tax_invoice_due_date', e.target.value)} /></Field>}
          </div>
          <p className="tr:text-xs tr:text-tracs-muted">Invoice and renewal dates create Calendar reminders. Payment terms are tracked by the payment due date.</p>
        </>}
        <Field label="Operational Notes"><Textarea value={form.notes} onChange={(e) => set('notes', e.target.value)} /></Field>
        <div className="tr:flex tr:justify-end tr:gap-tracs-2"><Button variant="secondary" onClick={onCancel}>Cancel</Button><Button variant="primary" disabled={saving} type="submit">{saving ? 'Saving...' : 'Save Client'}</Button></div>
      </form>
    </div>
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
    <div>
      <form className="tr:flex tr:flex-col tr:gap-tracs-3" onSubmit={submit}>
        <div className="tr:flex tr:flex-col tr:gap-tracs-2 tr:sm:flex-row tr:sm:items-center tr:sm:justify-between"><h3 className="tr:text-sm tr:font-semibold">Add Operational Record</h3><Select value={kind} onChange={(e) => { setKind(e.target.value); setForm({}); }}><option value="followup">Follow-up</option><option value="service">Service</option><option value="addon">Addon</option><option value="renewal">Renew Service</option><option value="billing">Billing</option></Select></div>
        {error && <div className="tr:rounded-tracs tr:border tr:border-tracs-danger-border tr:bg-tracs-danger-soft tr:p-3 tr:text-xs tr:text-tracs-danger">{error}</div>}
        {kind === 'service' && <div className="tr:grid tr:grid-cols-1 tr:gap-tracs-3 tr:md:grid-cols-2"><Field label="Service Name"><Input required value={form.service_name || ''} onChange={(e) => set('service_name', e.target.value)} /></Field><Field label="Type"><Select value={form.service_type || ''} onChange={(e) => set('service_type', e.target.value)}><option value="">Choose type</option>{serviceTypes.map((type) => <option key={type} value={type}>{type}</option>)}</Select></Field><Field label="Reference"><Input value={form.service_reference || ''} onChange={(e) => set('service_reference', e.target.value)} /></Field><Field label="Billing Cycle"><Select value={form.billing_cycle || 'monthly'} onChange={(e) => set('billing_cycle', e.target.value)}>{billingCycles.map((cycle) => <option key={cycle} value={cycle}>{label(cycle)}</option>)}</Select></Field><Field label="Price"><Input type="number" min="0" value={form.price || ''} onChange={(e) => set('price', e.target.value)} /></Field><Field label="Billing Day"><Input type="number" min="1" max="31" value={form.billing_day || ''} onChange={(e) => set('billing_day', e.target.value)} /></Field><Field label="Start Date"><Input type="date" value={form.start_date || ''} onChange={(e) => set('start_date', e.target.value)} /></Field><Field label="Renewal Date"><Input type="date" value={form.renewal_date || ''} onChange={(e) => set('renewal_date', e.target.value)} /></Field><Field label="Status"><Select value={form.status || 'active'} onChange={(e) => set('status', e.target.value)}>{serviceStatuses.map((status) => <option key={status} value={status}>{label(status)}</option>)}</Select></Field><label className="tr:flex tr:min-h-9 tr:items-center tr:gap-2 tr:text-xs tr:font-semibold tr:text-tracs-secondary"><input type="checkbox" checked={Boolean(form.auto_renew)} onChange={(e) => set('auto_renew', e.target.checked)} /> Auto renew</label><Field label="Plan Spec"><Textarea value={form.plan_spec || ''} onChange={(e) => set('plan_spec', e.target.value)} /></Field></div>}
        {kind === 'addon' && <div className="tr:grid tr:grid-cols-1 tr:gap-tracs-3 tr:md:grid-cols-2"><Field label="Service"><Select required value={form.service_id || ''} onChange={(e) => set('service_id', e.target.value)}><option value="">Choose service</option>{selected.services?.map((s) => <option key={s.id} value={s.id}>{s.service_name}</option>)}</Select></Field><Field label="Addon Name"><Input required value={form.addon_name || ''} onChange={(e) => set('addon_name', e.target.value)} /></Field><Field label="Price"><Input type="number" min="0" value={form.price || ''} onChange={(e) => set('price', e.target.value)} /></Field><Field label="Billing Cycle"><Select value={form.billing_cycle || 'included'} onChange={(e) => set('billing_cycle', e.target.value)}><option value="included">Included</option>{billingCycles.map((cycle) => <option key={cycle} value={cycle}>{label(cycle)}</option>)}</Select></Field><Field label="Renewal Date"><Input type="date" value={form.renewal_date || ''} onChange={(e) => set('renewal_date', e.target.value)} /></Field><Field label="Status"><Select value={form.status || 'active'} onChange={(e) => set('status', e.target.value)}>{serviceStatuses.map((status) => <option key={status} value={status}>{label(status)}</option>)}</Select></Field><Field label="Notes"><Textarea value={form.notes || ''} onChange={(e) => set('notes', e.target.value)} /></Field></div>}
        {kind === 'renewal' && <div className="tr:grid tr:grid-cols-1 tr:gap-tracs-3 tr:md:grid-cols-2"><Field label="Service"><Select required value={form.service_id || ''} onChange={(e) => set('service_id', e.target.value)}><option value="">Choose service</option>{selected.services?.map((s) => <option key={s.id} value={s.id}>{s.service_name} · {date(s.renewal_date)}</option>)}</Select></Field><Field label="New Renewal Date"><Input required type="date" value={form.new_renewal_date || ''} onChange={(e) => set('new_renewal_date', e.target.value)} /></Field><Field label="New Price"><Input type="number" min="0" value={form.new_price || ''} onChange={(e) => set('new_price', e.target.value)} placeholder="Leave empty to keep current price" /></Field><Field label="Note"><Textarea value={form.note || ''} onChange={(e) => set('note', e.target.value)} /></Field></div>}
        {kind === 'billing' && <div className="tr:grid tr:grid-cols-1 tr:gap-tracs-3 tr:md:grid-cols-2"><Field label="Service"><Select value={form.service_id || ''} onChange={(e) => set('service_id', e.target.value)}><option value="">General billing</option>{selected.services?.map((s) => <option key={s.id} value={s.id}>{s.service_name}</option>)}</Select></Field><Field label="Invoice Number"><Input value={form.invoice_number || ''} onChange={(e) => set('invoice_number', e.target.value)} /></Field><Field label="Invoice Date"><Input type="date" value={form.invoice_date || ''} onChange={(e) => set('invoice_date', e.target.value)} /></Field><Field label="Due Date"><Input type="date" value={form.due_date || ''} onChange={(e) => set('due_date', e.target.value)} /></Field><Field label="Amount"><Input type="number" min="0" value={form.amount || ''} onChange={(e) => set('amount', e.target.value)} /></Field><Field label="Invoice Status"><Select value={form.invoice_status || 'upcoming'} onChange={(e) => set('invoice_status', e.target.value)}><option value="upcoming">Upcoming</option><option value="sent">Sent</option><option value="cancelled">Cancelled</option></Select></Field><Field label="Payment Status"><Select value={form.payment_status || 'waiting'} onChange={(e) => set('payment_status', e.target.value)}><option value="waiting">Waiting</option><option value="paid">Paid</option><option value="overdue">Overdue</option></Select></Field><Field label="Payment Date"><Input type="date" value={form.payment_date || ''} onChange={(e) => set('payment_date', e.target.value)} /></Field><label className="tr:flex tr:items-center tr:gap-2 tr:text-xs tr:font-semibold tr:text-tracs-secondary"><input type="checkbox" checked={Boolean(form.tax_invoice_required)} onChange={(e) => set('tax_invoice_required', e.target.checked)} /> Tax invoice required</label><Field label="Send Tax Invoice On"><Input type="date" value={form.tax_invoice_due_date || ''} onChange={(e) => set('tax_invoice_due_date', e.target.value)} /></Field><Field label="Tax Invoice Number"><Input value={form.tax_invoice_number || ''} onChange={(e) => set('tax_invoice_number', e.target.value)} /></Field></div>}
        {kind === 'followup' && <div className="tr:grid tr:grid-cols-1 tr:gap-tracs-3 tr:md:grid-cols-2"><Field label="Title"><Input required value={form.title || ''} onChange={(e) => set('title', e.target.value)} placeholder="Send invoice" /></Field><Field label="Action Type"><Select value={form.action_type || 'general_followup'} onChange={(e) => set('action_type', e.target.value)}><option value="send_invoice">Send invoice</option><option value="quotation">Send quotation</option><option value="meeting">Meeting</option><option value="document">Document</option><option value="check_payment">Check payment</option><option value="send_tax_invoice">Send tax invoice</option><option value="renewal">Renewal</option><option value="general_followup">General follow-up</option></Select></Field><Field label="Due At · Asia/Jakarta"><Input required type="datetime-local" value={form.due_at || ''} onChange={(e) => set('due_at', e.target.value)} /></Field><Field label="Notes"><Textarea value={form.description || ''} onChange={e => set('description', e.target.value)} /></Field><Field label="Priority"><Select value={form.priority || 'medium'} onChange={(e) => set('priority', e.target.value)}><option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option><option value="critical">Critical</option></Select></Field><Field label="Assigned To"><Select value={form.assigned_to || context.user.id} onChange={(e) => set('assigned_to', e.target.value)}>{(context.users?.length ? context.users : [context.user]).map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}</Select></Field></div>}
        <div className="tr:flex tr:justify-end"><Button type="submit" variant="primary" disabled={saving}>{saving ? 'Saving...' : kind === 'renewal' ? 'Renew Service' : `Add ${label(kind)}`}</Button></div>
      </form>
    </div>
  );
}

function Detail({ selected, context, onEdit, onSaved, onRecord, onReminder }) {
  if (!selected) return null;
  return <div className="client-details">
    <div className="client-details-head"><strong>{selected.company_name}</strong>{context?.allowed_actions?.manage && <div><Button size="compact" onClick={onEdit}>Edit Client</Button><Button size="compact" onClick={onRecord}>Add Record / Reminder</Button></div>}</div>
    <div className="client-detail-grid">
      <Info title="PIC / Contact" lines={selected.contacts?.flatMap(c => [c.name, c.role_title, c.email, c.phone]).filter(Boolean).length ? selected.contacts.flatMap(c => [c.name, c.role_title, c.email, c.phone]).filter(Boolean) : ['No contact recorded.']} />
      <Info title="Billing Summary" lines={[`Paid: ${money(selected.total_paid_amount)}`, `Outstanding: ${money(selected.outstanding_amount)}`, `Monthly recurring: ${money(selected.mrr_amount)}`]} />
      <Info title="Notes" lines={[selected.notes || 'No notes yet.']} />
    </div>
    <h3 className="client-section-title">Services</h3><ServiceCards services={selected.services} />
    <h3 className="client-section-title">Invoice / Tax Invoice Information</h3>
    <Rows headers={['Invoice / Service', 'Invoice Date', 'Payment Due', 'Amount', 'Status', 'Tax Invoice']} rows={selected.billing} empty="No billing records yet." render={b => <><td>{b.invoice_number || 'Not numbered'}<small>{b.service_name || 'General billing'}</small></td><td>{date(b.invoice_date)}</td><td>{date(b.due_date)}</td><td>{money(b.amount)}</td><td>{label(b.invoice_status)}<small>{label(b.payment_status)}</small></td><td>{Number(b.tax_invoice_required) ? (b.tax_invoice_sent_at ? `Sent · ${date(b.tax_invoice_sent_at)}` : 'Pending') : 'Not required'}<small>{b.tax_invoice_number}</small></td></>} />
    <h3 className="client-section-title">Reminders</h3>
    <Rows headers={['Activity', 'Date', 'Status', 'Action']} rows={selected.followups} empty="No reminders yet." render={f => <><td>{f.title}<small>{label(f.action_type)} · {f.assignee_name}</small></td><td>{date(f.due_at)}</td><td>{f.status === 'open' ? 'Pending' : f.status === 'completed' ? 'Done' : 'Cancelled'}</td><td>{f.status !== 'cancelled' && <Button size="compact" onClick={() => onReminder(f)}>{context?.allowed_actions?.manage ? 'View / Edit' : 'View'}</Button>}</td></>} />
    <details className="client-history"><summary>Activity & renewal history</summary>{selected.activity?.map(a => <p key={a.id}>{a.summary}<small> · {a.actor_name} · {date(a.created_at)}</small></p>)}{selected.renewal_history?.map(h => <p key={h.id}>{h.service_name}: {date(h.old_renewal_date)} → {date(h.new_renewal_date)} · {h.note || 'Renewed'}</p>)}</details>
  </div>;
}

function Info({ title, lines }) {
  return <div className="client-info"><div className="tr:text-xs tr:font-semibold tr:text-tracs-secondary">{title}</div>{lines.map((line, i) => <div key={i} className="tr:mt-1 tr:text-sm">{line}</div>)}</div>;
}

function ServiceCards({ services = [] }) {
  if (!services.length) return <div className="tr:rounded-tracs tr:border tr:border-dashed tr:border-tracs-border tr:p-tracs-4 tr:text-sm tr:text-tracs-muted">No services tracked yet.</div>;
  return (
    <div className="tr:grid tr:grid-cols-1 tr:gap-tracs-3">
      {services.map((service) => {
        const tone = renewalTone(service.renewal_date, service.status);
        return (
          <div key={service.id} className="service-card">
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

function Rows({ rows = [], empty, render, headers = [] }) {
  if (!rows.length) return <div className="tr:rounded-tracs tr:border tr:border-dashed tr:border-tracs-border tr:p-tracs-4 tr:text-sm tr:text-tracs-muted">{empty}</div>;
  return <div className="clients-table-scroll tr:overflow-x-auto"><table className="tr:w-full tr:min-w-[680px] tr:text-left tr:text-sm"><thead><tr>{headers.map(h => <th key={h} scope="col">{h}</th>)}</tr></thead><tbody>{rows.map((row) => <tr key={row.id} className="tr:border-b tr:border-tracs-border last:tr:border-0">{render(row)}</tr>)}</tbody></table></div>;
}

function StatStrip({ summary }) {
  const mainStats = [
    ['Action Required', summary.action_required || 0],
    ['Invoice This Week', summary.invoice_this_week || 0],
    ['Renewal <= 30 Days', summary.renewal_soon || 0],
    ['Paid So Far', money(summary.total_paid_amount)],
    ['Outstanding', money(summary.outstanding_amount)],
    ['Est. Monthly Recurring', money(summary.mrr_amount)],
  ];
  return (
    <Card className="clients-stat-strip tr:p-0">
      <div className="tr:grid tr:grid-cols-2 tr:md:grid-cols-3 tr:lg:grid-cols-6">
        {mainStats.map(([title, value], index) => (
          <div key={title} className={`client-stat-cell tr:min-w-0 tr:p-tracs-3 ${index === 3 ? 'clients-money-start' : ''}`}>
            <div className="tr:text-base tr:font-semibold tr:leading-tight">{value}</div>
            <div className="tr:mt-1 tr:text-[11px] tr:leading-snug tr:text-tracs-muted">{title}</div>
          </div>
        ))}
      </div>
      <div className="tr:border-t tr:border-tracs-border tr:px-tracs-3 tr:py-2 tr:text-xs tr:text-tracs-muted">
        Waiting payment: {summary.waiting_payment || 0} · Tax invoice pending: {summary.tax_invoice_pending || 0}
      </div>
    </Card>
  );
}

function FilterBar({ filters, setFilters, context }) {
  const [open, setOpen] = useState(false);
  const canViewAll = Boolean(context.allowed_actions.view_all);
  const ownerValue = filters.scope === 'mine' ? 'mine' : filters.owner_user_id ? `owner:${filters.owner_user_id}` : 'all';

  function setOwner(value) {
    if (value === 'mine') {
      setFilters({ ...filters, scope: 'mine', owner_user_id: '' });
      return;
    }
    if (value === 'all') {
      setFilters({ ...filters, scope: 'all', owner_user_id: '' });
      return;
    }
    setFilters({ ...filters, scope: 'all', owner_user_id: value.replace('owner:', '') });
  }

  return (
    <div className="clients-filter-bar">
      <div className="tr:grid tr:grid-cols-1 tr:gap-tracs-2 tr:lg:grid-cols-[minmax(220px,1fr)_170px_170px_auto]">
        <Input aria-label="Search client, code, PIC" placeholder="Search client, code, PIC" value={filters.q} onChange={(e) => setFilters({ ...filters, q: e.target.value })} />
        <Select aria-label="Service filter" value={filters.service_type} onChange={(e) => setFilters({ ...filters, service_type: e.target.value })}><option value="">Any service</option>{serviceTypes.map((type) => <option key={type} value={type}>{type}</option>)}</Select>
        <Select aria-label="Renewal filter" value={filters.renewal_window} onChange={(e) => setFilters({ ...filters, renewal_window: e.target.value })}><option value="">Any renewal</option><option value="7">Renewal &lt;= 7 days</option><option value="30">Renewal &lt;= 30 days</option><option value="90">Renewal &lt;= 90 days</option></Select>
        <div className="tr:flex tr:gap-tracs-2">
          <Button className="tr:flex-1 tr:px-tracs-3 lg:tr:min-w-32" onClick={() => setOpen((value) => !value)} aria-expanded={open} aria-controls="client-more-filters">{icon('sliders-horizontal')}More Filters</Button>
          <Button className="tr:w-10 tr:px-0" aria-label="Reset filters" title="Reset filters" size="compact" onClick={() => { setFilters({ ...emptyFilters }); setOpen(false); }}>{icon('rotate-ccw')}<span className="tr:sr-only">Reset filters</span></Button>
        </div>
      </div>
      {open && (
        <div id="client-more-filters" className="clients-more-filters tr:mt-tracs-3 tr:grid tr:grid-cols-1 tr:gap-tracs-2 tr:border-t tr:border-tracs-border tr:pt-tracs-3 tr:md:grid-cols-2 tr:xl:grid-cols-4">
          <Select disabled={!canViewAll} value={ownerValue} onChange={(e) => setOwner(e.target.value)}>
            <option value="mine">My Clients</option>
            {canViewAll && <option value="all">All Owners</option>}
            {canViewAll && context.users.map((u) => <option key={u.id} value={`owner:${u.id}`}>{u.name}</option>)}
          </Select>
          <Select value={filters.status} onChange={(e) => setFilters({ ...filters, status: e.target.value })}><option value="">Any client status</option><option value="active">Active</option><option value="monitoring">Monitoring</option><option value="inactive">Inactive</option></Select>
          <Select value={filters.service_status} onChange={(e) => setFilters({ ...filters, service_status: e.target.value })}><option value="">Any service status</option>{serviceStatuses.map((status) => <option key={status} value={status}>{label(status)}</option>)}</Select>
          <Select value={filters.attention} onChange={(e) => setFilters({ ...filters, attention: e.target.value })}><option value="">Any attention</option><option value="attention">Needs attention</option><option value="critical">Critical</option><option value="warning">Warning</option><option value="due">Due</option><option value="watch">Watch</option></Select>
        </div>
      )}
    </div>
  );
}

function ClientsApp() {
  const context = useContextData();
  const [filters, setFilters] = useState({ ...emptyFilters });
  const clients = useClients(filters);
  const [year, setYear] = useState(Number(jakartaToday().slice(0, 4)));
  const calendar = useCalendarData(year, 'clients');
  const [expanded, setExpanded] = useState(new URLSearchParams(window.location.search).get('id'));
  const [selected, setSelected] = useState(null);
  const [detailError, setDetailError] = useState('');
  const [modal, setModal] = useState(null);
  const [reminder, setReminder] = useState(null);
  const detailSequence = useRef(0);
  const canManage = Boolean(context.data?.allowed_actions?.manage);
  const filtered = Object.entries(filters).some(([key, value]) => value !== emptyFilters[key]);
  const clientIds = useMemo(() => new Set((clients.data.clients || []).map(c => String(c.id))), [clients.data.clients]);
  async function loadDetail(id) {
    const requestId = ++detailSequence.current;
    setDetailError('');
    try {
      const res = await api.request(`/api/v1/client-portfolio/client.php?id=${id}`);
      if (requestId === detailSequence.current) setSelected(res.data);
    } catch (error) { if (requestId === detailSequence.current) setDetailError(error.message); }
  }
  useEffect(() => { setSelected(null); if (expanded) loadDetail(expanded); else detailSequence.current++; }, [expanded]);
  async function refresh() {
    await Promise.all([clients.refresh(), calendar.refresh(), expanded ? loadDetail(expanded) : Promise.resolve()]);
  }
  useEffect(() => {
    const focus = (event) => {
      if (event.type === 'storage' && event.key !== 'tracs-calendar-updated') return;
      if (event.type === 'visibilitychange' && document.hidden) return;
      clients.refresh(); if (expanded) loadDetail(expanded);
    };
    window.addEventListener('focus', focus);
    window.addEventListener('storage', focus);
    document.addEventListener('visibilitychange', focus);
    return () => { window.removeEventListener('focus', focus); window.removeEventListener('storage', focus); document.removeEventListener('visibilitychange', focus); };
  }, [expanded, filters]);
  async function afterSaved(client) {
    try { localStorage.setItem('tracs-calendar-updated', String(Date.now())); } catch { /* Storage may be disabled. */ }
    setModal(null); setSelected(client); setExpanded(String(client.id));
    await Promise.all([clients.refresh(), calendar.refresh()]);
  }
  function openReminder(f) {
    setReminder({ id: `reminder_${f.reminder_id || f.id}`, source: 'clients', source_id: f.id,
      title: `${selected.company_name} · ${f.title}`, date: f.due_at?.slice(0, 10) || jakartaToday(), start_time: f.due_at?.slice(11, 16) || '09:00',
      type: 'reminder', status: f.status === 'completed' ? 'done' : 'upcoming', notes: f.description || '',
      assignee: { name: f.assignee_name }, created_at: f.created_at, updated_at: f.updated_at,
      meta: { followup_id: f.id, client_id: selected.id, client_name: selected.company_name, reminder_title: f.title, editable: canManage, can_mark_done: canManage && f.status !== 'completed' && Boolean(f.due_at) } });
  }
  return <div className="tracs-react-root clients-react-shell tr:flex tr:flex-col tr:gap-tracs-4">
    <div><h1 className="tr:text-xl tr:font-semibold">Clients</h1><p className="tr:mt-1 tr:text-sm tr:text-tracs-muted">Owned portfolios, billing signals, invoice follow-ups, and renewal attention.</p></div>
    {context.error && <p role="alert">{context.error}</p>}
    {context.loading ? <p>Loading Clients…</p> : context.data?.schema_ready ? <>
      <StatStrip summary={clients.data.summary || {}} />
      <div className="clients-toolbar"><FilterBar filters={filters} setFilters={setFilters} context={context.data} />{canManage && <Button variant="primary" onClick={() => setModal('create')}>{icon('plus')}Add Client</Button>}</div>
      <section className="panel" aria-busy={clients.loading}>
        <div className="panel-head"><h2 className="panel-title">Clients</h2><span className="panel-meta">{clients.data.clients?.length || 0} {clients.data.clients?.length === 1 ? 'record' : 'records'}{clients.loading ? ' · Updating…' : ''}</span></div>
        {clients.error ? <p className="clients-empty" role="alert">{clients.error} <Button onClick={clients.refresh}>Retry</Button></p> : null}
        <div className="clients-table-scroll"><table className="clients-list"><thead><tr>{['Client / Company', 'Services', 'Next Action', 'Renewal', 'Status', 'Action'].map(h => <th scope="col" key={h}>{h}</th>)}</tr></thead><tbody>
          {(clients.data.clients || []).map(c => {
            const open = String(c.id) === String(expanded);
            const next = c.next_reminder;
            return <React.Fragment key={c.id}><tr className={`client-row ${open ? 'is-selected' : ''}`}>
              <td><strong>{c.company_name}</strong><small>{c.client_code || 'No code'} · {c.primary_contact_name || 'No PIC'}</small></td>
              <td>{c.service_types || 'No services'}<small>{c.service_count} services · {c.addon_count} addons</small></td>
              <td>{next ? next.title : c.next_action}<small>{date(next?.due_at || c.next_action_due_at)}</small></td>
              <td>{date(c.nearest_renewal_date)}</td><td><Badge tone={c.status}>{label(c.status)}</Badge></td>
              <td><Button size="compact" aria-expanded={open} aria-controls={`client-details-${c.id}`} onClick={() => setExpanded(open ? null : String(c.id))}>{open ? 'View Less' : 'View More'}{icon(open ? 'chevron-up' : 'chevron-down')}</Button></td>
            </tr>{open && <tr id={`client-details-${c.id}`} className="client-detail-row"><td colSpan={6}>{detailError ? <p role="alert">{detailError} <Button onClick={() => loadDetail(c.id)}>Retry</Button></p> : selected && String(selected.id) === String(c.id) ? <Detail selected={selected} context={context.data} onEdit={() => setModal('edit')} onRecord={() => setModal('record')} onSaved={afterSaved} onReminder={openReminder} /> : <p className="clients-empty">Loading client details…</p>}</td></tr>}</React.Fragment>;
          })}
          {!clients.loading && !clients.error && !clients.data.clients?.length && <tr><td colSpan={6}><div className="clients-empty"><strong>{filtered ? 'No clients match these filters.' : 'No clients yet.'}</strong><p>{filtered ? 'Try another search or reset the filters.' : 'Add your first client to start tracking services, billing, invoices and follow-ups.'}</p>{filtered ? <Button onClick={() => setFilters({ ...emptyFilters })}>Reset Filters</Button> : canManage && <Button onClick={() => setModal('create')}>Add Client</Button>}</div></td></tr>}
        </tbody></table></div>
      </section>
      <ClientCalendar calendar={calendar} year={year} setYear={setYear} clientIds={clientIds} onChanged={refresh} />
    </> : !context.error && <p>Client Portfolio storage is not ready. Apply the client portfolio migrations.</p>}
    {modal && <TracsModal title={modal === 'create' ? 'Add Client' : modal === 'edit' ? 'Edit Client' : `Add Record · ${selected?.company_name}`} onClose={() => setModal(null)}>{modal === 'record' ? <QuickActionForm selected={selected} context={context.data} onSaved={afterSaved} /> : <FormPanel context={context.data} selected={modal === 'edit' ? selected : null} onSaved={afterSaved} onCancel={() => setModal(null)} />}</TracsModal>}
    {reminder && <div className="calendar-react-root"><EventDetailPanel open date={reminder.date} event={calendar.events.find(e => e.id === reminder.id) || reminder} events={[reminder]} onClose={() => setReminder(null)} onOpenEvent={setReminder} onRefresh={refresh} /></div>}
  </div>;
}

const root = document.getElementById('tracs-clients-root');
if (root) createRoot(root).render(<React.StrictMode><ClientsApp /></React.StrictMode>);
