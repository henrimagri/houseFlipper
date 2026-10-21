import test from 'node:test';
import assert from 'node:assert/strict';
import { appendStrokePoint, drawMask, hasMaskPixels, imagePoint, maskHistory, MAX_MASK_STROKES, MAX_STROKE_POINTS, validPhotoColor } from '../src/lib/photoMask.mjs';

const paint = { mode: 'paint', radius: 0.025, points: [{ x: 0.2, y: 0.3 }] };
const erase = { mode: 'erase', radius: 0.05, points: [{ x: 0.2, y: 0.3 }, { x: 0.6, y: 0.8 }] };

test('coordinates use the displayed photograph bounds without assuming a 4:3 ratio', () => {
  assert.deepEqual(imagePoint(340, 200, { left: 20, top: 20, width: 640, height: 360 }), { x: 0.5, y: 0.5 });
  assert.deepEqual(imagePoint(120, 350, { left: 20, top: 50, width: 200, height: 600 }), { x: 0.5, y: 0.5 });
  assert.deepEqual(imagePoint(0, 800, { left: 20, top: 50, width: 200, height: 600 }), { x: 0, y: 1 });
  assert.equal(imagePoint(1, 2, { left: 0, top: 0, width: 0, height: 0 }), null);
});

test('a drag is one undoable stroke; redo preserves paint/erase ordering', () => {
  let state = maskHistory({ strokes: [], redo: [] }, { type: 'commit', stroke: paint });
  state = maskHistory(state, { type: 'commit', stroke: erase });
  assert.deepEqual(state.strokes, [paint, erase]);
  state = maskHistory(state, { type: 'undo' });
  assert.deepEqual(state, { strokes: [paint], redo: [erase] });
  state = maskHistory(state, { type: 'redo' });
  assert.deepEqual(state, { strokes: [paint, erase], redo: [] });
});

test('new strokes clear redo; reset removes all selection from the previous photograph', () => {
  let state = maskHistory({ strokes: [paint, erase], redo: [] }, { type: 'undo' });
  state = maskHistory(state, { type: 'commit', stroke: paint });
  assert.deepEqual(state.redo, []);
  assert.deepEqual(maskHistory(state, { type: 'reset' }), { strokes: [], redo: [] });
});

test('restoring history clones strokes and clears redo rather than mixing photographs', () => {
  const saved = structuredClone([paint, erase]);
  const state = maskHistory({ strokes: [erase], redo: [paint] }, { type: 'restore', strokes: saved });
  saved[0].points[0].x = 0.9;
  assert.equal(state.strokes[0].points[0].x, 0.2);
  assert.deepEqual(state.redo, []);
});

test('payload limits preserve old strokes rather than silently dropping selection', () => {
  let state = { strokes: [], redo: [] };
  for (let i = 0; i < MAX_MASK_STROKES; i++) state = maskHistory(state, { type: 'commit', stroke: paint });
  assert.equal(state.strokes.length, 64);
  assert.equal(maskHistory(state, { type: 'commit', stroke: erase }), state);
  assert.equal(maskHistory(state, { type: 'commit', stroke: { ...erase, points: [] } }), state);
});

test('point recording is immutable, omits duplicates and stops at the contract limit', () => {
  let stroke = structuredClone(paint);
  assert.equal(appendStrokePoint(stroke, { x: 0.2, y: 0.3 }), stroke);
  for (let i = 1; i < MAX_STROKE_POINTS; i++) stroke = appendStrokePoint(stroke, { x: i / MAX_STROKE_POINTS, y: 0.4 });
  assert.equal(stroke.points.length, 256);
  assert.equal(paint.points.length, 1);
  assert.equal(appendStrokePoint(stroke, { x: 1, y: 1 }), stroke);
});

test('overlay rasterization uses round strokes, smaller-side radius and destructive erasing', () => {
  const calls = [];
  const context = Object.fromEntries(['clearRect', 'save', 'beginPath', 'arc', 'fill', 'moveTo', 'lineTo', 'stroke', 'restore'].map(name => [name, (...args) => calls.push([name, ...args])]));
  Object.defineProperty(context, 'globalCompositeOperation', { set: value => calls.push(['composite', value]) });
  drawMask(context, 800, 400, [paint, erase]);
  assert.deepEqual(calls.filter(c => c[0] === 'composite'), [['composite', 'source-over'], ['composite', 'destination-out']]);
  for (const [index, call] of calls.filter(c => c[0] === 'arc').entries()) {
    assert.ok(Math.abs(call[1] - 159.8) < 1e-10);
    assert.ok(Math.abs(call[2] - 119.7) < 1e-10);
    assert.equal(call[3], index ? 20 : 10);
  }
  const last = calls.find(c => c[0] === 'lineTo');
  assert.ok(Math.abs(last[1] - 479.4) < 1e-10);
  assert.ok(Math.abs(last[2] - 319.2) < 1e-10);
  assert.equal(context.lineCap, 'round');
  assert.equal(context.lineWidth, 40);
  assert.deepEqual(calls[0], ['clearRect', 0, 0, 800, 400]);
});

test('empty masks are detected using alpha, not stale RGB or existence of paint strokes', () => {
  assert.equal(hasMaskPixels(new Uint8ClampedArray([255, 255, 255, 0, 22, 211, 238, 0])), false);
  assert.equal(hasMaskPixels(new Uint8ClampedArray([0, 0, 0, 0, 22, 211, 238, 255])), true);
  assert.equal(hasMaskPixels(new Uint8ClampedArray()), false);
});

test('only complete six-digit hexadecimal colors permit applying a simulation', () => {
  for (const color of ['#aabbcc', '#ABCDEF', '#000000']) assert.equal(validPhotoColor(color), true);
  for (const color of ['', '#', '#abc', '#gggggg', '#1234567', 'red', '#123456\n']) assert.equal(validPhotoColor(color), false);
});
