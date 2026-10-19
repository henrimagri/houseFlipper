import { FormEvent, useEffect, useMemo, useRef, useState } from 'react';
import { useLocation, useNavigate, useParams } from 'react-router-dom';
import { ArrowLeft, Box, Camera, Loader2, Share2, Upload } from 'lucide-react';
import api from '@/lib/api';
import GlbViewer from '@/components/GlbViewer';
import PropertyMaquetteViewer from '@/components/PropertyMaquetteViewer';
import PropertyPhotoSceneEditor from '@/components/PropertyPhotoSceneEditor';
import { roomLabel, visualizationModeLabel, type VisualizationPayload } from '@/lib/propertyVisualization';

type ShellTab = 'scene' | 'maquette' | 'model' | 'photos';

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
  const [tab, setTab] = useState<ShellTab>('scene');
  const [notice, setNotice] = useState('');

  async function load(nextTab?: ShellTab) {
    setLoading(true);
    setError('');
    try {
      const response = await api.get(`/visualizations/${subjectType}/${id}`);
      const payload = response.data.data as VisualizationPayload;
      setData(payload);
      setTab(nextTab ?? (payload.scene_graph ? 'scene' : payload.model ? 'model' : 'maquette'));
    } catch (exception: any) {
      setError(exception.response?.data?.message ?? 'Não foi possível abrir a visualização deste imóvel.');
      setData(null);
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => { void load(); }, [subjectType, id]);

  async function upload(event: FormEvent<HTMLInputElement>, kind: 'glb' | 'photo') {
    const file = event.currentTarget.files?.[0];
    if (!file) return;
    setUploading(true);
    setError('');
    try {
      const payload = new FormData();
      payload.append('arquivo', file);
      payload.append('kind', kind === 'glb' && file.name.toLowerCase().endsWith('.gltf') ? 'gltf' : kind);
      await api.post(`/visualizations/${subjectType}/${id}/assets`, payload);
      if (kind === 'photo') await rebuild();
      else await load('model');
    } catch (exception: any) {
      setError(exception.response?.data?.message ?? 'Falha ao enviar o arquivo.');
    } finally {
      setUploading(false);
      if (fileRef.current) fileRef.current.value = '';
      if (photoRef.current) photoRef.current.value = '';
    }
  }

  async function rebuild() {
    setBusy(true);
    setNotice('Analisando fotografias...');
    try {
      const response = await api.post(`/visualizations/${subjectType}/${id}/rebuild`);
      setData(response.data.data);
      setTab('scene');
      setNotice(statusLabel(response.data.data.scene_status));
    } catch (exception: any) {
      setError(exception.response?.data?.message ?? 'Não foi possível gerar a cena a partir das fotografias.');
    } finally {
      setBusy(false);
    }
  }

  async function mutate(actions: Array<Record<string, unknown> & { op: string }>) {
    setBusy(true);
    try {
      const response = await api.patch(`/visualizations/${subjectType}/${id}/scene`, { actions });
      setData(response.data.data);
    } catch (exception: any) {
      setError(exception.response?.data?.message ?? 'Não foi possível aplicar a alteração na cena.');
    } finally {
      setBusy(false);
    }
  }

  async function command(message: string, selectedId: string | null) {
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
    await api.post(`/visualizations/${subjectType}/${id}/versions`, { label });
    await load('scene');
  }

  async function restore(versionId: number) {
    const response = await api.post(`/visualizations/${subjectType}/${id}/versions/${versionId}/restore`);
    setData(response.data.data);
  }

  async function share() {
    const url = window.location.href;
    await navigator.clipboard.writeText(url);
    setNotice('Link copiado. Quem abrir precisa da mesma conta autorizada.');
  }

  const hasFloorplan = useMemo(() => Boolean(data?.gallery.some(item => item.kind === 'floorplan')), [data]);
  const graph = data?.scene_graph ?? null;

  if (loading) return <div className="min-h-[50vh] grid place-items-center text-slate-500"><Loader2 className="h-8 w-8 animate-spin text-primary-600" /></div>;
  if (!data) return <div role="alert" className="rounded-2xl border border-red-200 dark:border-red-900 bg-red-50 dark:bg-red-950 p-8 text-red-800 dark:text-red-200">{error}<button type="button" className="block mt-3 font-bold underline" onClick={() => void load()}>Tentar novamente</button></div>;

  const subject = data.subject;
  const modelUrl = data.model ? `${data.model.url}` : null;
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
        <button type="button" onClick={() => photoRef.current?.click()} disabled={uploading} className="inline-flex items-center gap-2 rounded-xl border border-slate-300 dark:border-slate-700 px-4 py-2 text-sm font-bold">Enviar foto</button>
        <input ref={fileRef} type="file" accept=".glb,.gltf,model/gltf-binary,model/gltf+json" className="hidden" onChange={event => void upload(event, 'glb')} />
        <input ref={photoRef} type="file" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" className="hidden" onChange={event => void upload(event, 'photo')} />
      </div>
    </header>

    {error && <p role="alert" className="rounded-xl bg-red-50 dark:bg-red-950 p-3 text-sm text-red-800 dark:text-red-200">{error}</p>}
    {notice && <p className="rounded-xl bg-slate-100 dark:bg-slate-800 p-3 text-sm text-slate-700 dark:text-slate-200">{notice}</p>}

    <section className={`rounded-3xl border border-slate-200 dark:border-slate-700 bg-slate-950 overflow-hidden ${fullscreen ? 'fixed inset-3 z-50' : ''}`}>
      <div className="flex flex-wrap items-center gap-2 px-4 py-3 text-slate-200 text-sm border-b border-white/10">
        <span className="inline-flex items-center gap-2 font-bold mr-2"><Box className="h-4 w-4" /> Visualizador</span>
        <TabButton active={tab === 'scene'} onClick={() => setTab('scene')}>Cena 3D</TabButton>
        <TabButton active={tab === 'maquette'} onClick={() => setTab('maquette')}>Maquete</TabButton>
        <TabButton active={tab === 'model'} onClick={() => setTab('model')} disabled={!modelUrl}>Modelo GLB</TabButton>
        <TabButton active={tab === 'photos'} onClick={() => setTab('photos')}>Fotos</TabButton>
        <button type="button" onClick={() => void rebuild()} disabled={busy} className="ml-auto rounded-lg bg-white text-slate-900 px-3 py-1.5 text-xs font-bold disabled:opacity-50">{busy ? 'Processando...' : 'Gerar a partir das fotos'}</button>
      </div>
      {tab === 'scene' && graph && (
        <PropertyPhotoSceneEditor
          graph={graph}
          canRenovate={Boolean(data.product_features.renovation_simulation)}
          versions={data.versions ?? []}
          busy={busy}
          commandHint="Ex.: paredes bege, piso em porcelanato branco, coloque um sofá."
          onMutate={mutate}
          onCommand={command}
          onSaveVersion={saveVersion}
          onRestore={restore}
          onFullscreen={() => setFullscreen(value => !value)}
        />
      )}
      {tab === 'scene' && !graph && (
        <EmptyPane
          icon={Box}
          title="Ainda sem cena gerada"
          text="Use as fotografias autorizadas deste registro para montar uma simulação editável. Sem fotos, a maquete paramétrica permanece disponível. A galeria não substitui o modelo 3D."
        />
      )}
      {tab === 'model' && modelUrl && <GlbViewer src={modelUrl} className="w-full h-[min(70vh,720px)]" />}
      {tab === 'model' && !modelUrl && <EmptyPane icon={Box} title="Sem arquivo GLB" text="Envie um modelo deste imóvel. A cena fotográfica e a maquete continuam disponíveis." />}
      {tab === 'maquette' && (
        <PropertyMaquetteViewer
          title={subject.title}
          areaM2={Number(subject.area_m2) || null}
          bedrooms={subject.bedrooms}
          bathrooms={subject.bathrooms}
          parkingSpaces={subject.parking_spaces}
          propertyType={subject.property_type}
          notes={subject.title}
          hasGlb={Boolean(modelUrl)}
          hasFloorplan={hasFloorplan}
          showChrome
          onFullscreen={() => setFullscreen(value => !value)}
          className="w-full h-[min(70vh,720px)]"
        />
      )}
      {tab === 'photos' && (
        <div className="min-h-[320px] bg-slate-900 p-6 grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
          {data.gallery.filter(item => item.kind !== 'glb').length === 0 && <p className="text-slate-300 col-span-full">Nenhuma foto ou planta anexada a esta visualização. Isto não é um modelo 3D editável.</p>}
          {data.gallery.map(item => (
            <article key={item.id} className="rounded-2xl border border-white/10 p-4 text-slate-200">
              <p className="font-bold">{item.name}</p>
              <p className="text-xs text-slate-400 mt-1">{item.kind === 'floorplan' ? 'Planta 2D' : item.kind === 'panorama' ? 'Panorama' : 'Foto'}{item.room_key ? ` · ${roomLabel(item.room_key)}` : ''}</p>
            </article>
          ))}
        </div>
      )}
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
        <p className="text-xs text-slate-500 mt-1">Paleta vem do histograma quando o arquivo local existe. Confiança é estimativa, não prova. Fotos originais não são alteradas pelo editor.</p>
        <div className="mt-4 space-y-3">
          {data.photo_analyses.length === 0 && <p className="text-sm text-slate-500">Nenhuma análise registrada. Gere a cena para classificar as fotos deste registro.</p>}
          {data.photo_analyses.map(item => <article key={item.id} className="rounded-xl border border-slate-200 dark:border-slate-800 p-3 text-sm">
            <p className="font-bold">{item.source_name || 'Arquivo'}</p>
            <p className="text-slate-500 mt-1">{roomLabel(item.room_guess)} · confiança {item.confidence}% · origem {item.origin}</p>
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

function TabButton({ active, onClick, disabled, children }: { active: boolean; onClick: () => void; disabled?: boolean; children: string }) {
  return <button type="button" disabled={disabled} onClick={onClick} className={`rounded-lg px-3 py-1.5 text-xs font-bold ${active ? 'bg-white text-slate-900' : 'border border-white/15 text-slate-200'} disabled:opacity-40`}>{children}</button>;
}

function EmptyPane({ icon: Icon, title, text }: { icon: typeof Box; title: string; text: string }) {
  return <div className="h-[min(52vh,520px)] grid place-items-center text-center px-6 text-slate-300">
    <div>
      <Icon className="h-10 w-10 mx-auto text-violet-300" />
      <h2 className="font-black text-xl mt-4">{title}</h2>
      <p className="text-sm mt-2 max-w-xl mx-auto">{text}</p>
    </div>
  </div>;
}

function Fact({ label, value }: { label: string; value: string | number }) {
  return <div className="rounded-xl bg-slate-50 dark:bg-slate-950 p-3"><p className="text-xs text-slate-500">{label}</p><p className="font-bold mt-1">{value}</p></div>;
}
