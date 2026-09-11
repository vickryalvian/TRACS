import { useCallback, useEffect, useRef, useState } from 'react';
import { calendarApi } from '../api/calendarApi';
import { yearRange } from '../utils/date';

export function useCalendarData(year, requiredSource = null) {
  const cache = useRef(new Map());
  const sequence = useRef(0);
  const [events, setEvents] = useState([]);
  const [metadata, setMetadata] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const loadYear = useCallback(async (targetYear, force = false) => {
    const requestId = ++sequence.current;
    setError('');
    if (!force && cache.current.has(targetYear)) {
      setEvents(cache.current.get(targetYear));
      setLoading(false);
      return;
    }
    setLoading(true);
    try {
      const range = yearRange(targetYear);
      const data = await calendarApi.events(range.start, range.end);
      if (requestId !== sequence.current) return;
      if (requiredSource && data.sources?.[requiredSource]?.available === false) throw new Error('Client reminders could not be loaded. Please retry.');
      cache.current.set(targetYear, data.events || []);
      setEvents(data.events || []);
    } catch (requestError) {
      if (requestId !== sequence.current) return;
      setError(requestError.message || 'Calendar data could not be loaded.');
    } finally {
      if (requestId === sequence.current) setLoading(false);
    }
  }, [requiredSource]);

  useEffect(() => {
    loadYear(year);
  }, [loadYear, year]);

  useEffect(() => {
    calendarApi.metadata()
      .then(setMetadata)
      .catch((requestError) => setError(requestError.message || 'Calendar metadata could not be loaded.'));
  }, []);

  const refresh = useCallback(async () => {
    cache.current.clear();
    await loadYear(year, true);
  }, [loadYear, year]);

  useEffect(() => {
    const changed = (event) => {
      if (event.type === 'storage' && event.key !== 'tracs-calendar-updated') return;
      if (event.type === 'visibilitychange' && document.hidden) return;
      refresh();
    };
    window.addEventListener('focus', changed);
    window.addEventListener('storage', changed);
    document.addEventListener('visibilitychange', changed);
    return () => {
      window.removeEventListener('focus', changed);
      window.removeEventListener('storage', changed);
      document.removeEventListener('visibilitychange', changed);
    };
  }, [refresh]);

  return { events, metadata, loading, error, refresh, retry: () => loadYear(year, true) };
}
