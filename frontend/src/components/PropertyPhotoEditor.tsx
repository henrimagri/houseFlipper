import { FormEvent, PointerEvent, useEffect, useMemo, useRef, useState } from 'react';
import { BrainCircuit, Check, Eraser, History, Image as ImageIcon, MousePointer2, Palette, Sparkles, Undo2 } from 'lucide-react';
import type { PhotoEditPoint, PhotoEditRequest, PhotoEditSimulation, VisualizationAsset } from '@/lib/propertyVisualization';
import { usePrivateAssetUrl } from '@/hooks/usePrivateAssetUrl';

type Props = {
  photos: VisualizationAsset[];
  simulations?: PhotoEditSimulation[];
  busy?: boolean;
  onAnalyze?: (assetId: number) => Promise<void>;
  onApply: (request: PhotoEditRequest, source: VisualizationAsset) => Promise<PhotoEditSimulation>;
};

const DEFAULT_COLOR = '#d8c6ae';

export default function PropertyPhotoEditor({ photos, simulations = [], busy, onAnalyze, onApply }: Props) {
  const [selectedId, setSelectedId] = useState<number | null>(photos[0]?.id ?? null);
  const [wallColor, setWallColor] = useState(DEFAULT_COLOR);
  const [material, setMaterial] = useState<'paint' | 'wood'>('paint');
  const [points, setPoints] = useState<PhotoEditPoint[]>([]);
  const [pointMode, setPointMode] = useState<0 | 1>(1);
  const [history, setHistory] = useState<PhotoEditSimulation[]>(simulations);
  const [activeSimulation, setActiveSimulation] = useState<PhotoEditSimulation | null>(simulations[0] ?? null);
  const [applying, setApplying] = useState(false);
  const [error, setError] = useState('');
  const simulationsKey = simulations.map(item => item.id).join('|');

  useEffect(() => {
    if (selectedId === null && photos[0]) setSelectedId(photos[0].id);
    if (selectedId !== null && !photos.some(item => item.id === selectedId)) setSelectedId(photos[0]?.id ?? null);
  }, [photos, selectedId]);

  useEffect(() => {
    if (simulations.length === 0) return;
    setHistory(simulations);
    setActiveSimulation(simulations[0] ?? null);
  }, [simulationsKey]);

  const selected = useMemo(() => photos.find(item => item.id === selectedId) ?? null, [photos, selectedId]);
  const current = activeSimulation?.source_asset_id === selectedId ? activeSimulation : null;

  async function apply(event: FormEvent) {
    event.preventDefault();
    if (!selected) return;
    setApplying(true);
    setError('');
    try {
      const simulation = await onApply({
        asset_id: selected.id,
        color: wallColor,
        opacity: 0.82,
        material,
        points,
      }, selected);
      setHistory(items => [simulation, ...items.filter(item => item.id !== simulation.id)]);
      setActiveSimulation(simulation);
    } catch (exception: any) {
      setError(exception?.message ?? 'Não foi possível preparar a simulação desta fotografia.');
    } finally {
      setApplying(false);
    }
  }

  function restore(simulation: PhotoEditSimulation) {
    setSelectedId(simulation.source_asset_id);
    setWallColor(simulation.color);
    setPoints(simulation.points);
    setActiveSimulation(simulation);
  }

  if (photos.length === 0) {
    return <div className="grid min-h-[420px] place-items-center bg-slate-950 px-6 text-center text-slate-300">
      <div><ImageIcon className="mx-auto h-10 w-10 text-violet-300" /><h2 className="mt-4 text-xl font-black">Envie uma fotografia para começar</h2><p className="mt-2 max-w-lg text-sm">O editor mantém a imagem original e cria versões de simulação separadas.</p></div>
    </div>;
  }

  return <div className="bg-slate-950 p-4 text-slate-100 sm:p-6">
    <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_320px]">
      <div className="min-w-0 space-y-4">
        <div className="flex gap-2 overflow-x-auto pb-1" aria-label="Selecionar fotografia">
          {photos.map(photo => <button key={photo.id} type="button" onClick={() => { setSelectedId(photo.id); setActiveSimulation(null); setPoints([]); }} aria-pressed={photo.id === selectedId} className={`w-28 shrink-0 overflow-hidden rounded-xl border text-left ${photo.id === selectedId ? 'border-cyan-400 ring-2 ring-cyan-400/30' : 'border-white/10'}`}>
            <PrivateThumbnail photo={photo} /><span className="block truncate px-2 py-1.5 text-[11px] font-bold">{photo.name}</span>
          </button>)}
        </div>

        {selected && <div className="grid gap-3 md:grid-cols-2" aria-label="Comparação antes e depois">
          <InteractivePhotoFrame label="Antes · selecione a superfície" src={selected.url} points={points} pointMode={pointMode} onAddPoint={point => setPoints(items => [...items.slice(-15), point])} />
          <PhotoFrame label={current?.result_url ? 'Depois · resultado processado' : 'Depois · aguardando processamento'} src={current?.result_url || selected.url} />
        </div>}
        <p className="rounded-xl border border-cyan-300/20 bg-cyan-400/10 px-3 py-2 text-xs text-cyan-100">Clique na parede ou no piso com “incluir” e marque móveis, janelas e áreas incorretas com “excluir”. O backend usa esses pontos no SAM 2 e salva uma nova versão; a foto original permanece intacta.</p>
      </div>

      <aside className="space-y-4 rounded-2xl border border-white/10 bg-slate-900/80 p-4">
        <form onSubmit={apply} className="space-y-4">
          <div>
            <label htmlFor="photo-wall-color" className="flex items-center gap-2 text-xs font-black uppercase tracking-wide text-slate-300"><Palette className="h-4 w-4" /> Cor desejada</label>
            <div className="mt-2 flex items-center gap-2"><input id="photo-wall-color" type="color" value={wallColor} onChange={event => setWallColor(event.target.value)} className="h-10 w-14 rounded border border-white/15 bg-transparent" /><input value={wallColor} onChange={event => /^#[0-9a-f]{0,6}$/i.test(event.target.value) && setWallColor(event.target.value)} className="min-w-0 flex-1 rounded-lg border border-white/15 bg-black/20 px-3 py-2 text-sm uppercase" aria-label="Cor em hexadecimal" /></div>
          </div>
          <div><label htmlFor="photo-material" className="text-xs font-black uppercase tracking-wide text-slate-300">Acabamento</label><select id="photo-material" value={material} onChange={event => setMaterial(event.target.value as 'paint' | 'wood')} className="mt-2 w-full rounded-lg border border-white/15 bg-slate-950 px-3 py-2 text-sm"><option value="paint">Tinta — preservar luz e textura</option><option value="wood">Madeira — padrão orientado pela superfície</option></select></div>
          <div><p className="text-xs font-black uppercase tracking-wide text-slate-300">Correção da máscara</p><div className="mt-2 grid grid-cols-2 gap-2"><button type="button" onClick={() => setPointMode(1)} className={`inline-flex items-center justify-center gap-2 rounded-lg px-3 py-2 text-xs font-bold ${pointMode === 1 ? 'bg-cyan-500 text-slate-950' : 'border border-white/15'}`}><MousePointer2 className="h-4 w-4" /> Pincel Incluir</button><button type="button" onClick={() => setPointMode(0)} className={`inline-flex items-center justify-center gap-2 rounded-lg px-3 py-2 text-xs font-bold ${pointMode === 0 ? 'bg-rose-400 text-slate-950' : 'border border-white/15'}`}><Eraser className="h-4 w-4" /> Borracha Excluir</button></div><div className="mt-2 flex items-center justify-between text-[11px] text-slate-400"><span>{points.filter(point => point.label === 1).length} inclusões · {points.filter(point => point.label === 0).length} exclusões</span><button type="button" onClick={() => setPoints(items => items.slice(0, -1))} disabled={points.length === 0} className="inline-flex items-center gap-1 disabled:opacity-40"><Undo2 className="h-3.5 w-3.5" /> desfazer ponto</button></div></div>
          {error && <p role="alert" className="text-xs text-rose-300">{error}</p>}
          <button type="submit" disabled={busy || applying || !selected} className="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-cyan-500 px-4 py-2.5 text-sm font-black text-slate-950 disabled:opacity-45"><Sparkles className="h-4 w-4" />{applying ? 'Aplicando...' : 'Aplicar simulação'}</button>
          {selected && onAnalyze && <button type="button" disabled={busy || applying} onClick={() => void onAnalyze(selected.id)} className="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-white/15 px-4 py-2.5 text-sm font-bold disabled:opacity-45"><BrainCircuit className="h-4 w-4" /> Analisar foto com IA</button>}
        </form>

        <section className="border-t border-white/10 pt-4">
          <h3 className="flex items-center gap-2 text-sm font-black"><History className="h-4 w-4" /> Histórico de simulações</h3>
          {history.length === 0 ? <p className="mt-2 text-xs text-slate-400">Nenhuma simulação nesta sessão.</p> : <ul className="mt-2 max-h-48 space-y-2 overflow-auto">{history.map(item => <li key={item.id}><button type="button" onClick={() => restore(item)} className={`w-full rounded-xl border px-3 py-2 text-left text-xs ${activeSimulation?.id === item.id ? 'border-cyan-400 bg-cyan-400/10' : 'border-white/10'}`}><span className="flex items-center justify-between gap-2 font-bold"><span className="truncate">{item.label}</span>{activeSimulation?.id === item.id && <Check className="h-3.5 w-3.5" />}</span><span className="mt-1 block text-[11px] text-slate-400">{new Date(item.created_at).toLocaleString('pt-BR')} · {item.status}</span></button></li>)}</ul>}
        </section>
      </aside>
    </div>
  </div>;
}

