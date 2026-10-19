import { useId, useRef, useState, type ElementType } from 'react';
import { ChevronDown } from 'lucide-react';

export const normalizeLocation = (text: string) => text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();

export default function LocationComboField({ label, value, onChange, placeholder, options, icon: Icon }: { label: string; value: string; onChange: (value: string) => void; placeholder?: string; options: string[]; icon?: ElementType }) {
  const id = useId();
  const input = useRef<HTMLInputElement>(null);
  const [open, setOpen] = useState(false);
  const [filtering, setFiltering] = useState(false);
  const [active, setActive] = useState(-1);
  const matches = options.filter(option => !filtering || normalizeLocation(option).includes(normalizeLocation(value)));
  const choose = (option: string) => { onChange(option); setOpen(false); setFiltering(false); setActive(-1); };

  return <div className="text-sm relative" onBlur={event => { if (!event.currentTarget.contains(event.relatedTarget as Node)) setOpen(false); }}>
    <label htmlFor={id}>{label}</label>
    <div className="relative mt-1">
      {Icon && <Icon aria-hidden="true" className="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />}
      <input ref={input} id={id} role="combobox" aria-autocomplete="list" aria-expanded={open} aria-controls={`${id}-list`} aria-activedescendant={open && active >= 0 && matches[active] ? `${id}-option-${active}` : undefined} autoComplete="off"
        className={`input pr-10 ${Icon ? 'pl-9' : ''}`} value={value} placeholder={placeholder}
        onFocus={() => { setOpen(true); setFiltering(false); setActive(-1); }}
        onChange={event => { onChange(event.target.value); setFiltering(true); setOpen(true); setActive(-1); }}
        onKeyDown={event => {
          if (event.key === 'Escape') { setOpen(false); setActive(-1); }
          if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault(); setOpen(true);
            setActive(index => matches.length ? (index + (event.key === 'ArrowDown' ? 1 : -1) + matches.length) % matches.length : -1);
          }
          if (event.key === 'Enter' && open && active >= 0 && matches[active]) { event.preventDefault(); choose(matches[active]); }
        }} />
      <button type="button" aria-label={`Mostrar opções de ${label}`} aria-expanded={open} aria-controls={`${id}-list`} className="absolute right-0 top-0 h-full px-3 text-slate-500 dark:text-slate-400" onMouseDown={event => event.preventDefault()} onClick={() => { input.current?.focus(); setFiltering(false); setActive(-1); setOpen(!open); }}><ChevronDown aria-hidden="true" className="size-4" /></button>
    </div>
    {open && <ul id={`${id}-list`} role="listbox" aria-label={`Opções de ${label}`} className="absolute z-50 mt-1 max-h-56 w-full min-w-44 overflow-y-auto rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-1 shadow-lg">
      {matches.map((option, index) => <li key={option} id={`${id}-option-${index}`} role="option" aria-selected={value === option} className={`cursor-pointer rounded-lg px-3 py-2 ${active === index ? 'bg-primary-100 dark:bg-primary-900 text-primary-900 dark:text-primary-200' : 'text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800'}`} onMouseDown={event => event.preventDefault()} onMouseEnter={() => setActive(index)} onClick={() => choose(option)}>{option}</li>)}
      {!matches.length && <li role="presentation" className="px-3 py-2 text-xs text-slate-500 dark:text-slate-400">Sem sugestões para esta cidade. Você pode digitar o {label.toLowerCase()}.</li>}
    </ul>}
  </div>;
}
