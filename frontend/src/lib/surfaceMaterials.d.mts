import type * as Three from 'three';
export const SURFACE_TEXTURE_SIZE: number;
export type SurfaceMaterialOptions = {
  kind?: string;
  color?: string;
  finish?: 'matte' | 'satin' | 'semigloss' | 'gloss';
  /** Dimensions along the plane's local U and V axes, in meters. */
  width?: number;
  height?: number;
};
export function generateSurfaceMaps(options?: SurfaceMaterialOptions): {
  kind: string; size: number; baseColor: Uint8Array; bump: Uint8Array; roughness: Uint8Array;
  bumpScale: number; tileMeters: [number, number]; repeat: [number, number];
};
export function createSurfaceMaterial(THREE: typeof Three, options?: SurfaceMaterialOptions): Three.MeshStandardMaterial;
