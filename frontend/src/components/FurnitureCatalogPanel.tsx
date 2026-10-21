import { useState } from 'react';
import { Armchair, BedDouble, Box, ExternalLink, LampFloor, Leaf, Search, Sofa, Table2, Archive, Droplets } from 'lucide-react';
import { catalogEntry } from '@/lib/furnitureCatalog';
import { objectLabel } from '@/lib/propertyVisualization';

const icons = { sofa: Sofa, chair: Armchair, table: Table2, bed: BedDouble, wardrobe: Archive, lamp: LampFloor, plant: Leaf, sink: Droplets };

export default function FurnitureCatalogPanel({ kinds, disabled, onAdd }: {
  kinds: readonly string[];
  disabled?: boolean;
  onAdd: (kind: string) => void;
}) {
  const [query, setQuery] = useState('');
  const [source, setSource] = useState<'all' | 'glb' | 'parametric'>('all');
  const normalize = (value: string) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
  const entries = [...new Set(kinds)].filter(kind => !['window', 'door', 'partition'].includes(kind)).filter(kind => {
    const entry = catalogEntry(kind);
    return (source === 'all' || (source === 'glb' ? Boolean(entry?.assetUrl) : !entry?.assetUrl))
      && normalize([objectLabel(kind), entry?.label, ...(entry?.materials ?? [])].join(' ')).includes(normalize(query));
  });

  return <section aria-label="Catálogo de móveis" className="space-y-3">
    <div className="relative"><Search size={14} className="absolute left-2.5 top-2.5 text-slate-400" aria-hidden="true" /><input aria-label="Buscar móvel ou material" value={query} onChange={event => setQuery(event.target.value)} placeholder="Móvel ou material" className="w-full rounded-lg border border-white/15 bg-slate-950 py-2 pl-8 pr-2 text-xs" /></div>
    <label className="block text-[11px] text-slate-300">Origem do modelo<select aria-label="Origem do móvel" value={source} onChange={event => setSource(event.target.value as typeof source)} className="mt-1 w-full rounded-lg border border-white/15 bg-slate-950 p-2"><option value="all">Todo o catálogo</option><option value="glb">Modelos GLB licenciados</option><option value="parametric">Modelos paramétricos</option></select></label>
    <p className="text-[10px] leading-relaxed text-slate-400">Dimensões de referência em largura × altura × profundidade. Ajuste escala e posição depois de inserir. Modelos adicionados são propostas de decoração.</p>
    <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-1">
      {entries.map(kind => {
        const entry = catalogEntry(kind);
        const Icon = icons[kind as keyof typeof icons] ?? Box;
        return <article key={kind} className="rounded-xl border border-white/10 bg-slate-950/60 p-3">
          <div className="mb-2 flex items-start gap-2"><Icon size={22} className="shrink-0 text-cyan-300" aria-hidden="true" /><div><h4 className="text-xs font-semibold text-white">{entry?.label ?? objectLabel(kind)}</h4><p className="mt-1 text-[10px] text-slate-400">{entry?.assetUrl ? 'GLB licenciado · materiais do modelo' : 'Paramétrico · geometria aproximada'}</p></div></div>
          {entry && <><p className="text-[11px] text-slate-300">{entry.dimensionsM.map(n => n.toLocaleString('pt-BR')).join(' × ')} m</p><p className="mt-1 text-[10px] text-slate-400">{entry.materials.join(' · ')}</p></>}
          <button type="button" disabled={disabled} onClick={() => onAdd(kind)} className="mt-3 w-full rounded-lg bg-cyan-400/15 px-2 py-2 text-xs font-semibold text-cyan-200 hover:bg-cyan-400/25 disabled:opacity-40">Adicionar {objectLabel(kind).toLocaleLowerCase('pt-BR')}</button>
          {entry && <details className="mt-2 text-[10px] text-slate-400"><summary className="cursor-pointer">Licença e autoria</summary><p className="mt-1">{entry.license} · {entry.attribution}</p>{entry.sourceUrl && <a href={entry.sourceUrl} target="_blank" rel="noreferrer" className="mt-1 inline-flex items-center gap-1 text-cyan-200">Fonte do modelo <ExternalLink size={10} /></a>}</details>}
        </article>;
      })}
    </div>
    {entries.length === 0 && <p role="status" className="rounded-lg border border-white/10 p-3 text-xs text-slate-300">Nenhum móvel encontrado. Tente outro nome ou origem.</p>}
  </section>;
}
