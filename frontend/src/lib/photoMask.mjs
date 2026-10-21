export const MAX_MASK_STROKES = 64;
export const MAX_STROKE_POINTS = 256;

// The rect must be the displayed image itself, not a letterboxed container.
export function imagePoint(clientX, clientY, rect) {
  if (rect.width <= 0 || rect.height <= 0) return null;
  return { x: Math.max(0, Math.min(1, (clientX - rect.left) / rect.width)), y: Math.max(0, Math.min(1, (clientY - rect.top) / rect.height)) };
}

export function maskHistory(state, action) {
  if (action.type === 'reset') return { strokes: [], redo: [] };
  if (action.type === 'restore') return { strokes: structuredClone(action.strokes), redo: [] };
  if (action.type === 'commit') {
    if (state.strokes.length >= MAX_MASK_STROKES || !action.stroke.points.length) return state;
    return { strokes: [...state.strokes, structuredClone(action.stroke)], redo: [] };
  }
  if (action.type === 'undo' && state.strokes.length) return { strokes: state.strokes.slice(0, -1), redo: [...state.redo, state.strokes.at(-1)] };
  if (action.type === 'redo' && state.redo.length) return { strokes: [...state.strokes, state.redo.at(-1)], redo: state.redo.slice(0, -1) };
  return state;
}

export function appendStrokePoint(stroke, point) {
  if (stroke.points.length >= MAX_STROKE_POINTS) return stroke;
  const last = stroke.points.at(-1);
  if (last && last.x === point.x && last.y === point.y) return stroke;
  return { ...stroke, points: [...stroke.points, point] };
}

// Radius is relative to the smaller side, identical to the worker contract.
export function drawMask(context, width, height, strokes) {
  context.clearRect(0, 0, width, height);
  context.save();
  for (const stroke of strokes) {
    if (!stroke.points.length) continue;
    const radius = Math.max(0.5, stroke.radius * Math.min(width, height));
    context.globalCompositeOperation = stroke.mode === 'erase' ? 'destination-out' : 'source-over';
    context.fillStyle = '#22d3ee';
    context.strokeStyle = '#22d3ee';
    context.lineWidth = radius * 2;
    context.lineCap = 'round';
    context.lineJoin = 'round';
    const first = stroke.points[0];
    context.beginPath();
    context.arc(first.x * (width - 1), first.y * (height - 1), radius, 0, Math.PI * 2);
    context.fill();
    if (stroke.points.length > 1) {
      context.beginPath();
      context.moveTo(first.x * (width - 1), first.y * (height - 1));
      for (const point of stroke.points.slice(1)) context.lineTo(point.x * (width - 1), point.y * (height - 1));
      context.stroke();
    }
  }
  context.restore();
}

export function hasMaskPixels(pixels) {
  for (let i = 3; i < pixels.length; i += 4) if (pixels[i] > 0) return true;
  return false;
}

export function validPhotoColor(color) { return /^#[0-9a-f]{6}$/i.test(color); }
