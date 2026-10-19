import { useEffect, useMemo, useRef, useState } from 'react';
import { Box, Maximize2, RefreshCw } from 'lucide-react';
import { buildPropertySceneSpec, type PropertySceneSpec, type SceneInput, type SceneRoom, type ViewMode } from '@/lib/propertySceneSpec';

type Props = SceneInput & {
  className?: string;
  showChrome?: boolean;
  onFullscreen?: () => void;
};

const FLOOR: Record<string, number> = {
  living: 0xcbb79a,
  kitchen: 0xd7d2c8,
  bedroom: 0xb7c3b0,
  suite: 0xa9b7c6,
  bathroom: 0xd5e2e6,
  laundry: 0xcfd3c8,
  corridor: 0xe6dfd2,
  balcony: 0xc5d0c2,
  garage: 0x9aa3ad,
  yard: 0x6f8b5c,
  commercial: 0xd8d0c4,
};

export default function PropertyMaquetteViewer({
  className,
  showChrome = true,
  onFullscreen,
  ...input
}: Props) {
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const cameraApi = useRef<{ mode: (next: ViewMode) => void; room: (key: string) => void; reset: () => void } | null>(null);
  const [mode, setMode] = useState<ViewMode>('exterior');
  const [roomKey, setRoomKey] = useState('living');
  const spec = useMemo(() => buildPropertySceneSpec(input), [
    input.areaM2, input.bathrooms, input.bedrooms, input.hasFloorplan, input.hasGlb,
    input.notes, input.parkingSpaces, input.propertyType, input.suites, input.title,
  ]);

  useEffect(() => {
    const canvas = canvasRef.current;
    if (!canvas) return;
    let disposed = false;
    let frame = 0;
    let renderer: { dispose?: () => void; render: (scene: unknown, camera: unknown) => void; setSize: (w: number, h: number, update: boolean) => void; setPixelRatio: (ratio: number) => void; shadowMap?: { enabled: boolean } } | undefined;
    let scene: any;
    let camera: any;
    let controls: { update: () => void; dispose?: () => void; target: { copy: (v: any) => void }; enableDamping: boolean } | undefined;
    let THREE: any;
    const state = { mode, roomKey };

    (async () => {
      THREE = await import('three');
      const { OrbitControls } = await import('three/examples/jsm/controls/OrbitControls.js');
      if (disposed) return;

      scene = new THREE.Scene();
      scene.background = new THREE.Color(0xe8eef4);
      scene.fog = new THREE.Fog(0xe8eef4, 28, 90);
      camera = new THREE.PerspectiveCamera(46, canvas.clientWidth / Math.max(canvas.clientHeight, 1), 0.08, 220);
      const gl = new THREE.WebGLRenderer({ canvas, antialias: true });
      renderer = gl;
      gl.setPixelRatio(Math.min(window.devicePixelRatio, 2));
      gl.setSize(canvas.clientWidth, canvas.clientHeight, false);
      if (gl.shadowMap) gl.shadowMap.enabled = true;
      scene.add(new THREE.AmbientLight(0xf6f3ee, 0.62));
      const hemi = new THREE.HemisphereLight(0xf8fafc, 0x8a9a7a, 0.55);
      scene.add(hemi);
      const sun = new THREE.DirectionalLight(0xfff4e5, 1.2);
      sun.position.set(14, 18, 9);
      sun.castShadow = true;
      scene.add(sun);

      const root = buildScene(THREE, spec, state.mode);
      scene.add(root);

      const orbit = new OrbitControls(camera, canvas);
      controls = orbit;
      orbit.enableDamping = true;
      applyCamera(THREE, camera, orbit, spec, state.mode, state.roomKey);

      cameraApi.current = {
        mode(next) {
          state.mode = next;
          applyCamera(THREE, camera, controls, spec, next, state.roomKey);
        },
        room(key) {
          state.roomKey = key;
          applyCamera(THREE, camera, controls, spec, 'interior', key);
        },
        reset() {
          applyCamera(THREE, camera, controls, spec, state.mode, state.roomKey);
        },
      };

      const tick = () => {
        if (disposed) return;
        controls?.update();
        renderer?.render(scene, camera);
        frame = requestAnimationFrame(tick);
      };
      tick();
      const onResize = () => {
        if (!canvas || disposed) return;
        camera.aspect = canvas.clientWidth / Math.max(canvas.clientHeight, 1);
        camera.updateProjectionMatrix();
        renderer?.setSize(canvas.clientWidth, canvas.clientHeight, false);
      };
      window.addEventListener('resize', onResize);
    })().catch(() => undefined);

    return () => {
      disposed = true;
      cancelAnimationFrame(frame);
      cameraApi.current = null;
      controls?.dispose?.();
      renderer?.dispose?.();
    };
  }, [spec, mode]);

  useEffect(() => {
    if (mode === 'interior' || mode === 'plan') cameraApi.current?.room(roomKey);
  }, [roomKey, mode]);

  const interiorRooms = spec.rooms.filter(room => !['yard', 'garage'].includes(room.kind));

  return (
    <div className={`relative overflow-hidden bg-slate-200 dark:bg-slate-900 ${className ?? ''}`}>
      <canvas ref={canvasRef} className="block w-full h-full min-h-[320px]" role="img" aria-label={`Maquete 3D de ${spec.title}`} />
      {showChrome && (
        <>
          <div className="absolute top-3 left-3 right-3 flex flex-wrap items-center gap-2">
            {(['exterior', 'interior', 'plan'] as ViewMode[]).map(item => (
              <button
                key={item}
                type="button"
                onClick={() => setMode(item)}
                className={`rounded-full px-3 py-1.5 text-xs font-bold backdrop-blur ${mode === item ? 'bg-slate-900 text-white' : 'bg-white/85 text-slate-700'}`}
              >
                {item === 'exterior' ? 'Ver exterior' : item === 'interior' ? 'Ver interior' : 'Planta'}
              </button>
            ))}
            <span className="ml-auto inline-flex items-center gap-1 rounded-full bg-amber-100/95 text-amber-950 px-3 py-1.5 text-[11px] font-bold">
              <Box className="h-3.5 w-3.5" /> {spec.fidelityLabel}
            </span>
            <button type="button" onClick={() => cameraApi.current?.reset()} className="rounded-full bg-white/85 p-2 text-slate-700" aria-label="Resetar câmera">
              <RefreshCw className="h-4 w-4" />
            </button>
            {onFullscreen && (
              <button type="button" onClick={onFullscreen} className="rounded-full bg-white/85 p-2 text-slate-700" aria-label="Tela cheia">
                <Maximize2 className="h-4 w-4" />
              </button>
            )}
          </div>
          {(mode === 'interior' || mode === 'plan') && spec.propertyKind !== 'land' && (
            <div className="absolute bottom-12 left-3 right-3 flex gap-2 overflow-x-auto pb-1">
              {interiorRooms.map(room => (
                <button
                  key={room.key}
                  type="button"
                  onClick={() => { setMode('interior'); setRoomKey(room.key); }}
                  className={`shrink-0 rounded-full px-3 py-1.5 text-[11px] font-bold ${roomKey === room.key ? 'bg-slate-900 text-white' : 'bg-white/90 text-slate-700'}`}
                >
                  {room.label}
                </button>
              ))}
            </div>
          )}
          <p className="absolute bottom-3 left-3 right-3 text-[11px] text-slate-700 bg-white/80 rounded-lg px-2 py-1">{spec.disclaimer}</p>
        </>
      )}
    </div>
  );
}

