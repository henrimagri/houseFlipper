import { FormEvent, useState } from 'react';
import { Plus, Ruler } from 'lucide-react';

export type RoomInput = { type: string; furnished: boolean; dimensions: { width: number; depth: number; height: number } };

export default function CreateEditableRoom({ busy, hasRooms, onCreate }: { busy: boolean; hasRooms: boolean; onCreate: (input: RoomInput) => Promise<void> }) {
  const [open, setOpen] = useState(!hasRooms);
  const [type, setType] = useState('living');
  const [furnished, setFurnished] = useState(true);
  const [dimensions, setDimensions] = useState({ width: '4', depth: '5', height: '2.7' });
  const [error, setError] = useState('');
  async function submit(event: FormEvent) {
    event.preventDefault();
    setError('');
    try {
      await onCreate({ type, furnished, dimensions: { width: Number(dimensions.width), depth: Number(dimensions.depth), height: Number(dimensions.height) } });
      setOpen(false);
    } catch (err) { setError(err instanceof Error ? err.message : 'Não foi possível criar o ambiente.'); }
  }
  return <div className="border-b border-white/10 bg-slate-900 px-5 py-4 text-slate-100">
    <button type="button" onClick={() => setOpen(!open)} aria-expanded={open} className="flex items-center gap-2 text-sm font-bold"><Plus size={16} />{hasRooms ? 'Adicionar ambiente' : 'Criar meu ambiente 3D'}</button>
    {open && <form onSubmit={submit} className="mt-4 space-y-4">
      <p className="text-sm text-slate-300">Comece sem fotos. Informe as medidas do cômodo e escolha uma proposta de mobiliário ou um ambiente vazio.</p>
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <label className="text-xs">Ambiente<select value={type} onChange={event => setType(event.target.value)} className="mt-1 block w-full rounded-lg border border-white/20 bg-slate-950 p-2.5">{Object.entries({ living: 'Sala', bedroom: 'Quarto', kitchen: 'Cozinha', bathroom: 'Banheiro', office: 'Escritório', outdoor: 'Área externa' }).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></label>
        {(['width', 'depth', 'height'] as const).map(field => <label key={field} className="text-xs">{{ width: 'Largura (m)', depth: 'Comprimento (m)', height: 'Altura (m)' }[field]}<input required type="number" step="0.01" min={field === 'height' ? 2 : 1.8} max={field === 'height' ? 8 : 20} value={dimensions[field]} onChange={event => setDimensions(current => ({ ...current, [field]: event.target.value }))} className="mt-1 block w-full rounded-lg border border-white/20 bg-slate-950 p-2.5" /></label>)}
        <label className="text-xs">Começar com<select value={furnished ? 'furnished' : 'empty'} onChange={event => setFurnished(event.target.value === 'furnished')} className="mt-1 block w-full rounded-lg border border-white/20 bg-slate-950 p-2.5"><option value="furnished">Mobiliário sugerido</option><option value="empty">Ambiente vazio</option></select></label>
      </div>
      <p className="flex items-center gap-2 text-xs text-amber-200"><Ruler size={14} />Simulação paramétrica: as medidas são informadas por você; não são extraídas de uma fotografia.</p>
      {error && <p role="alert" className="text-sm text-red-300">{error}</p>}
      <button disabled={busy} className="rounded-lg bg-cyan-400 px-4 py-2 text-sm font-bold text-slate-950 disabled:opacity-50">{busy ? 'Criando...' : 'Criar ambiente editável'}</button>
    </form>}
  </div>;
}
