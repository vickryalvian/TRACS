import { useCallback, useEffect, useState } from 'react';
import { loadShiftAssignments } from '../api';

export function useShiftAssignments(filters, enabled = true) {
  const [state, setState] = useState({
    data: null,
    loading: enabled,
    error: null,
  });

  const load = useCallback(async (signal) => {
    if (!enabled) {
      return;
    }

    setState((current) => ({ ...current, loading: true, error: null }));
    try {
      const response = await loadShiftAssignments(filters, { signal });
      setState({ data: response.data, loading: false, error: null });
    } catch (error) {
      if (error?.name === 'AbortError') {
        return;
      }
      setState({ data: null, loading: false, error });
    }
  }, [enabled, filters]);

  useEffect(() => {
    const controller = new AbortController();
    load(controller.signal);
    return () => controller.abort();
  }, [load]);

  return { ...state, refresh: () => load(), retry: () => load() };
}
