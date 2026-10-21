import { FormEvent, PointerEvent, useEffect, useMemo, useReducer, useRef, useState } from 'react';
import { BrainCircuit, Check, Eraser, History, Image as ImageIcon, MousePointer2, Paintbrush, Palette, Redo2, Sparkles, Undo2, Hand, ZoomIn, ZoomOut, Eye, EyeOff } from 'lucide-react';
import type { PhotoEditPoint, PhotoEditRequest, PhotoEditSimulation, PhotoFinish, PhotoMaskStroke, PhotoSelection, VisualizationAsset } from '@/lib/propertyVisualization';
import { usePrivateAssetUrl } from '@/hooks/usePrivateAssetUrl';
import { appendStrokePoint, createRequestGate, drawMask, grayscaleMaskToOverlay, hasMaskPixels, imagePoint, maskHistory, MAX_MASK_STROKES, MAX_STROKE_POINTS, validPhotoColor } from '@/lib/photoMask.mjs';

type Props = {
  photos: VisualizationAsset[];
  simulations?: PhotoEditSimulation[];
  busy?: boolean;
  onAnalyze?: (assetId: number) => Promise<void>;
  onPrepare: (assetId: number, points: PhotoEditPoint[], signal: AbortSignal) => Promise<PhotoSelection>;
  onApply: (request: PhotoEditRequest, source: VisualizationAsset, signal: AbortSignal) => Promise<PhotoEditSimulation>;
  onConfirm: (previewId: number, signal: AbortSignal) => Promise<PhotoEditSimulation>;
  onDiscardPreview: (previewId: number) => Promise<void>;
};
type Tool = 'sam' | 'paint' | 'erase' | 'pan';
const DEFAULT_COLOR = '#d8c6ae';
const STEPS = ['Selecionar', 'Refinar', 'Personalizar', 'Comparar', 'Salvar'];
const button = 'inline-flex items-center justify-center gap-2 rounded-lg border border-white/15 px-3 py-2 text-xs font-bold disabled:opacity-40';

