import React, { useEffect, useMemo, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { FileText, Paperclip, Plus, RotateCcw, Server, SlidersHorizontal, Upload, X, ChevronDown, ChevronUp } from 'lucide-react';
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

const api = createApiClient();
const emptyFilters = { scope: 'mine', q: '', owner_user_id: '', status: '', billing_status: '', attention: '', service_type: '', service_status: '', renewal_window: '' };
const serviceTypes = ['Dedicated Server', 'Colocation', 'VPS', 'IP Transit', 'Cloud', 'Domain', 'SSL', 'Other'];
const serviceStatuses = ['active', 'monitoring', 'pending_renewal', 'suspended', 'terminated', 'inactive'];
const billingCycles = ['monthly', 'quarterly', 'semiannual', 'annual', 'one_time', 'custom'];
const documentTypes = [
  ['document', 'Document'],
  ['quotation', 'Quotation'],
  ['invoice', 'Invoice'],
  ['tax_invoice', 'Tax invoice'],
  ['contract', 'Contract'],
  ['screenshot', 'Screenshot'],
  ['other', 'Other'],
];

function icon(name, cls = 'tr:h-4 tr:w-4') {
  const Icon = { 'file-text': FileText, paperclip: Paperclip, plus: Plus, 'rotate-ccw': RotateCcw, server: Server, 'sliders-horizontal': SlidersHorizontal, upload: Upload, x: X, 'chevron-down': ChevronDown, 'chevron-up': ChevronUp }[name];
  return Icon ? <Icon className={cls} aria-hidden="true" /> : null;
}

function label(value) {
  return String(value || '').replaceAll('_', ' ').replace(/\b\w/g, (m) => m.toUpperCase());
}

function money(value) {
  if (value === null || value === undefined || value === '') return '-';
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value));
}

