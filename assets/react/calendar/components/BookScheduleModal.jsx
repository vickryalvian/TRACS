import React, { useEffect, useMemo, useRef, useState } from 'react';
import { CalendarPlus, Save, X } from 'lucide-react';
import { calendarApi } from '../api/calendarApi';
import { formatDate, parseDisplayDate } from '../utils/date';
import { EVENT_TYPES, STATUSES } from '../utils/events';
import { Field, TracsButton, TracsInput, TracsSelect, TracsTextarea } from './CalendarPrimitives';

const blankForm = (date, currentUserId) => ({
  title: '',
  event_date: formatDate(date),
  start_time: '09:00',
  end_time: '10:00',
  event_type: 'meeting',
  status: 'upcoming',
  source_module: 'calendar',
  assigned_user_id: currentUserId ? String(currentUserId) : '',
  notes: '',
  visibility: 'team',
  reminder_minutes: '15',
  recurrence_rule: 'none',
});

function formFromEvent(event, currentUserId) {
  if (!event) return blankForm(undefined, currentUserId);
  return {
    title: event.title || '',
    event_date: formatDate(event.date),
    start_time: event.start_time || '09:00',
    end_time: event.end_time === '24:00' ? '23:59' : (event.end_time || '10:00'),
    event_type: event.type || 'meeting',
    status: event.status || 'upcoming',
    source_module: event.meta?.source_module || 'calendar',
    assigned_user_id: event.assignee?.id ? String(event.assignee.id) : '',
    notes: event.notes || '',
    visibility: event.meta?.visibility || 'team',
    reminder_minutes: event.meta?.reminder_minutes == null ? '' : String(event.meta.reminder_minutes),
    recurrence_rule: event.meta?.recurrence || 'none',
  };
}

