import { FormEvent, useEffect, useMemo, useRef, useState } from 'react';
import { Loader2, RotateCcw, Send, TriangleAlert } from 'lucide-react';
import { getAuthToken } from '@/lib/authToken';
import type { ModelViewerDiagnostic } from '@/lib/propertyVisualization';

type Props = {
  src: string;
  eligible: boolean;
  diagnostic?: ModelViewerDiagnostic | null;
  className?: string;
  editable?: boolean;
  busy?: boolean;
  overrides?: Record<string, { color?: string; material?: string }>;
  onEdit?: (regionId: string, color: string, material: string) => Promise<void> | void;
  onUndo?: () => Promise<void> | void;
  onCommand?: (message: string, selectedId: string | null) => Promise<string>;
};

const regionLabels: Record<string, string> = {
  'geometry-wall_candidate': 'Parede candidata',
  'geometry-floor_candidate': 'Piso candidato',
  'geometry-ceiling_candidate': 'Teto candidato',
  'geometry-horizontal_surface': 'Superfície horizontal',
  'geometry-other_surface': 'Outra superfície',
};

export default function GlbViewer({ src, eligible, diagnostic, className, editable = false, busy = false, overrides = {}, onEdit, onUndo, onCommand }: Props) {
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const [status, setStatus] = useState<'idle' | 'loading' | 'ready' | 'failed'>('idle');
  const [error, setError] = useState('');
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [commandText, setCommandText] = useState('');
  const [commandReply, setCommandReply] = useState('');
  const overrideKey = useMemo(() => JSON.stringify(overrides), [overrides]);

  useEffect(() => {
    if (!eligible) {
      setStatus('idle');
      setError('');
      return;
    }

    const canvas = canvasRef.current;
    if (!canvas) return;
    let disposed = false;
    let renderer: any;
    let controls: any;
    let scene: any;
    let frame = 0;
    let removeResize: () => void = () => {};
    const token = getAuthToken();
    setStatus('loading');
    setError('');

    (async () => {
      const THREE = await import('three');
      const { GLTFLoader } = await import('three/examples/jsm/loaders/GLTFLoader.js');
      const { OrbitControls } = await import('three/examples/jsm/controls/OrbitControls.js');
      if (disposed) return;

      scene = new THREE.Scene();
      scene.background = new THREE.Color(0x0f172a);
      const camera = new THREE.PerspectiveCamera(50, canvas.clientWidth / Math.max(canvas.clientHeight, 1), 0.01, 1000);
      renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: false });
      renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
      renderer.setSize(canvas.clientWidth, canvas.clientHeight, false);
      renderer.outputColorSpace = THREE.SRGBColorSpace;
      renderer.toneMapping = THREE.ACESFilmicToneMapping;
      renderer.toneMappingExposure = 1.08;
      scene.add(new THREE.HemisphereLight(0xffffff, 0x334155, 1.25));
      const sun = new THREE.DirectionalLight(0xffffff, 1.35);
      sun.position.set(4, 8, 6);
      scene.add(sun);

      controls = new OrbitControls(camera, canvas);
      controls.enableDamping = true;
      controls.screenSpacePanning = true;

      const response = await fetch(src, { headers: token ? { Authorization: `Bearer ${token}` } : {} });
      if (!response.ok) throw new Error(`download:${response.status}`);
      const buffer = await response.arrayBuffer();
      if (buffer.byteLength < 20) throw new Error('empty_model');
      if (disposed) return;

      const loader = new GLTFLoader();
      const gltf: any = await new Promise((resolve, reject) => loader.parse(buffer, '', resolve, reject));
      if (disposed) return;
      gltf.scene.traverse((object: any) => {
        if (!object.isMesh) return;
        if (object.geometry?.getAttribute?.('color')) {
          const previousMaterials = Array.isArray(object.material) ? object.material : [object.material];
          object.material = new THREE.MeshStandardMaterial({
            color: 0xffffff,
            vertexColors: true,
            side: THREE.DoubleSide,
            metalness: 0,
            roughness: 0.88,
          });
          previousMaterials.filter(Boolean).forEach((material: any) => material.dispose?.());
          return;
        }
        const materials = Array.isArray(object.material) ? object.material : [object.material];
        materials.filter(Boolean).forEach((material: any) => {
          // Reconstructions can contain open surfaces. Rendering both sides avoids
          // an apparently black/empty model when the first camera sees the back face.
          material.side = THREE.DoubleSide;
          if ('metalness' in material) material.metalness = 0;
          const override = overrides[material.name];
          if (override?.color && material.color) material.color.set(override.color);
          if (override?.material === 'wood') material.roughness = 0.66;
          if (override?.material === 'porcelain') material.roughness = 0.28;
          material.needsUpdate = true;
        });
      });
      scene.add(gltf.scene);

      const box = new THREE.Box3().setFromObject(gltf.scene);
      const size = box.getSize(new THREE.Vector3());
      const center = box.getCenter(new THREE.Vector3());
      const maxDimension = Math.max(size.x, size.y, size.z);
      if (![size.x, size.y, size.z, center.x, center.y, center.z, maxDimension].every(Number.isFinite) || maxDimension <= 0) throw new Error('invalid_bounds');

      const fovRadians = THREE.MathUtils.degToRad(camera.fov);
      const distance = Math.max(maxDimension / (2 * Math.tan(fovRadians / 2)) * 1.25, 0.5);
      const dimensions = [size.x, size.y, size.z];
      const thinnestAxis = dimensions.indexOf(Math.min(...dimensions));
      const viewingDirection = new THREE.Vector3(
        thinnestAxis === 0 ? 1 : 0,
        thinnestAxis === 1 ? 1 : 0,
        thinnestAxis === 2 ? 1 : 0,
      ).normalize();
      camera.up.set(0, thinnestAxis === 1 ? 0 : 1, thinnestAxis === 1 ? 1 : 0);
      controls.target.copy(center);
      camera.position.copy(center).addScaledVector(viewingDirection, distance);
      camera.near = Math.max(maxDimension / 1000, 0.001);
      camera.far = Math.max(distance * 20, maxDimension * 50);
      camera.updateProjectionMatrix();
      controls.update();

      let pointerStart: { x: number; y: number } | null = null;
      const pointerDown = (event: PointerEvent) => { pointerStart = { x: event.clientX, y: event.clientY }; };
      const pointerUp = (event: PointerEvent) => {
        if (!editable || !pointerStart || Math.hypot(event.clientX - pointerStart.x, event.clientY - pointerStart.y) > 5) return;
        const bounds = canvas.getBoundingClientRect();
        const pointer = new THREE.Vector2(((event.clientX - bounds.left) / bounds.width) * 2 - 1, -((event.clientY - bounds.top) / bounds.height) * 2 + 1);
        const raycaster = new THREE.Raycaster();
        raycaster.setFromCamera(pointer, camera);
        const hit = raycaster.intersectObject(gltf.scene, true).find((item: any) => item.object?.isMesh);
        if (!hit) return;
        const materialIndex = hit.face?.materialIndex ?? 0;
        const hitObject = hit.object as any;
        const material = Array.isArray(hitObject.material) ? hitObject.material[materialIndex] : hitObject.material;
        const id = String(material?.name ?? hitObject.name ?? '');
        if (id.startsWith('geometry-')) setSelectedId(id);
      };
      canvas.addEventListener('pointerdown', pointerDown);
      canvas.addEventListener('pointerup', pointerUp);
      const previousRemoveResize = removeResize;
      removeResize = () => { previousRemoveResize(); canvas.removeEventListener('pointerdown', pointerDown); canvas.removeEventListener('pointerup', pointerUp); };

      const onResize = () => {
        if (disposed) return;
        camera.aspect = canvas.clientWidth / Math.max(canvas.clientHeight, 1);
        camera.updateProjectionMatrix();
        renderer.setSize(canvas.clientWidth, canvas.clientHeight, false);
      };
      window.addEventListener('resize', onResize);
      removeResize = () => window.removeEventListener('resize', onResize);

      const tick = () => {
        if (disposed) return;
        controls.update();
        renderer.render(scene, camera);
        frame = requestAnimationFrame(tick);
      };
      setStatus('ready');
      tick();
    })().catch((exception: unknown) => {
      if (disposed) return;
      const reason = exception instanceof Error ? exception.message : '';
      setError(reason.startsWith('download:') ? 'Não foi possível baixar o modelo autorizado.' : reason === 'invalid_bounds' ? 'A geometria não possui dimensões válidas para enquadramento.' : 'O arquivo aprovado não pôde ser interpretado como um modelo GLB válido.');
      setStatus('failed');
    });

    return () => {
      disposed = true;
      removeResize();
      cancelAnimationFrame(frame);
      controls?.dispose?.();
      scene?.traverse?.((object: any) => {
        object.geometry?.dispose?.();
        const materials = Array.isArray(object.material) ? object.material : object.material ? [object.material] : [];
        materials.forEach((material: any) => {
          Object.values(material).forEach((value: any) => value?.isTexture && value.dispose?.());
          material.dispose?.();
        });
      });
      renderer?.dispose?.();
    };
  }, [eligible, src, editable, overrideKey]);

  async function submitCommand(event: FormEvent) {
    event.preventDefault();
    if (!onCommand || commandText.trim().length < 3) return;
    const reply = await onCommand(commandText, selectedId);
    setCommandReply(reply);
    setCommandText('');
  }

  async function undoLastEdit() {
    if (!onUndo) return;
    await onUndo();
    setCommandReply('Última alteração desfeita.');
  }

  if (!eligible) {
    return <div role="status" className={`grid place-items-center bg-slate-950 p-6 text-center text-slate-300 ${className ?? ''}`}><div><TriangleAlert className="mx-auto h-8 w-8 text-amber-300" /><p className="mt-3 font-black">Modelo bloqueado para visualização</p><p className="mt-1 max-w-lg text-sm text-slate-400">{diagnostic?.message ?? 'A elegibilidade deste arquivo ainda não foi confirmada.'}</p></div></div>;
  }

  return <div className={`relative bg-slate-950 ${className ?? ''}`}>
    <canvas ref={canvasRef} className="h-full w-full" role="img" aria-label="Visualizador 3D do imóvel" />
    {editable && status === 'ready' && <div className="absolute inset-x-3 bottom-3 rounded-2xl border border-white/15 bg-slate-950/90 p-3 text-slate-100 shadow-2xl backdrop-blur">
      <div className="flex flex-wrap items-center gap-2 text-xs"><span className="font-black text-cyan-300">{selectedId ? regionLabels[selectedId] ?? selectedId : 'Clique em uma superfície para selecionar'}</span>{selectedId && <>{[['#9bb7d4', 'Azul', 'paint'], ['#d9cbb8', 'Bege', 'paint'], ['#8b7355', 'Madeira', 'wood']].map(([color, label, material]) => <button key={label} type="button" disabled={busy} onClick={() => void onEdit?.(selectedId, color, material)} className="rounded-lg border border-white/15 px-2.5 py-1.5 font-bold hover:bg-white/10 disabled:opacity-40">{label}</button>)}</>}<button type="button" disabled={busy} onClick={() => void undoLastEdit()} className="ml-auto inline-flex items-center gap-1 rounded-lg border border-white/15 px-2.5 py-1.5 font-bold disabled:opacity-40"><RotateCcw className="h-3.5 w-3.5" /> Desfazer</button></div>
      {onCommand && <form onSubmit={submitCommand} className="mt-2 flex gap-2"><input value={commandText} onChange={event => setCommandText(event.target.value)} placeholder="Ex.: pinte esta parede de azul" className="min-w-0 flex-1 rounded-lg border border-white/15 bg-slate-900 px-3 py-2 text-xs outline-none focus:border-cyan-400" /><button disabled={busy || commandText.trim().length < 3} className="rounded-lg bg-cyan-500 px-3 text-slate-950 disabled:opacity-40" aria-label="Executar comando"><Send className="h-4 w-4" /></button></form>}
      {commandReply && <p className="mt-2 text-xs text-slate-300">{commandReply}</p>}
    </div>}
    {status === 'loading' && <div role="status" className="absolute inset-0 grid place-items-center bg-slate-950/75 text-sm font-bold text-slate-200"><span className="inline-flex items-center gap-2"><Loader2 className="h-5 w-5 animate-spin text-cyan-400" /> Carregando modelo validado...</span></div>}
    {status === 'failed' && <div role="alert" className="absolute inset-0 grid place-items-center bg-slate-950 p-6 text-center"><div><TriangleAlert className="mx-auto h-8 w-8 text-rose-300" /><p className="mt-3 font-black text-slate-100">Falha ao abrir o modelo</p><p className="mt-1 max-w-lg text-sm text-slate-400">{error}</p></div></div>}
  </div>;
}
