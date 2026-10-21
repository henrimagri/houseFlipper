export type FurnitureCatalogEntry = {
  id: string;
  kind: string;
  label: string;
  dimensionsM: [number, number, number];
  materials: string[];
  anchor: 'floor' | 'wall' | 'ceiling';
  assetUrl?: string;
  license: string;
  attribution: string;
  sourceUrl?: string;
};

export const FURNITURE_CATALOG: FurnitureCatalogEntry[] = [
  {
    id: 'khronos-sheen-sofa', kind: 'sofa', label: 'Sofá madeira e tecido', dimensionsM: [2.1, 0.95, 0.9],
    materials: ['madeira', 'couro', 'tecido PBR'], anchor: 'floor', assetUrl: '/models/furniture/sheen-wood-leather-sofa.glb',
    license: 'CC BY 4.0 + CC0', attribution: 'Darmstadt Graphics Group GmbH; Fran Calvente; melhorias de Eric Chadwick',
    sourceUrl: 'https://github.com/KhronosGroup/glTF-Sample-Assets/tree/main/Models/SheenWoodLeatherSofa',
  },
  {
    id: 'khronos-sheen-chair', kind: 'chair', label: 'Cadeira de madeira e veludo', dimensionsM: [0.62, 0.94, 0.68],
    materials: ['madeira', 'veludo PBR', 'metal'], anchor: 'floor', assetUrl: '/models/furniture/sheen-chair.glb',
    license: 'CC0-1.0', attribution: 'Wayfair, LLC; Eric Chadwick',
    sourceUrl: 'https://github.com/KhronosGroup/glTF-Sample-Assets/tree/main/Models/SheenChair',
  },
  { id: 'flipcontrol-table', kind: 'table', label: 'Mesa contemporânea', dimensionsM: [1.3, 0.76, 0.8], materials: ['madeira', 'metal'], anchor: 'floor', license: 'Projeto original', attribution: 'FlipControl' },
  { id: 'flipcontrol-bed', kind: 'bed', label: 'Cama casal', dimensionsM: [2.05, 0.75, 1.48], materials: ['tecido', 'madeira'], anchor: 'floor', license: 'Projeto original', attribution: 'FlipControl' },
  { id: 'flipcontrol-wardrobe', kind: 'wardrobe', label: 'Armário', dimensionsM: [1.2, 2.15, 0.55], materials: ['madeira'], anchor: 'floor', license: 'Projeto original', attribution: 'FlipControl' },
  { id: 'flipcontrol-lamp', kind: 'lamp', label: 'Luminária de piso', dimensionsM: [0.45, 1.55, 0.45], materials: ['metal', 'tecido'], anchor: 'floor', license: 'Projeto original', attribution: 'FlipControl' },
  { id: 'flipcontrol-plant', kind: 'plant', label: 'Planta decorativa', dimensionsM: [0.65, 1.05, 0.65], materials: ['cerâmica', 'folhagem'], anchor: 'floor', license: 'Projeto original', attribution: 'FlipControl' },
  { id: 'flipcontrol-sink', kind: 'sink', label: 'Bancada simplificada', dimensionsM: [0.7, 0.92, 0.45], materials: ['acabamento liso'], anchor: 'floor', license: 'Projeto original', attribution: 'FlipControl' },
  { id: 'flipcontrol-window', kind: 'window', label: 'Janela ampla', dimensionsM: [1.8, 1.25, 0.1], materials: ['vidro', 'alumínio'], anchor: 'wall', license: 'Projeto original', attribution: 'FlipControl' },
  { id: 'flipcontrol-door', kind: 'door', label: 'Porta', dimensionsM: [0.9, 2.1, 0.12], materials: ['madeira'], anchor: 'wall', license: 'Projeto original', attribution: 'FlipControl' },
];

export function catalogEntry(kind: string) {
  return FURNITURE_CATALOG.find(item => item.kind === kind);
}
