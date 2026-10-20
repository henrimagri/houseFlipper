import { useEffect, useMemo, useRef, useState } from 'react';
import { Command, Search, X } from 'lucide-react';
import { useNavigate } from 'react-router-dom';

type Item = { label: string; path: string; section?: string };
export default function CommandPalette({ open, onClose, items }: { open: boolean; onClose: () => void; items: Item[] }) {
  const [query, setQuery] = useState(''); const input = useRef<HTMLInputElement>(null); const navigate = useNavigate();
  const results = useMemo(() => items.filter(item => `${item.label} ${item.section ?? ''}`.toLowerCase().includes(query.toLowerCase())).slice(0, 10), [items, query]);
  useEffect(() => { if (open) { setQuery(''); requestAnimationFrame(() => input.current?.focus()); } }, [open]);
  useEffect(() => { if (!open) return; const key = (event: KeyboardEvent) => { if (event.key === 'Escape') onClose(); }; document.addEventListener('keydown', key); return () => document.removeEventListener('keydown', key); }, [open, onClose]);
  if (!open) return null;
  const go = (path: string) => { onClose(); navigate(path); };
  return <div className="fixed inset-0 z-[80] flex justify-center bg-slate-950/55 px-4 pt-[12vh] backdrop-blur-sm" onMouseDown={event => { if (event.target === event.currentTarget) onClose(); }}><div role="dialog" aria-modal="true" aria-label="Comandos e páginas" className="h-fit w-full max-w-xl overflow-hidden rounded-2xl border bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900"><div className="flex items-center gap-3 border-b px-4"><Search className="size-5 text-slate-400"/><input ref={input} value={query} onChange={event => setQuery(event.target.value)} onKeyDown={event => { if (event.key === 'Enter' && results[0]) go(results[0].path); }} placeholder="Buscar uma página deste produto…" className="h-14 flex-1 bg-transparent text-sm outline-none"/><button type="button" onClick={onClose} aria-label="Fechar comandos" className="rounded-lg p-2 hover:bg-slate-100 dark:hover:bg-slate-800"><X className="size-4"/></button></div><div className="max-h-80 overflow-y-auto p-2">{results.length ? results.map((item, index) => <button key={`${item.path}-${index}`} type="button" onClick={() => go(item.path)} className="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left hover:bg-slate-100 dark:hover:bg-slate-800"><Command className="size-4 text-primary-500"/><span className="flex-1 text-sm font-semibold">{item.label}</span>{item.section && <span className="text-[10px] uppercase tracking-wider text-slate-400">{item.section}</span>}</button>) : <p className="p-8 text-center text-sm text-slate-500">Nenhuma página encontrada neste produto.</p>}</div><p className="border-t px-4 py-2 text-[10px] text-slate-400">Enter abre o primeiro resultado · Esc fecha</p></div></div>;
}
