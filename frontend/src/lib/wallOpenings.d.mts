import type { SceneOpening } from './propertyVisualization';
export const WALL_SIDES: Array<SceneOpening['side']>;
export function wallTransform(side: SceneOpening['side'], dimensions: { width: number; depth: number; height: number }): { length: number; height: number; position: number[]; rotationY: number };
export function openingBounds(opening: Pick<SceneOpening, 'width' | 'height' | 'offset' | 'sill'>): { left: number; right: number; bottom: number; top: number };
export function openingError(opening: Pick<SceneOpening, 'kind' | 'width' | 'height' | 'offset' | 'sill'> & { id?: string }, length: number, height: number, neighbors?: SceneOpening[]): string | null;
export function wallRectangles(length: number, height: number, openings?: SceneOpening[]): Array<{ x: number; y: number; width: number; height: number }>;
export function wallGeometryData(length: number, height: number, openings?: SceneOpening[]): { positions: number[]; uvs: number[] };
