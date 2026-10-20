const MAP: Record<string, string> = {
  published: 'bg-emerald-100 text-emerald-800',
  draft: 'bg-slate-100 text-slate-700',
  ready: 'bg-sky-100 text-sky-800',
  paused: 'bg-amber-100 text-amber-800',
  new: 'bg-sky-100 text-sky-800',
  novo: 'bg-sky-100 text-sky-800',
  contacted: 'bg-indigo-100 text-indigo-800',
  qualified: 'bg-violet-100 text-violet-800',
  won: 'bg-emerald-100 text-emerald-800',
  lost: 'bg-rose-100 text-rose-800',
  projected: 'bg-amber-100 text-amber-800',
  paid: 'bg-emerald-100 text-emerald-800',
  cancelled: 'bg-slate-200 text-slate-600',
  alta: 'bg-rose-100 text-rose-800',
  normal: 'bg-slate-100 text-slate-700',
  baixa: 'bg-gray-100 text-gray-600',
};

export default function StatusBadge({ value }: { value: string }) {
  return <span className={`inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold uppercase tracking-wide ${MAP[value] ?? 'bg-gray-100 text-gray-700'}`}>{value}</span>;
}