function applyCamera(THREE: any, camera: any, controls: any, spec: PropertySceneSpec, mode: ViewMode, roomKey: string) {
  const center = new THREE.Vector3(0, spec.envelope.height * 0.45, 0);
  const size = Math.max(spec.lot.width, spec.lot.depth, 8);
  if (mode === 'plan') {
    camera.position.set(0, size * 1.15, 0.01);
    controls.target.copy(center);
    camera.near = 0.2;
    camera.far = size * 20;
    camera.updateProjectionMatrix();
    return;
  }
  if (mode === 'interior') {
    const room = spec.rooms.find(item => item.key === roomKey) ?? spec.rooms.find(item => item.kind === 'living') ?? spec.rooms[0];
    const eye = new THREE.Vector3(room.x + room.w * 0.28, 1.58, room.z + room.d * 0.28);
    const look = new THREE.Vector3(room.x + room.w * 0.72, 1.2, room.z + room.d * 0.78);
    camera.position.copy(eye);
    controls.target.copy(look);
    camera.near = 0.05;
    camera.far = 80;
    camera.updateProjectionMatrix();
    return;
  }
  camera.position.set(size * 0.38, size * 0.32, -size * 0.7);
  controls.target.copy(center);
  camera.near = size / 120;
  camera.far = size * 24;
  camera.updateProjectionMatrix();
}

