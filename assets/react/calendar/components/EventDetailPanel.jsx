import React, { useEffect, useState } from 'react';
import { CalendarDays, Check, Clock3, ExternalLink, Pencil, Trash2, UserRound, X } from 'lucide-react';
import { calendarApi } from '../api/calendarApi';
import { CalendarBadge } from './EventBadge';
import { TracsButton } from './CalendarPrimitives';
import { eventTime, formatDate } from '../utils/date';
import { eventTypeLabel, sourceLabel, TYPE_TONES } from '../utils/events';

export function EventDetailPanel({
  open,
  date,
  events,
  event,
  onClose,
  onOpenEvent,
  onEdit,
  onRefresh,
}) {
  const [working, setWorking] = useState(false);
  useEffect(() => {
    if (!open) return undefined;
    const close = (keyEvent) => keyEvent.key === 'Escape' && onClose();
    window.addEventListener('keydown', close);
    return () => window.removeEventListener('keydown', close);
  }, [open, onClose]);
  if (!open) return null;

  const selected = event;
  const sourceUrl = selected?.meta?.url;
  const sourceActionLabel = selected?.source === 'cases'
    ? 'Open Case'
    : selected?.source === 'meetings' || selected?.source === 'meeting_actions'
      ? 'Open Meeting'
      : selected?.source === 'shifts'
        ? 'Open Shift Assignment'
        : 'View Source';
  const markDone = async () => {
    setWorking(true);
    try {
      await calendarApi.markDone(selected);
      window.showToast?.('Item marked done', 'success');
      await onRefresh();
      onClose();
    } catch (error) {
      window.showToast?.('error', 'Unable to complete item', error.message, { persistent: true });
    } finally {
      setWorking(false);
    }
  };
  const remove = async () => {
    if (!window.confirm('Delete this manual calendar schedule?')) return;
    setWorking(true);
    try {
      await calendarApi.remove(selected.source_id);
      window.showToast?.('Schedule deleted', 'success');
      await onRefresh();
      onClose();
    } catch (error) {
      window.showToast?.('error', 'Unable to delete schedule', error.message, { persistent: true });
    } finally {
      setWorking(false);
    }
  };

  return (
    <div className="cal:fixed cal:inset-0 cal:z-[10000] cal:bg-black/60" role="presentation" onMouseDown={onClose}>
      <aside
        className="cal:absolute cal:inset-y-0 cal:right-0 cal:flex cal:w-[min(94vw,460px)] cal:flex-col cal:border-l cal:border-tracs-border-strong cal:bg-tracs-card cal:shadow-tracs-lg"
        role="dialog"
        aria-modal="true"
        aria-label={selected ? 'Event details' : 'Date summary'}
        onMouseDown={(mouseEvent) => mouseEvent.stopPropagation()}
      >
        <div className="cal:flex cal:items-center cal:justify-between cal:border-b cal:border-tracs-border cal:bg-tracs-surface-2 cal:px-4 cal:py-3">
          <div>
            <h2 className="cal:text-sm cal:font-semibold cal:text-tracs-primary">{selected ? 'Event Details' : 'Date Summary'}</h2>
            <p className="cal:font-mono cal:text-[9px] cal:text-tracs-muted">{formatDate(selected?.date || date)}</p>
          </div>
          <TracsButton size="icon" icon={X} onClick={onClose} aria-label="Close details" />
        </div>

        {selected ? (
          <div className="cal:flex cal:flex-1 cal:flex-col cal:overflow-y-auto">
            <div className="cal:border-b cal:border-tracs-border cal:p-4">
              <div className="cal:flex cal:flex-wrap cal:gap-1.5">
                <CalendarBadge tone={TYPE_TONES[selected.type]}>{eventTypeLabel(selected.type)}</CalendarBadge>
                <CalendarBadge tone={selected.status === 'overdue' ? 'red' : selected.status === 'done' ? 'green' : 'blue'}>{selected.status.replaceAll('_', ' ')}</CalendarBadge>
              </div>
              <h3 className="cal:mt-3 cal:text-base cal:font-semibold cal:leading-snug cal:text-tracs-primary">{selected.title}</h3>
              <div className="cal:mt-3 cal:grid cal:grid-cols-2 cal:gap-2">
                <Detail icon={CalendarDays} label="Date" value={formatDate(selected.date)} />
                <Detail icon={Clock3} label="Time" value={eventTime(selected)} />
                <Detail icon={UserRound} label="Owner / Assignee" value={selected.assignee?.name || 'Unassigned'} />
                <Detail icon={ExternalLink} label="Source Module" value={sourceLabel(selected.source)} />
              </div>
            </div>
            <div className="cal:flex cal:flex-col cal:gap-4 cal:p-4">
              <Block label="Notes / Description" value={selected.notes || 'No notes available.'} />
              {selected.division?.name ? <Block label="Division / Role" value={selected.division.name} /> : null}
              {selected.meta?.ticket_id ? <Block label="Related Record" value={selected.meta.ticket_id} /> : null}
              <Block label="Created / Updated" value={`${selected.created_at || '—'} · ${selected.updated_at || '—'}`} />
            </div>
            <div className="cal:mt-auto cal:flex cal:flex-wrap cal:gap-2 cal:border-t cal:border-tracs-border cal:bg-tracs-surface-2 cal:p-4">
              {sourceUrl ? (
                <a
                  href={sourceUrl}
                  className="cal:inline-flex cal:min-h-8 cal:items-center cal:justify-center cal:gap-1.5 cal:rounded-tracs cal:border cal:border-tracs-border cal:bg-tracs-surface-2 cal:px-3 cal:text-[11.5px] cal:font-medium cal:text-tracs-secondary cal:transition hover:cal:border-tracs-border-strong hover:cal:bg-tracs-surface-3 hover:cal:text-tracs-primary focus-visible:cal:outline-none focus-visible:cal:ring-2 focus-visible:cal:ring-tracs-accent"
                >
                  <ExternalLink className="cal:size-3.5" />{sourceActionLabel}
                </a>
              ) : null}
              {selected.meta?.can_mark_done ? <TracsButton icon={Check} variant="primary" loading={working} onClick={markDone}>Mark Done</TracsButton> : null}
              {selected.source === 'calendar' && selected.meta?.editable ? <TracsButton icon={Pencil} onClick={() => onEdit(selected)}>Edit</TracsButton> : null}
              {selected.source === 'calendar' && selected.meta?.editable ? <TracsButton icon={Trash2} onClick={remove} loading={working}>Delete</TracsButton> : null}
            </div>
          </div>
        ) : (
          <div className="cal:flex cal:flex-1 cal:flex-col cal:overflow-y-auto cal:p-4">
            {events.length ? (
              <div className="cal:flex cal:flex-col cal:gap-2">
                {events.map((item) => (
                  <button
                    key={item.id}
                    type="button"
                    onClick={() => onOpenEvent(item)}
                    className="cal:rounded-tracs cal:border cal:border-tracs-border cal:bg-tracs-surface-2 cal:p-3 cal:text-left cal:transition hover:cal:border-tracs-border-strong hover:cal:bg-tracs-surface-3 focus-visible:cal:outline-none focus-visible:cal:ring-2 focus-visible:cal:ring-tracs-accent"
                  >
                    <div className="cal:flex cal:items-center cal:justify-between cal:gap-2">
                      <CalendarBadge tone={TYPE_TONES[item.type]}>{eventTypeLabel(item.type)}</CalendarBadge>
                      <span className="cal:font-mono cal:text-[9px] cal:text-tracs-muted">{eventTime(item)}</span>
                    </div>
                    <strong className="cal:mt-2 cal:block cal:text-xs cal:text-tracs-primary">{item.title}</strong>
                    <span className="cal:mt-1 cal:block cal:text-[9px] cal:text-tracs-muted">{item.assignee?.name || sourceLabel(item.source)}</span>
                  </button>
                ))}
              </div>
            ) : <p className="cal:py-12 cal:text-center cal:text-xs cal:text-tracs-muted">No calendar items for this date.</p>}
          </div>
        )}
      </aside>
    </div>
  );
}

function Detail({ icon: Icon, label, value }) {
  return (
    <div className="cal:rounded-tracs cal:border cal:border-tracs-border cal:bg-tracs-surface-2 cal:p-2.5">
      <span className="cal:flex cal:items-center cal:gap-1 cal:font-mono cal:text-[8px] cal:font-bold cal:uppercase cal:tracking-[.08em] cal:text-tracs-muted"><Icon className="cal:size-3" />{label}</span>
      <strong className="cal:mt-1 cal:block cal:text-[10px] cal:text-tracs-primary">{value}</strong>
    </div>
  );
}

function Block({ label, value }) {
  return (
    <div>
      <span className="cal:font-mono cal:text-[8px] cal:font-bold cal:uppercase cal:tracking-[.08em] cal:text-tracs-muted">{label}</span>
      <p className="cal:mt-1 cal:whitespace-pre-wrap cal:text-xs cal:leading-relaxed cal:text-tracs-secondary">{value}</p>
    </div>
  );
}
