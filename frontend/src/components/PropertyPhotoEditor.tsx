import { FormEvent, PointerEvent, useEffect, useMemo, useReducer, useRef, useState } from 'react';
import { BrainCircuit, Check, Eraser, History, Image as ImageIcon, MousePointer2, Paintbrush, Palette, Redo2, Sparkles, Undo2 } from 'lucide-react';
import type { PhotoEditPoint, PhotoEditRequest, PhotoEditSimulation, PhotoMaskStroke, VisualizationAsset } from '@/lib/propertyVisualization';
import { usePrivateAssetUrl } from '@/hooks/usePrivateAssetUrl';
import { appendStrokePoint, drawMask, hasMaskPixels, imagePoint, maskHistory, MAX_MASK_STROKES, MAX_STROKE_POINTS, validPhotoColor } from '@/lib/photoMask.mjs';

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
  const [opacity, setOpacity] = useState(0.82);
  const [points, setPoints] = useState<PhotoEditPoint[]>([]);
  const [pointMode, setPointMode] = useState<0 | 1>(1);
  const [maskMode, setMaskMode] = useState<'sam' | 'manual'>('sam');
  const [brushMode, setBrushMode] = useState<'paint' | 'erase'>('paint');
  const [radius, setRadius] = useState(0.03);
  const [mask, dispatchMask] = useReducer(maskHistory, { strokes: [], redo: [] });
  const [maskHasPixels, setMaskHasPixels] = useState(false);
  const [drawing, setDrawing] = useState(false);
  const [history, setHistory] = useState<PhotoEditSimulation[]>(simulations);
  const [activeSimulation, setActiveSimulation] = useState<PhotoEditSimulation | null>(simulations[0] ?? null);
  const [applying, setApplying] = useState(false);
  const [error, setError] = useState('');
  const simulationsKey = simulations.map(item => item.id).join('|');
  const locked = !!busy || applying;

  function choosePhoto(id: number | null) {
    setSelectedId(id);
    setActiveSimulation(null);
    setPoints([]);
    dispatchMask({ type: 'reset' });
    setMaskHasPixels(false);
    setMaskMode('sam');
    setDrawing(false);
    setError('');
  }

  useEffect(() => {
    if (selectedId === null && photos[0]) choosePhoto(photos[0].id);
    if (selectedId !== null && !photos.some(item => item.id === selectedId)) choosePhoto(photos[0]?.id ?? null);
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
    if (!selected || locked || drawing) return;
    if (!validPhotoColor(wallColor)) { setError('Informe uma cor hexadecimal completa, como #AABBCC.'); return; }
    if (maskMode === 'manual' && !maskHasPixels) { setError('Pinte uma área da fotografia antes de aplicar a máscara manual.'); return; }
    setApplying(true);
    setError('');
    try {
      const simulation = await onApply({
        asset_id: selected.id,
        color: wallColor,
        opacity,
        material,
        points: maskMode === 'sam' ? points : [],
        mask_mode: maskMode,
        ...(maskMode === 'manual' ? { mask_strokes: mask.strokes } : {}),
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
    if (simulation.metadata_available === false) {
      choosePhoto(simulation.source_asset_id);
      setActiveSimulation(simulation);
      setError('Resultado antigo aberto. Os parâmetros desta versão não puderam ser identificados com segurança; a máscara e o acabamento não foram restaurados. Faça uma nova seleção para editar.');
      return;
    }
    setSelectedId(simulation.source_asset_id);
    setWallColor(simulation.color);
    setPoints(simulation.points ?? []);
    setMaterial(simulation.material ?? 'paint');
    setOpacity(simulation.opacity ?? 0.82);
    setMaskMode(simulation.mask_mode ?? 'sam');
    dispatchMask({ type: 'restore', strokes: simulation.mask_strokes ?? [] });
    setMaskHasPixels(false);
    setDrawing(false);
    setError('');
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
          {photos.map(photo => <button key={photo.id} type="button" disabled={locked || drawing} onClick={() => choosePhoto(photo.id)} aria-pressed={photo.id === selectedId} className={`w-28 shrink-0 overflow-hidden rounded-xl border text-left disabled:opacity-50 ${photo.id === selectedId ? 'border-cyan-400 ring-2 ring-cyan-400/30' : 'border-white/10'}`}>
            <PrivateThumbnail photo={photo} /><span className="block truncate px-2 py-1.5 text-[11px] font-bold">{photo.name}</span>
          </button>)}
        </div>

        {selected && <div className="grid gap-3 md:grid-cols-2" aria-label="Comparação antes e depois">
          <InteractivePhotoFrame key={selected.id} label={maskMode === 'manual' ? 'Antes · máscara manual' : 'Antes · pontos para seleção por IA'} src={selected.url} points={points} pointMode={pointMode} onAddPoint={point => setPoints(items => [...items.slice(-15), point])} maskMode={maskMode} strokes={mask.strokes} radius={radius} brushMode={brushMode} disabled={locked} onCommit={stroke => dispatchMask({ type: 'commit', stroke })} onMaskChange={setMaskHasPixels} onDrawing={setDrawing} />
          <PhotoFrame label={current?.result_url ? 'Depois · resultado processado' : 'Depois · aguardando processamento'} src={current?.result_url || selected.url} />
        </div>}
        <p className="rounded-xl border border-cyan-300/20 bg-cyan-400/10 px-3 py-2 text-xs text-cyan-100">{maskMode === 'manual' ? 'Pinte a área desejada e use a borracha para corrigir a seleção azul. A máscara é desenhada por você, sem segmentação automática. O acabamento será aplicado somente nessa região; a fotografia original é preservada.' : 'Clique para indicar pontos de inclusão e exclusão. O SAM 2 interpreta esses pontos para segmentar a superfície; eles não são pinceladas. A seleção gerada pela IA precisa ser conferida no resultado.'}</p>
      </div>

      <aside className="space-y-4 rounded-2xl border border-white/10 bg-slate-900/80 p-4">
        <form onSubmit={apply} className="space-y-4">
          <fieldset disabled={locked || drawing} className="space-y-4 disabled:opacity-60">
          <legend className="mb-2 text-xs font-black uppercase tracking-wide text-slate-300">Seleção da superfície</legend>
          <div className="grid grid-cols-2 gap-2">{([['sam', 'Pontos com IA'], ['manual', 'Máscara manual']] as const).map(([mode, label]) => <button key={mode} type="button" aria-pressed={maskMode === mode} onClick={() => setMaskMode(mode)} className={`rounded-lg border px-2 py-2 text-xs font-bold ${maskMode === mode ? 'border-cyan-300 bg-cyan-400/20 text-cyan-100' : 'border-white/15'}`}>{label}</button>)}</div>
          <div>
            <label htmlFor="photo-wall-color" className="flex items-center gap-2 text-xs font-black uppercase tracking-wide text-slate-300"><Palette className="h-4 w-4" /> Cor desejada</label>
            <div className="mt-2 flex items-center gap-2"><input id="photo-wall-color" type="color" value={validPhotoColor(wallColor) ? wallColor : DEFAULT_COLOR} onChange={event => setWallColor(event.target.value)} className="h-10 w-14 rounded border border-white/15 bg-transparent" /><input value={wallColor} onChange={event => /^#[0-9a-f]{0,6}$/i.test(event.target.value) && setWallColor(event.target.value)} className="min-w-0 flex-1 rounded-lg border border-white/15 bg-black/20 px-3 py-2 text-sm uppercase" aria-label="Cor em hexadecimal" aria-invalid={!validPhotoColor(wallColor)} /></div>
            {!validPhotoColor(wallColor) && <p className="mt-1 text-xs text-rose-300">Use # e seis dígitos hexadecimais.</p>}
          </div>
          <div><label htmlFor="photo-material" className="text-xs font-black uppercase tracking-wide text-slate-300">Acabamento</label><select id="photo-material" value={material} onChange={event => setMaterial(event.target.value as 'paint' | 'wood')} className="mt-2 w-full rounded-lg border border-white/15 bg-slate-950 px-3 py-2 text-sm"><option value="paint">Tinta — preservar luz e textura</option><option value="wood">Madeira — padrão orientado pela superfície</option></select></div>
          {maskMode === 'sam' ? <div>
            <p className="text-xs font-black uppercase tracking-wide text-slate-300">Pontos para SAM 2</p>
            <div className="mt-2 grid grid-cols-2 gap-2"><button type="button" aria-pressed={pointMode === 1} onClick={() => setPointMode(1)} className={`inline-flex items-center justify-center gap-2 rounded-lg px-3 py-2 text-xs font-bold ${pointMode === 1 ? 'bg-cyan-500 text-slate-950' : 'border border-white/15'}`}><MousePointer2 className="h-4 w-4" /> Ponto incluir</button><button type="button" aria-pressed={pointMode === 0} onClick={() => setPointMode(0)} className={`rounded-lg px-3 py-2 text-xs font-bold ${pointMode === 0 ? 'bg-rose-400 text-slate-950' : 'border border-white/15'}`}>Ponto excluir</button></div>
            <div className="mt-2 flex items-center justify-between text-[11px] text-slate-400"><span>{points.filter(point => point.label === 1).length} inclusões · {points.filter(point => point.label === 0).length} exclusões</span><button type="button" onClick={() => setPoints(items => items.slice(0, -1))} disabled={!points.length} className="inline-flex items-center gap-1 disabled:opacity-40"><Undo2 className="h-3.5 w-3.5" /> Desfazer ponto</button></div>
          </div> : <div className="space-y-3">
            <div className="grid grid-cols-2 gap-2"><button type="button" aria-pressed={brushMode === 'paint'} onClick={() => setBrushMode('paint')} className={`inline-flex items-center justify-center gap-2 rounded-lg px-3 py-2 text-xs font-bold ${brushMode === 'paint' ? 'bg-cyan-500 text-slate-950' : 'border border-white/15'}`}><Paintbrush className="h-4 w-4" /> Pincel</button><button type="button" aria-pressed={brushMode === 'erase'} onClick={() => setBrushMode('erase')} className={`inline-flex items-center justify-center gap-2 rounded-lg px-3 py-2 text-xs font-bold ${brushMode === 'erase' ? 'bg-rose-400 text-slate-950' : 'border border-white/15'}`}><Eraser className="h-4 w-4" /> Borracha</button></div>
            <label className="block text-xs text-slate-300">Largura do pincel: {(radius * 200).toFixed(1)}% do menor lado<input aria-label="Largura do pincel" type="range" min="0.002" max="0.15" step="0.002" value={radius} onChange={event => setRadius(Number(event.target.value))} className="mt-2 w-full accent-cyan-400" /></label>
            <div className="flex flex-wrap gap-3 text-xs"><button type="button" disabled={!mask.strokes.length} onClick={() => dispatchMask({ type: 'undo' })} className="inline-flex items-center gap-1 disabled:opacity-40"><Undo2 className="h-4 w-4" /> Desfazer traço</button><button type="button" disabled={!mask.redo.length} onClick={() => dispatchMask({ type: 'redo' })} className="inline-flex items-center gap-1 disabled:opacity-40"><Redo2 className="h-4 w-4" /> Refazer</button><button type="button" onClick={() => dispatchMask({ type: 'reset' })} disabled={!mask.strokes.length} className="disabled:opacity-40">Limpar</button></div>
            <p className="text-[11px] text-slate-400">{mask.strokes.length}/{MAX_MASK_STROKES} traços. Solte e continue para iniciar um novo traço. {mask.strokes.length >= MAX_MASK_STROKES ? 'Limite atingido: desfaça um traço ou limpe a seleção.' : !maskHasPixels ? 'Pinte uma região para habilitar a aplicação.' : ''}</p>
          </div>}
          </fieldset>
          {error && <p role="alert" className="text-xs text-rose-300">{error}</p>}
          <button type="submit" disabled={locked || drawing || !selected || !validPhotoColor(wallColor) || (maskMode === 'manual' && !maskHasPixels)} className="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-cyan-500 px-4 py-2.5 text-sm font-black text-slate-950 disabled:opacity-45"><Sparkles className="h-4 w-4" />{applying ? 'Aplicando...' : 'Aplicar simulação'}</button>
          {selected && onAnalyze && <button type="button" disabled={busy || applying} onClick={() => void onAnalyze(selected.id)} className="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-white/15 px-4 py-2.5 text-sm font-bold disabled:opacity-45"><BrainCircuit className="h-4 w-4" /> Analisar foto com IA</button>}
        </form>

        <section className="border-t border-white/10 pt-4">
          <h3 className="flex items-center gap-2 text-sm font-black"><History className="h-4 w-4" /> Histórico de simulações</h3>
          {history.length === 0 ? <p className="mt-2 text-xs text-slate-400">Nenhuma simulação nesta sessão.</p> : <ul className="mt-2 max-h-48 space-y-2 overflow-auto">{history.map(item => <li key={item.id}><button type="button" disabled={locked || drawing} onClick={() => restore(item)} className={`w-full rounded-xl border px-3 py-2 text-left text-xs ${activeSimulation?.id === item.id ? 'border-cyan-400 bg-cyan-400/10' : 'border-white/10'}`}><span className="flex items-center justify-between gap-2 font-bold"><span className="truncate">{item.label}</span>{activeSimulation?.id === item.id && <Check className="h-3.5 w-3.5" />}</span><span className="mt-1 block text-[11px] text-slate-400">{new Date(item.created_at).toLocaleString('pt-BR')} · {item.status}</span></button></li>)}</ul>}
        </section>
      </aside>
    </div>
  </div>;
}

function PhotoFrame({ label, src }: { label: string; src: string }) {
  const privateUrl = usePrivateAssetUrl(src);
  return <figure className="self-start overflow-hidden rounded-2xl border border-white/10 bg-black">
    <figcaption className="border-b border-white/10 px-3 py-2 text-xs font-bold text-slate-300">{label}</figcaption>
    <div className="relative">{privateUrl ? <img src={privateUrl} alt={label} className="block h-auto w-full" /> : <span className="grid min-h-48 place-items-center text-xs text-slate-500">Carregando imagem protegida…</span>}</div>
  </figure>;
}

type InteractivePhotoProps = {
  label: string; src: string; points: PhotoEditPoint[]; pointMode: 0 | 1;
  onAddPoint: (point: PhotoEditPoint) => void;
  maskMode: 'sam' | 'manual'; strokes: PhotoMaskStroke[]; radius: number;
  brushMode: 'paint' | 'erase'; disabled: boolean;
  onCommit: (stroke: PhotoMaskStroke) => void;
  onMaskChange: (painted: boolean) => void;
  onDrawing: (drawing: boolean) => void;
};

function InteractivePhotoFrame({ label, src, points, pointMode, onAddPoint, maskMode, strokes, radius, brushMode, disabled, onCommit, onMaskChange, onDrawing }: InteractivePhotoProps) {
  const [size, setSize] = useState({ width: 0, height: 0 });
  const [draft, setDraft] = useState<PhotoMaskStroke | null>(null);
  const strokeRef = useRef<PhotoMaskStroke | null>(null);
  const pointerId = useRef<number | null>(null);
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const [cursor, setCursor] = useState<{ x: number; y: number } | null>(null);
  const privateUrl = usePrivateAssetUrl(src);
  const [imageError, setImageError] = useState(false);

  useEffect(() => {
    const canvas = canvasRef.current;
    if (!canvas || !size.width || !size.height) { onMaskChange(false); return; }
    const context = canvas.getContext('2d', { willReadFrequently: true });
    if (!context) { onMaskChange(false); return; }
    const scale = Math.min(1, 1024 / Math.max(size.width, size.height));
    canvas.width = Math.max(1, Math.round(size.width * scale));
    canvas.height = Math.max(1, Math.round(size.height * scale));
    drawMask(context, canvas.width, canvas.height, maskMode === 'manual' ? [...strokes, ...(draft ? [draft] : [])] : []);
    // Only inspect committed masks: pointer moves never read back the RGBA buffer.
    if (!draft) onMaskChange(maskMode === 'manual' && hasMaskPixels(context.getImageData(0, 0, canvas.width, canvas.height).data));
  }, [size, strokes, draft, maskMode, onMaskChange]);

  function mark(event: PointerEvent<HTMLDivElement>) {
    return imagePoint(event.clientX, event.clientY, event.currentTarget.getBoundingClientRect());
  }
  function start(event: PointerEvent<HTMLDivElement>) {
    if (disabled || !size.width || imageError || !event.isPrimary || event.button !== 0) return;
    const point = mark(event);
    if (!point) return;
    if (maskMode === 'sam') {
      onAddPoint({ x: Math.min(size.width - 1, Math.round(point.x * size.width)), y: Math.min(size.height - 1, Math.round(point.y * size.height)), label: pointMode });
      return;
    }
    if (strokes.length >= MAX_MASK_STROKES) return;
    event.preventDefault();
    event.currentTarget.setPointerCapture(event.pointerId);
    pointerId.current = event.pointerId;
    const next = { mode: brushMode, radius, points: [point] };
    strokeRef.current = next;
    setDraft(next);
    onDrawing(true);
  }
  function move(event: PointerEvent<HTMLDivElement>) {
    const point = mark(event);
    setCursor(point);
    if (!point || event.pointerId !== pointerId.current || !strokeRef.current) return;
    const next = appendStrokePoint(strokeRef.current, point);
    strokeRef.current = next;
    setDraft(next);
  }
  function finish(event: PointerEvent<HTMLDivElement>, cancel = false) {
    if (event.pointerId !== pointerId.current) return;
    const stroke = strokeRef.current;
    strokeRef.current = null;
    pointerId.current = null;
    setDraft(null);
    if (stroke && !cancel) onCommit(stroke);
    onDrawing(false);
    if (event.currentTarget.hasPointerCapture(event.pointerId)) event.currentTarget.releasePointerCapture(event.pointerId);
  }
  const cursorDiameter = radius * Math.min(size.width, size.height) * 2;
  return <figure className="self-start overflow-hidden rounded-2xl border border-white/10 bg-black">
    <figcaption className="border-b border-white/10 px-3 py-2 text-xs font-bold text-slate-300">{label}</figcaption>
    <div className="relative cursor-crosshair touch-none" aria-label={maskMode === 'manual' ? 'Desenhar máscara sobre a fotografia' : 'Adicionar ponto de seleção à fotografia'} onPointerDown={start} onPointerMove={move} onPointerUp={event => finish(event)} onPointerCancel={event => finish(event, true)} onLostPointerCapture={event => finish(event, true)} onPointerLeave={() => setCursor(null)}>
      {privateUrl ? <img src={privateUrl} alt={label} onLoad={event => { setSize({ width: event.currentTarget.naturalWidth, height: event.currentTarget.naturalHeight }); setImageError(false); }} onError={() => { setSize({ width: 0, height: 0 }); setImageError(true); }} className="block h-auto w-full select-none" draggable={false} /> : <span className="grid min-h-48 place-items-center text-xs text-slate-500">Carregando imagem protegida…</span>}
      {imageError && <p role="alert" className="p-3 text-xs text-rose-300">Não foi possível abrir esta fotografia.</p>}
      <canvas ref={canvasRef} className="pointer-events-none absolute inset-0 h-full w-full opacity-45" aria-label="Prévia da máscara manual" />
      {maskMode === 'sam' && size.width > 0 && points.map((point, index) => <span key={`${point.x}-${point.y}-${index}`} aria-label={point.label ? 'Ponto incluído' : 'Ponto excluído'} className={`pointer-events-none absolute h-4 w-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white shadow ${point.label ? 'bg-cyan-500' : 'bg-rose-500'}`} style={{ left: `${point.x / size.width * 100}%`, top: `${point.y / size.height * 100}%` }} />)}
      {maskMode === 'manual' && cursor && size.width > 0 && <span className={`pointer-events-none absolute -translate-x-1/2 -translate-y-1/2 rounded-full border ${brushMode === 'erase' ? 'border-rose-300' : 'border-white'}`} style={{ left: `${cursor.x * 100}%`, top: `${cursor.y * 100}%`, width: `${cursorDiameter / size.width * 100}%`, height: `${cursorDiameter / size.height * 100}%` }} />}
    </div>
    {draft?.points.length === MAX_STROKE_POINTS && <p role="status" className="p-2 text-xs text-amber-200">Limite deste traço atingido. Solte o pincel e comece outro traço para continuar.</p>}
  </figure>;
}

function PrivateThumbnail({ photo }: { photo: VisualizationAsset }) {
  return <span aria-label={`Fotografia protegida ${photo.name}`} className="grid h-16 place-items-center bg-slate-900 text-cyan-300"><ImageIcon className="h-5 w-5" /></span>;
}
