import React, { useEffect, useRef } from 'react';
import { createPortal } from 'react-dom';
import { X } from 'lucide-react';

export function TracsModal({ title, onClose, children, wide = false }) {
  const ref = useRef(null);
  const closeRef = useRef(onClose);
  closeRef.current = onClose;
  useEffect(() => {
    const previous = document.activeElement;
    const overflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    const dialog = ref.current;
    dialog.querySelector('input, button, select, textarea, [tabindex="0"]')?.focus();
    function keydown(event) {
      if (event.target.closest('[role="dialog"]') !== dialog) return;
      if (event.key === 'Escape') { event.stopPropagation(); closeRef.current(); }
      if (event.key !== 'Tab') return;
      const items = [...dialog.querySelectorAll('button:not(:disabled), input:not(:disabled), select:not(:disabled), textarea:not(:disabled), a[href], [tabindex="0"]')].filter(el => el.getClientRects().length);
      const first = items[0], last = items.at(-1);
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
    }
    dialog.addEventListener('keydown', keydown);
    return () => { document.body.style.overflow = overflow; dialog.removeEventListener('keydown', keydown); previous?.focus(); };
  }, []);
  return createPortal(<div className="modal-overlay clients-modal-overlay" style={{ display: 'flex', zIndex: 10010 }} onMouseDown={e => { if (e.target === e.currentTarget) onClose(); }}>
    <section ref={ref} className="modal clients-modal" role="dialog" aria-modal="true" aria-label={title} style={{ width: wide ? 'min(1200px, 96vw)' : 'min(760px, 96vw)', maxWidth: 'none', maxHeight: '92dvh', display: 'flex', flexDirection: 'column' }}>
      <div className="modal-head"><div className="modal-title">{title}</div><button className="modal-close" type="button" onClick={onClose} aria-label="Close modal"><X size={16} /></button></div>
      <div className="modal-body tracs-react-root clients-react-shell" style={{ overflowY: 'auto', minHeight: 0 }}>{children}</div>
    </section>
  </div>, document.body);
}
