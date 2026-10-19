import { FormEvent, useEffect, useMemo, useRef, useState } from 'react';
import { Maximize2, RotateCcw, Save } from 'lucide-react';
import { getAuthToken } from '@/lib/authToken';
import {
  materialLabel,
  objectLabel,
  roomLabel,
  type SceneGraph,
  type SceneObject,
  type VisualizationPayload,
} from '@/lib/propertyVisualization';

export type EditorMode = 'exterior' | 'interior' | 'floorplan' | 'decorate' | 'renovate';

type Action = Record<string, unknown> & { op: string };

type Props = {
  graph: SceneGraph;
  canRenovate: boolean;
  versions: NonNullable<VisualizationPayload['versions']>;
  busy?: boolean;
  commandHint?: string;
  onMutate: (actions: Action[]) => Promise<void>;
  onCommand: (message: string, selectedId: string | null) => Promise<string>;
  onSaveVersion: (label: string) => Promise<void>;
  onRestore: (versionId: number) => Promise<void>;
  onFullscreen?: () => void;
  className?: string;
};

const LIBRARY = ['sofa', 'bed', 'table', 'chair', 'wardrobe', 'lamp', 'plant'] as const;
const MATERIALS = ['ceramic', 'porcelain', 'wood', 'laminate', 'marble', 'paint'] as const;
const COLORS = ['#f4f1ea', '#d9cbb8', '#9bb7d4', '#c5c7cc', '#2f3338', '#9aaa8c', '#e3d39a', '#8b7355'];

