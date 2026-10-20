import { Search, Loader2, X } from 'lucide-react';
import type { FormEvent } from 'react';

type Props = { value: string; onChange: (value: string) => void; onSubmit?: () => void; placeholder?: string; label?: string; loading?: boolean; shortcut?: string; className?: string };

export default function AppSearchInput({ value, onChange, onSubmit, placeholder = 'Pesquisar', label = 'Pesquisar', loading = false, shortcut, className = '' }: Props) {
  function submit(event: FormEvent) { event.preventDefault(); if (!loading && value.trim()) onSubmit?.(); }
  return <form role="search" onSubmit={submit} className={`group flex h-11 items-center gap-2 rounded-xl border border-slate-200 bg-slate-50/90 px-3 transition focus-within:border-primary-400 focus-within:bg-white focus-within:ring-4 focus-within:ring-primary-100/70 dark:border-slate-700 dark:bg-slate-950/70 dark:focus-within:bg-slate-950 dark:focus-within:ring-primary-950 ${className}`}>
    {loading ? <Loader2 className="size-4 animate-spin text-primary-600" aria-hidden="true" /> : <Search className="size-4 text-slate-400" aria-hidden="true" />}
    <label className="sr-only">{label}</label>
    <input type="search" value={value} onChange={event => onChange(event.target.value)} placeholder={placeholder} className="min-w-0 flex-1 bg-transparent text-sm outline-none placeholder:text-slate-400 [&::-webkit-search-cancel-button]:hidden" />
    {value && <button type="button" onClick={() => onChange('')} aria-label="Limpar pesquisa" className="rounded-md p-1 text-slate-400 hover:bg-slate-200 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"><X className="size-3.5" /></button>}
    {shortcut && !value && <kbd className="hidden rounded-md border bg-white px-1.5 py-0.5 text-[10px] text-slate-400 dark:bg-slate-900 sm:inline">{shortcut}</kbd>}
  </form>;
}
