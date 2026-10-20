import { useEffect, useRef, useState } from 'react';
import { Check, ChevronDown, Monitor, Moon, Sun } from 'lucide-react';
import { useTheme, type ThemeMode } from './ThemeProvider';

const choices: Array<{ value: ThemeMode; label: string; Icon: typeof Monitor }> = [
  { value: 'system', label: 'Sistema', Icon: Monitor },
  { value: 'light', label: 'Claro', Icon: Sun },
  { value: 'dark', label: 'Escuro', Icon: Moon },
];

export default function ThemeSelector({ compact = false }: { compact?: boolean }) {
  const { mode, setMode } = useTheme();
  const [open, setOpen] = useState(false);
  const root = useRef<HTMLDivElement>(null);
  const current = choices.find(item => item.value === mode) ?? choices[0];

  useEffect(() => {
    if (!open) return;
    const onPointer = (event: MouseEvent) => {
      if (!root.current?.contains(event.target as Node)) setOpen(false);
    };
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') setOpen(false);
    };
    document.addEventListener('mousedown', onPointer);
    document.addEventListener('keydown', onKey);
    return () => {
      document.removeEventListener('mousedown', onPointer);
      document.removeEventListener('keydown', onKey);
    };
  }, [open]);

  return <div ref={root} className="relative">
    <button type="button" aria-label={`Tema: ${current.label}`} aria-haspopup="menu" aria-expanded={open} onClick={() => setOpen(value => !value)} className="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-200 bg-white/80 px-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-primary-300 hover:bg-white dark:border-slate-700 dark:bg-slate-900/80 dark:text-slate-200 dark:hover:border-primary-700">
      <current.Icon className="size-4" aria-hidden="true" />
      {!compact && <span className="hidden xl:inline">{current.label}</span>}
      <ChevronDown className={`size-3.5 transition ${open ? 'rotate-180' : ''}`} aria-hidden="true" />
    </button>
    {open && <div role="menu" aria-label="Escolher tema" className="absolute right-0 z-50 mt-2 w-44 rounded-2xl border border-slate-200 bg-white p-1.5 shadow-2xl dark:border-slate-700 dark:bg-slate-900">
      {choices.map(({ value, label, Icon }) => <button key={value} role="menuitemradio" aria-checked={mode === value} type="button" onClick={() => { setMode(value); setOpen(false); }} className="flex w-full items-center gap-2 rounded-xl px-3 py-2 text-left text-sm hover:bg-slate-100 dark:hover:bg-slate-800">
        <Icon className="size-4 text-slate-500" aria-hidden="true" /><span className="flex-1">{label}</span>{mode === value && <Check className="size-4 text-primary-600" aria-hidden="true" />}
      </button>)}
    </div>}
  </div>;
}
