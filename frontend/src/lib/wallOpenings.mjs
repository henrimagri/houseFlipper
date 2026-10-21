export const WALL_SIDES = ['south', 'north', 'west', 'east'];

export function wallTransform(side, dimensions) {
  const { width, depth, height } = dimensions;
  if (![width, depth, height].every(value => Number.isFinite(value) && value > 0)) throw new Error('Dimensões do ambiente inválidas.');
  const transforms = {
    south: { length: width, height, position: [0, height / 2, -depth / 2], rotationY: 0 },
    north: { length: width, height, position: [0, height / 2, depth / 2], rotationY: Math.PI },
    west: { length: depth, height, position: [-width / 2, height / 2, 0], rotationY: Math.PI / 2 },
    east: { length: depth, height, position: [width / 2, height / 2, 0], rotationY: -Math.PI / 2 },
  };
  if (!transforms[side]) throw new Error('Parede inválida.');
  return transforms[side];
}

export function openingBounds(opening) {
  return { left: opening.offset - opening.width / 2, right: opening.offset + opening.width / 2, bottom: opening.sill, top: opening.sill + opening.height };
}

export function openingError(opening, length, height, neighbors = []) {
  if (![opening.width, opening.height, opening.offset, opening.sill, length, height].every(Number.isFinite)) return 'Preencha todas as medidas com números válidos.';
  if (!['door', 'window'].includes(opening.kind)) return 'Escolha porta ou janela.';
  if (opening.width < 0.3 || opening.height < 0.3) return 'Largura e altura devem ser de pelo menos 0,30 m.';
  if (opening.kind === 'door' && opening.sill !== 0) return 'Portas devem começar no piso (base 0 m).';
  if (opening.kind === 'window' && opening.sill < 0.1) return 'Janelas precisam de base de pelo menos 0,10 m.';
  const bounds = openingBounds(opening);
  if (bounds.left < -length / 2 + 0.1 - 1e-8 || bounds.right > length / 2 - 0.1 + 1e-8 || bounds.top > height - 0.1 + 1e-8) return 'Mantenha ao menos 0,10 m de parede nas laterais e acima da abertura.';
  for (const neighbor of neighbors) {
    if (neighbor.id && neighbor.id === opening.id) continue;
    const other = openingBounds(neighbor);
    if (bounds.left < other.right + 0.05 - 1e-8 && bounds.right > other.left - 0.05 + 1e-8 && bounds.bottom < other.top + 0.05 - 1e-8 && bounds.top > other.bottom - 0.05 + 1e-8) return 'As aberturas precisam de ao menos 0,05 m de separação.';
  }
  return null;
}

// Each rectangular cell is entirely wall or entirely hole. No triangulation can bridge a doorway.
export function wallRectangles(length, height, openings = []) {
  if (![length, height].every(value => Number.isFinite(value) && value > 0)) throw new Error('Dimensões da parede inválidas.');
  const holes = openings.map(openingBounds);
  if (holes.some(hole => !Object.values(hole).every(Number.isFinite) || hole.left >= hole.right || hole.bottom >= hole.top || hole.left < -length / 2 || hole.right > length / 2 || hole.bottom < 0 || hole.top > height)) throw new Error('Abertura fora da parede.');
  const xs = [...new Set([-length / 2, length / 2, ...holes.flatMap(hole => [hole.left, hole.right])])].sort((a, b) => a - b);
  const ys = [...new Set([0, height, ...holes.flatMap(hole => [hole.bottom, hole.top])])].sort((a, b) => a - b);
  const rectangles = [];
  for (let x = 0; x < xs.length - 1; x++) {
    for (let y = 0; y < ys.length - 1; y++) {
      const centerX = (xs[x] + xs[x + 1]) / 2;
      const centerY = (ys[y] + ys[y + 1]) / 2;
      if (holes.some(hole => centerX > hole.left && centerX < hole.right && centerY > hole.bottom && centerY < hole.top)) continue;
      const width = xs[x + 1] - xs[x], cellHeight = ys[y + 1] - ys[y];
      if (width > 1e-8 && cellHeight > 1e-8) rectangles.push({ x: xs[x], y: ys[y], width, height: cellHeight });
    }
  }
  return rectangles;
}

export function wallGeometryData(length, height, openings = []) {
  const positions = [], uvs = [];
  for (const rect of wallRectangles(length, height, openings)) {
    const left = rect.x, right = left + rect.width, bottom = rect.y, top = bottom + rect.height;
    for (const [x, y] of [[left, bottom], [right, bottom], [right, top], [left, bottom], [right, top], [left, top]]) {
      positions.push(x, y - height / 2, 0);
      uvs.push((x + length / 2) / length, y / height);
    }
  }
  return { positions, uvs };
}
