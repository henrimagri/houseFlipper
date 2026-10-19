export type SceneObject = {
  id: string;
  kind: string;
  room: string;
  origin: string;
  position: number[];
  rotation: number;
  scale: number;
  color: string;
  hidden?: boolean;
};

export type SceneSurface = {
  id: string;
  kind: string;
  room: string;
  color: string;
  material: string;
};

export type SceneGraph = {
  version: number;
  fidelity: string;
  active_room: string;
  disclaimer: string;
  rooms: Array<{ key: string; label: string; confidence?: number; unknowns?: string[] }>;
  surfaces: SceneSurface[];
  objects: SceneObject[];
  photo_planes: Array<{ id: string; room: string; asset_id: number; wall: string }>;
  photo_assets?: Array<{ id: number; url: string; name: string; room_key?: string | null }>;
  exterior?: { kind: string; has_facade_photo: boolean; label: string };
  library?: string[];
  subject?: Record<string, unknown>;
};

export type VisualizationPayload = {
  status: string;
  level: string;
  quality_score: number | null;
  origin_label: string | null;
  limitations: string | null;
  faithful: boolean;
  illustrative: boolean;
  scene_status?: string | null;
  scene_brief?: {
    tipo?: string | null;
    area_anunciada_m2?: number | string | null;
    fachada_disponivel?: boolean;
    planta_disponivel?: boolean;
    ambientes?: Array<{ nome: string; rotulo?: string; imagens_referencia?: number[]; confianca_visual?: number }>;
    limitations?: string[];
  } | null;
  scene_graph?: SceneGraph | null;
  versions?: Array<{ id: number; label: string; created_at?: string }>;
  model: { id: number; kind: string; name: string; url: string } | null;
  gallery: Array<{ id: number; kind: string; name: string; room_key?: string | null }>;
  photo_analyses: Array<{
    id: number;
    source_name: string | null;
    room_guess: string | null;
    confidence: number;
    origin: string;
    quality: string;
    limitations: string[];
    palette?: Array<{ hex: string; share?: number }>;
  }>;
  rooms: Array<{ key: string; label: string; source: string; available: boolean }>;
  subject: {
    title: string;
    city?: string | null;
    neighborhood?: string | null;
    state?: string | null;
    area_m2?: number | string | null;
    bedrooms?: number | null;
    bathrooms?: number | null;
    parking_spaces?: number | null;
    property_type?: string | null;
    back_path: string;
  };
  product_features: { renovation_simulation: boolean; visualization_3d?: boolean };
};

export function visualizationModeLabel(payload: Pick<VisualizationPayload, 'faithful' | 'level'>): string {
  if (payload.faithful && payload.level === 'a_model') return 'Modelo 3D do imóvel';
  if (payload.level === 'photo_referenced') return 'Cena referenciada nas fotos';
  if (payload.level === 'parametric_spec') return 'Maquete paramétrica do anúncio';
  if (payload.level === 'reconstructed_floorplan') return 'Planta 2D + maquete paramétrica';
  if (payload.level === 'd_illustrative') return 'Simulação ilustrativa';
  return 'Pré-visualização sem modelo 3D confiável';
}

export function roomLabel(key: string | null | undefined): string {
  const labels: Record<string, string> = {
    living: 'Sala', bedroom: 'Quarto', bathroom: 'Banheiro', kitchen: 'Cozinha',
    balcony: 'Varanda', garage: 'Garagem', facade: 'Fachada', outdoor: 'Área externa',
  };
  return key ? (labels[key] ?? key) : 'Ambiente não identificado';
}

export function objectLabel(kind: string): string {
  const labels: Record<string, string> = {
    sofa: 'Sofá', bed: 'Cama', table: 'Mesa', chair: 'Cadeira', sink: 'Pia',
    wardrobe: 'Armário', window: 'Janela', door: 'Porta', lamp: 'Luminária',
    plant: 'Planta', partition: 'Divisória',
  };
  return labels[kind] ?? kind;
}

export function materialLabel(kind: string): string {
  const labels: Record<string, string> = {
    paint: 'Pintura', ceramic: 'Cerâmica', porcelain: 'Porcelanato',
    wood: 'Madeira', laminate: 'Laminado', marble: 'Mármore',
  };
  return labels[kind] ?? kind;
}