function buildScene(THREE: any, spec: PropertySceneSpec, mode: ViewMode) {
  const root = new THREE.Group();
  const lot = new THREE.Mesh(
    new THREE.BoxGeometry(spec.lot.width, 0.08, spec.lot.depth),
    new THREE.MeshStandardMaterial({ color: spec.propertyKind === 'house' ? 0x7d9468 : 0xb7c0c9, roughness: 0.95 }),
  );
  lot.position.y = -0.04;
  lot.receiveShadow = true;
  root.add(lot);

  if (mode === 'exterior') {
    if (spec.propertyKind === 'apartment') addApartmentShell(THREE, root, spec);
    else if (spec.propertyKind === 'house') addHouseMass(THREE, root, spec);
    else if (spec.propertyKind === 'commercial') addCommercialShell(THREE, root, spec);
    else addLand(THREE, root, spec);
    for (const room of spec.rooms.filter(item => ['garage', 'yard', 'balcony'].includes(item.kind))) {
      addRoom(THREE, root, room, spec);
    }
    return root;
  }

  for (const room of spec.rooms) addRoom(THREE, root, room, spec);
  addWalls(THREE, root, spec);
  return root;
}

function addHouseMass(THREE: any, root: any, spec: PropertySceneSpec) {
  const w = spec.envelope.width;
  const d = Math.max(spec.envelope.depth * 0.78, 6);
  const h = spec.envelope.height;
  const body = mesh(THREE, w, h, d, 0xe8dfd2, 0, h / 2, 0);
  root.add(body);
  addHouseShell(THREE, root, spec);
  for (let i = -2; i <= 2; i += 1) {
    root.add(mesh(THREE, 1.1, 1.15, 0.05, 0x7dd3fc, i * 1.5, 1.5, -d / 2 + 0.04));
  }
  root.add(mesh(THREE, 1.05, 2.15, 0.08, 0x3f2f24, 0, 1.1, -d / 2 + 0.05));
}

function addLand(THREE: any, root: any, spec: PropertySceneSpec) {
  root.add(mesh(THREE, spec.envelope.width, 0.12, spec.envelope.depth, 0x8fa37a, 0, 0.06, 0));
}

function addRoom(THREE: any, root: any, room: SceneRoom, spec: PropertySceneSpec) {
  if (spec.propertyKind === 'apartment' && room.kind === 'garage') {
    const slab = mesh(THREE, room.w, 0.12, room.d, FLOOR[room.kind], room.x + room.w / 2, 0.06, room.z + room.d / 2);
    root.add(slab);
    addLabel(THREE, root, room);
    return;
  }
  const floor = mesh(THREE, room.w - 0.04, 0.07, room.d - 0.04, FLOOR[room.kind] ?? 0xd6d3d1, room.x + room.w / 2, 0.035, room.z + room.d / 2);
  root.add(floor);
  addLabel(THREE, root, room);
}

function addWalls(THREE: any, root: any, spec: PropertySceneSpec) {
  const wallMat = new THREE.MeshStandardMaterial({ color: 0xf4efe6, roughness: 0.82 });
  const glassMat = new THREE.MeshStandardMaterial({ color: 0x93c5fd, transparent: true, opacity: 0.38, roughness: 0.12, metalness: 0.15 });
  const thick = 0.12;
  const height = spec.envelope.height;
  for (const room of spec.rooms) {
    if (room.kind === 'yard') continue;
    const h = room.kind === 'balcony' || room.kind === 'garage' ? Math.max(room.h, 1.05) : height;
    const edges = [
      { x: room.x + room.w / 2, z: room.z, w: room.w, d: thick },
      { x: room.x + room.w / 2, z: room.z + room.d, w: room.w, d: thick },
      { x: room.x, z: room.z + room.d / 2, w: thick, d: room.d },
      { x: room.x + room.w, z: room.z + room.d / 2, w: thick, d: room.d },
    ];
    for (const edge of edges) {
      const door = spec.openings.find(item => item.kind === 'door' && Math.abs(item.x - edge.x) < 0.55 && Math.abs(item.z - edge.z) < 0.55);
      if (door) {
        const long = edge.w > edge.d;
        const span = long ? edge.w : edge.d;
        const gap = 0.92;
        const remain = Math.max((span - gap) / 2, 0.2);
        if (long) {
          root.add(mesh(THREE, remain, h, thick, 0xf4efe6, edge.x - gap / 2 - remain / 2, h / 2, edge.z, wallMat));
          root.add(mesh(THREE, remain, h, thick, 0xf4efe6, edge.x + gap / 2 + remain / 2, h / 2, edge.z, wallMat));
          root.add(mesh(THREE, gap, 0.45, thick, 0xf4efe6, edge.x, h - 0.22, edge.z, wallMat));
        } else {
          root.add(mesh(THREE, thick, h, remain, 0xf4efe6, edge.x, h / 2, edge.z - gap / 2 - remain / 2, wallMat));
          root.add(mesh(THREE, thick, h, remain, 0xf4efe6, edge.x, h / 2, edge.z + gap / 2 + remain / 2, wallMat));
          root.add(mesh(THREE, thick, 0.45, gap, 0xf4efe6, edge.x, h - 0.22, edge.z, wallMat));
        }
        continue;
      }
      root.add(mesh(THREE, edge.w, h, edge.d, 0xf4efe6, edge.x, h / 2, edge.z, wallMat));
    }
  }
  for (const opening of spec.openings.filter(item => item.kind === 'window')) {
    root.add(mesh(THREE, opening.w, 1.15, 0.05, 0x93c5fd, opening.x, 1.45, opening.z, glassMat));
  }
}