export default function PropertyPhotoSceneEditor({
  graph, canRenovate, versions, busy, commandHint, onMutate, onCommand, onSaveVersion, onRestore, onFullscreen, className,
}: Props) {
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const api = useRef<{ select: (id: string | null) => void } | null>(null);
  const [mode, setMode] = useState<EditorMode>('interior');
  const [panel, setPanel] = useState<'ambientes' | 'objetos' | 'materiais' | 'cores' | 'projetos' | null>('ambientes');
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [message, setMessage] = useState('');
  const [reply, setReply] = useState('');
  const [undo, setUndo] = useState<Array<{ forward: Action[]; inverse: Action[] }>>([]);
  const [redo, setRedo] = useState<Array<{ forward: Action[]; inverse: Action[] }>>([]);
  const [versionLabel, setVersionLabel] = useState('versão 1');
  const signature = useMemo(() => JSON.stringify(graph), [graph]);

  const room = graph.active_room;
  const surfaces = graph.surfaces.filter(item => item.room === room);
  const objects = graph.objects.filter(item => item.room === room && !item.hidden);
  const selected = graph.objects.find(item => item.id === selectedId) ?? null;
  const floor = surfaces.find(item => item.kind === 'floor');
  const wall = surfaces.find(item => item.kind === 'wall');

  useEffect(() => {
    const canvas = canvasRef.current;
    if (!canvas) return;
    let disposed = false;
    let frame = 0;
    let renderer: any;
    let scene: any;
    let camera: any;
    let controls: any;
    let raycaster: any;
    let pointer = { x: 0, y: 0 };
    let dragging: { id: string; mesh: any } | null = null;
    const token = getAuthToken();
    const selectable: any[] = [];

    (async () => {
      const THREE = await import('three');
      const { OrbitControls } = await import('three/examples/jsm/controls/OrbitControls.js');
      if (disposed) return;
      scene = new THREE.Scene();
      scene.background = new THREE.Color(mode === 'exterior' ? 0xcfe4f4 : 0x1b1d22);
      camera = new THREE.PerspectiveCamera(48, canvas.clientWidth / Math.max(canvas.clientHeight, 1), 0.08, 120);
      renderer = new THREE.WebGLRenderer({ canvas, antialias: true });
      renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
      renderer.setSize(canvas.clientWidth, canvas.clientHeight, false);
      renderer.shadowMap.enabled = true;
      scene.add(new THREE.AmbientLight(0xf6f3ee, 0.7));
      const sun = new THREE.DirectionalLight(0xfff4e5, 1.15);
      sun.position.set(6, 10, 5);
      sun.castShadow = true;
      scene.add(sun);
      scene.add(new THREE.HemisphereLight(0xf8fafc, 0x8a9a7a, 0.4));
      controls = new OrbitControls(camera, canvas);
      controls.enableDamping = true;
      raycaster = new THREE.Raycaster();
      const root = new THREE.Group();
      scene.add(root);

      const textures = await loadTextures(THREE, graph, token);
      if (disposed) return;
      if (mode === 'exterior') buildExterior(THREE, root, graph, textures, selectable);
      else if (mode === 'floorplan') buildFloorplan(THREE, root, graph, selectable);
      else buildInterior(THREE, root, graph, textures, selectable);

      camera.position.set(mode === 'floorplan' ? 0.1 : 4.2, mode === 'floorplan' ? 9 : 2.4, mode === 'floorplan' ? 0.1 : 4.8);
      controls.target.set(0, mode === 'floorplan' ? 0 : 1.1, 0);
      if (mode === 'floorplan') {
        camera.up.set(0, 0, -1);
        camera.lookAt(0, 0, 0);
      }

      api.current = { select: (id) => highlight(selectable, id) };

      const plane = new THREE.Plane(new THREE.Vector3(0, 1, 0), 0);
      const hit = new THREE.Vector3();
      const onPointerDown = (event: PointerEvent) => {
        const rect = canvas.getBoundingClientRect();
        pointer.x = ((event.clientX - rect.left) / rect.width) * 2 - 1;
        pointer.y = -((event.clientY - rect.top) / rect.height) * 2 + 1;
        raycaster.setFromCamera(pointer, camera);
        const hits = raycaster.intersectObjects(selectable, true);
        const found = hits[0]?.object;
        const id = found?.userData?.id as string | undefined;
        setSelectedId(id ?? null);
        highlight(selectable, id ?? null);
        if (id && (mode === 'decorate' || mode === 'renovate') && found) {
          dragging = { id, mesh: found };
          controls.enabled = false;
        }
      };
      const onPointerMove = (event: PointerEvent) => {
        if (!dragging) return;
        const rect = canvas.getBoundingClientRect();
        pointer.x = ((event.clientX - rect.left) / rect.width) * 2 - 1;
        pointer.y = -((event.clientY - rect.top) / rect.height) * 2 + 1;
        raycaster.setFromCamera(pointer, camera);
        raycaster.ray.intersectPlane(plane, hit);
        if (!hit) return;
        dragging.mesh.position.x = Math.max(-2.1, Math.min(2.1, hit.x));
        dragging.mesh.position.z = Math.max(-1.6, Math.min(1.8, hit.z));
      };
      const onPointerUp = () => {
        if (dragging) {
          const mesh = dragging.mesh;
          const id = dragging.id;
          const position = [Number(mesh.position.x.toFixed(2)), 0, Number(mesh.position.z.toFixed(2))];
          void push([{ op: 'transform', id, position }]);
        }
        dragging = null;
        controls.enabled = true;
      };
      canvas.addEventListener('pointerdown', onPointerDown);
      window.addEventListener('pointermove', onPointerMove);
      window.addEventListener('pointerup', onPointerUp);

      const tick = () => {
        if (disposed) return;
        controls.update();
        renderer.render(scene, camera);
        frame = requestAnimationFrame(tick);
      };
      tick();
      const onResize = () => {
        if (!canvas || disposed) return;
        camera.aspect = canvas.clientWidth / Math.max(canvas.clientHeight, 1);
        camera.updateProjectionMatrix();
        renderer.setSize(canvas.clientWidth, canvas.clientHeight, false);
      };
      window.addEventListener('resize', onResize);
      (canvas as any)._cleanup = () => {
        canvas.removeEventListener('pointerdown', onPointerDown);
        window.removeEventListener('pointermove', onPointerMove);
        window.removeEventListener('pointerup', onPointerUp);
        window.removeEventListener('resize', onResize);
      };
    })().catch(() => undefined);

    return () => {
      disposed = true;
      cancelAnimationFrame(frame);
      (canvas as any)._cleanup?.();
      renderer?.dispose?.();
    };
  }, [signature, mode]);

  async function push(actions: Action[]) {
    const inverse = invertActions(actions, graph);
    if (inverse.length) setUndo(list => [{ forward: actions, inverse }, ...list].slice(0, 24));
    setRedo([]);
    await onMutate(actions);
  }

  async function undoLast() {
    const [next, ...rest] = undo;
    if (!next) return;
    setUndo(rest);
    setRedo(list => [next, ...list]);
    await onMutate(next.inverse);
  }

  async function redoLast() {
    const [next, ...rest] = redo;
    if (!next) return;
    setRedo(rest);
    setUndo(list => [next, ...list]);
    await onMutate(next.forward);
  }

  async function sendCommand(event: FormEvent) {
    event.preventDefault();
    if (message.trim().length < 3) return;
    const text = await onCommand(message.trim(), selectedId);
    setReply(text);
    setMessage('');
  }

  return (
    <div className={`relative bg-slate-950 text-slate-100 ${className ?? ''}`}>
      <div className="flex flex-wrap items-center gap-2 px-4 py-3 border-b border-white/10 text-xs">
        {(['exterior', 'interior', 'floorplan', 'decorate', 'renovate'] as EditorMode[]).map(item => (
          <button
            key={item}
            type="button"
            disabled={item === 'renovate' && !canRenovate}
            onClick={() => setMode(item)}
            className={`rounded-lg px-3 py-1.5 font-bold disabled:opacity-40 ${mode === item ? 'bg-white text-slate-900' : 'border border-white/15'}`}
          >
            {modeLabel(item)}
          </button>
        ))}
        <button type="button" disabled={!undo.length} onClick={() => void undoLast()} className="rounded-lg border border-white/15 px-2 py-1 disabled:opacity-40">Desfazer</button>
        <button type="button" disabled={!redo.length} onClick={() => void redoLast()} className="rounded-lg border border-white/15 px-2 py-1 disabled:opacity-40">Refazer</button>
        <span className="ml-auto text-[11px] text-amber-200 max-w-xl truncate">{graph.disclaimer}</span>
        <button type="button" onClick={onFullscreen} className="rounded-lg border border-white/15 p-1.5" aria-label="Tela cheia"><Maximize2 className="h-4 w-4" /></button>
      </div>
      <div className="grid lg:grid-cols-[1fr_280px]">
        <div className="relative">
          <canvas ref={canvasRef} className="w-full h-[min(68vh,700px)]" role="img" aria-label="Editor 3D do imóvel" />
          {mode === 'renovate' && (
            <p className="absolute left-4 bottom-20 max-w-md rounded-xl bg-black/70 px-3 py-2 text-xs text-amber-100">
              Reforma e remodelação é conceito visual. Alterações estruturais exigem avaliação profissional antes de obra real.
            </p>
          )}
          <form onSubmit={sendCommand} className="absolute inset-x-4 bottom-4">
            <div className="rounded-2xl bg-black/70 backdrop-blur border border-white/10 px-4 py-3 flex items-end gap-2">
              <div className="flex-1">
                <input
                  value={message}
                  onChange={event => setMessage(event.target.value)}
                  placeholder="Descreva o que deseja modificar neste ambiente..."
                  className="w-full bg-transparent text-sm outline-none placeholder:text-slate-400"
                />
                {reply && <p className="text-[11px] text-emerald-200 mt-1">{reply}</p>}
                {commandHint && !reply && <p className="text-[11px] text-slate-400 mt-1">{commandHint}</p>}
              </div>
              <button type="submit" disabled={busy || message.trim().length < 3} className="rounded-lg bg-white text-slate-900 px-3 py-1.5 text-xs font-bold disabled:opacity-40">Aplicar</button>
            </div>
          </form>
        </div>
        <aside className="border-l border-white/10 p-4 space-y-3 text-sm bg-slate-900/70">
          <div className="flex flex-wrap gap-1">
            {(['ambientes', 'objetos', 'materiais', 'cores', 'projetos'] as const).map(item => (
              <button key={item} type="button" onClick={() => setPanel(panel === item ? null : item)} className={`rounded-md px-2 py-1 text-[11px] font-bold ${panel === item ? 'bg-white text-slate-900' : 'border border-white/15'}`}>{item}</button>
            ))}
          </div>
          {panel === 'ambientes' && (
            <div className="space-y-2">
              {graph.rooms.map(item => (
                <button key={item.key} type="button" onClick={() => void push([{ op: 'set_active_room', room: item.key }])} className={`w-full text-left rounded-xl border px-3 py-2 ${item.key === room ? 'border-white bg-white/10' : 'border-white/10'}`}>
                  <p className="font-bold">{item.label}</p>
                  <p className="text-[11px] text-slate-400">confiança estimada {Math.round((item.confidence ?? 0) * 100)}%</p>
                </button>
              ))}
            </div>
          )}
          {panel === 'objetos' && (
            <div className="space-y-2">
              <div className="grid grid-cols-2 gap-1">
                {(graph.library ?? LIBRARY).filter(kind => !['window', 'door', 'partition'].includes(kind)).map(kind => (
                  <button key={kind} type="button" disabled={busy} onClick={() => void push([{ op: 'add_object', kind, room }])} className="rounded-lg border border-white/15 px-2 py-1.5 text-[11px] font-bold">{objectLabel(kind)}</button>
                ))}
              </div>
              {mode === 'renovate' && canRenovate && (
                <button type="button" onClick={() => void push([{ op: 'add_object', kind: 'partition', room }])} className="w-full rounded-lg border border-amber-300/40 px-2 py-1.5 text-[11px] font-bold">Adicionar divisória conceitual</button>
              )}
              <ul className="space-y-1 max-h-40 overflow-auto">
                {objects.map(item => (
                  <li key={item.id}>
                    <button type="button" onClick={() => setSelectedId(item.id)} className={`w-full text-left rounded-lg px-2 py-1 ${selectedId === item.id ? 'bg-white/15' : ''}`}>{objectLabel(item.kind)}</button>
                  </li>
                ))}
              </ul>
              {selected && (
                <div className="flex flex-wrap gap-1">
                  <button type="button" onClick={() => void push([{ op: 'duplicate', id: selected.id }])} className="rounded-md border border-white/15 px-2 py-1 text-[11px]">Duplicar</button>
                  <button type="button" onClick={() => void push([{ op: 'hide', id: selected.id, hidden: true }])} className="rounded-md border border-white/15 px-2 py-1 text-[11px]">Ocultar</button>
                  <button type="button" onClick={() => void push([{ op: 'remove_object', id: selected.id }])} className="rounded-md border border-white/15 px-2 py-1 text-[11px]">Remover</button>
                </div>
              )}
            </div>
          )}
          {panel === 'materiais' && (
            <div className="space-y-2">
              <p className="text-[11px] text-slate-400">Piso atual: {materialLabel(floor?.material ?? 'ceramic')}</p>
              {MATERIALS.map(material => (
                <button key={material} type="button" onClick={() => void push([{ op: 'paint_kind', kind: 'floor', material, color: materialColor(material), room }])} className="w-full rounded-lg border border-white/15 px-2 py-1.5 text-left">{materialLabel(material)}</button>
              ))}
            </div>
          )}
          {panel === 'cores' && (
            <div className="space-y-3">
              <p className="text-[11px] text-slate-400">Paredes</p>
              <div className="flex flex-wrap gap-1">
                {COLORS.map(color => (
                  <button key={color} type="button" aria-label={`Parede ${color}`} onClick={() => void push([{ op: 'paint_kind', kind: 'wall', color, room }])} className="h-7 w-7 rounded-full border border-white/30" style={{ background: color }} />
                ))}
              </div>
              <input type="color" value={wall?.color ?? '#d9cbb8'} onChange={event => void push([{ op: 'paint_kind', kind: 'wall', color: event.target.value, room }])} className="h-8 w-full bg-transparent" />
              {selected && (
                <>
                  <p className="text-[11px] text-slate-400">Móvel selecionado</p>
                  <input type="color" value={selected.color} onChange={event => void push([{ op: 'set_object_color', id: selected.id, color: event.target.value }])} className="h-8 w-full bg-transparent" />
                </>
              )}
            </div>
          )}
          {panel === 'projetos' && (
            <div className="space-y-2">
              <div className="flex gap-1">
                <input value={versionLabel} onChange={event => setVersionLabel(event.target.value)} className="flex-1 rounded-lg bg-black/40 border border-white/15 px-2 py-1 text-xs" />
                <button type="button" onClick={() => void onSaveVersion(versionLabel)} className="rounded-lg bg-white text-slate-900 px-2"><Save className="h-4 w-4" /></button>
              </div>
              <button type="button" onClick={() => undoOriginal(versions, onRestore)} className="w-full rounded-lg border border-white/15 px-2 py-1.5 text-left text-[11px]"><RotateCcw className="inline h-3 w-3 mr-1" /> Restaurar original</button>
              {versions.map(item => (
                <button key={item.id} type="button" onClick={() => void onRestore(item.id)} className="w-full rounded-lg border border-white/10 px-2 py-1.5 text-left text-[11px]">{item.label}</button>
              ))}
            </div>
          )}
        </aside>
      </div>
    </div>
  );
}

