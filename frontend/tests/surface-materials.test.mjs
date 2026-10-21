import test from 'node:test';
import assert from 'node:assert/strict';
import * as THREE from 'three';
import { generateSurfaceMaps, createSurfaceMaterial } from '../src/lib/surfaceMaterials.mjs';

const kinds = ['wood', 'laminate', 'ceramic', 'porcelain', 'marble', 'paint'];
for (const kind of kinds) {
  test(`${kind}: bounded deterministic procedural maps and dielectric material`, () => {
    const options = { kind, color: '#af906d', width: 5.2, height: 4.2 };
    const maps = generateSurfaceMaps(options);
    const repeated = generateSurfaceMaps(options);
    assert.ok(maps.size <= 256);
    for (const channel of ['baseColor', 'bump', 'roughness']) {
      assert.deepEqual(maps[channel], repeated[channel]);
      assert.equal(maps[channel].length, maps.size ** 2 * 4);
      assert.ok(new Set(maps[channel].filter((_, i) => i % 4 === 0)).size > 2, channel);
      for (let i = 0; i < maps[channel].length; i += 4) assert.equal(maps[channel][i + 3], 255);
    }
    const material = createSurfaceMaterial(THREE, options);
    assert.ok(material.isMeshStandardMaterial);
    assert.equal(material.metalness, 0);
    assert.equal(material.roughness, 1);
    assert.equal(material.map.colorSpace, THREE.SRGBColorSpace);
    assert.equal(material.bumpMap.colorSpace, THREE.NoColorSpace);
    assert.equal(material.roughnessMap.colorSpace, THREE.NoColorSpace);
    assert.equal(material.userData.photographic, false);
    assert.ok(material.bumpScale > 0 && material.bumpScale < 0.005);
    const textures = [material.map, material.bumpMap, material.roughnessMap];
    assert.equal(new Set(textures).size, 3);
    for (const texture of textures) {
      assert.deepEqual(texture.repeat.toArray(), maps.repeat);
      assert.equal(texture.wrapS, THREE.RepeatWrapping);
      let disposed = false;
      texture.addEventListener('dispose', () => { disposed = true; });
      texture.dispose();
      assert.ok(disposed);
    }
    material.dispose();
  });
}

test('repeat uses meters: porcelain keeps 60cm tiles on differently sized surfaces', () => {
  const floor = generateSurfaceMaps({ kind: 'porcelain', width: 6, height: 3 });
  const wall = generateSurfaceMaps({ kind: 'porcelain', width: 3, height: 2.4 });
  assert.deepEqual(floor.repeat, [10, 5]);
  assert.deepEqual(wall.repeat, [5, 4]);
  assert.deepEqual(floor.baseColor, wall.baseColor);
});

test('finishes change roughness, preserve base color and relief, remain nonmetallic', () => {
  const finishes = ['matte', 'satin', 'semigloss', 'gloss'].map(finish => generateSurfaceMaps({ kind: 'paint', finish }));
  for (let i = 1; i < finishes.length; i++) {
    assert.deepEqual(finishes[i].baseColor, finishes[0].baseColor);
    assert.deepEqual(finishes[i].bump, finishes[0].bump);
    assert.ok(finishes[i].roughness[0] < finishes[i - 1].roughness[0]);
  }
  for (const maps of finishes) for (let i = 0; i < maps.roughness.length; i += 4) {
    assert.ok(maps.roughness[i] >= 30 && maps.roughness[i] <= 250);
  }
});

test('invalid dimensions/color/finish fail before allocating renderer resources', () => {
  for (const width of [0, -1, Infinity, NaN, 1001, '3']) assert.throws(() => generateSurfaceMaps({ width }));
  for (const color of ['red', '#abc', '#gggggg', null]) assert.throws(() => generateSurfaceMaps({ color }));
  assert.throws(() => generateSurfaceMaps({ finish: 'metal' }));
  assert.equal(generateSurfaceMaps({ kind: 'unknown' }).kind, 'paint');
});

test('maps belong to individual materials and black/white colors stay within byte range', () => {
  const a = createSurfaceMaterial(THREE, { color: '#000000' });
  const b = createSurfaceMaterial(THREE, { color: '#ffffff' });
  assert.notEqual(a.map, b.map);
  assert.notEqual(a.map.image.data, b.map.image.data);
  assert.ok(a.map.image.data.every((value, i) => value === (i % 4 === 3 ? 255 : 0)));
  for (const material of [a, b]) {
    material.map.dispose(); material.bumpMap.dispose(); material.roughnessMap.dispose(); material.dispose();
  }
});
