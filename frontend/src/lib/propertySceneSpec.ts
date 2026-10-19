export type SceneFidelity = 'real' | 'reconstructed' | 'parametric' | 'illustrative';
export type PropertyKind = 'apartment' | 'house' | 'commercial' | 'studio' | 'land';
export type RoomKind =
  | 'living'
  | 'kitchen'
  | 'bedroom'
  | 'suite'
  | 'bathroom'
  | 'laundry'
  | 'balcony'
  | 'garage'
  | 'corridor'
  | 'yard'
  | 'commercial';
export type ViewMode = 'exterior' | 'interior' | 'plan';

export type SceneRoom = {
  key: string;
  label: string;
  kind: RoomKind;
  x: number;
  z: number;
  w: number;
  d: number;
  h: number;
};

export type SceneOpening = {
  x: number;
  z: number;
  w: number;
  d: number;
  kind: 'door' | 'window';
  axis: 'x' | 'z';
};

export type PropertySceneSpec = {
  version: 1;
  fidelity: SceneFidelity;
  propertyKind: PropertyKind;
  title: string;
  areaM2: number;
  envelope: { width: number; depth: number; height: number; floors: number; targetFloor: number };
  lot: { width: number; depth: number };
  rooms: SceneRoom[];
  openings: SceneOpening[];
  flags: { balcony: boolean; yard: boolean; garage: boolean; suite: boolean; roof: boolean };
  disclaimer: string;
  fidelityLabel: string;
};

export type SceneInput = {
  title?: string | null;
  areaM2?: number | null;
  bedrooms?: number | null;
  bathrooms?: number | null;
  parkingSpaces?: number | null;
  suites?: number | null;
  propertyType?: string | null;
  notes?: string | null;
  hasGlb?: boolean;
  hasFloorplan?: boolean;
};

const INTERIOR_KINDS = new Set<RoomKind>(['living', 'kitchen', 'bedroom', 'suite', 'bathroom', 'laundry', 'corridor', 'commercial']);

export function fidelityLabel(fidelity: SceneFidelity): string {
  if (fidelity === 'real') return 'Modelo 3D enviado';
  if (fidelity === 'reconstructed') return 'Planta 2D + maquete paramétrica';
  if (fidelity === 'parametric') return 'Maquete paramétrica do anúncio';
  return 'Simulação ilustrativa';
}

export function resolvePropertyKind(propertyType?: string | null, title?: string | null, notes?: string | null): PropertyKind {
  const text = normalize(`${propertyType ?? ''} ${title ?? ''} ${notes ?? ''}`);
  if (/(terreno|\bland\b)/.test(text)) return 'land';
  if (/(studio|kitnet|conjugado)/.test(text)) return 'studio';
  if (/(casa|sobrado|\bhouse\b)/.test(text)) return 'house';
  if (/(comercial|loja|galpao|galpão|sala comercial|\bcommercial\b)/.test(text)) return 'commercial';
  return 'apartment';
}

export function interiorArea(spec: PropertySceneSpec): number {
  return spec.rooms
    .filter(room => INTERIOR_KINDS.has(room.kind))
    .reduce((sum, room) => sum + room.w * room.d, 0);
}

export function buildPropertySceneSpec(input: SceneInput): PropertySceneSpec {
  const title = (input.title || 'Imóvel').trim();
  const text = `${title} ${input.notes ?? ''}`;
  const kind = resolvePropertyKind(input.propertyType, title, input.notes);
  const hasArea = Number(input.areaM2) > 12;
  const hasRooms = Number(input.bedrooms) > 0 || Number(input.bathrooms) > 0 || kind === 'land' || kind === 'commercial';
  const fidelity: SceneFidelity = input.hasGlb
    ? 'real'
    : input.hasFloorplan
      ? 'reconstructed'
      : hasArea || hasRooms
        ? 'parametric'
        : 'illustrative';
  const areaM2 = hasArea ? clamp(Number(input.areaM2), 22, 480) : kind === 'land' ? 220 : 72;
  const bedrooms = kind === 'studio' || kind === 'land' || kind === 'commercial'
    ? 0
    : clamp(Math.round(Number(input.bedrooms) || (fidelity === 'illustrative' ? 2 : 1)), 1, 6);
  const bathrooms = kind === 'land'
    ? 0
    : clamp(Math.round(Number(input.bathrooms) || 1), kind === 'commercial' ? 1 : 1, 4);
  const parking = clamp(Math.round(Number(input.parkingSpaces) || 0), 0, 4);
  const suiteCount = Math.max(Number(input.suites) > 0 ? 1 : 0, /\bsuite|suíte/i.test(text) ? 1 : 0);
  const flags = {
    balcony: kind !== 'land' && /\bvaranda|sacada|terra[cç]o/i.test(text),
    yard: kind === 'house' || /\bquintal|jardim/i.test(text),
    garage: parking > 0,
    suite: suiteCount > 0 && bedrooms > 0,
    roof: kind === 'house',
  };
  const laidOut = layout(kind, areaM2, bedrooms, bathrooms, parking, flags);
  const openings = openingsFromRooms(laidOut.rooms);
  const disclaimer = input.hasGlb
    ? 'A navegação principal usa o arquivo GLB enviado. A maquete ao lado é só leitura dos metadados do anúncio.'
    : input.hasFloorplan
      ? 'Há planta 2D no registro. O volume 3D ainda é paramétrico, não uma extrusão cotada da imagem.'
      : fidelity === 'parametric'
        ? 'Maquete gerada da área, dos cômodos e do tipo do anúncio. Não é fotogrametria nem visita do imóvel real.'
        : 'Poucos dados no anúncio. Volume ilustrativo, identificado como simulação, sem fingir o imóvel fotografado.';

  return {
    version: 1,
    fidelity,
    propertyKind: kind,
    title,
    areaM2,
    envelope: laidOut.envelope,
    lot: laidOut.lot,
    rooms: laidOut.rooms,
    openings,
    flags,
    disclaimer,
    fidelityLabel: fidelityLabel(fidelity),
  };
}