function modeLabel(mode: EditorMode): string {
  return { exterior: 'Exterior', interior: 'Interior', floorplan: 'Planta', decorate: 'Decorar', renovate: 'Reformar' }[mode];
}

function materialColor(material: string): string {
  return { porcelain: '#e8e6e1', wood: '#8b7355', laminate: '#b08968', marble: '#d8d2c8', paint: '#f4f1ea', ceramic: '#cbb79a' }[material] ?? '#cbb79a';
}

function invertActions(actions: Action[], graph: SceneGraph): Action[] {
  const inverted: Action[] = [];
  for (const action of actions) {
    if (action.op === 'paint_kind') {
      const surface = graph.surfaces.find(item => item.kind === action.kind && (!action.room || item.room === action.room));
      if (surface) inverted.push({ op: 'paint_kind', kind: action.kind, room: action.room, color: surface.color, material: surface.material });
    } else if (action.op === 'set_object_color') {
      const object = graph.objects.find(item => item.id === action.id);
      if (object) inverted.push({ op: 'set_object_color', id: object.id, color: object.color });
    } else if (action.op === 'transform') {
      const object = graph.objects.find(item => item.id === action.id);
      if (object) inverted.push({ op: 'transform', id: object.id, position: object.position, rotation: object.rotation, scale: object.scale });
    } else if (action.op === 'remove_object') {
      const object = graph.objects.find(item => item.id === action.id);
      if (object) inverted.push({ op: 'upsert_object', object });
    } else if (action.op === 'hide') {
      inverted.push({ op: 'hide', id: action.id, hidden: !(action.hidden as boolean) });
    } else if (action.op === 'set_active_room') {
      inverted.push({ op: 'set_active_room', room: graph.active_room });
    }
  }
  return inverted;
}

