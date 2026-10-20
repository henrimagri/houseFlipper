import { useCallback, useEffect, useLayoutEffect, useMemo, useRef, useState, type CSSProperties } from 'react';
import { ChevronLeft, ChevronRight, X } from 'lucide-react';
import { useLocation, useNavigate } from 'react-router-dom';
import api from '@/lib/api';
import { ProductSlug } from '@/lib/products';
import { TOURS } from '@/lib/tours';

type Rect = { top: number; left: number; width: number; height: number };
const TOUR_VERSION = 2;

function routeMatches(pathname: string, route?: string) { return !route || pathname === route || pathname.startsWith(`${route}/`); }
function findTarget(selector: string) { return document.querySelector<HTMLElement>(selector); }

async function waitForTarget(selector: string, timeout = 5000) {
  const start = performance.now();
  while (performance.now() - start < timeout) {
    const element = findTarget(selector);
    if (element) return element;
    await new Promise<void>(resolve => requestAnimationFrame(() => resolve()));
  }
  return null;
}

export default function ProductTour({ product, force, onClose }: { product: ProductSlug; force?: boolean; onClose?: () => void }) {
  const navigate = useNavigate(); const location = useLocation(); const steps = TOURS[product] ?? [];
  const [open, setOpen] = useState(false); const [index, setIndex] = useState(0); const [rect, setRect] = useState<Rect | null>(null); const [loaded, setLoaded] = useState(false);
  const root = useRef<HTMLDivElement>(null); const panel = useRef<HTMLDivElement>(null); const restoreFocus = useRef<HTMLElement | null>(null); const closed = useRef(false);
  const step = steps[index];

  useEffect(() => {
    let alive = true;
    api.get(`/onboarding/${product}`).then(({ data }) => {
      if (!alive) return; const payload = data.data ?? data; const current = Number(payload.current_step || 0);
      if (force) { closed.current = false; setIndex(0); setOpen(true); }
      else { setIndex(Number.isFinite(current) ? Math.min(current, Math.max(0, steps.length - 1)) : 0); setOpen(!closed.current && !payload.completed_at && !payload.dismissed); }
      setLoaded(true);
    }).catch(() => setLoaded(true));
    return () => { alive = false; };
  }, [product, force, steps.length]);

  const measure = useCallback(() => {
    if (!step) return; const target = findTarget(step.selector);
    if (!target) { setRect(null); return; }
    const box = target.getBoundingClientRect();
    setRect(previous => {
      const next = { top: box.top, left: box.left, width: box.width, height: box.height };
      if (previous && Math.abs(previous.top - next.top) < .5 && Math.abs(previous.left - next.left) < .5 && Math.abs(previous.width - next.width) < .5 && Math.abs(previous.height - next.height) < .5) return previous;
      return next;
    });
  }, [step]);

  useEffect(() => {
    if (!open || !step) return;
    if (!routeMatches(location.pathname, step.route)) { setRect(null); navigate(step.route!); return; }
    let cancelled = false; let resizeObserver: ResizeObserver | undefined;
    void waitForTarget(step.selector).then(target => {
      if (cancelled || !target) { if (!cancelled) setRect(null); return; }
      target.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'center', inline: 'nearest' });
      requestAnimationFrame(() => { if (!cancelled) measure(); });
      resizeObserver = new ResizeObserver(measure); resizeObserver.observe(target);
    });
    const update = () => measure(); window.addEventListener('resize', update); window.addEventListener('scroll', update, true);
    const mutations = new MutationObserver(update); mutations.observe(document.body, { childList: true, subtree: true });
    return () => { cancelled = true; resizeObserver?.disconnect(); mutations.disconnect(); window.removeEventListener('resize', update); window.removeEventListener('scroll', update, true); };
  }, [open, step, location.pathname, navigate, measure]);

  const persist = async (nextIndex: number, completed: boolean, dismissed = false) => {
    const completedSteps = steps.slice(0, completed ? steps.length : Math.min(nextIndex + 1, steps.length)).map(item => item.id);
    await api.put(`/onboarding/${product}`, { tour_version: TOUR_VERSION, current_step: nextIndex, completed_steps: completedSteps, completed, dismissed }).catch(() => undefined);
  };
  const close = useCallback(async (completed = false, dismissed = true) => {
    closed.current = true; setOpen(false); setRect(null); onClose?.(); if (!force) await persist(completed ? steps.length : index, completed, dismissed);
  }, [force, index, onClose, product, steps]);
  const next = async () => { if (index >= steps.length - 1) { await close(true, false); return; } const value = index + 1; setRect(null); setIndex(value); if (!force) await persist(value, false); };
  const back = async () => { const value = Math.max(0, index - 1); setRect(null); setIndex(value); if (!force) await persist(value, false); };

  useEffect(() => {
    if (!open) return; restoreFocus.current = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    const previousOverflow = document.body.style.overflow; document.body.style.overflow = 'hidden'; requestAnimationFrame(() => panel.current?.focus());
    const keyboard = (event: KeyboardEvent) => {
      if (event.key === 'Escape') { event.preventDefault(); void close(false, true); return; }
      if (event.key !== 'Tab' || !panel.current) return;
      const nodes = Array.from(panel.current.querySelectorAll<HTMLElement>('button:not(:disabled),a[href],[tabindex]:not([tabindex="-1"])'));
      if (!nodes.length) return; const first = nodes[0], last = nodes[nodes.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    };
    document.addEventListener('keydown', keyboard);
    return () => { document.removeEventListener('keydown', keyboard); document.body.style.overflow = previousOverflow; restoreFocus.current?.focus(); };
  }, [open, close]);

  const spotlight = useMemo(() => rect ? { top: Math.max(8, rect.top - 7), left: Math.max(8, rect.left - 7), width: Math.min(window.innerWidth - Math.max(8, rect.left - 7) - 8, rect.width + 14), height: Math.min(window.innerHeight - Math.max(8, rect.top - 7) - 8, rect.height + 14) } : null, [rect]);
  const [panelStyle, setPanelStyle] = useState<CSSProperties>({});
  useLayoutEffect(() => {
    if (!open) return; const width = panel.current?.offsetWidth ?? Math.min(360, window.innerWidth - 24); const height = panel.current?.offsetHeight ?? 260; const gap = 18; let left = Math.max(12, (window.innerWidth - width) / 2); let top = Math.max(12, window.innerHeight - height - 16);
    if (spotlight && window.innerWidth >= 640) {
      const roomRight = window.innerWidth - (spotlight.left + spotlight.width); const roomLeft = spotlight.left;
      if (roomRight >= width + gap) { left = spotlight.left + spotlight.width + gap; top = spotlight.top; }
      else if (roomLeft >= width + gap) { left = spotlight.left - width - gap; top = spotlight.top; }
      else if (window.innerHeight - (spotlight.top + spotlight.height) >= height + gap) { left = spotlight.left; top = spotlight.top + spotlight.height + gap; }
      else { left = spotlight.left; top = spotlight.top - height - gap; }
    }
    setPanelStyle({ left: Math.max(12, Math.min(left, window.innerWidth - width - 12)), top: Math.max(12, Math.min(top, window.innerHeight - height - 12)) });
  }, [open, spotlight, index]);

  if (!loaded || !open || !step) return null;
  return <div ref={root} className="fixed inset-0 z-[90] pointer-events-auto" role="dialog" aria-modal="true" aria-labelledby="tour-title" onKeyDown={event => { if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); void close(false, true); } }} onPointerDown={event => { if (!panel.current?.contains(event.target as Node)) { event.preventDefault(); event.stopPropagation(); } }}>
    <div aria-hidden="true" className="absolute inset-0 bg-slate-950/64 backdrop-blur-[1px]" />
    {spotlight && <div aria-hidden="true" className="pointer-events-none fixed rounded-xl border-2 border-cyan-300 bg-transparent shadow-[0_0_0_4px_rgba(34,211,238,.22),0_0_35px_rgba(34,211,238,.32)]" style={spotlight} />}
    <div ref={panel} tabIndex={-1} className="fixed z-10 w-[min(360px,calc(100vw-24px))] rounded-2xl border border-slate-200 bg-white p-5 text-slate-900 shadow-2xl outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100" style={panelStyle} onPointerDown={event => event.stopPropagation()}>
      <div className="flex items-start justify-between gap-3"><p className="text-[10px] font-black uppercase tracking-[.18em] text-primary-600">Tutorial · {index + 1} de {steps.length}</p><button type="button" onClick={() => void close(false, true)} className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" aria-label="Fechar tutorial"><X className="size-4" /></button></div>
      <h2 id="tour-title" className="mt-2 text-lg font-black">{step.title}</h2><p className="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">{step.body}</p>
      {!rect && <p role="status" className="mt-3 rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-950 dark:text-amber-200">Localizando o elemento nesta página…</p>}
      <div className="mt-5 h-1 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"><div className="h-full rounded-full bg-gradient-to-r from-primary-600 to-cyan-400 transition-all" style={{ width: `${((index + 1) / steps.length) * 100}%` }} /></div>
      <div className="mt-5 flex flex-wrap items-center justify-between gap-2"><button type="button" onClick={() => void close(false, true)} className="rounded-lg px-2 py-2 text-xs font-bold text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800">Pular tutorial</button><div className="flex gap-2"><button type="button" disabled={index === 0} onClick={() => void back()} className="inline-flex items-center gap-1 rounded-xl border px-3 py-2 text-xs font-bold disabled:opacity-40"><ChevronLeft className="size-3.5" /> Voltar</button><button type="button" onClick={() => void next()} className="inline-flex items-center gap-1 rounded-xl bg-primary-600 px-3 py-2 text-xs font-bold text-white">{index >= steps.length - 1 ? 'Concluir' : 'Próximo'}{index < steps.length - 1 && <ChevronRight className="size-3.5" />}</button></div></div>
    </div>
  </div>;
}
