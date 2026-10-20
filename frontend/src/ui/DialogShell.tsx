import { useEffect, useId, useRef, type ReactNode } from 'react';
import { X } from 'lucide-react';

type DialogShellProps = {
  title: string;
  description?: string;
  children: ReactNode;
  footer?: ReactNode;
  onClose: () => void;
  size?: 'sm' | 'md' | 'lg' | 'xl';
  closeDisabled?: boolean;
};

const widths = { sm: 'max-w-md', md: 'max-w-xl', lg: 'max-w-4xl', xl: 'max-w-6xl' };

export default function DialogShell({ title, description, children, footer, onClose, size = 'md', closeDisabled = false }: DialogShellProps) {
  const titleId = useId();
  const descriptionId = useId();
  const panel = useRef<HTMLDivElement>(null);
  const onCloseRef = useRef(onClose);
  onCloseRef.current = onClose;

  useEffect(() => {
    const trigger = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    const first = panel.current?.querySelector<HTMLElement>('button:not(:disabled), a[href], input:not(:disabled), select:not(:disabled), textarea:not(:disabled), [tabindex]:not([tabindex="-1"])');
    first?.focus();
    const keyboard = (event: KeyboardEvent) => {
      if (event.key === 'Escape' && !closeDisabled) onCloseRef.current();
      if (event.key !== 'Tab' || !panel.current) return;
      const nodes = Array.from(panel.current.querySelectorAll<HTMLElement>('button:not(:disabled), a[href], input:not(:disabled), select:not(:disabled), textarea:not(:disabled), [tabindex]:not([tabindex="-1"])'));
      if (!nodes.length) return;
      const firstNode = nodes[0];
      const lastNode = nodes[nodes.length - 1];
      if (event.shiftKey && document.activeElement === firstNode) { event.preventDefault(); lastNode.focus(); }
      else if (!event.shiftKey && document.activeElement === lastNode) { event.preventDefault(); firstNode.focus(); }
    };
    document.addEventListener('keydown', keyboard);
    return () => {
      document.removeEventListener('keydown', keyboard);
      document.body.style.overflow = previousOverflow;
      trigger?.focus();
    };
  }, [closeDisabled]);

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto bg-slate-950/60 p-0 backdrop-blur-[2px] sm:p-4" onMouseDown={(event) => { if (event.target === event.currentTarget && !closeDisabled) onClose(); }}>
      <div className="grid min-h-full place-items-center">
        <div ref={panel} role="dialog" aria-modal="true" aria-labelledby={titleId} aria-describedby={description ? descriptionId : undefined} className={`flex max-h-[100dvh] w-full flex-col overflow-hidden bg-white text-slate-900 shadow-2xl dark:bg-slate-900 dark:text-slate-100 sm:max-h-[calc(100dvh-2rem)] sm:rounded-2xl ${widths[size]}`}>
          <header className="flex shrink-0 items-start justify-between gap-4 border-b px-5 py-4 dark:border-slate-700">
            <div><h2 id={titleId} className="text-lg font-bold">{title}</h2>{description && <p id={descriptionId} className="mt-1 text-sm text-slate-500 dark:text-slate-400">{description}</p>}</div>
            <button type="button" disabled={closeDisabled} onClick={onClose} aria-label="Fechar" className="rounded-lg p-2 hover:bg-slate-100 disabled:opacity-50 dark:hover:bg-slate-800"><X className="h-5 w-5" /></button>
          </header>
          <div className="min-h-0 flex-1 overflow-y-auto p-5">{children}</div>
          {footer && <footer className="shrink-0 border-t px-5 py-4 dark:border-slate-700">{footer}</footer>}
        </div>
      </div>
    </div>
  );
}