function undoOriginal(versions: NonNullable<VisualizationPayload['versions']>, restore: (id: number) => Promise<void>) {
  const original = versions.find(item => item.label === 'original');
  if (original) void restore(original.id);
}

function highlight(objects: any[], id: string | null) {
  for (const mesh of objects) {
    const material = mesh.material;
    if (material?.emissive) material.emissive.setHex(mesh.userData.id === id ? 0x335577 : 0x000000);
  }
}

async function loadTextures(THREE: any, graph: SceneGraph, token: string | null) {
  const map = new Map<number, any>();
  const assets = graph.photo_assets ?? [];
  await Promise.all(assets.map(async asset => {
    try {
      const blob = await fetch(asset.url, { headers: token ? { Authorization: `Bearer ${token}` } : {} }).then(response => {
        if (!response.ok) throw new Error('photo');
        return response.blob();
      });
      const url = URL.createObjectURL(blob);
      const texture = await new Promise((resolve, reject) => {
        new THREE.TextureLoader().load(url, resolve, undefined, reject);
      });
      (texture as any).colorSpace = THREE.SRGBColorSpace;
      map.set(asset.id, texture);
      URL.revokeObjectURL(url);
    } catch {
      // Keep the room palette when the photograph cannot be decoded.
    }
  }));
  return map;
}

