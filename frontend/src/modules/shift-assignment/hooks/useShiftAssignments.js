import { useCallback, useEffect, useState } from 'react';
import { loadShiftAssignments } from '../api';

export function useShiftAssignments(filters, enabled = true) {
  const [state, setState] = useState({
    data: null,
    loading: enabled,
    error: null,
  });

  const load = useCallback(async () => {
    if (!enabled) {
      return;
    }

    setState((current) => ({ ...current, loading: true, error: null }));
    try {
      const response = await loadShiftAssignments(filters);
      setState({ data: response.data, loading: false, error: null });
    } catch (error) {
      setState({ data: null, loading: false, error });
    }
  }, [enabled, filters]);

  useEffect(() => {
    load();
  }, [load]);

  return { ...state, retry: load };
}
