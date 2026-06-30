import { useCallback, useEffect, useState } from 'react';
import { loadGlobalContext, loadShiftContext } from '../api';

export function useShiftAssignmentContext() {
  const [state, setState] = useState({
    global: null,
    shift: null,
    loading: true,
    error: null,
  });

  const load = useCallback(async () => {
    setState((current) => ({ ...current, loading: true, error: null }));

    try {
      const [globalResponse, shiftResponse] = await Promise.all([
        loadGlobalContext(),
        loadShiftContext(),
      ]);
      setState({
        global: globalResponse.data,
        shift: shiftResponse.data,
        loading: false,
        error: null,
      });
    } catch (error) {
      setState({ global: null, shift: null, loading: false, error });
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  return { ...state, retry: load };
}