function hex(THREE: any, value: string) {
  return new THREE.Color(value);
}

function buildInterior(THREE: any, root: any, graph: SceneGraph, textures: Map<number, any>, selectable: any[]) {
  const room = graph.active_room;
  const wall = graph.surfaces.find(item => item.room === room && item.kind === 'wall');
  const floor = graph.surfaces.find(item => item.room === room && item.kind === 'floor');
  const ceiling = graph.surfaces.find(item => item.room === room && item.kind === 'ceiling');
  const width = 5.2;
  const depth = 4.2;
  const height = 2.65;
  addBox(THREE, root, [width, 0.06, depth], [0, 0, 0], floor?.color ?? '#cbb79a', floorMaterial(floor?.material), false);
  addBox(THREE, root, [width, 0.04, depth], [0, height, 0], ceiling?.color ?? '#f4f1ea', { roughness: 0.9, metalness: 0 }, false);
  const wallMat = { roughness: 0.86, metalness: 0 };
  addBox(THREE, root, [width, height, 0.08], [0, height / 2, -depth / 2], wall?.color ?? '#d9cbb8', wallMat, false);
  addBox(THREE, root, [width, height, 0.08], [0, height / 2, depth / 2], wall?.color ?? '#d9cbb8', wallMat, false);
  addBox(THREE, root, [0.08, height, depth], [-width / 2, height / 2, 0], wall?.color ?? '#d9cbb8', wallMat, false);
  addBox(THREE, root, [0.08, height, depth], [width / 2, height / 2, 0], wall?.color ?? '#d9cbb8', wallMat, false);
  for (const plane of graph.photo_planes.filter(item => item.room === room)) {
    const texture = textures.get(plane.asset_id);
    const mesh = addBox(THREE, root, [3.4, 2.05, 0.02], [0, 1.25, -depth / 2 + 0.08], '#111111', { map: texture, roughness: 0.7, metalness: 0 }, false);
    mesh.userData.photo = true;
  }
  for (const object of graph.objects.filter(item => item.room === room && !item.hidden)) {
    const mesh = furniture(THREE, object);
    mesh.position.set(object.position[0] ?? 0, object.position[1] ?? 0, object.position[2] ?? 0);
    mesh.rotation.y = ((object.rotation ?? 0) * Math.PI) / 180;
    mesh.scale.setScalar(object.scale ?? 1);
    mesh.userData.id = object.id;
    mesh.traverse((child: any) => { child.userData.id = object.id; selectable.push(child); });
    root.add(mesh);
  }
}

