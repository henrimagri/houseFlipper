import { FormEvent, useEffect, useMemo, useRef, useState } from 'react';
import { useLocation, useNavigate, useParams } from 'react-router-dom';
import { ArrowLeft, Box, Camera, CheckCircle2, Cuboid, Images, Loader2, Paintbrush, ScanLine, Share2, TriangleAlert, Upload } from 'lucide-react';
import api from '@/lib/api';
import GlbViewer from '@/components/GlbViewer';
import PropertyMaquetteViewer from '@/components/PropertyMaquetteViewer';
import PropertyPhotoEditor from '@/components/PropertyPhotoEditor';
import PropertyPhotoSceneEditor from '@/components/PropertyPhotoSceneEditor';
import CreateEditableRoom, { type RoomInput } from '@/components/CreateEditableRoom';
import { roomLabel, visualizationModeLabel, type ModelViewerDiagnostic, type PhotoEditRequest, type PhotoEditSimulation, type VisualizationAsset, type VisualizationPayload, type SceneGraph } from '@/lib/propertyVisualization';

type WorkspaceMode = 'photo_edit' | 'scene_3d' | 'reconstruction_3d';
type ReconstructionMethod = 'auto' | 'calibrated' | 'tsdf' | 'dense_colmap';

function reconstructionMethodDescription(result: Record<string, unknown>): string {
  const actual = String(result.actual_method ?? 'em processamento');
  const fallback = result.calibrated_depth_fallback as Record<string, unknown> | undefined;
  const stereo = fallback?.calibrated_stereo as Record<string, unknown> | undefined;
  const strategy = String(stereo?.strategy ?? 'original');
  if (strategy === 'corrected') return `${actual} · profundidade monocular com correção estéreo local experimental`;
  if (strategy === 'hybrid') return `${actual} · fusão híbrida de estéreo verificado e profundidade monocular`;
  if (actual.includes('tsdf')) return `${actual} · TSDF com profundidade monocular calibrada`;
  if (actual.includes('calibrated')) return `${actual} · prévia geométrica incompleta`;
  return actual;
}