function PhotoFrame({ label, src }: { label: string; src: string }) {
  const privateUrl = usePrivateAssetUrl(src);
  return <figure className="overflow-hidden rounded-2xl border border-white/10 bg-black">
    <figcaption className="border-b border-white/10 px-3 py-2 text-xs font-bold text-slate-300">{label}</figcaption>
    <div className="relative aspect-[4/3]">{privateUrl ? <img src={privateUrl} alt={label} className="h-full w-full object-contain" /> : <span className="grid h-full place-items-center text-xs text-slate-500">Carregando imagem protegida…</span>}</div>
  </figure>;
}

function InteractivePhotoFrame({ label, src, points, pointMode, onAddPoint }: { label: string; src: string; points: PhotoEditPoint[]; pointMode: 0 | 1; onAddPoint: (point: PhotoEditPoint) => void }) {
  const [size, setSize] = useState({ width: 1, height: 1 });
  const drawing = useRef(false);
  const lastPoint = useRef<{ x: number; y: number } | null>(null);
  const privateUrl = usePrivateAssetUrl(src);
  function mark(event: PointerEvent<HTMLDivElement>, force = false) {
    if (!force && !drawing.current) return;
    const rect = event.currentTarget.getBoundingClientRect();
    const screenX = event.clientX - rect.left;
    const screenY = event.clientY - rect.top;
    const previous = lastPoint.current;
    if (!force && previous && Math.hypot(screenX - previous.x, screenY - previous.y) < 28) return;
    lastPoint.current = { x: screenX, y: screenY };
    onAddPoint({ x: Math.round(screenX / rect.width * size.width), y: Math.round(screenY / rect.height * size.height), label: pointMode });
  }
  return <figure className="overflow-hidden rounded-2xl border border-white/10 bg-black">
    <figcaption className="border-b border-white/10 px-3 py-2 text-xs font-bold text-slate-300">{label}</figcaption>
    <div className="relative aspect-[4/3] cursor-crosshair touch-none" onPointerDown={event => { drawing.current = true; lastPoint.current = null; event.currentTarget.setPointerCapture(event.pointerId); mark(event, true); }} onPointerMove={event => mark(event)} onPointerUp={() => { drawing.current = false; lastPoint.current = null; }} onPointerCancel={() => { drawing.current = false; lastPoint.current = null; }}>
      {privateUrl ? <img src={privateUrl} alt={label} onLoad={event => setSize({ width: event.currentTarget.naturalWidth, height: event.currentTarget.naturalHeight })} className="h-full w-full object-fill" draggable={false} /> : <span className="grid h-full place-items-center text-xs text-slate-500">Carregando imagem protegida…</span>}
      {points.map((point, index) => <span key={`${point.x}-${point.y}-${index}`} aria-label={point.label ? 'Ponto incluído' : 'Ponto excluído'} className={`absolute h-4 w-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white shadow ${point.label ? 'bg-cyan-500' : 'bg-rose-500'}`} style={{ left: `${point.x / size.width * 100}%`, top: `${point.y / size.height * 100}%` }} />)}
    </div>
  </figure>;
}

function PrivateThumbnail({ photo }: { photo: VisualizationAsset }) {
  return <span aria-label={`Fotografia protegida ${photo.name}`} className="grid h-16 place-items-center bg-slate-900 text-cyan-300"><ImageIcon className="h-5 w-5" /></span>;
}