function buildExterior(THREE: any, root: any, graph: SceneGraph, textures: Map<number, any>, selectable: any[]) {
  const type = String(graph.subject?.property_type ?? '');
  const conceptual = graph.exterior?.kind !== 'photo_referenced';
  const mass = addBox(THREE, root, type.includes('apto') || type.includes('apart') ? [6, 8, 4.5] : [7, 3.4, 5.4], [0, type.includes('apto') || type.includes('apart') ? 4 : 1.7, 0], conceptual ? '#c9b8a4' : '#d7c4ae', { roughness: 0.8 }, true);
  mass.userData.id = 'facade-mass';
  selectable.push(mass);
  if (!(type.includes('apto') || type.includes('apart'))) {
    addBox(THREE, root, [7.4, 0.16, 5.8], [0, 3.55, 0], '#6d4c3d', { roughness: 0.7 }, false).rotation.z = 0;
    const roof = new THREE.ConeGeometry(4.6, 1.4, 4);
    const roofMesh = new THREE.Mesh(roof, new THREE.MeshStandardMaterial({ color: 0x6d4c3d, roughness: 0.75 }));
    roofMesh.position.set(0, 4.2, 0);
    roofMesh.rotation.y = Math.PI / 4;
    root.add(roofMesh);
  }
  const facade = graph.photo_planes.find(item => item.room === 'facade') ?? graph.photo_planes[0];
  if (facade && graph.exterior?.has_facade_photo) {
    const texture = textures.get(facade.asset_id);
    addBox(THREE, root, [5.2, 3.1, 0.04], [0, 1.8, 2.3], '#111', { map: texture, roughness: 0.65 }, false);
  }
  addBox(THREE, root, [18, 0.04, 18], [0, 0, 0], '#8aa56a', { roughness: 1 }, false);
}