function addApartmentShell(THREE: any, root: any, spec: PropertySceneSpec) {
  const floors = spec.envelope.floors;
  const floorH = 3.05;
  const bodyW = spec.envelope.width * 0.92;
  const bodyD = Math.max(spec.envelope.depth * 0.55, 7);
  for (let index = 0; index < floors; index += 1) {
    const highlight = index === spec.envelope.targetFloor;
    const body = mesh(THREE, bodyW, floorH - 0.08, bodyD, highlight ? 0xf3e7d4 : 0xd4cfc6, 0, index * floorH + floorH / 2, bodyD * 0.12);
    body.castShadow = true;
    root.add(body);
    const frontZ = -bodyD / 2 + bodyD * 0.12;
    for (let col = -2; col <= 2; col += 1) {
      const pane = mesh(THREE, 1.05, 1.2, 0.05, highlight ? 0x7dd3fc : 0x64748b, col * 1.45, index * floorH + 1.55, frontZ - 0.03);
      (pane.material as { transparent?: boolean }).transparent = true;
      (pane.material as { opacity?: number }).opacity = highlight ? 0.62 : 0.38;
      root.add(pane);
    }
  }
  root.add(mesh(THREE, bodyW + 0.4, 0.18, bodyD + 0.4, 0x9aa3ad, 0, floors * floorH + 0.1, bodyD * 0.12));
}

function addHouseShell(THREE: any, root: any, spec: PropertySceneSpec) {
  const w = spec.envelope.width + 0.4;
  const d = spec.envelope.depth * 0.72;
  const ridge = spec.envelope.height + 1.35;
  const roofMat = new THREE.MeshStandardMaterial({ color: 0x5b6570, roughness: 0.78 });
  const left = mesh(THREE, w * 0.72, 0.14, d, 0x5b6570, -w * 0.12, ridge, 0, roofMat);
  left.rotation.z = 0.42;
  const right = mesh(THREE, w * 0.72, 0.14, d, 0x5b6570, w * 0.12, ridge, 0, roofMat);
  right.rotation.z = -0.42;
  root.add(left, right);
  root.add(mesh(THREE, 0.9, 2.15, 0.08, 0x3f2f24, 0, 1.1, -d / 2 + 0.06));
}

function addCommercialShell(THREE: any, root: any, spec: PropertySceneSpec) {
  const glass = new THREE.MeshStandardMaterial({ color: 0x7dd3fc, transparent: true, opacity: 0.42, roughness: 0.1 });
  root.add(mesh(THREE, spec.envelope.width * 0.9, 1.8, 0.06, 0x7dd3fc, 0, 1.2, -spec.envelope.depth / 2 + 0.1, glass));
}

function addLabel(THREE: any, root: any, room: SceneRoom) {
  const canvas = document.createElement('canvas');
  canvas.width = 512;
  canvas.height = 128;
  const ctx = canvas.getContext('2d');
  if (!ctx) return;
  ctx.fillStyle = 'rgba(15,23,42,0.78)';
  ctx.roundRect(16, 24, 480, 80, 28);
  ctx.fill();
  ctx.fillStyle = '#f8fafc';
  ctx.font = '600 42px Inter, system-ui, sans-serif';
  ctx.textAlign = 'center';
  ctx.textBaseline = 'middle';
  ctx.fillText(room.label, 256, 64);
  const texture = new THREE.CanvasTexture(canvas);
  const sprite = new THREE.Sprite(new THREE.SpriteMaterial({ map: texture, transparent: true, depthTest: false }));
  sprite.position.set(room.x + room.w / 2, 1.22, room.z + room.d / 2);
  sprite.scale.set(Math.min(room.w * 0.55, 1.7), 0.42, 1);
  root.add(sprite);
}

function mesh(THREE: any, w: number, h: number, d: number, color: number, x: number, y: number, z: number, material?: any) {
  const item = new THREE.Mesh(
    new THREE.BoxGeometry(Math.max(w, 0.04), Math.max(h, 0.04), Math.max(d, 0.04)),
    material ?? new THREE.MeshStandardMaterial({ color, roughness: 0.78, metalness: 0.04 }),
  );
  item.position.set(x, y, z);
  item.castShadow = true;
  item.receiveShadow = true;
  return item;
}