function layout(
  kind: PropertyKind,
  areaM2: number,
  bedrooms: number,
  bathrooms: number,
  parking: number,
  flags: PropertySceneSpec['flags'],
) {
  const height = 2.72;
  if (kind === 'land') {
    const width = Math.sqrt(areaM2) * 1.35;
    const depth = areaM2 / width;
    return {
      envelope: { width, depth, height: 0.4, floors: 0, targetFloor: 0 },
      lot: { width: width + 6, depth: depth + 6 },
      rooms: [room('yard', 'Terreno', 'yard', -width / 2, -depth / 2, width, depth, 0.08)],
    };
  }

  if (kind === 'commercial') {
    const width = Math.sqrt(areaM2) * 1.4;
    const depth = areaM2 / width;
    const bathW = Math.min(2.4, width * 0.28);
    return {
      envelope: { width, depth, height, floors: 1, targetFloor: 0 },
      lot: { width: width + 4, depth: depth + 6 },
      rooms: [
        room('commercial', 'Salão', 'commercial', -width / 2, -depth / 2, width - bathW, depth, height),
        room('bathroom-1', 'Sanitário', 'bathroom', width / 2 - bathW, -depth / 2, bathW, Math.min(3.2, depth * 0.4), height),
      ],
    };
  }

  const aspect = kind === 'house' ? 1.46 : 1.22;
  const width = Math.sqrt(areaM2 * aspect);
  const depth = areaM2 / width;
  const ox = -width / 2;
  const oz = -depth / 2;
  const rooms: SceneRoom[] = [];
  const frontD = depth * (kind === 'studio' ? 0.62 : 0.4);
  const livingW = kind === 'studio' ? width * 0.7 : width * 0.63;
  const kitchenW = width - livingW;
  const laundryD = areaM2 > 88 ? Math.min(2.1, frontD * 0.38) : 0;
  rooms.push(room('living', 'Sala', 'living', ox, oz, livingW, frontD, height));
  rooms.push(room('kitchen', 'Cozinha', 'kitchen', ox + livingW, oz, kitchenW, frontD - laundryD, height));
  if (laundryD > 0) {
    rooms.push(room('laundry', 'Área de serviço', 'laundry', ox + livingW, oz + frontD - laundryD, kitchenW, laundryD, height));
  }

  if (kind === 'studio') {
    const bathW = Math.min(2.3, width * 0.32);
    rooms.push(room('bathroom-1', 'Banheiro', 'bathroom', width / 2 - bathW, oz + frontD, bathW, depth - frontD, height));
    rooms.push(room('corridor', 'Circulação', 'corridor', ox, oz + frontD, width - bathW, depth - frontD, height));
  } else {
    const corridorD = bedrooms + bathrooms > 2 ? 1.12 : 0.95;
    rooms.push(room('corridor', 'Circulação', 'corridor', ox, oz + frontD, width, corridorD, height));
    let remainZ = oz + frontD + corridorD;
    let remainD = depth - frontD - corridorD;
    if (remainD < 2.8) remainD = 2.8;
    const bathStrip = bathrooms > 0 ? Math.min(2.55 * Math.min(bathrooms, 2), width * 0.34) : 0;
    const bedW = (width - bathStrip) / Math.max(bedrooms, 1);
    for (let index = 0; index < bedrooms; index += 1) {
      const isSuite = flags.suite && index === bedrooms - 1;
      rooms.push(room(
        isSuite ? 'suite' : `bedroom-${index + 1}`,
        isSuite ? 'Suíte' : `Quarto ${index + 1}`,
        isSuite ? 'suite' : 'bedroom',
        ox + bedW * index,
        remainZ,
        bedW,
        remainD,
        height,
      ));
    }
    if (bathrooms > 0) {
      const bathD = remainD / bathrooms;
      for (let index = 0; index < bathrooms; index += 1) {
        rooms.push(room(
          `bathroom-${index + 1}`,
          bathrooms === 1 ? 'Banheiro' : `Banheiro ${index + 1}`,
          'bathroom',
          ox + width - bathStrip,
          remainZ + bathD * index,
          bathStrip,
          bathD,
          height,
        ));
      }
    }
  }

  if (flags.balcony) {
    const balW = Math.min(livingW * 0.82, width * 0.55);
    rooms.push(room('balcony', 'Varanda', 'balcony', ox + (livingW - balW) / 2, oz - 1.45, balW, 1.45, 1.1));
  }

  if (flags.garage) {
    const garageW = Math.min(width * 0.78, 3.2 * Math.max(parking, 1));
    const garageD = 5.1;
    if (kind === 'house') {
      rooms.push(room('garage', 'Garagem', 'garage', ox + width + 0.25, oz, garageW, Math.min(depth, garageD), 2.35));
    } else {
      rooms.push(room('garage', 'Garagem', 'garage', ox + (width - garageW) / 2, oz + depth + 0.25, garageW, garageD, 2.2));
    }
  }

  if (flags.yard && kind === 'house') {
    const yardD = Math.min(4.2, depth * 0.45);
    rooms.push(room('yard', 'Quintal', 'yard', ox, oz + depth + (flags.garage ? 0 : 0.2), width, yardD, 0.08));
  }

  const bounds = boundsOf(rooms);
  const envW = bounds.w;
  const envD = bounds.d;
  return {
    envelope: {
      width: envW,
      depth: envD,
      height,
      floors: kind === 'apartment' ? 5 : 1,
      targetFloor: kind === 'apartment' ? 2 : 0,
    },
    lot: { width: envW + (kind === 'house' ? 7 : 4.5), depth: envD + (kind === 'house' ? 8 : 5) },
    rooms,
  };
}