function fileSize(value) {
  const bytes = Number(value || 0);
  if (!bytes) return '0 B';
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / 1048576).toFixed(1)} MB`;
}

function compactMoney(value) {
  const amount = Number(value || 0);
  if (Math.abs(amount) >= 1000000000) return `Rp ${(amount / 1000000000).toFixed(2)}M`;
  if (Math.abs(amount) >= 1000000) return `Rp ${(amount / 1000000).toFixed(2)}jt`;
  return money(amount);
}

function date(value) {
  if (!value) return '-';
  return new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(`${String(value).slice(0, 10)}T00:00:00+07:00`));
}

function dateTime(value) {
  if (!value) return '-';
  const raw = String(value);
  const normalized = raw.includes('T') ? raw : raw.replace(' ', 'T');
  return new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(new Date(`${normalized.slice(0, 16)}:00+07:00`));
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

function Field({ label: title, children, error, hint, id }) {
  return (
    <label className="tr:flex tr:min-w-0 tr:flex-col tr:gap-1 tr:text-xs tr:font-semibold tr:text-tracs-secondary" htmlFor={id}>
      <span>{title}</span>
      {children}
      {hint && !error ? <span className="tr:text-[11px] tr:font-medium tr:text-tracs-muted">{hint}</span> : null}
      {error ? <span className="tr:text-[11px] tr:font-semibold tr:text-tracs-danger" id={`${id}-error`}>{error}</span> : null}
    </label>
  );
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

const serviceDetailFields = ['service_type', 'price', 'start_date', 'renewal_date', 'billing_day'];
const billingDetailFields = ['due_date', 'amount', 'tax_invoice_required', 'tax_invoice_due_date'];

function hasValue(value) {
  if (typeof value === 'boolean') return value;
  return value !== null && value !== undefined && String(value).trim() !== '';
}

async function uploadClientAttachments(clientId, files, documentType) {
  if (!files.length) return null;
  const body = new FormData();
  body.append('client_id', String(clientId));
  body.append('document_type', documentType);
  files.forEach((file) => body.append('attachments[]', file));
  return api.request('/api/v1/client-portfolio/attachments.php', { method: 'POST', body });
}

async function clientAction(body) {
  return api.request('/api/v1/client-portfolio/actions.php', { method: 'POST', body });
}

function AttachmentPicker({ files, setFiles, documentType, setDocumentType, disabled = false }) {
  const inputRef = useRef(null);
  function addFiles(nextFiles) {
    const unique = new Map(files.map((file) => [`${file.name}:${file.size}:${file.lastModified}`, file]));
    Array.from(nextFiles || []).forEach((file) => unique.set(`${file.name}:${file.size}:${file.lastModified}`, file));
    setFiles(Array.from(unique.values()));
  }
  function removeFile(index) {
    setFiles(files.filter((_, fileIndex) => fileIndex !== index));
  }
  function handlePaste(event) {
    const pasted = Array.from(event.clipboardData?.files || []);
    if (!pasted.length) return;
    event.preventDefault();
    addFiles(pasted.map((file, index) => {
      if (file.name) return file;
      const ext = file.type === 'image/jpeg' ? 'jpg' : file.type === 'image/webp' ? 'webp' : 'png';
      return new File([file], `pasted-screenshot-${Date.now()}-${index + 1}.${ext}`, { type: file.type || 'image/png' });
    }));
  }
  return (
    <div className="client-attachments-editor" onPaste={handlePaste}>
      <div className="client-attachments-editor__head">
        <div>
          <h3 className="client-section-title">Files</h3>
          <p>Upload quotations, invoices, tax documents, contracts, or paste screenshots.</p>
        </div>
        <Select aria-label="Document type" value={documentType} onChange={(event) => setDocumentType(event.target.value)}>
          {documentTypes.map(([value, title]) => <option key={value} value={value}>{title}</option>)}
        </Select>
      </div>
      <button
        className="client-dropzone"
        disabled={disabled}
        onClick={() => inputRef.current?.click()}
        onDragOver={(event) => event.preventDefault()}
        onDrop={(event) => { event.preventDefault(); addFiles(event.dataTransfer?.files); }}
        type="button"
      >
        {icon('upload', 'tr:h-5 tr:w-5')}
        <span><strong>Choose files or drop them here</strong><small>Paste screenshots with Cmd/Ctrl+V while this area is focused.</small></span>
      </button>
      <input ref={inputRef} data-unsaved-ignore hidden multiple type="file" onChange={(event) => addFiles(event.target.files)} />
      {files.length ? (
        <ul className="client-attachment-list">
          {files.map((file, index) => (
            <li key={`${file.name}:${file.size}:${file.lastModified}`}>
              {icon('paperclip')}
              <span>{file.name}<small>{file.type || 'File'} · {fileSize(file.size)}</small></span>
              <Button disabled={disabled} onClick={() => removeFile(index)} size="compact" variant="quiet">Remove</Button>
            </li>
          ))}
        </ul>
      ) : null}
    </div>
  );
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
  const [fieldErrors, setFieldErrors] = useState({});
  const [attachmentFiles, setAttachmentFiles] = useState([]);
  const [documentType, setDocumentType] = useState('document');
  const canPickOwner = Boolean(context?.allowed_actions?.view_all);
  const errorRef = useRef(null);
  const formRef = useRef(null);
  function set(key, value) {
    setForm((current) => ({ ...current, [key]: value }));
    setFieldErrors((current) => {
      if (!current[key]) return current;
      const next = { ...current };
      delete next[key];
      return next;
    });
  }
  function validate() {
    const next = {};
    if (!String(form.company_name || '').trim()) next.company_name = 'Company name is required.';
    if (!selected && !String(form.service_name || '').trim() && serviceDetailFields.some((key) => hasValue(form[key]))) {
      next.service_name = 'Add a service name, or clear the service details to save the client profile only.';
    }
    if (!selected && !String(form.invoice_date || '').trim() && billingDetailFields.some((key) => hasValue(form[key]))) {
      next.invoice_date = 'Add an invoice date, or clear the billing details to save the client profile only.';
    }
    return next;
  }
  async function submit(event) {
    event.preventDefault();
    const nextErrors = validate();
    setFieldErrors(nextErrors);
    if (Object.keys(nextErrors).length) {
      setError('Review the highlighted fields before saving.');
      requestAnimationFrame(() => errorRef.current?.focus());
      return;
    }
    setSaving(true); setError('');
    try {
      const path = selected ? `/api/v1/client-portfolio/client.php?id=${selected.id}` : '/api/v1/client-portfolio/clients.php';
      const res = await api.request(path, { method: selected ? 'PATCH' : 'POST', body: form });
      let client = res.data;
      if (attachmentFiles.length) {
        const upload = await uploadClientAttachments(client.id, attachmentFiles, documentType);
        client = upload?.data?.client || client;
      }
      window.TRACSUnsavedChanges?.markSaved(formRef.current);
      setAttachmentFiles([]);
      onSaved(client);
    } catch (err) {
      setError(err.message);
    } finally {
      setSaving(false);
    }
  }
  return (
    <div>
      <form className="tr:flex tr:flex-col tr:gap-tracs-3" onSubmit={submit} noValidate ref={formRef}>
        <div className="tr:flex tr:items-start tr:justify-between tr:gap-tracs-3">
          <div><h2 className="tr:text-sm tr:font-semibold">Client Information</h2><p className="tr:mt-1 tr:text-xs tr:text-tracs-muted">Profile, primary PIC, owner, and operational notes.</p></div>
        </div>
        {error && <div className="tr:rounded-tracs tr:border tr:border-tracs-danger-border tr:bg-tracs-danger-soft tr:p-3 tr:text-xs tr:text-tracs-danger" ref={errorRef} role="alert" tabIndex="-1">{error}</div>}
        <div className="tr:grid tr:grid-cols-1 tr:gap-tracs-3 tr:md:grid-cols-2">
          <Field id="client-company-name" label="Company" error={fieldErrors.company_name}><Input id="client-company-name" required aria-describedby={fieldErrors.company_name ? 'client-company-name-error' : undefined} value={form.company_name} onChange={(e) => set('company_name', e.target.value)} /></Field>
          <Field id="client-code" label="Client Code"><Input id="client-code" value={form.client_code || ''} onChange={(e) => set('client_code', e.target.value)} /></Field>
          <Field id="client-status" label="Status"><Select id="client-status" value={form.status} onChange={(e) => set('status', e.target.value)}><option value="active">Active</option><option value="monitoring">Monitoring</option><option value="inactive">Inactive</option></Select></Field>
          <Field id="client-owner" label="Owner"><Select id="client-owner" disabled={!canPickOwner} value={form.owner_user_id} onChange={(e) => set('owner_user_id', e.target.value)}>{canPickOwner ? context.users.map((u) => <option key={u.id} value={u.id}>{u.name}</option>) : <option value={context?.user?.id}>{context?.user?.name}</option>}</Select></Field>
          <Field id="client-contact-name" label="PIC Name"><Input id="client-contact-name" value={form.contact_name} onChange={(e) => set('contact_name', e.target.value)} /></Field>
          <Field id="client-contact-email" label="PIC Email"><Input id="client-contact-email" type="email" value={form.contact_email} onChange={(e) => set('contact_email', e.target.value)} /></Field>
          <Field id="client-contact-phone" label="PIC Phone"><Input id="client-contact-phone" value={form.contact_phone} onChange={(e) => set('contact_phone', e.target.value)} /></Field>
          <Field id="client-contact-role" label="PIC Role"><Input id="client-contact-role" value={form.contact_role} onChange={(e) => set('contact_role', e.target.value)} /></Field>
        </div>
        {!selected && <>
          <div className="client-section-heading">
            <h3 className="client-section-title">Service Information</h3>
            <span>Optional</span>
          </div>
          <div className="client-form-grid">
            <Field id="client-service-name" label="Service Name" hint="Leave this section empty when you only need the client profile." error={fieldErrors.service_name}><Input id="client-service-name" aria-describedby={fieldErrors.service_name ? 'client-service-name-error' : undefined} value={form.service_name || ''} onChange={e => set('service_name', e.target.value)} /></Field>
            <Field id="client-service-type" label="Service Type"><Select id="client-service-type" value={form.service_type || ''} onChange={e => set('service_type', e.target.value)}><option value="">Choose type</option>{serviceTypes.map(t => <option key={t}>{t}</option>)}</Select></Field>
            <Field id="client-service-billing-cycle" label="Billing Cycle"><Select id="client-service-billing-cycle" value={form.billing_cycle || 'monthly'} onChange={e => set('billing_cycle', e.target.value)}>{billingCycles.map(t => <option key={t} value={t}>{label(t)}</option>)}</Select></Field>
            <Field id="client-service-price" label="Price"><Input id="client-service-price" type="number" min="0" step="0.01" value={form.price || ''} onChange={e => set('price', e.target.value)} /></Field>
            <Field id="client-service-start-date" label="Start Date"><Input id="client-service-start-date" type="date" value={form.start_date || ''} onChange={e => set('start_date', e.target.value)} /></Field>
            <Field id="client-service-renewal-date" label="Renewal Date"><Input id="client-service-renewal-date" type="date" value={form.renewal_date || ''} onChange={e => set('renewal_date', e.target.value)} /></Field>
          </div>
          <div className="client-section-heading">
            <h3 className="client-section-title">Billing / Administration</h3>
            <span>Optional</span>
          </div>
          <div className="client-form-grid">
            <Field id="client-invoice-date" label="Invoice Date" error={fieldErrors.invoice_date}><Input id="client-invoice-date" aria-describedby={fieldErrors.invoice_date ? 'client-invoice-date-error' : undefined} type="date" value={form.invoice_date || ''} onChange={e => set('invoice_date', e.target.value)} /></Field>
            <Field id="client-payment-due-date" label="Payment Due Date"><Input id="client-payment-due-date" type="date" min={form.invoice_date || undefined} value={form.due_date || ''} onChange={e => set('due_date', e.target.value)} /></Field>
            <Field id="client-invoice-amount" label="Invoice Amount"><Input id="client-invoice-amount" type="number" min="0" step="0.01" value={form.amount || ''} onChange={e => set('amount', e.target.value)} /></Field>
            <Field id="client-billing-day" label="Billing Day"><Input id="client-billing-day" type="number" min="1" max="31" value={form.billing_day || ''} onChange={e => set('billing_day', e.target.value)} /></Field>
            <label><input type="checkbox" checked={Boolean(form.tax_invoice_required)} onChange={e => set('tax_invoice_required', e.target.checked)} /> Tax invoice required</label>
            {form.tax_invoice_required && <Field id="client-tax-invoice-due-date" label="Send Tax Invoice On"><Input id="client-tax-invoice-due-date" type="date" required value={form.tax_invoice_due_date || ''} onChange={e => set('tax_invoice_due_date', e.target.value)} /></Field>}
          </div>
          <p className="tr:text-xs tr:text-tracs-muted">Invoice and renewal dates create Calendar reminders. Payment terms are tracked by the payment due date.</p>
        </>}
        <Field id="client-notes" label="Operational Notes"><Textarea id="client-notes" value={form.notes} onChange={(e) => set('notes', e.target.value)} /></Field>
        <AttachmentPicker files={attachmentFiles} setFiles={setAttachmentFiles} documentType={documentType} setDocumentType={setDocumentType} disabled={saving} />
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

function monthTitle(year, month) {
  return new Intl.DateTimeFormat('en-GB', { month: 'long', year: 'numeric' }).format(new Date(year, month - 1, 1));
}

function checklistActionLabel(type) {
  return {
    send_invoice: 'Invoice sent',
    send_tax_invoice: 'Tax invoice sent',
    check_payment: 'Payment received',
    renewal: 'Renewal follow-up',
  }[type] || label(type);
}

function MonthlyChecklist({ client, onSaved, onChanged }) {
  const today = jakartaToday();
  const [year, setYear] = useState(Number(today.slice(0, 4)));
  const [month, setMonth] = useState(Number(today.slice(5, 7)));
  const [state, setState] = useState({ loading: true, error: '', items: [] });
  const [working, setWorking] = useState(null);

  async function load() {
    setState((current) => ({ ...current, loading: true, error: '' }));
    try {
      const res = await clientAction({ action: 'monthly_checklist', client_id: client.id, year, month });
      setState({ loading: false, error: '', items: res.data || [] });
      await onChanged?.();
    } catch (error) {
      setState((current) => ({ ...current, loading: false, error: error.message }));
    }
  }

  useEffect(() => { load(); }, [client.id, year, month]);

  function navigate(delta) {
    const next = new Date(year, month - 1 + delta, 1);
    setYear(next.getFullYear());
    setMonth(next.getMonth() + 1);
  }

  function goToday() {
    setYear(Number(today.slice(0, 4)));
    setMonth(Number(today.slice(5, 7)));
  }

  async function update(item, status) {
    setWorking(item.id);
    try {
      const res = await clientAction({ action: 'update_monthly_checklist', client_id: client.id, followup_id: item.id, status });
      await onSaved?.(res.data);
      await load();
    } catch (error) {
      setState((current) => ({ ...current, error: error.message }));
    } finally {
      setWorking(null);
    }
  }

  return (
    <section className="client-monthly-checklist">
      <div className="client-monthly-checklist__head">
        <div>
          <div className="client-monthly-checklist__period">{monthTitle(year, month)}</div>
          <h3 className="client-section-title">Monthly Checklist</h3>
        </div>
        <div>
          <Button size="compact" variant="quiet" aria-label="Previous month" onClick={() => navigate(-1)}>{'<'}</Button>
          <Button size="compact" variant="quiet" onClick={goToday}>Today</Button>
          <Button size="compact" variant="quiet" aria-label="Next month" onClick={() => navigate(1)}>{'>'}</Button>
        </div>
      </div>
      {state.error ? <p className="clients-empty" role="alert">{state.error}</p> : null}
      {state.loading ? <p className="clients-empty">Loading monthly checklist...</p> : (
        <ul className="client-checklist-rows">
          {state.items.map((item) => {
            const completed = item.status === 'completed';
            const notApplicable = item.status === 'not_applicable';
            return (
              <li key={item.id} className={notApplicable ? 'is-muted' : item.status === 'overdue' ? 'is-overdue' : ''}>
                <label>
                  <input
                    checked={completed}
                    disabled={working === item.id || notApplicable}
                    onChange={(event) => update(item, event.target.checked ? 'completed' : 'open')}
                    type="checkbox"
                  />
                  <span>{checklistActionLabel(item.action_type)}<small>{completed && item.completed_at ? `Completed ${dateTime(item.completed_at)}${item.completed_by_name ? ` · ${item.completed_by_name}` : ''}` : item.status === 'overdue' ? `Overdue · Due ${date(item.due_at)}` : notApplicable ? 'Not applicable this month' : `Pending · Due ${date(item.due_at)}`}</small></span>
                </label>
                <Button disabled={working === item.id} size="compact" variant="quiet" onClick={() => update(item, notApplicable ? 'open' : 'not_applicable')}>{notApplicable ? 'Use' : 'N/A'}</Button>
              </li>
            );
          })}
        </ul>
      )}
    </section>
  );
}

function Detail({ selected, context, onEdit, onSaved, onChanged, onRecord, onReminder }) {
  if (!selected) return null;
  return <div className="client-details">
    <div className="client-details-head"><strong>{selected.company_name}</strong>{context?.allowed_actions?.manage && <div><Button size="compact" onClick={onEdit}>Edit Client</Button><Button size="compact" onClick={onRecord}>Add Record / Reminder</Button></div>}</div>
    <div className="client-detail-grid">
      <Info title="PIC / Contact" lines={selected.contacts?.flatMap(c => [c.name, c.role_title, c.email, c.phone]).filter(Boolean).length ? selected.contacts.flatMap(c => [c.name, c.role_title, c.email, c.phone]).filter(Boolean) : ['No contact recorded.']} />
      <Info title="Billing Summary" lines={[`Paid: ${money(selected.total_paid_amount)}`, `Outstanding: ${money(selected.outstanding_amount)}`, `Monthly recurring: ${money(selected.mrr_amount)}`]} />
      <Info title="Notes" lines={[selected.notes || 'No notes yet.']} />
    </div>
    <MonthlyChecklist client={selected} onSaved={onSaved} onChanged={onChanged} />
    <h3 className="client-section-title">Services</h3><ServiceCards services={selected.services} />
    <h3 className="client-section-title">Invoice / Tax Invoice Information</h3>
    <Rows headers={['Invoice / Service', 'Invoice Date', 'Payment Due', 'Amount', 'Status', 'Tax Invoice']} rows={selected.billing} empty="No billing records yet." render={b => <><td>{b.invoice_number || 'Not numbered'}<small>{b.service_name || 'General billing'}</small></td><td>{date(b.invoice_date)}</td><td>{date(b.due_date)}</td><td>{money(b.amount)}</td><td>{label(b.invoice_status)}<small>{label(b.payment_status)}</small></td><td>{Number(b.tax_invoice_required) ? (b.tax_invoice_sent_at ? `Sent · ${date(b.tax_invoice_sent_at)}` : 'Pending') : 'Not required'}<small>{b.tax_invoice_number}</small></td></>} />
    <h3 className="client-section-title">Documents</h3><AttachmentList attachments={selected.attachments} />
    <h3 className="client-section-title">Reminders</h3>
    <Rows headers={['Activity', 'Date', 'Status', 'Action']} rows={selected.followups} empty="No reminders yet." render={f => <><td>{f.title}<small>{label(f.action_type)} · {f.assignee_name}</small></td><td>{date(f.due_at)}</td><td>{f.status === 'open' ? 'Pending' : f.status === 'completed' ? 'Done' : f.status === 'not_applicable' ? 'N/A' : 'Cancelled'}</td><td>{f.status !== 'cancelled' && <Button size="compact" onClick={() => onReminder(f)}>{context?.allowed_actions?.manage ? 'View / Edit' : 'View'}</Button>}</td></>} />
    <details className="client-history"><summary>Activity & renewal history</summary>{selected.activity?.map(a => <p key={a.id}>{a.summary}<small> · {a.actor_name} · {date(a.created_at)}</small></p>)}{selected.renewal_history?.map(h => <p key={h.id}>{h.service_name}: {date(h.old_renewal_date)} → {date(h.new_renewal_date)} · {h.note || 'Renewed'}</p>)}</details>
  </div>;
}

function AttachmentList({ attachments = [] }) {
  if (!attachments.length) return <div className="tr:rounded-tracs tr:border tr:border-dashed tr:border-tracs-border tr:p-tracs-4 tr:text-sm tr:text-tracs-muted">No client files uploaded yet.</div>;
  return (
    <div className="client-attachment-grid">
      {attachments.map((attachment) => (
        <a key={attachment.id} href={attachment.download_url || attachment.url} className="client-attachment-card">
          {icon('file-text', 'tr:h-5 tr:w-5 tr:text-tracs-secondary')}
          <span>{attachment.original_filename}<small>{label(attachment.document_type)} · {fileSize(attachment.file_size)}</small></span>
        </a>
      ))}
    </div>
  );
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

function HeaderStats({ summary }) {
  const stats = [
    ['Action Required', summary.action_required || 0],
    ['Invoice This Week', summary.invoice_this_week || 0],
    ['Renewal <=30d', summary.renewal_soon || 0],
    ['Outstanding', compactMoney(summary.outstanding_amount)],
    ['MRR', compactMoney(summary.mrr_amount)],
  ];
  return <div className="clients-header-stats">{stats.map(([title, value]) => <span key={title}><strong>{value}</strong> {title}</span>)}</div>;
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
      type: 'reminder', status: f.status === 'completed' ? 'done' : f.status === 'not_applicable' ? 'not_applicable' : 'upcoming', notes: f.description || '',
      assignee: { name: f.assignee_name }, created_at: f.created_at, updated_at: f.updated_at,
      meta: { followup_id: f.id, client_id: selected.id, client_name: selected.company_name, reminder_title: f.title, editable: canManage, can_mark_done: canManage && !['completed', 'not_applicable'].includes(f.status) && Boolean(f.due_at) } });
  }
  return <div className="tracs-react-root clients-react-shell tr:flex tr:flex-col tr:gap-tracs-4">
    <div className="topbar clients-topbar">
      <div className="topbar-left">
        <div className="page-title">Clients</div>
      </div>
      <div className="topbar-right">
        <HeaderStats summary={clients.data.summary || {}} />
      </div>
    </div>
    {context.error && <p role="alert">{context.error}</p>}
    {context.loading ? <p>Loading Clients…</p> : context.data?.schema_ready ? <>
      <ClientCalendar calendar={calendar} year={year} setYear={setYear} clientIds={clientIds} onChanged={refresh} />
      <div className="clients-toolbar"><FilterBar filters={filters} setFilters={setFilters} context={context.data} />{canManage && <Button className="clients-add-button" variant="primary" onClick={() => setModal('create')}>{icon('plus')}Add Client</Button>}</div>
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
            </tr>{open && <tr id={`client-details-${c.id}`} className="client-detail-row"><td colSpan={6}>{detailError ? <p role="alert">{detailError} <Button onClick={() => loadDetail(c.id)}>Retry</Button></p> : selected && String(selected.id) === String(c.id) ? <Detail selected={selected} context={context.data} onEdit={() => setModal('edit')} onRecord={() => setModal('record')} onSaved={afterSaved} onChanged={refresh} onReminder={openReminder} /> : <p className="clients-empty">Loading client details…</p>}</td></tr>}</React.Fragment>;
          })}
          {!clients.loading && !clients.error && !clients.data.clients?.length && <tr><td colSpan={6}><div className="clients-empty"><strong>{filtered ? 'No clients match these filters.' : 'No clients yet.'}</strong><p>{filtered ? 'Try another search or reset the filters.' : 'Add your first client to start tracking services, billing, invoices and follow-ups.'}</p>{filtered ? <Button onClick={() => setFilters({ ...emptyFilters })}>Reset Filters</Button> : null}</div></td></tr>}
        </tbody></table></div>
      </section>
    </> : !context.error && <p>Client Portfolio storage is not ready. Apply the client portfolio migrations.</p>}
    {modal && <TracsModal title={modal === 'create' ? 'Add Client' : modal === 'edit' ? 'Edit Client' : `Add Record · ${selected?.company_name}`} onClose={() => setModal(null)}>{modal === 'record' ? <QuickActionForm selected={selected} context={context.data} onSaved={afterSaved} /> : <FormPanel context={context.data} selected={modal === 'edit' ? selected : null} onSaved={afterSaved} onCancel={() => setModal(null)} />}</TracsModal>}
    {reminder && <div className="calendar-react-root"><EventDetailPanel open date={reminder.date} event={calendar.events.find(e => e.id === reminder.id) || reminder} events={[reminder]} onClose={() => setReminder(null)} onOpenEvent={setReminder} onRefresh={refresh} /></div>}
  </div>;
}

const root = document.getElementById('tracs-clients-root');
if (root) createRoot(root).render(<React.StrictMode><ClientsApp /></React.StrictMode>);
