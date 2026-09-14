import { useLayoutEffect, useRef } from 'react';

function normalize(value) {
  if (Array.isArray(value)) return value.map(normalize);
  if (value && typeof value === 'object') return Object.fromEntries(Object.keys(value).sort().filter(key => value[key] != null && value[key] !== '' && value[key] !== false).map(key => [key, normalize(value[key])]));
  return value == null ? '' : String(value);
}

// React owns the values and restores them; the shared guard owns exit prompts.
export function useUnsavedForm(rootRef, state, { active = true, baseline = state, resetKey = active, restore } = {}) {
  const current = useRef(state);
  current.current = state;
  const restoreRef = useRef(restore);
  restoreRef.current = restore;
  useLayoutEffect(() => {
    const root = rootRef.current;
    if (!active || !root) return undefined;
    root.setAttribute('data-unsaved-state', '');
    const release = window.TRACSUnsavedChanges?.trackState(root, () => normalize(current.current), {
      initialState: normalize(baseline),
      restore: () => restoreRef.current?.(baseline),
    });
    return () => { release?.(); root.removeAttribute('data-unsaved-state'); };
  }, [active, resetKey]);
  useLayoutEffect(() => { if (active) window.TRACSUnsavedChanges?.refresh(); }, [state, active]);
}

export function requestFormClose(root, close) {
  if (root?.querySelector('[aria-busy="true"]') || root?.getAttribute('aria-busy') === 'true') return;
  if (window.TRACSUnsavedChanges) return window.TRACSUnsavedChanges.requestModalClose(root, close);
  close();
}