export default function PropertyViewer() {
  const { id = '' } = useParams();
  const location = useLocation();
  const subjectType = location.pathname.startsWith('/leads/') ? 'opportunities' : 'propriedades';
  const navigate = useNavigate();
  const fileRef = useRef<HTMLInputElement>(null);
  const photoRef = useRef<HTMLInputElement>(null);
  const [data, setData] = useState<VisualizationPayload | null>(null);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(true);
  const [uploading, setUploading] = useState(false);
  const [busy, setBusy] = useState(false);
  const [fullscreen, setFullscreen] = useState(false);
  const [mode, setMode] = useState<WorkspaceMode>('photo_edit');
  const [notice, setNotice] = useState('');
  const [captureConfirmed, setCaptureConfirmed] = useState(false);
  const [selectedPhotoIds, setSelectedPhotoIds] = useState<number[]>([]);
  const [reconstructionMethod, setReconstructionMethod] = useState<ReconstructionMethod>('calibrated');
  const [preview, setPreview] = useState<{ label: string; graph: SceneGraph } | null>(null);

  async function load(nextMode?: WorkspaceMode) {
    setLoading(true);
    setError('');
    try {
      const diagnosticAssetId = new URLSearchParams(location.search).get('diagnostic_asset_id');
      const response = await api.get(`/visualizations/${subjectType}/${id}`, {
        params: diagnosticAssetId ? { diagnostic_asset_id: diagnosticAssetId } : undefined,
      });
      const payload = response.data.data as VisualizationPayload;
      setData(payload);
      setMode(nextMode ?? (payload.gallery.some(item => item.kind === 'photo' || item.kind === 'panorama') ? 'photo_edit' : 'scene_3d'));
    } catch (exception: any) {
      setError(exception.response?.data?.message ?? 'Não foi possível abrir a visualização deste imóvel.');
      setData(null);
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => { void load(); }, [subjectType, id, location.search]);

  async function upload(event: FormEvent<HTMLInputElement>, kind: 'glb' | 'photo') {
    const files = Array.from(event.currentTarget.files ?? []);
    if (files.length === 0) return;
    setUploading(true);
    setError('');
    try {
      const payload = new FormData();
      if (kind === 'photo') files.forEach(file => payload.append('arquivos[]', file));
      else payload.append('arquivo', files[0]);
      payload.append('kind', kind === 'glb' && files[0].name.toLowerCase().endsWith('.gltf') ? 'gltf' : kind);
      const response = await api.post(`/visualizations/${subjectType}/${id}/assets`, payload);
      const created = Array.isArray(response.data.data) ? response.data.data : [response.data.data];
      setNotice(`${files.length} arquivo(s) enviado(s) com sucesso.`);
      await load(kind === 'photo' ? 'photo_edit' : 'reconstruction_3d');
      if (kind === 'photo') {
        setSelectedPhotoIds(created.map((item: { id: number }) => Number(item.id)).filter(Boolean));
        setCaptureConfirmed(false);
      }
    } catch (exception: any) {
      setError(exception.response?.data?.message ?? 'Falha ao enviar o arquivo.');
    } finally {
      setUploading(false);
      if (fileRef.current) fileRef.current.value = '';
      if (photoRef.current) photoRef.current.value = '';
    }
  }

  async function analyzeNeural(assetId: number) {
    setBusy(true);
    setError('');
    setNotice('Executando profundidade neural e segmentação na GPU...');
    try {
      const response = await api.post(`/visualizations/${subjectType}/${id}/vision`, { asset_id: assetId });
      setData(response.data.data);
      setMode('photo_edit');
      setNotice('Mapa de profundidade e máscara neural concluídos.');
    } catch (exception: any) {
      setError(exception.response?.data?.message ?? 'Não foi possível executar a análise neural.');
    } finally {
      setBusy(false);
    }
  }

  async function reconstruct() {
    const assetIds = selectedPhotoIds;
    setBusy(true);
    setError('');
    setNotice('Validando captura e iniciando o método selecionado...');
    try {
      const response = await api.post(`/visualizations/${subjectType}/${id}/reconstruct`, {
        asset_ids: assetIds,
        capture_confirmed: captureConfirmed,
        method: reconstructionMethod,
      });
      let current = response.data.data as VisualizationPayload;
      setData(current);
      setMode('reconstruction_3d');
      const job = current.processing_jobs?.find((item) => item.kind === 'photogrammetry');
      if (!job) throw new Error('O job de reconstrução não foi registrado.');
      setNotice('Reconstrução aceita. Você pode acompanhar as etapas sem manter uma requisição aberta.');
      for (let attempt = 0; attempt < 600 && !['completed', 'failed', 'cancelled'].includes(job.status); attempt += 1) {
        await new Promise((resolve) => window.setTimeout(resolve, 3000));
        const polled = await api.get(`/visualizations/${subjectType}/${id}/reconstruction-jobs/${job.id}`);
        current = polled.data.data as VisualizationPayload;
        setData(current);
        const latest = current.processing_jobs?.find((item) => item.id === job.id);
        if (!latest) throw new Error('O acompanhamento do job foi perdido.');
        job.status = latest.status;
        job.result = latest.result;
        job.error = latest.error;
        const stage = typeof latest.result?.stage === 'string' ? latest.result.stage.replace(/_/g, ' ') : 'processando';
        const percent = typeof latest.result?.percent === 'number' ? ` · ${latest.result.percent}%` : '';
        setNotice(`Reconstrução: ${stage}${percent}.`);
      }
      if (job.status === 'failed') {
        throw new Error(job.error ?? 'O worker não concluiu a reconstrução.');
      }
      if (job.status === 'cancelled') throw new Error('A reconstrução foi cancelada.');
      if (job.status !== 'completed') {
        throw new Error('A reconstrução excedeu o tempo máximo de acompanhamento.');
      }
      setNotice('Reconstrução concluída. Confira a qualidade geométrica e as limitações registradas.');
    } catch (exception: any) {
      setError(exception.response?.data?.message ?? exception.message ?? 'O conjunto não pôde ser reconstruído.');
    } finally {
      setBusy(false);
    }
  }

  async function cancelReconstruction() {
    const job = data?.processing_jobs?.find(item => item.kind === 'photogrammetry' && !['completed', 'failed', 'cancelled'].includes(item.status));
    if (!job) return;
    await api.delete(`/visualizations/${subjectType}/${id}/reconstruction-jobs/${job.id}`);
    setNotice('Cancelamento solicitado. O worker encerrará no próximo ponto seguro.');
  }

  async function rebuild() {
    if (preview) return;
    setBusy(true);
    setNotice('Analisando fotografias...');
    try {
      const response = await api.post(`/visualizations/${subjectType}/${id}/rebuild`);
      setData(response.data.data);
      setMode('scene_3d');
      setNotice(statusLabel(response.data.data.scene_status));
    } catch (exception: any) {
      setError(exception.response?.data?.message ?? 'Não foi possível gerar a cena a partir das fotografias.');
    } finally {
      setBusy(false);
    }
  }

  async function createRoom(input: RoomInput) {
    setBusy(true);
    setError('');
    try {
      const response = await api.post(`/visualizations/${subjectType}/${id}/rooms`, input);
      setData(response.data.data);
      setNotice('Ambiente criado e salvo. Selecione uma superfície ou móvel para começar.');
    } catch (exception: any) {
      throw new Error(exception.response?.data?.message ?? 'Não foi possível criar o ambiente.');
    } finally { setBusy(false); }
  }

  async function mutate(actions: Array<Record<string, unknown> & { op: string }>) {
    if (preview) throw new Error('Volte à versão atual antes de editar.');
    setBusy(true);
    setError('');
    try {
      const response = await api.patch(`/visualizations/${subjectType}/${id}/scene`, { actions });
      setData(response.data.data);
      setNotice('Alteração salva.');
    } catch (exception: any) {
      setError(exception.response?.data?.message ?? 'Não foi possível aplicar a alteração na cena.');
      throw new Error(exception.response?.data?.message ?? 'A alteração não foi salva.');
    } finally {
      setBusy(false);
    }
  }

  async function command(message: string, selectedId: string | null) {
    if (preview) return 'A comparação é somente leitura. Volte à versão atual para editar.';
    setBusy(true);
    try {
      const response = await api.post(`/visualizations/${subjectType}/${id}/commands`, { message, selected_id: selectedId });
      setData(response.data.data);
      return String(response.data.command?.message ?? (response.data.command?.ok ? 'Cena atualizada.' : 'Comando não executado.'));
    } catch (exception: any) {
      return exception.response?.data?.message ?? 'Não foi possível interpretar o comando.';
    } finally {
      setBusy(false);
    }
  }

  async function saveVersion(label: string) {
    setBusy(true);
    try {
      const response = await api.post(`/visualizations/${subjectType}/${id}/versions`, { label });
      setData(current => current ? { ...current, versions: [response.data.data, ...(current.versions ?? [])].slice(0, 12) } : current);
      setNotice('Versão salva.');
    } catch (exception: any) { setError(exception.response?.data?.message ?? 'Não foi possível salvar a versão.'); }
    finally { setBusy(false); }
  }

  async function restore(versionId: number) {
    setBusy(true);
    try {
      const response = await api.post(`/visualizations/${subjectType}/${id}/versions/${versionId}/restore`);
      setData(response.data.data);
      setPreview(null);
      setNotice('Versão restaurada.');
    } catch (exception: any) { setError(exception.response?.data?.message ?? 'Não foi possível restaurar a versão.'); }
    finally { setBusy(false); }
  }

  async function compareVersion(versionId: number) {
    setBusy(true);
    try {
      const response = await api.get(`/visualizations/${subjectType}/${id}/versions/${versionId}`);
      setPreview(response.data.data);
    } catch (exception: any) { setError(exception.response?.data?.message ?? 'Não foi possível abrir a comparação.'); }
    finally { setBusy(false); }
  }

  async function share() {
    const url = window.location.href;
    await navigator.clipboard.writeText(url);
    setNotice('Link copiado. Quem abrir precisa da mesma conta autorizada.');
  }

  async function applyPhotoSimulation(request: PhotoEditRequest, _source: VisualizationAsset): Promise<PhotoEditSimulation> {
    if (request.mask_mode !== 'manual' && request.points.every(point => point.label === 0)) throw new Error('Marque ao menos um ponto da superfície que deseja alterar.');
    setBusy(true);
    setNotice(request.mask_mode === 'manual' ? 'Aplicando a máscara desenhada sobre uma cópia da fotografia...' : 'Refinando a máscara e preservando sombras e iluminação da fotografia...');
    try {
      const response = await api.post(`/visualizations/${subjectType}/${id}/photo-simulations`, request);
      const payload = response.data.data as VisualizationPayload;
      setData(payload);
      const simulation = payload.photo_editing?.simulations?.[0];
      if (!simulation) throw new Error('O processamento terminou sem devolver a versão editada.');
      setNotice('Simulação processada e salva sem alterar a fotografia original.');
      return simulation;
    } catch (exception: any) {
      throw new Error(exception.response?.data?.message ?? exception.message ?? 'Não foi possível processar a simulação.');
    } finally {
      setBusy(false);
    }
  }

  const hasFloorplan = useMemo(() => Boolean(data?.gallery.some(item => item.kind === 'floorplan')), [data]);
  const graph = data?.scene_graph ?? null;
  const capturePhotos = data?.gallery.filter(item => (item.kind === 'photo' || item.kind === 'panorama') && selectedPhotoIds.includes(item.id)) ?? [];
  const availablePhotos = data?.gallery.filter(item => item.kind === 'photo' || item.kind === 'panorama') ?? [];
  const reconstructionArtifacts = data?.gallery.filter(item => item.kind === 'point_cloud' || item.kind === 'reconstruction_mesh') ?? [];
  const activeReconstructionJob = data?.processing_jobs?.find(item => item.kind === 'photogrammetry');

  function toggleCapturePhoto(assetId: number) {
    setCaptureConfirmed(false);
    setSelectedPhotoIds(current => current.includes(assetId) ? current.filter(id => id !== assetId) : [...current, assetId]);
  }

  if (loading) return <div className="min-h-[50vh] grid place-items-center text-slate-500"><Loader2 className="h-8 w-8 animate-spin text-primary-600" /></div>;
  if (!data) return <div role="alert" className="rounded-2xl border border-red-200 dark:border-red-900 bg-red-50 dark:bg-red-950 p-8 text-red-800 dark:text-red-200">{error}<button type="button" className="block mt-3 font-bold underline" onClick={() => void load()}>Tentar novamente</button></div>;

  const subject = data.subject;
  const modelUrl = data.model ? `${data.model.url}` : null;
  const modelEligible = data.model?.viewer_eligible === true;
  const modelDiagnostic = data.model?.viewer_diagnostic ?? {
    code: data.model ? 'eligibility_not_confirmed' : 'model_not_available',
    message: data.model
      ? 'O arquivo existe, mas ainda não foi aprovado para o viewer. Valide geometria, materiais, escala e origem antes de exibi-lo como reconstrução final.'
      : 'Ainda não existe um modelo 3D elegível para este imóvel. Use fotografias autorizadas para iniciar a reconstrução.',
  };
  const label = visualizationModeLabel(data);

  return <div className="space-y-5">
    <header className="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
      <div>
        <button type="button" onClick={() => navigate(subject.back_path)} className="inline-flex items-center gap-2 text-sm font-bold text-primary-600 dark:text-primary-400"><ArrowLeft className="h-4 w-4" /> Voltar ao registro</button>
        <h1 className="text-3xl font-black mt-2 text-slate-900 dark:text-slate-100">{subject.title}</h1>
        <p className="text-slate-500 dark:text-slate-400 mt-1">{[subject.neighborhood, subject.city, subject.state].filter(Boolean).join(' · ') || 'Localidade não informada'}</p>
      </div>
      <div className="flex flex-wrap gap-2">
        <span className={`rounded-full px-3 py-1.5 text-xs font-bold ${data.level === 'photo_referenced' || data.faithful ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200' : 'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200'}`}>{label}</span>
        <button type="button" onClick={() => void share()} className="inline-flex items-center gap-2 rounded-xl border border-slate-300 dark:border-slate-700 px-4 py-2 text-sm font-bold"><Share2 className="h-4 w-4" /> Compartilhar</button>
        <button type="button" onClick={() => fileRef.current?.click()} disabled={uploading} className="inline-flex items-center gap-2 rounded-xl bg-primary-600 text-white px-4 py-2 text-sm font-bold disabled:opacity-60"><Upload className="h-4 w-4" /> {uploading ? 'Enviando...' : 'Enviar GLB'}</button>
        <button type="button" onClick={() => photoRef.current?.click()} disabled={uploading} className="inline-flex items-center gap-2 rounded-xl border border-slate-300 dark:border-slate-700 px-4 py-2 text-sm font-bold"><Images className="h-4 w-4" /> Enviar fotos</button>
        <input ref={fileRef} type="file" accept=".glb,.gltf,model/gltf-binary,model/gltf+json" className="hidden" onChange={event => void upload(event, 'glb')} />
        <input ref={photoRef} multiple type="file" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" className="hidden" onChange={event => void upload(event, 'photo')} />
      </div>
    </header>

    {error && <p role="alert" className="rounded-xl bg-red-50 dark:bg-red-950 p-3 text-sm text-red-800 dark:text-red-200">{error}</p>}
    {notice && <p className="rounded-xl bg-slate-100 dark:bg-slate-800 p-3 text-sm text-slate-700 dark:text-slate-200">{notice}</p>}

    <CreationJourney
      hasPhotos={availablePhotos.length > 0}
      mode={mode}
      processing={Boolean(activeReconstructionJob && !['completed', 'failed', 'cancelled'].includes(activeReconstructionJob.status))}
      editorReady={Boolean(graph || modelEligible)}
      presentationReady={Boolean((data.versions?.length ?? 0) > 0 || (data.photo_editing?.simulations?.length ?? 0) > 0)}
    />

    <nav aria-label="Modos da visualização do imóvel" className="grid gap-3 md:grid-cols-3">
      <ModeButton icon={Paintbrush} active={mode === 'photo_edit'} onClick={() => setMode('photo_edit')} title="A · Simulação em fotografia" text="Pintura e acabamentos sobre a foto original, sem gerar geometria 3D" />
      <ModeButton icon={Cuboid} active={mode === 'scene_3d'} onClick={() => setMode('scene_3d')} title="B · Ambiente 3D editável" text="Crie um cômodo com medidas informadas e experimente reformas" />
      <ModeButton icon={ScanLine} active={mode === 'reconstruction_3d'} onClick={() => setMode('reconstruction_3d')} title="C · Reconstrução multivista" text="Geometria experimental: consulte o método e a qualidade realmente obtidos" />
    </nav>

    <div className="flex flex-wrap items-center gap-2 rounded-2xl border border-slate-200 bg-white p-3 text-xs dark:border-slate-700 dark:bg-slate-900">
      <span className="font-black text-slate-700 dark:text-slate-200">Comparar:</span>
      <button type="button" onClick={() => setMode('photo_edit')} className="rounded-lg border border-slate-200 px-3 py-1.5 font-bold dark:border-slate-700">Foto original / reforma</button>
      <button type="button" onClick={() => setMode('reconstruction_3d')} className="rounded-lg border border-slate-200 px-3 py-1.5 font-bold dark:border-slate-700">Geometria observada</button>
      <button type="button" onClick={() => setMode('scene_3d')} className="rounded-lg border border-slate-200 px-3 py-1.5 font-bold dark:border-slate-700">Ambiente aproximado</button>
    </div>

    <section className={`rounded-3xl border border-slate-200 bg-slate-950 dark:border-slate-700 ${fullscreen ? 'fixed inset-3 z-50 overflow-auto' : 'overflow-hidden'}`}>
      {mode === 'photo_edit' && <PropertyPhotoEditor photos={availablePhotos} simulations={data.photo_editing?.simulations ?? []} busy={busy} onAnalyze={analyzeNeural} onApply={applyPhotoSimulation} />}

      {mode === 'scene_3d' && <>
        <div className="flex flex-wrap items-center gap-3 border-b border-white/10 px-4 py-3 text-slate-200">
          <div className="min-w-0 flex-1"><p className="font-black">Ambiente editável aproximado</p><p className="text-xs text-slate-400">Geometria paramétrica completa para experimentar reformas. As medidas estimadas são identificadas e podem ser substituídas pelas medidas do usuário.</p></div>
          <button type="button" onClick={() => void rebuild()} disabled={busy || Boolean(preview) || availablePhotos.length === 0} className="rounded-lg bg-white px-3 py-2 text-xs font-bold text-slate-900 disabled:opacity-40">{busy ? 'Processando...' : graph ? 'Atualizar pelas fotos' : 'Criar pelas fotos'}</button>
        </div>
        {!preview && <CreateEditableRoom busy={busy} hasRooms={Boolean(graph?.rooms.length)} onCreate={createRoom} />}
        {preview && <div className="flex items-center justify-between gap-3 bg-amber-400/10 px-5 py-3 text-sm text-amber-100"><span>Comparação: {preview.label} · somente leitura. A cena atual está preservada.</span><button className="rounded-lg border border-amber-200/40 px-3 py-2 font-bold" onClick={() => setPreview(null)}>Voltar à versão atual</button></div>}
        {graph ? <PropertyPhotoSceneEditor
          graph={preview?.graph ?? graph}
          readOnly={Boolean(preview)}
          canRenovate={Boolean(data.product_features.renovation_simulation)}
          versions={data.versions ?? []}
          busy={busy}
          commandHint="Ex.: paredes bege, piso em porcelanato branco, coloque um sofá."
          onMutate={mutate}
          onCommand={command}
          onSaveVersion={saveVersion}
          onRestore={restore}
          onPreview={compareVersion}
          onFullscreen={() => setFullscreen(value => !value)}
        /> : <div>
          <div className="border-b border-amber-300/20 bg-amber-400/10 px-4 py-3 text-xs text-amber-100">Maquete paramétrica ilustrativa. Ela organiza os dados do anúncio e não representa uma reconstrução fiel do imóvel.</div>
          <PropertyMaquetteViewer
            title={subject.title}
            areaM2={Number(subject.area_m2) || null}
            bedrooms={subject.bedrooms}
            bathrooms={subject.bathrooms}
            parkingSpaces={subject.parking_spaces}
            propertyType={subject.property_type}
            notes={subject.title}
            hasGlb={modelEligible}
            hasFloorplan={hasFloorplan}
            showChrome
            onFullscreen={() => setFullscreen(value => !value)}
            className="h-[min(70vh,720px)] w-full"
          />
        </div>}
      </>}

      {mode === 'reconstruction_3d' && <div className="space-y-0 text-slate-100">
        <div className="border-b border-white/10 p-5">
          <h2 className="flex items-center gap-2 text-lg font-black"><ScanLine className="h-5 w-5 text-cyan-400" /> Reconstrução observada</h2>
          <p className="mt-1 text-sm text-slate-400">A qualidade depende do método executado. Profundidade monocular calibrada continua sendo uma estimativa; regiões não observadas permanecem desconhecidas.</p>
        </div>
        {data.model?.diagnostic_preview && <div className="border-b border-amber-300/20 bg-amber-400/10 px-5 py-3 text-sm text-amber-100">
          Candidato experimental aberto somente para diagnóstico. O modelo principal do imóvel continua preservado.
          <button type="button" onClick={() => navigate(location.pathname)} className="ml-3 font-bold underline">Voltar ao modelo ativo</button>
        </div>}
        {!data.model?.diagnostic_preview && (data.diagnostic_models?.length ?? 0) > 0 && <div className="flex flex-wrap items-center gap-2 border-b border-white/10 px-5 py-3 text-xs text-slate-300">
          <span>Candidatos disponíveis para inspeção:</span>
          {data.diagnostic_models?.map(candidate => <button key={candidate.id} type="button" onClick={() => navigate(`${location.pathname}?diagnostic_asset_id=${candidate.id}`)} className="rounded-lg border border-amber-300/30 px-2.5 py-1.5 font-bold text-amber-200">Inspecionar {candidate.name}</button>)}
        </div>}
        {modelUrl && modelEligible ? <GlbViewer
          src={modelUrl}
          eligible={modelEligible}
          diagnostic={data.model?.viewer_diagnostic}
          className="h-[min(70vh,720px)] w-full"
          editable
          busy={busy}
          overrides={graph?.reconstruction_overrides}
          onEdit={(regionId, color, material) => mutate([{ op: 'set_reconstruction_material', id: regionId, color, material }])}
          onUndo={() => mutate([{ op: 'undo_reconstruction' }])}
          onRedo={() => mutate([{ op: 'redo_reconstruction' }])}
          onCommand={command}
        /> : <ModelDiagnosticPanel diagnostic={modelDiagnostic} hasModel={Boolean(modelUrl)} artifacts={reconstructionArtifacts} />}

        <div className="border-t border-white/10 bg-slate-900/70 p-5">
          <div className="flex items-start gap-3">
            <ScanLine className="h-6 w-6 shrink-0 text-cyan-400" />
            <div className="min-w-0 flex-1">
              <h3 className="font-black">Captura para reconstrução 3D</h3>
              <p className="mt-1 text-sm text-slate-300">Envie de 8 a 120 fotos do mesmo ambiente, com pelo menos 60% de sobreposição entre posições consecutivas.</p>
              <ul className="mt-3 grid gap-2 text-sm text-slate-300 sm:grid-cols-2">
                {['Fotografe de vários ângulos, caminhando devagar.', 'Mantenha iluminação e foco consistentes.', 'Evite pessoas ou objetos em movimento.', 'Separe cada ambiente e nunca misture imóveis.', 'Não use imagens borradas ou muito escuras.', 'Inclua cantos, piso e paredes com bastante textura.'].map(item => <li key={item} className="flex gap-2"><CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-cyan-400" />{item}</li>)}
              </ul>
              <div className="mt-4 flex gap-2 overflow-x-auto pb-2" aria-label="Selecionar fotos para reconstrução">
                {availablePhotos.map(photo => <label key={photo.id} className={`w-28 shrink-0 cursor-pointer overflow-hidden rounded-xl border ${selectedPhotoIds.includes(photo.id) ? 'border-cyan-400 ring-2 ring-cyan-400/20' : 'border-white/10'}`}><img src={photo.url} alt="" className="h-16 w-full object-cover" /><span className="flex items-center gap-2 px-2 py-2 text-[11px] font-bold"><input type="checkbox" checked={selectedPhotoIds.includes(photo.id)} onChange={() => toggleCapturePhoto(photo.id)} />{photo.name}</span></label>)}
                {availablePhotos.length === 0 && <p className="text-sm text-slate-400">Envie fotografias para montar o conjunto de captura.</p>}
              </div>
              <p className="mt-2 text-xs font-bold uppercase tracking-wide text-cyan-300">{capturePhotos.length} selecionadas de {availablePhotos.length} disponíveis</p>
              <label className="mt-2 flex items-start gap-2 text-sm font-medium"><input type="checkbox" checked={captureConfirmed} onChange={event => setCaptureConfirmed(event.target.checked)} className="mt-1" /> Confirmo que as {capturePhotos.length} fotos selecionadas são autorizadas, pertencem ao mesmo ambiente e possuem sobreposição.</label>
              <label className="mt-4 block max-w-md text-xs font-black uppercase tracking-wide text-slate-300">Método real utilizado
                <select value={reconstructionMethod} onChange={event => setReconstructionMethod(event.target.value as ReconstructionMethod)} disabled={busy} className="mt-2 w-full rounded-xl border border-white/15 bg-slate-950 px-3 py-2.5 text-sm font-semibold normal-case tracking-normal text-slate-100">
                  <option value="tsdf">TSDF volumétrico em CPU</option>
                  <option value="calibrated">Fallback multivista calibrado</option>
                  <option value="auto">Automático: denso quando disponível</option>
                  <option value="dense_colmap">COLMAP denso (exige CUDA compatível)</option>
                </select>
              </label>
              <div className="mt-4 flex flex-wrap gap-2">
                <button type="button" disabled={busy || !captureConfirmed || capturePhotos.length < 8} onClick={() => void reconstruct()} className="inline-flex items-center gap-2 rounded-xl bg-cyan-500 px-4 py-2.5 text-sm font-black text-slate-950 disabled:cursor-not-allowed disabled:opacity-40"><Box className="h-4 w-4" /> Iniciar reconstrução ({capturePhotos.length}/8 mín.)</button>
                {busy && <button type="button" onClick={() => void cancelReconstruction()} className="rounded-xl border border-rose-300/40 px-4 py-2.5 text-sm font-black text-rose-200">Cancelar com segurança</button>}
              </div>
              {data.processing_jobs?.find(item => item.kind === 'photogrammetry')?.result && (() => {
                const result = data.processing_jobs?.find(item => item.kind === 'photogrammetry')?.result as Record<string, unknown>;
                return <p className="mt-3 text-xs text-slate-400">Método solicitado: {String(result.requested_method ?? 'não informado')} · método efetivo: {reconstructionMethodDescription(result)}</p>;
              })()}
            </div>
          </div>
        </div>
      </div>}
    </section>

    <div className="grid xl:grid-cols-[1.1fr_0.9fr] gap-5">
      <section className="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-5">
        <h2 className="font-black">Explore este imóvel</h2>
        <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">A cena 3D usa só evidências deste registro. Uma foto de um cômodo não vira planta fiel do imóvel inteiro.</p>
        <div className="grid sm:grid-cols-2 gap-3 mt-4">
          <Fact label="Quartos" value={subject.bedrooms ?? '—'} />
          <Fact label="Banheiros" value={subject.bathrooms ?? '—'} />
          <Fact label="Vagas" value={subject.parking_spaces ?? '—'} />
          <Fact label="Área" value={subject.area_m2 ? `${subject.area_m2} m²` : '—'} />
        </div>
        <ul className="mt-5 space-y-2 text-sm">
          {data.rooms.map(room => <li key={room.key} className="flex justify-between gap-3"><span>{room.label}</span><span className="text-slate-500">{room.available ? 'Na cena' : 'Sem geometria'}</span></li>)}
        </ul>
      </section>
      <section className="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-5">
        <h2 className="font-black flex items-center gap-2"><Camera className="h-4 w-4" /> Análise das fotografias</h2>
        <p className="text-xs text-slate-500 mt-1">A confiança do ambiente é separada da confiança dos objetos. Quando não existe classificação de cômodo, nenhuma porcentagem artificial é exibida. Fotos originais não são alteradas pelo editor.</p>
        <div className="mt-4 space-y-3">
          {data.photo_analyses.length === 0 && <p className="text-sm text-slate-500">Nenhuma análise registrada. Gere a cena para classificar as fotos deste registro.</p>}
          {data.photo_analyses.map(item => <article key={item.id} className="rounded-xl border border-slate-200 dark:border-slate-800 p-3 text-sm">
            <p className="font-bold">{item.source_name || 'Arquivo'}</p>
            <p className="text-slate-500 mt-1">{item.room_guess && item.room_confidence != null ? `${roomLabel(item.room_guess)} · confiança do ambiente ${item.room_confidence}%` : 'Ambiente não classificado · sem confiança de cômodo'} · origem {item.origin}</p>
            {item.detection_confidence != null && <p className="mt-1 text-xs text-slate-500">Maior confiança entre objetos: {item.detection_confidence}%.</p>}
            {item.elements && item.elements.length > 0 && <p className="mt-2 text-xs text-slate-600 dark:text-slate-300">Objetos detectados: {item.elements.map(element => `${detectedLabel(element.label)} ${Math.round((element.confidence ?? 0) * 100)}%`).join(', ')}.</p>}
          </article>)}
        </div>
      </section>
    </div>
  </div>;
}

function statusLabel(status?: string | null): string {
  return ({
    analyzing: 'Analisando fotografias',
    ready: 'Cena pronta para edição',
    insufficient: 'Dados insuficientes para reconstrução fiel',
    failed: 'Falha ao gerar a cena',
  } as Record<string, string>)[status ?? ''] ?? 'Processamento concluído';
}

function ModeButton({ icon: Icon, active, onClick, title, text }: { icon: typeof Box; active: boolean; onClick: () => void; title: string; text: string }) {
  return <button type="button" aria-pressed={active} onClick={onClick} className={`flex items-start gap-3 rounded-2xl border p-4 text-left transition ${active ? 'border-cyan-500 bg-cyan-50 ring-2 ring-cyan-500/15 dark:bg-cyan-950/30' : 'border-slate-200 bg-white hover:border-slate-300 dark:border-slate-700 dark:bg-slate-900'}`}><span className={`grid h-10 w-10 shrink-0 place-items-center rounded-xl ${active ? 'bg-cyan-500 text-slate-950' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'}`}><Icon className="h-5 w-5" /></span><span><span className="block font-black text-slate-900 dark:text-slate-100">{title}</span><span className="mt-1 block text-xs text-slate-500 dark:text-slate-400">{text}</span></span></button>;
}

function CreationJourney({ hasPhotos, mode, processing, editorReady, presentationReady }: {
  hasPhotos: boolean;
  mode: WorkspaceMode;
  processing: boolean;
  editorReady: boolean;
  presentationReady: boolean;
}) {
  const steps = [
    ['1', 'Imóvel', true],
    ['2', 'Fotografias', hasPhotos],
    ['3', 'Modalidade', Boolean(mode)],
    ['4', processing ? 'Processando' : 'Processamento', processing || editorReady],
    ['5', 'Editor', editorReady],
    ['6', 'Apresentação', presentationReady],
  ] as const;

  return <section aria-label="Etapas de criação do projeto visual" className="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
    <div className="flex items-start justify-between gap-4"><div><h2 className="font-black text-slate-900 dark:text-slate-100">Projeto visual do imóvel</h2><p className="mt-1 text-xs text-slate-500 dark:text-slate-400">Você pode usar a simulação aproximada enquanto a reconstrução observada estiver indisponível.</p></div><span className="rounded-full bg-cyan-100 px-3 py-1 text-[11px] font-black text-cyan-800 dark:bg-cyan-950 dark:text-cyan-200">fluxo guiado</span></div>
    <ol className="mt-4 grid gap-2 sm:grid-cols-3 xl:grid-cols-6">{steps.map(([number, label, complete]) => <li key={number} className={`rounded-xl border px-3 py-2 ${complete ? 'border-emerald-300 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950/40' : 'border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-950'}`}><span className="text-[10px] font-black uppercase tracking-widest text-slate-400">Etapa {number}</span><span className="mt-1 flex items-center gap-1.5 text-xs font-bold">{complete && <CheckCircle2 className="h-3.5 w-3.5 text-emerald-500" />}{label}</span></li>)}</ol>
  </section>;
}

function ModelDiagnosticPanel({ diagnostic, hasModel, artifacts }: { diagnostic: ModelViewerDiagnostic; hasModel: boolean; artifacts: VisualizationAsset[] }) {
  return <div className="grid min-h-[340px] place-items-center px-5 py-8">
    <div className="w-full max-w-2xl rounded-2xl border border-amber-300/20 bg-amber-400/10 p-5">
      <div className="flex gap-3"><TriangleAlert className="h-6 w-6 shrink-0 text-amber-300" /><div><h3 className="font-black text-amber-50">{hasModel ? 'Modelo ainda não elegível para o viewer' : 'Reconstrução 3D ainda não disponível'}</h3><p className="mt-1 text-sm text-amber-100/80">{diagnostic.message}</p></div></div>
      {diagnostic.checks && diagnostic.checks.length > 0 && <ul className="mt-4 grid gap-2">{diagnostic.checks.map(check => <li key={check.label} className="flex items-start justify-between gap-4 rounded-lg border border-white/10 px-3 py-2 text-xs"><span><span className="font-bold">{check.label}</span>{check.detail && <span className="mt-0.5 block text-slate-400">{check.detail}</span>}</span><span className={check.status === 'passed' ? 'text-emerald-300' : check.status === 'failed' ? 'text-rose-300' : 'text-amber-300'}>{check.status === 'passed' ? 'Aprovado' : check.status === 'failed' ? 'Reprovado' : 'Pendente'}</span></li>)}</ul>}
      {artifacts.length > 0 && <div className="mt-4"><p className="text-xs font-black uppercase tracking-wide text-slate-300">Artefatos técnicos disponíveis</p><ul className="mt-2 flex flex-wrap gap-2">{artifacts.map(artifact => <li key={artifact.id} className="rounded-full border border-white/10 px-3 py-1 text-xs text-slate-300">{artifact.kind === 'point_cloud' ? 'Nuvem de pontos' : 'Malha de reconstrução'} · {artifact.name}</li>)}</ul><p className="mt-2 text-xs text-slate-400">Esses artefatos ajudam no diagnóstico, mas não são apresentados como modelo final do imóvel.</p></div>}
    </div>
  </div>;
}

function Fact({ label, value }: { label: string; value: string | number }) {
  return <div className="rounded-xl bg-slate-50 dark:bg-slate-950 p-3"><p className="text-xs text-slate-500">{label}</p><p className="font-bold mt-1">{value}</p></div>;
}

function detectedLabel(label?: string): string {
  return ({ couch: 'sofá', chair: 'cadeira', 'dining table': 'mesa de jantar', 'potted plant': 'planta em vaso', bowl: 'tigela', bed: 'cama' } as Record<string, string>)[label ?? ''] ?? (label || 'objeto');
}