function openingsFromRooms(rooms: SceneRoom[]): SceneOpening[] {
  const openings: SceneOpening[] = [];
  for (let i = 0; i < rooms.length; i += 1) {
    for (let j = i + 1; j < rooms.length; j += 1) {
      const shared = sharedEdge(rooms[i], rooms[j]);
      if (!shared || shared.length < 1.05) continue;
      if (!canConnect(rooms[i], rooms[j])) continue;
      openings.push({
        x: shared.midX,
        z: shared.midZ,
        w: shared.axis === 'z' ? 0.92 : 0.14,
        d: shared.axis === 'x' ? 0.92 : 0.14,
        kind: 'door',
        axis: shared.axis,
      });
    }
  }
  for (const item of rooms) {
    if (!['living', 'kitchen', 'bedroom', 'suite', 'commercial'].includes(item.kind)) continue;
    openings.push({
      x: item.x + item.w / 2,
      z: item.z + 0.06,
      w: Math.min(1.4, item.w * 0.42),
      d: 0.08,
      kind: 'window',
      axis: 'x',
    });
  }
  return openings;
}

function canConnect(a: SceneRoom, b: SceneRoom): boolean {
  const pair = new Set([a.kind, b.kind]);
  if (pair.has('yard') && !pair.has('living') && !pair.has('kitchen')) return false;
  if (pair.has('garage') && !pair.has('corridor') && !pair.has('living') && !pair.has('kitchen')) return false;
  if (a.kind === 'balcony' || b.kind === 'balcony') return a.kind === 'living' || b.kind === 'living';
  return true;
}

function sharedEdge(a: SceneRoom, b: SceneRoom) {
  const eps = 0.12;
  const aR = a.x + a.w;
  const aB = a.z + a.d;
  const bR = b.x + b.w;
  const bB = b.z + b.d;
  if (Math.abs(aR - b.x) < eps || Math.abs(bR - a.x) < eps) {
    const z0 = Math.max(a.z, b.z);
    const z1 = Math.min(aB, bB);
    if (z1 - z0 >= 1) {
      return { axis: 'z' as const, midX: Math.abs(aR - b.x) < eps ? aR : a.x, midZ: (z0 + z1) / 2, length: z1 - z0 };
    }
  }
  if (Math.abs(aB - b.z) < eps || Math.abs(bB - a.z) < eps) {
    const x0 = Math.max(a.x, b.x);
    const x1 = Math.min(aR, bR);
    if (x1 - x0 >= 1) {
      return { axis: 'x' as const, midX: (x0 + x1) / 2, midZ: Math.abs(aB - b.z) < eps ? aB : a.z, length: x1 - x0 };
    }
  }
  return null;
}

function boundsOf(rooms: SceneRoom[]) {
  const minX = Math.min(...rooms.map(room => room.x));
  const minZ = Math.min(...rooms.map(room => room.z));
  const maxX = Math.max(...rooms.map(room => room.x + room.w));
  const maxZ = Math.max(...rooms.map(room => room.z + room.d));
  return { x: minX, z: minZ, w: maxX - minX, d: maxZ - minZ };
}

function room(key: string, label: string, kind: RoomKind, x: number, z: number, w: number, d: number, h: number): SceneRoom {
  return { key, label, kind, x, z, w: Math.max(w, 0.9), d: Math.max(d, 0.8), h };
}

function clamp(value: number, min: number, max: number) {
  return Math.min(max, Math.max(min, value));
}

function normalize(value: string) {
  return value.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase();
}
