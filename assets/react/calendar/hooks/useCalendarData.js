import { useCallback, useEffect, useRef, useState } from 'react';
import { calendarApi } from '../api/calendarApi';
import { yearRange } from '../utils/date';

export function useCalendarData(year) {
  const cache = useRef(new Map());
  const [events, setEvents] = useState([]);
  const [metadata, setMetadata] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const loadYear = useCallback(async (targetYear, force = false) => {
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
      cache.current.set(targetYear, data.events || []);
      setEvents(data.events || []);
    } catch (requestError) {
      setError(requestError.message || 'Calendar data could not be loaded.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadYear(year);
  }, [loadYear, year]);

  useEffect(() => {
    calendarApi.metadata()
      .then(setMetadata)
      .catch((requestError) => setError(requestError.message || 'Calendar metadata could not be loaded.'));
  }, []);

  const refresh = useCallback(async () => {
    cache.current.delete(year);
    await loadYear(year, true);
  }, [loadYear, year]);

  return { events, metadata, loading, error, refresh, retry: () => loadYear(year, true) };
}