function buildFloorplan(THREE: any, root: any, graph: SceneGraph, selectable: any[]) {
  graph.rooms.forEach((room, index) => {
    const x = (index % 3) * 4.2 - 4.2;
    const z = Math.floor(index / 3) * 3.6 - 1.5;
    const slab = addBox(THREE, root, [3.6, 0.04, 3], [x, 0.02, z], '#efe7da', { roughness: 0.9 }, true);
    slab.userData.id = room.key;
    selectable.push(slab);
    const wall = graph.surfaces.find(item => item.room === room.key && item.kind === 'wall');
    addBox(THREE, root, [3.6, 0.18, 0.08], [x, 0.1, z - 1.46], wall?.color ?? '#d9cbb8', { roughness: 0.8 }, false);
    addBox(THREE, root, [3.6, 0.18, 0.08], [x, 0.1, z + 1.46], wall?.color ?? '#d9cbb8', { roughness: 0.8 }, false);
  });
}

function addBox(THREE: any, root: any, size: number[], position: number[], color: string, extras: Record<string, unknown>, selectable: boolean) {
  const mesh = new THREE.Mesh(
    new THREE.BoxGeometry(size[0], size[1], size[2]),
    new THREE.MeshStandardMaterial({ color: hex(THREE, color), ...extras }),
  );
  mesh.position.set(position[0], position[1], position[2]);
  mesh.castShadow = true;
  mesh.receiveShadow = true;
  root.add(mesh);
  if (selectable) mesh.userData.selectable = true;
  return mesh;
}

function floorMaterial(kind?: string) {
  if (kind === 'wood' || kind === 'laminate') return { roughness: 0.45, metalness: 0.05 };
  if (kind === 'marble' || kind === 'porcelain') return { roughness: 0.28, metalness: 0.08 };
  return { roughness: 0.7, metalness: 0 };
}

function furniture(THREE: any, object: SceneObject) {
  const group = new THREE.Group();
  const color = object.color;
  const add = (w: number, h: number, d: number, x: number, y: number, z: number) => {
    const mesh = new THREE.Mesh(new THREE.BoxGeometry(w, h, d), new THREE.MeshStandardMaterial({ color: hex(THREE, color), roughness: 0.7 }));
    mesh.position.set(x, y, z);
    mesh.castShadow = true;
    group.add(mesh);
    return mesh;
  };
  switch (object.kind) {
    case 'sofa':
      add(1.8, 0.38, 0.78, 0, 0.19, 0);
      add(1.8, 0.48, 0.16, 0, 0.5, -0.31);
      add(0.16, 0.42, 0.78, -0.82, 0.4, 0);
      add(0.16, 0.42, 0.78, 0.82, 0.4, 0);
      break;
    case 'bed':
      add(2.05, 0.28, 1.45, 0, 0.28, 0);
      add(2.05, 0.7, 0.12, 0, 0.55, -0.68);
      break;
    case 'table':
      add(1.15, 0.06, 0.7, 0, 0.72, 0);
      add(0.08, 0.7, 0.08, -0.48, 0.35, -0.26);
      add(0.08, 0.7, 0.08, 0.48, 0.35, -0.26);
      add(0.08, 0.7, 0.08, -0.48, 0.35, 0.26);
      add(0.08, 0.7, 0.08, 0.48, 0.35, 0.26);
      break;
    case 'chair':
      add(0.42, 0.08, 0.42, 0, 0.42, 0);
      add(0.42, 0.46, 0.08, 0, 0.67, -0.17);
      break;
    case 'sink':
      add(0.7, 0.12, 0.45, 0, 0.86, 0);
      add(0.7, 0.72, 0.45, 0, 0.36, 0);
      break;
    case 'wardrobe':
      add(1.1, 2.05, 0.5, 0, 1.02, 0);
      break;
    case 'window':
      add(1.3, 1.1, 0.06, 0, 1.4, 0);
      break;
    case 'door':
      add(0.86, 2.05, 0.08, 0, 1.02, 0);
      break;
    case 'lamp':
      add(0.18, 1.4, 0.18, 0, 0.7, 0);
      add(0.42, 0.08, 0.42, 0, 1.42, 0);
      break;
    case 'plant':
      add(0.28, 0.28, 0.28, 0, 0.14, 0);
      add(0.5, 0.5, 0.5, 0, 0.62, 0);
      break;
    case 'partition':
      add(0.12, 2.4, 2.2, 0, 1.2, 0);
      break;
    default:
      add(0.5, 0.5, 0.5, 0, 0.25, 0);
  }
  return group;
}
