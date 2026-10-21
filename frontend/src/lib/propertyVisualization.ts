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
  side?: string;
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
  lighting?: { preset: string; ambient_intensity: number; directional_intensity: number; color: string };
  room_dimensions?: Record<string, { width: number; depth: number; height: number; source: 'estimated' | 'user' }>;
  photo_planes: Array<{ id: string; room: string; asset_id: number; wall: string }>;
  photo_assets?: Array<{ id: number; url: string; name: string; room_key?: string | null }>;
  exterior?: { kind: string; has_facade_photo: boolean; label: string };
  library?: string[];
  subject?: Record<string, unknown>;
  reconstruction_overrides?: Record<string, { color?: string; material?: string; source?: string }>;
  reconstruction_history?: Array<{ id: string; previous?: Record<string, unknown> | null }>;
};

export type VisualizationAsset = {
  id: number;
  kind: string;
  name: string;
  room_key?: string | null;
  url: string;
};

export type PhotoEditPoint = { x: number; y: number; label: 0 | 1 };

export type PhotoEditRequest = {
  asset_id: number;
  color: string;
  opacity: number;
  material: 'paint' | 'wood';
  points: PhotoEditPoint[];
};

export type PhotoEditSimulation = {
  id: string;
  status: 'processing' | 'completed' | 'failed';
  source_asset_id: number;
  source_url?: string | null;
  result_url?: string | null;
  comparison_url?: string | null;
  created_at: string;
  label: string;
  color: string;
  opacity: number;
  material: 'paint' | 'wood';
  points: PhotoEditPoint[];
};

export type ModelViewerDiagnostic = {
  code?: string;
  message: string;
  checks?: Array<{ label: string; status: 'passed' | 'failed' | 'pending'; detail?: string }>;
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
  model: {
    id: number;
    kind: string;
    name: string;
    url: string;
    fidelity?: string;
    viewer_eligible?: boolean;
    viewer_diagnostic?: ModelViewerDiagnostic | null;
    diagnostic_preview?: boolean;
  } | null;
  diagnostic_models?: Array<{ id: number; name: string; active: boolean }>;
  gallery: VisualizationAsset[];
  photo_editing?: { simulations: PhotoEditSimulation[] } | null;
  processing_jobs?: Array<{
    id: number;
    worker_job_id?: string | null;
    kind: string;
    status: string;
    result?: Record<string, unknown> | null;
    error?: string | null;
    created_at?: string;
    completed_at?: string | null;
  }>;
  photo_analyses: Array<{
    id: number;
    source_name: string | null;
    room_guess: string | null;
    confidence: number | null;
    detection_confidence?: number | null;
    room_confidence?: number | null;
    room_provenance?: Record<string, unknown> | null;
    origin: string;
    quality: string;
    limitations: string[];
    elements?: Array<{ label?: string; confidence?: number; box_xyxy?: number[] }>;
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
  if (payload.level === 'photogrammetry_preview') return 'Prévia fotogramétrica esparsa';
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