export function BookScheduleModal({ open, date, event, metadata, onClose, onSaved }) {
  const modalRef = useRef(null);
  const titleRef = useRef(null);
  const [form, setForm] = useState(blankForm(date, metadata?.current_user?.id));
  const [errors, setErrors] = useState({});
  const [saving, setSaving] = useState(false);
  const editing = event?.source === 'calendar';

  useEffect(() => {
    if (!open) return;
    setForm(formFromEvent(event || (date ? { date } : null), metadata?.current_user?.id));
    setErrors({});
    setSaving(false);
    setTimeout(() => titleRef.current?.focus(), 30);
  }, [open, date, event, metadata?.current_user?.id]);

  useEffect(() => {
    if (!open) return undefined;
    const close = (keyEvent) => keyEvent.key === 'Escape' && !saving && onClose();
    window.addEventListener('keydown', close);
    return () => window.removeEventListener('keydown', close);
  }, [open, onClose, saving]);

  const users = useMemo(() => metadata?.users || [], [metadata]);
  if (!open) return null;

  const setValue = (name, value) => {
    setForm((current) => ({ ...current, [name]: value }));
    setErrors((current) => ({ ...current, [name]: undefined }));
  };

  const validate = () => {
    const next = {};
    if (!form.title.trim()) next.title = 'Title is required.';
    if (!parseDisplayDate(form.event_date)) next.event_date = 'Use dd-mm-yyyy.';
    if (!/^\d{2}:\d{2}$/.test(form.start_time)) next.start_time = 'Start time is required.';
    if (!/^\d{2}:\d{2}$/.test(form.end_time)) next.end_time = 'End time is required.';
    if (!form.event_type) next.event_type = 'Event type is required.';
    setErrors(next);
    const first = Object.keys(next)[0];
    if (first) {
      const field = modalRef.current?.querySelector(`[name="${first}"]`);
      field?.scrollIntoView({ behavior: 'smooth', block: 'center' });
      setTimeout(() => field?.focus(), 250);
      window.showToast?.('error', 'Schedule needs attention', next[first], {
        context: 'modal',
        modal: modalRef.current,
        sourceElement: field,
        persistent: true,
        closable: true,
      });
      return false;
    }
    return true;
  };

  const submit = async (submitEvent) => {
    submitEvent.preventDefault();
    if (!validate()) return;
    setSaving(true);
    try {
      const payload = {
        ...form,
        event_date: parseDisplayDate(form.event_date),
        assigned_user_id: form.assigned_user_id || null,
        reminder_minutes: form.reminder_minutes === '' ? null : Number(form.reminder_minutes),
      };
      if (editing) {
        await calendarApi.update({ ...payload, id: event.source_id });
      } else {
        await calendarApi.create(payload);
      }
      window.showToast?.('success', editing ? 'Schedule updated' : 'Schedule booked', editing ? 'The calendar schedule was updated.' : 'The new schedule is now on the calendar.', {
        context: 'modal',
        modal: modalRef.current,
        sourceElement: modalRef.current,
        duration: 900,
      });
      await onSaved();
      setTimeout(onClose, 700);
    } catch (requestError) {
      setErrors(requestError.errors || {});
      const first = Object.keys(requestError.errors || {})[0];
      const field = first ? modalRef.current?.querySelector(`[name="${first}"]`) : null;
      field?.scrollIntoView({ behavior: 'smooth', block: 'center' });
      field?.focus();
      window.showToast?.('error', 'Schedule not saved', requestError.message, {
        context: 'modal',
        modal: modalRef.current,
        sourceElement: field || modalRef.current,
        persistent: true,
        closable: true,
      });
      setSaving(false);
    }
  };

  return (
    <div className="modal-overlay calendar-modal-overlay cal:fixed cal:inset-0 cal:z-[11000] cal:flex cal:items-center cal:justify-center cal:bg-black/80 cal:p-4" onMouseDown={() => !saving && onClose()}>
      <form
        ref={modalRef}
        className="modal calendar-modal cal:flex cal:max-h-[min(92vh,760px)] cal:w-[min(94vw,680px)] cal:flex-col cal:overflow-hidden cal:rounded-tracs-lg cal:border cal:border-tracs-border-strong cal:bg-tracs-card cal:shadow-tracs-lg"
        role="dialog"
        aria-modal="true"
        aria-labelledby="calendarBookTitle"
        onSubmit={submit}
        onMouseDown={(mouseEvent) => mouseEvent.stopPropagation()}
      >
        <div className="cal:flex cal:items-center cal:justify-between cal:border-b cal:border-tracs-border cal:bg-tracs-surface-2 cal:px-4 cal:py-3">
          <div className="cal:flex cal:items-center cal:gap-2.5">
            <span className="cal:flex cal:size-8 cal:items-center cal:justify-center cal:rounded-tracs cal:bg-tracs-accent-soft cal:text-tracs-accent"><CalendarPlus className="cal:size-4" /></span>
            <div>
              <h2 id="calendarBookTitle" className="cal:text-sm cal:font-semibold cal:text-tracs-primary">{editing ? 'Edit Schedule' : 'Book Schedule'}</h2>
              <p className="cal:font-mono cal:text-[9px] cal:text-tracs-muted">Manual Calendar event · Asia/Jakarta</p>
            </div>
          </div>
          <TracsButton type="button" size="icon" icon={X} onClick={onClose} disabled={saving} aria-label="Close modal" />
        </div>

        <div className="cal:grid cal:grid-cols-1 cal:gap-3 cal:overflow-y-auto cal:p-4 cal:sm:grid-cols-2">
          <div className="cal:sm:col-span-2">
            <Field label="Title *" error={errors.title}>
              <TracsInput ref={titleRef} name="title" value={form.title} onChange={(e) => setValue('title', e.target.value)} error={errors.title} placeholder="Operational schedule title" />
            </Field>
          </div>
          <Field label="Date *" error={errors.event_date} hint="Display format: dd-mm-yyyy">
            <TracsInput name="event_date" inputMode="numeric" value={form.event_date} onChange={(e) => setValue('event_date', e.target.value)} error={errors.event_date} placeholder="dd-mm-yyyy" />
          </Field>
          <Field label="Event Type *" error={errors.event_type}>
            <TracsSelect name="event_type" value={form.event_type} onChange={(e) => setValue('event_type', e.target.value)} error={errors.event_type}>
              {EVENT_TYPES.filter(([value]) => value !== 'all').map(([value, label]) => <option key={value} value={value}>{label}</option>)}
            </TracsSelect>
          </Field>
          <Field label="Start Time *" error={errors.start_time}>
            <TracsInput name="start_time" type="time" value={form.start_time} onChange={(e) => setValue('start_time', e.target.value)} error={errors.start_time} />
          </Field>
          <Field label="End Time *" error={errors.end_time}>
            <TracsInput name="end_time" type="time" value={form.end_time} onChange={(e) => setValue('end_time', e.target.value)} error={errors.end_time} />
          </Field>
          <Field label="Related Module / Source">
            <TracsSelect name="source_module" value={form.source_module} onChange={(e) => setValue('source_module', e.target.value)}>
              <option value="calendar">Calendar</option><option value="cases">Cases</option>
              <option value="shifts">Shifting Assignment</option><option value="meetings">Meetings / MoM</option>
              <option value="reminders">Reminders</option><option value="tasks">Checklist</option>
              <option value="maintenance">Maintenance</option><option value="other">Other</option>
            </TracsSelect>
          </Field>
          <Field label="Assigned User / Agent">
            <TracsSelect name="assigned_user_id" value={form.assigned_user_id} onChange={(e) => setValue('assigned_user_id', e.target.value)}>
              <option value="">Unassigned</option>
              {users.map((user) => <option key={user.id} value={String(user.id)}>{user.name}</option>)}
            </TracsSelect>
          </Field>
          <Field label="Visibility">
            <TracsSelect name="visibility" value={form.visibility} onChange={(e) => setValue('visibility', e.target.value)}>
              <option value="private">Private</option><option value="team">Team</option><option value="all">All users</option>
            </TracsSelect>
          </Field>
          <Field label="Reminder Before Event">
            <TracsSelect name="reminder_minutes" value={form.reminder_minutes} onChange={(e) => setValue('reminder_minutes', e.target.value)}>
              <option value="">No reminder</option><option value="5">5 minutes</option><option value="15">15 minutes</option>
              <option value="30">30 minutes</option><option value="60">1 hour</option><option value="1440">1 day</option>
            </TracsSelect>
          </Field>
          <Field label="Repeat / Recurrence">
            <TracsSelect name="recurrence_rule" value={form.recurrence_rule} onChange={(e) => setValue('recurrence_rule', e.target.value)}>
              <option value="none">Does not repeat</option><option value="daily">Daily</option>
              <option value="weekly">Weekly</option><option value="monthly">Monthly</option><option value="yearly">Yearly</option>
            </TracsSelect>
          </Field>
          <Field label="Status">
            <TracsSelect name="status" value={form.status} onChange={(e) => setValue('status', e.target.value)}>
              {STATUSES.filter(([value]) => value !== 'all').map(([value, label]) => <option key={value} value={value}>{label}</option>)}
            </TracsSelect>
          </Field>
          <div className="cal:sm:col-span-2">
            <Field label="Notes">
              <TracsTextarea name="notes" value={form.notes} onChange={(e) => setValue('notes', e.target.value)} placeholder="Context, handover notes, participants, or preparation details" />
            </Field>
          </div>
        </div>

        <div className="cal:flex cal:items-center cal:justify-end cal:gap-2 cal:border-t cal:border-tracs-border cal:bg-tracs-surface-2 cal:px-4 cal:py-3">
          <TracsButton type="button" onClick={onClose} disabled={saving}>Cancel</TracsButton>
          <TracsButton type="submit" variant="primary" icon={Save} loading={saving}>{editing ? 'Save Changes' : 'Book Schedule'}</TracsButton>
        </div>
      </form>
    </div>
  );
}
