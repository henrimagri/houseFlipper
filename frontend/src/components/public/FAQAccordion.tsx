import { useState } from 'react';
import { PUBLIC_FAQ } from '@/lib/publicCatalog';
import { ChevronDown } from 'lucide-react';

export default function FAQAccordion({ items = PUBLIC_FAQ }: { items?: { q: string; a: string }[] }) {
  const [open, setOpen] = useState<number | null>(0);
  return (
    <div className="divide-y overflow-hidden rounded-2xl border bg-white shadow-sm dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900">
      {items.map((item, index) => (
        <div key={item.q}>
          <button type="button" className="flex w-full items-center justify-between gap-4 px-5 py-4 text-left font-semibold transition hover:bg-slate-50 dark:hover:bg-slate-800" onClick={() => setOpen(open === index ? null : index)} aria-expanded={open === index}>
            <span>{item.q}</span><ChevronDown className={`size-4 shrink-0 text-slate-400 transition ${open === index ? 'rotate-180' : ''}`} />
          </button>
          {open === index && <p className="px-5 pb-4 text-sm leading-6 text-slate-600 dark:text-slate-300">{item.a}</p>}
        </div>
      ))}
    </div>
  );
}