export default function PropertyPhotoEditor({ photos, simulations = [], busy, onAnalyze, onPrepare, onApply, onConfirm, onDiscardPreview }: Props) {
  const [selectedId, setSelectedId] = useState<number | null>(photos[0]?.id ?? null);
  const [wallColor, setWallColor] = useState(DEFAULT_COLOR);
  const [material, setMaterial] = useState<'paint' | 'wood'>('paint');
  const [finish, setFinish] = useState<PhotoFinish>('matte');
  const [opacity, setOpacity] = useState(0.82);
  const [points, setPoints] = useState<PhotoEditPoint[]>([]);
  const [pointMode, setPointMode] = useState<0 | 1>(1);
  const [tool, setTool] = useState<Tool>('sam');
  const [base, setBase] = useState<PhotoSelection | null>(null);
  const [baseStale, setBaseStale] = useState(false);
  const [baseReady, setBaseReady] = useState(false);
  const [radius, setRadius] = useState(0.03);
  const [overlayOpacity, setOverlayOpacity] = useState(0.45);
  const [showOverlay, setShowOverlay] = useState(true);
  const [zoom, setZoom] = useState(1);
  const [pan, setPan] = useState({ x: 0, y: 0 });
  const [mask, dispatchMask] = useReducer(maskHistory, { strokes: [], redo: [] });
  const [maskHasPixels, setMaskHasPixels] = useState(false);
  const [drawing, setDrawing] = useState(false);
  const [stage, setStage] = useState(0);
  const [selectionConfirmed, setSelectionConfirmed] = useState(false);
  const [history, setHistory] = useState<PhotoEditSimulation[]>(simulations);
  const [activeSimulation, setActiveSimulation] = useState<PhotoEditSimulation | null>(null);
  const [preview, setPreview] = useState<PhotoEditSimulation | null>(null);
  const [pending, setPending] = useState<'select' | 'preview' | 'save' | null>(null);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const gate = useRef(createRequestGate());
  const revision = useRef(0);
  const previewRevision = useRef(-1);
  const selected = useMemo(() => photos.find(item => item.id === selectedId) ?? null, [photos, selectedId]);
  const locked = !!busy || pending !== null;
  const ready = maskHasPixels && !baseStale && (!base || baseReady);
  const current = preview ?? (activeSimulation?.source_asset_id === selectedId ? activeSimulation : null);
  const simulationsKey = simulations.map(item => item.id).join('|');

  useEffect(() => () => gate.current.cancel(), []);
  useEffect(() => { setHistory(simulations); }, [simulationsKey]);
  useEffect(() => {
    if (selectedId === null && photos[0]) choosePhoto(photos[0].id);
    else if (selectedId !== null && !photos.some(item => item.id === selectedId)) choosePhoto(photos[0]?.id ?? null);
  }, [photos, selectedId]);

  function invalidate(selection = false) {
    revision.current++;
    previewRevision.current = -1;
    if (preview?.preview_id) void onDiscardPreview(preview.preview_id).catch(() => {});
    setPreview(null);
    setNotice('Ajustes não salvos. Gere uma nova prévia para comparar.');
    setStage(value => value >= 3 ? 2 : value);
    if (selection) { setSelectionConfirmed(false); setStage(1); }
  }
  function choosePhoto(id: number | null) {
    gate.current.cancel(); setPending(null); invalidate();
    setSelectedId(id); setActiveSimulation(null); setPoints([]); setBase(null); setBaseStale(false); setBaseReady(false);
    dispatchMask({ type: 'reset' }); setMaskHasPixels(false); setTool('sam'); setDrawing(false);
    setSelectionConfirmed(false); setStage(0); setZoom(1); setPan({ x: 0, y: 0 }); setError(''); setNotice('');
  }
  function changePoints(next: PhotoEditPoint[]) {
    invalidate(true); setPoints(next); setBaseStale(base !== null); setStage(0);
  }
  function changeMask(action: Parameters<typeof maskHistory>[1]) { invalidate(true); dispatchMask(action); }
  function cancelPending() {
    const wasSaving = pending === 'save';
    gate.current.cancel(); setPending(null);
    setNotice(wasSaving ? 'Acompanhamento interrompido. A confirmação pode ter chegado ao servidor; confira o histórico antes de tentar novamente.' : 'Acompanhamento interrompido. O processamento no servidor pode continuar, mas seu retorno não alterará esta edição.');
  }
  async function prepareSelection() {
    if (!selected || locked || !points.some(point => point.label === 1)) return;
    const request = gate.current.start(); if (!request) return;
    setPending('select'); setError(''); setNotice('SAM 2 segmentando a superfície. O modelo pode precisar ser carregado.');
    try {
      const selection = await onPrepare(selected.id, points, request.signal);
      if (!gate.current.current(request)) return;
      if (selection.source_asset_id !== selected.id) throw new Error('A seleção retornada não corresponde à fotografia aberta.');
      invalidate(true); setBaseReady(false); setBase(selection); setBaseStale(false);
      dispatchMask({ type: 'reset' }); setTool('paint'); setStage(1); setShowOverlay(true);
      setNotice('Máscara neural gerada. Aguarde a prévia da seleção e confira as bordas antes de continuar.');
    } catch (exception: any) { if (gate.current.current(request)) setError(exception?.message ?? 'Não foi possível gerar a máscara neural.'); }
    finally { if (gate.current.current(request)) { gate.current.end(request); setPending(null); } }
  }
  async function apply(event: FormEvent) {
    event.preventDefault();
    if (!selected || locked || drawing || !ready || !selectionConfirmed || !validPhotoColor(wallColor)) return;
    const request = gate.current.start(); if (!request) return;
    const version = revision.current;
    setPending('preview'); setError(''); setNotice('Processando uma prévia sem salvar no histórico.');
    try {
      const result = await onApply({ asset_id: selected.id, color: wallColor, opacity, material, finish, points: [], mask_mode: base ? 'refined' : 'manual', ...(base ? { base_mask_asset_id: base.mask_asset_id } : {}), mask_strokes: mask.strokes, preview: true }, selected, request.signal);
      if (!gate.current.current(request) || revision.current !== version) return;
      if (result.source_asset_id !== selected.id || !result.preview_id || !result.result_url) throw new Error('O servidor não devolveu uma prévia válida desta fotografia.');
      setPreview(result); previewRevision.current = version; setStage(3); setNotice('Prévia pronta. Compare o resultado com o original antes de salvar.');
    } catch (exception: any) { if (gate.current.current(request)) setError(exception?.message ?? 'Não foi possível preparar a prévia.'); }
    finally { if (gate.current.current(request)) { gate.current.end(request); setPending(null); } }
  }
  async function save() {
    if (!preview?.preview_id || previewRevision.current !== revision.current || locked || drawing) return;
    const request = gate.current.start(); if (!request) return;
    setPending('save'); setError('');
    try {
      const saved = await onConfirm(preview.preview_id, request.signal);
      if (!gate.current.current(request)) return;
      setHistory(items => [saved, ...items.filter(item => item.id !== saved.id)]); setActiveSimulation(saved); setPreview(null); previewRevision.current = -1;
      setStage(4); setNotice('Resultado salvo como nova versão. A fotografia original foi preservada.');
    } catch (exception: any) { if (gate.current.current(request)) setError(exception?.message ?? 'Não foi possível confirmar a versão.'); }
    finally { if (gate.current.current(request)) { gate.current.end(request); setPending(null); } }
  }
  function restore(simulation: PhotoEditSimulation) {
    choosePhoto(simulation.source_asset_id); setActiveSimulation(simulation);
    if (simulation.metadata_available === false) { setStage(3); setError('Resultado antigo aberto. Parâmetros ambíguos não foram restaurados; crie uma nova seleção para editar.'); return; }
    setWallColor(simulation.color); setMaterial(simulation.material ?? 'paint'); setFinish(simulation.finish ?? 'matte'); setOpacity(simulation.opacity ?? 0.82); setPoints(simulation.points ?? []);
    dispatchMask({ type: 'restore', strokes: simulation.mask_strokes ?? [] });
    if (simulation.base_mask_asset_id && simulation.base_mask_url) {
      setBase({ mask_asset_id: simulation.base_mask_asset_id, mask_url: simulation.base_mask_url, source_asset_id: simulation.source_asset_id, points: simulation.points ?? [], dimensions: simulation.dimensions ?? { width: 0, height: 0 } });
      setTool('paint');
    } else setTool(simulation.mask_mode === 'manual' ? 'paint' : 'sam');
    const restorable = !!(simulation.base_mask_asset_id && simulation.base_mask_url) || simulation.mask_mode === 'manual';
    setStage(restorable ? 1 : 3); setNotice(restorable ? 'Versão reaberta. Confira a seleção restaurada e confirme antes de gerar outra prévia.' : 'Resultado salvo aberto. Esta versão não tem uma máscara base vinculada; volte ao refinamento e gere uma nova seleção para editar.');
  }

  if (photos.length === 0) return <div className="grid min-h-[420px] place-items-center bg-slate-950 px-6 text-center text-slate-300"><div><ImageIcon className="mx-auto h-10 w-10 text-violet-300" /><h2 className="mt-4 text-xl font-black">Envie uma fotografia para começar</h2><p className="mt-2 max-w-lg text-sm">O editor mantém a imagem original e cria versões de simulação separadas.</p></div></div>;

  return <div className="bg-slate-950 p-4 text-slate-100 sm:p-6">
    <ol aria-label="Etapas da simulação" className="mb-5 flex flex-wrap gap-2">{STEPS.map((label, index) => <li key={label} aria-current={stage === index ? 'step' : undefined} className={`rounded-full border px-3 py-2 text-xs font-bold ${stage === index ? 'border-cyan-300 bg-cyan-500/20 text-cyan-100' : 'border-white/10 text-slate-400'}`}>{index + 1}. {label}</li>)}</ol>
    <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_320px]">
      <div className="min-w-0 space-y-4">
        <div className="flex gap-2 overflow-x-auto pb-1" aria-label="Selecionar fotografia">{photos.map(photo => <button key={photo.id} type="button" disabled={locked || drawing} onClick={() => choosePhoto(photo.id)} aria-pressed={photo.id === selectedId} className={`w-28 shrink-0 overflow-hidden rounded-xl border text-left disabled:opacity-50 ${photo.id === selectedId ? 'border-cyan-400 ring-2 ring-cyan-400/30' : 'border-white/10'}`}><PrivateThumbnail photo={photo} /><span className="block truncate px-2 py-1.5 text-[11px] font-bold">{photo.name}</span></button>)}</div>
        {selected && (stage >= 3 && current?.result_url ? <div className="grid gap-3 md:grid-cols-2" aria-label="Comparação antes e depois"><PhotoFrame label="Antes · fotografia original" src={selected.url} /><PhotoFrame label={preview ? 'Depois · prévia ainda não salva' : 'Depois · versão salva'} src={current.result_url} /></div> : <InteractivePhotoFrame key={selected.id} label="Fotografia original · seleção editável" src={selected.url} points={points} pointMode={pointMode} onAddPoint={point => changePoints([...points.slice(-15), point])} tool={tool} strokes={mask.strokes} radius={radius} disabled={locked || stage >= 2} onCommit={stroke => changeMask({ type: 'commit', stroke })} onMaskChange={setMaskHasPixels} onDrawing={setDrawing} baseUrl={base?.mask_url ?? null} expectedDimensions={base?.dimensions} onBaseReady={setBaseReady} showOverlay={showOverlay} overlayOpacity={overlayOpacity} zoom={zoom} pan={pan} onPan={setPan} />)}
        {stage < 2 && <fieldset disabled={locked || drawing} aria-label="Ferramentas junto à fotografia" className="space-y-3 rounded-xl border border-white/10 bg-slate-900 p-3 disabled:opacity-60">
              <div className="grid grid-cols-4 gap-2">{([['sam', 'Pontos', MousePointer2], ['paint', 'Pincel', Paintbrush], ['erase', 'Borracha', Eraser], ['pan', 'Mover', Hand]] as const).map(([value, label, Icon]) => <button key={value} type="button" aria-pressed={tool === value} onClick={() => setTool(value)} className={`${button} !px-1 sm:!px-3 ${tool === value ? 'border-cyan-300 bg-cyan-400/20 text-cyan-100' : ''}`}><Icon className="h-4 w-4" />{label}</button>)}</div>
              {tool === 'sam' ? <div className="flex flex-wrap items-center gap-2">
                <div className="grid grid-cols-2 gap-2"><button type="button" aria-pressed={pointMode === 1} onClick={() => setPointMode(1)} className={`${button} ${pointMode === 1 ? 'bg-cyan-500 text-slate-950' : ''}`}>Ponto incluir</button><button type="button" aria-pressed={pointMode === 0} onClick={() => setPointMode(0)} className={`${button} ${pointMode === 0 ? 'bg-rose-400 text-slate-950' : ''}`}>Ponto excluir</button></div>
                <p className="text-xs text-slate-400">{points.filter(point => point.label === 1).length} inclusões · {points.filter(point => !point.label).length} exclusões</p>
                <button type="button" onClick={() => changePoints(points.slice(0, -1))} disabled={!points.length} className={button}><Undo2 className="h-4 w-4" />Desfazer ponto</button>
                <button type="button" onClick={() => void prepareSelection()} disabled={!points.some(point => point.label === 1)} className={`${button} bg-cyan-500 text-slate-950`}><BrainCircuit className="h-4 w-4" />{base ? 'Atualizar máscara neural' : 'Gerar máscara neural'}</button>
              </div> : tool !== 'pan' && <label className="block text-xs text-slate-300">Largura do pincel: {(radius * 200).toFixed(1)}%<input aria-label="Largura do pincel" type="range" min="0.002" max="0.15" step="0.002" value={radius} onChange={event => setRadius(Number(event.target.value))} className="mt-2 w-full accent-cyan-400" /></label>}
        </fieldset>}
        {stage < 3 && <div className="flex flex-wrap items-center gap-2"><button type="button" disabled={drawing} onClick={() => setZoom(value => Math.max(1, value - 0.25))} className={button} aria-label="Diminuir zoom"><ZoomOut className="h-4 w-4" /></button><span className="text-xs">{Math.round(zoom * 100)}%</span><button type="button" disabled={drawing} onClick={() => setZoom(value => Math.min(4, value + 0.25))} className={button} aria-label="Aumentar zoom"><ZoomIn className="h-4 w-4" /></button><button type="button" disabled={drawing} onClick={() => { setZoom(1); setPan({ x: 0, y: 0 }); }} className={button}>Enquadrar</button><button type="button" onClick={() => setShowOverlay(value => !value)} className={button}>{showOverlay ? <Eye className="h-4 w-4" /> : <EyeOff className="h-4 w-4" />}{showOverlay ? 'Ocultar seleção' : 'Mostrar seleção'}</button><label className="flex items-center gap-2 text-xs">Overlay<input aria-label="Opacidade da seleção" type="range" min="0.1" max="0.8" step="0.05" value={overlayOpacity} onChange={event => setOverlayOpacity(Number(event.target.value))} className="w-24 accent-cyan-400" /></label></div>}
        <p className="rounded-xl border border-cyan-300/20 bg-cyan-400/10 px-3 py-2 text-xs text-cyan-100">{base ? 'A máscara neural original permanece preservada. Pincel e borracha alteram essa seleção; limpar ajustes restaura sua base neural.' : tool === 'sam' ? 'SAM 2 interpreta esses pontos para selecionar a superfície. Gere e confira a máscara antes de personalizar.' : 'Seleção desenhada por você, sem segmentação automática. A fotografia original não é alterada.'}</p>
        {(notice || pending) && <p role="status" className="text-sm text-slate-300">{notice}</p>}
        {error && <p role="alert" className="rounded-xl border border-rose-400/30 bg-rose-400/10 p-3 text-sm text-rose-200">{error}</p>}
      </div>
      <aside className="space-y-4 rounded-2xl border border-white/10 bg-slate-900/80 p-4">
        {pending && <button type="button" onClick={cancelPending} className={`${button} w-full`}>Interromper acompanhamento</button>}
        <form onSubmit={apply} className="space-y-4">
          <fieldset disabled={locked || drawing} className="space-y-4 disabled:opacity-60">
            <legend className="mb-2 text-xs font-black uppercase tracking-wide text-slate-300">{stage < 2 ? 'Seleção da superfície' : 'Personalizar acabamento'}</legend>
            {stage < 2 ? <>
              <p className="text-xs text-slate-400">Pontos, pincel e borracha ficam junto à fotografia. Confira a região destacada e confirme a seleção abaixo.</p>
              <div className="flex flex-wrap gap-2"><button type="button" disabled={!mask.strokes.length} onClick={() => changeMask({ type: 'undo' })} className={button}><Undo2 className="h-4 w-4" />Desfazer traço</button><button type="button" disabled={!mask.redo.length} onClick={() => changeMask({ type: 'redo' })} className={button}><Redo2 className="h-4 w-4" />Refazer</button><button type="button" disabled={!mask.strokes.length} onClick={() => changeMask({ type: 'reset' })} className={button}>Limpar ajustes</button>{base && <button type="button" onClick={() => { changeMask({ type: 'reset' }); setPoints(base.points); setBaseStale(false); }} className={button}>Restaurar máscara neural</button>}</div>
              <p className="text-xs text-slate-400">{mask.strokes.length}/{MAX_MASK_STROKES} traços. {base && !baseReady ? 'Carregando máscara protegida…' : baseStale ? 'Pontos alterados. Atualize a máscara neural ou restaure a seleção anterior.' : ready ? 'Máscara pronta para conferência.' : 'Selecione uma região da fotografia.'}</p>
              <button type="button" disabled={!ready} onClick={() => { setSelectionConfirmed(true); setStage(2); setNotice('Seleção confirmada. Escolha cor e acabamento para gerar a prévia.'); }} className={`${button} w-full bg-cyan-500 text-slate-950`}><Check className="h-4 w-4" />Confirmar seleção</button>
            </> : <>
              <button type="button" onClick={() => { setStage(1); setSelectionConfirmed(false); }} className={button}>Voltar ao refinamento</button>
              <div><label htmlFor="photo-wall-color" className="flex items-center gap-2 text-xs font-bold"><Palette className="h-4 w-4" />Cor desejada</label><div className="mt-2 flex gap-2"><input id="photo-wall-color" type="color" value={validPhotoColor(wallColor) ? wallColor : DEFAULT_COLOR} onChange={event => { invalidate(); setWallColor(event.target.value); }} className="h-10 w-14 rounded border border-white/15 bg-transparent" /><input value={wallColor} onChange={event => { if (/^#[0-9a-f]{0,6}$/i.test(event.target.value)) { invalidate(); setWallColor(event.target.value); } }} aria-label="Cor em hexadecimal" aria-invalid={!validPhotoColor(wallColor)} className="min-w-0 flex-1 rounded-lg border border-white/15 bg-black/20 px-3 py-2 text-sm uppercase" /></div>{!validPhotoColor(wallColor) && <p className="text-xs text-rose-300">Use # e seis dígitos hexadecimais.</p>}</div>
              <label className="block text-xs font-bold">Material<select value={material} onChange={event => { invalidate(); setMaterial(event.target.value as 'paint' | 'wood'); }} className="mt-2 w-full rounded-lg border border-white/15 bg-slate-950 p-2"><option value="paint">Tinta</option><option value="wood">Madeira</option></select></label>
              <label className="block text-xs font-bold">Acabamento<select value={finish} onChange={event => { invalidate(); setFinish(event.target.value as PhotoFinish); }} className="mt-2 w-full rounded-lg border border-white/15 bg-slate-950 p-2"><option value="matte">Fosco</option><option value="satin">Acetinado</option><option value="semigloss">Semibrilho</option></select></label>
              <p className="text-xs text-slate-400">Acabamentos são aproximações visuais; não representam certificação de uma tinta real.</p>
              <button type="submit" disabled={!selectionConfirmed || !ready || !validPhotoColor(wallColor)} className={`${button} w-full bg-cyan-500 text-slate-950`}><Sparkles className="h-4 w-4" />Aplicar simulação · gerar prévia</button>
              <button type="button" disabled={!preview?.preview_id || previewRevision.current !== revision.current} onClick={() => void save()} className={`${button} w-full bg-violet-600`}><Check className="h-4 w-4" />Confirmar e salvar versão</button>
            </>}
          </fieldset>
          {selected && onAnalyze && <button type="button" disabled={locked || drawing} onClick={() => void onAnalyze(selected.id)} className={`${button} w-full`}><BrainCircuit className="h-4 w-4" />Analisar foto com IA</button>}
        </form>
        <section className="border-t border-white/10 pt-4"><h3 className="flex items-center gap-2 text-sm font-black"><History className="h-4 w-4" />Histórico de simulações</h3>{!history.length ? <p className="mt-2 text-xs text-slate-400">Nenhuma versão salva.</p> : <ul className="mt-2 max-h-48 space-y-2 overflow-auto">{history.map(item => <li key={item.id}><button type="button" disabled={locked || drawing || !photos.some(photo => photo.id === item.source_asset_id)} onClick={() => restore(item)} className={`${button} w-full justify-start text-left ${activeSimulation?.id === item.id ? 'border-cyan-400 bg-cyan-400/10' : ''}`}><span><span className="block">{item.label}</span><span className="text-[11px] text-slate-400">{new Date(item.created_at).toLocaleString('pt-BR')} · {item.status}</span></span></button></li>)}</ul>}</section>
      </aside>
    </div>
  </div>;
}

function PhotoFrame({ label, src }: { label: string; src: string }) {
  const privateUrl = usePrivateAssetUrl(src);
  return <figure className="self-start overflow-hidden rounded-2xl border border-white/10 bg-black"><figcaption className="border-b border-white/10 px-3 py-2 text-xs font-bold text-slate-300">{label}</figcaption><div className="relative">{privateUrl ? <img src={privateUrl} alt={label} className="block h-auto w-full" /> : <span role="status" className="grid min-h-48 place-items-center text-xs text-slate-500">{privateUrl === '' ? 'Não foi possível abrir a imagem protegida.' : 'Carregando imagem protegida…'}</span>}</div></figure>;
}

type InteractivePhotoProps = {
  label: string; src: string; points: PhotoEditPoint[]; pointMode: 0 | 1;
  onAddPoint: (point: PhotoEditPoint) => void; tool: Tool;
  strokes: PhotoMaskStroke[]; radius: number; disabled: boolean;
  onCommit: (stroke: PhotoMaskStroke) => void;
  onMaskChange: (painted: boolean) => void; onDrawing: (drawing: boolean) => void;
  baseUrl: string | null; expectedDimensions?: { width: number; height: number };
  onBaseReady: (ready: boolean) => void;
  showOverlay: boolean; overlayOpacity: number;
  zoom: number; pan: { x: number; y: number }; onPan: (pan: { x: number; y: number }) => void;
};

function InteractivePhotoFrame({ label, src, points, pointMode, onAddPoint, tool, strokes, radius, disabled, onCommit, onMaskChange, onDrawing, baseUrl, expectedDimensions, onBaseReady, showOverlay, overlayOpacity, zoom, pan, onPan }: InteractivePhotoProps) {
  const [size, setSize] = useState({ width: 0, height: 0 });
  const [draft, setDraft] = useState<PhotoMaskStroke | null>(null);
  const strokeRef = useRef<PhotoMaskStroke | null>(null);
  const panStart = useRef<{ x: number; y: number; pan: { x: number; y: number } } | null>(null);
  const pointerId = useRef<number | null>(null);
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const [cursor, setCursor] = useState<{ x: number; y: number } | null>(null);
  const privateUrl = usePrivateAssetUrl(src);
  const [imageError, setImageError] = useState(false);
  const [baseRaster, setBaseRaster] = useState<{ src: string; canvas: HTMLCanvasElement } | null>(null);
  const [maskError, setMaskError] = useState('');
  const activeBase = baseRaster?.src === baseUrl ? baseRaster.canvas : null;
  const baseValid = !baseUrl || activeBase !== null;

  useEffect(() => {
    setMaskError('');
    if (!baseUrl) { setBaseRaster(null); onBaseReady(false); }
  }, [baseUrl, onBaseReady]);
  useEffect(() => { onBaseReady(!!activeBase); }, [activeBase, onBaseReady]);
  useEffect(() => {
    const canvas = canvasRef.current;
    if (!canvas || !size.width || !size.height || !baseValid) { onMaskChange(false); return; }
    const context = canvas.getContext('2d', { willReadFrequently: true });
    if (!context) { onMaskChange(false); return; }
    const scale = Math.min(1, 1024 / Math.max(size.width, size.height));
    canvas.width = Math.max(1, Math.round(size.width * scale));
    canvas.height = Math.max(1, Math.round(size.height * scale));
    drawMask(context, canvas.width, canvas.height, [...strokes, ...(draft ? [draft] : [])], activeBase);
    // RGB readback only after a stroke finishes, never per pointer move.
    if (!draft) onMaskChange(hasMaskPixels(context.getImageData(0, 0, canvas.width, canvas.height).data));
  }, [size, strokes, draft, activeBase, baseValid, onMaskChange]);

  function mark(event: PointerEvent<HTMLDivElement>) {
    // currentTarget is the transformed image frame; zoom and pan are already in its rect.
    return imagePoint(event.clientX, event.clientY, event.currentTarget.getBoundingClientRect());
  }
  function start(event: PointerEvent<HTMLDivElement>) {
    if ((!size.width || imageError || !event.isPrimary || event.button !== 0) || (disabled && tool !== 'pan')) return;
    const point = mark(event); if (!point) return;
    if (tool === 'sam') {
      onAddPoint({ x: Math.round(point.x * (size.width - 1)), y: Math.round(point.y * (size.height - 1)), label: pointMode });
      return;
    }
    if (tool !== 'pan' && (!baseValid || strokes.length >= MAX_MASK_STROKES)) return;
    event.preventDefault(); event.currentTarget.setPointerCapture(event.pointerId); pointerId.current = event.pointerId;
    if (tool === 'pan') panStart.current = { x: event.clientX, y: event.clientY, pan };
    else { const next = { mode: tool === 'erase' ? 'erase' as const : 'paint' as const, radius, points: [point] }; strokeRef.current = next; setDraft(next); }
    onDrawing(true);
  }
  function move(event: PointerEvent<HTMLDivElement>) {
    const point = mark(event); setCursor(point);
    if (event.pointerId !== pointerId.current) return;
    if (panStart.current) {
      onPan({ x: panStart.current.pan.x + event.clientX - panStart.current.x, y: panStart.current.pan.y + event.clientY - panStart.current.y });
      return;
    }
    if (!point || !strokeRef.current) return;
    const next = appendStrokePoint(strokeRef.current, point); strokeRef.current = next; setDraft(next);
  }
  function finish(event: PointerEvent<HTMLDivElement>, cancel = false) {
    if (event.pointerId !== pointerId.current) return;
    const stroke = strokeRef.current;
    strokeRef.current = null; pointerId.current = null; panStart.current = null; setDraft(null);
    if (stroke && !cancel) onCommit(stroke);
    onDrawing(false);
    if (event.currentTarget.hasPointerCapture(event.pointerId)) event.currentTarget.releasePointerCapture(event.pointerId);
  }
  const cursorDiameter = radius * Math.min(size.width, size.height) * 2;
  return <figure className="self-start overflow-hidden rounded-2xl border border-white/10 bg-black">
    <figcaption className="border-b border-white/10 px-3 py-2 text-xs font-bold text-slate-300">{label}</figcaption>
    {baseUrl && size.width > 0 && <PrivateMaskRaster key={baseUrl} src={baseUrl} size={size} expectedDimensions={expectedDimensions} onLoad={setBaseRaster} onError={setMaskError} />}
    <div className="overflow-hidden">
      <div className={`relative touch-none ${tool === 'pan' ? 'cursor-grab' : 'cursor-crosshair'}`} style={{ transform: `translate(${pan.x}px, ${pan.y}px) scale(${zoom})`, transformOrigin: '0 0' }} aria-label={tool === 'sam' ? 'Adicionar ponto de seleção à fotografia' : 'Refinar máscara sobre a fotografia'} onPointerDown={start} onPointerMove={move} onPointerUp={event => finish(event)} onPointerCancel={event => finish(event, true)} onLostPointerCapture={event => finish(event, true)} onPointerLeave={() => setCursor(null)}>
        {privateUrl ? <img src={privateUrl} alt={label} onLoad={event => { setSize({ width: event.currentTarget.naturalWidth, height: event.currentTarget.naturalHeight }); setImageError(false); }} onError={() => { setSize({ width: 0, height: 0 }); setImageError(true); }} className="block h-auto w-full select-none" draggable={false} /> : <span className="grid min-h-48 place-items-center text-xs text-slate-500">{privateUrl === '' ? 'Não foi possível abrir a imagem protegida.' : 'Carregando imagem protegida…'}</span>}
        <canvas ref={canvasRef} className="pointer-events-none absolute inset-0 h-full w-full" style={{ opacity: showOverlay && baseValid ? overlayOpacity : 0 }} aria-label="Prévia da máscara neural e manual" />
        {tool === 'sam' && size.width > 0 && points.map((point, index) => <span key={`${point.x}-${point.y}-${index}`} aria-label={point.label ? 'Ponto incluído' : 'Ponto excluído'} className={`pointer-events-none absolute h-4 w-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white shadow ${point.label ? 'bg-cyan-500' : 'bg-rose-500'}`} style={{ left: `${point.x / Math.max(1, size.width - 1) * 100}%`, top: `${point.y / Math.max(1, size.height - 1) * 100}%` }} />)}
        {(tool === 'paint' || tool === 'erase') && cursor && size.width > 0 && <span className={`pointer-events-none absolute -translate-x-1/2 -translate-y-1/2 rounded-full border ${tool === 'erase' ? 'border-rose-300' : 'border-white'}`} style={{ left: `${cursor.x * 100}%`, top: `${cursor.y * 100}%`, width: `${cursorDiameter / size.width * 100}%`, height: `${cursorDiameter / size.height * 100}%` }} />}
      </div>
    </div>
    {(imageError || maskError) && <p role="alert" className="p-3 text-xs text-rose-300">{maskError || 'Não foi possível abrir esta fotografia.'}</p>}
    {draft?.points.length === MAX_STROKE_POINTS && <p role="status" className="p-2 text-xs text-amber-200">Limite deste traço atingido. Solte o pincel e comece outro traço.</p>}
  </figure>;
}

function PrivateMaskRaster({ src, size, expectedDimensions, onLoad, onError }: { src: string; size: { width: number; height: number }; expectedDimensions?: { width: number; height: number }; onLoad: (raster: { src: string; canvas: HTMLCanvasElement }) => void; onError: (message: string) => void }) {
  const url = usePrivateAssetUrl(src);
  useEffect(() => {
    if (!url) { if (url === '') onError('Não foi possível carregar a máscara privada. Tente reabrir a seleção.'); return; }
    let active = true;
    const image = new window.Image();
    image.onload = () => {
      if (!active) return;
      if (image.naturalWidth !== size.width || image.naturalHeight !== size.height || (expectedDimensions?.width && (expectedDimensions.width !== size.width || expectedDimensions.height !== size.height))) {
        onError('As dimensões da máscara não correspondem à fotografia orientada. Gere novamente a seleção.'); return;
      }
      const canvas = document.createElement('canvas');
      const scale = Math.min(1, 1024 / Math.max(size.width, size.height));
      canvas.width = Math.max(1, Math.round(size.width * scale)); canvas.height = Math.max(1, Math.round(size.height * scale));
      const context = canvas.getContext('2d', { willReadFrequently: true });
      if (!context) { onError('Canvas indisponível para visualizar a máscara.'); return; }
      context.drawImage(image, 0, 0, canvas.width, canvas.height);
      const pixels = context.getImageData(0, 0, canvas.width, canvas.height);
      pixels.data.set(grayscaleMaskToOverlay(pixels.data)); context.putImageData(pixels, 0, 0);
      onError(''); onLoad({ src, canvas });
    };
    image.onerror = () => { if (active) onError('A máscara retornada não pôde ser decodificada.'); };
    image.src = url;
    return () => { active = false; image.onload = null; image.onerror = null; };
  }, [url, src, size.width, size.height, expectedDimensions?.width, expectedDimensions?.height, onLoad, onError]);
  return null;
}

function PrivateThumbnail({ photo }: { photo: VisualizationAsset }) {
  return <span aria-label={`Fotografia protegida ${photo.name}`} className="grid h-16 place-items-center bg-slate-900 text-cyan-300"><ImageIcon className="h-5 w-5" /></span>;
}
