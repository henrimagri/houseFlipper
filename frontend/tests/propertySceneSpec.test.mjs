import test from 'node:test';
import assert from 'node:assert/strict';
import { buildPropertySceneSpec, interiorArea, resolvePropertyKind } from '../src/lib/propertySceneSpec.ts';

test('apartment spec names rooms and stays near advertised area', () => {
  const spec = buildPropertySceneSpec({
    title: 'Apto Aldeota com varanda',
    areaM2: 86,
    bedrooms: 2,
    bathrooms: 2,
    parkingSpaces: 2,
    propertyType: 'apartment',
  });
  assert.equal(spec.propertyKind, 'apartment');
  assert.equal(spec.fidelity, 'parametric');
  assert.ok(spec.rooms.some(room => room.kind === 'living' && room.label === 'Sala'));
  assert.ok(spec.rooms.some(room => room.kind === 'kitchen'));
  assert.equal(spec.rooms.filter(room => room.kind === 'bedroom' || room.kind === 'suite').length, 2);
  assert.ok(spec.rooms.some(room => room.kind === 'garage'));
  assert.ok(spec.rooms.some(room => room.kind === 'balcony'));
  assert.equal(spec.fidelityLabel, 'Maquete paramétrica do anúncio');
  const area = interiorArea(spec);
  assert.ok(area > 86 * 0.7 && area < 86 * 1.35, `interior ${area}`);
  assert.ok(spec.rooms.every(room => room.label.length > 1));
});

test('house gets roof flag and yard; land has no interior', () => {
  const house = buildPropertySceneSpec({ title: 'Casa com quintal', areaM2: 140, bedrooms: 3, bathrooms: 2, parkingSpaces: 1, propertyType: 'house' });
  assert.equal(house.propertyKind, 'house');
  assert.equal(house.flags.roof, true);
  assert.ok(house.rooms.some(room => room.kind === 'yard'));
  const land = buildPropertySceneSpec({ title: 'Terreno centro', areaM2: 300, propertyType: 'land' });
  assert.equal(land.propertyKind, 'land');
  assert.equal(land.rooms.every(room => room.kind === 'yard'), true);
  assert.equal(resolvePropertyKind('apartamento', 'Cobertura'), 'apartment');
});

test('sparse listing is labelled illustrative and never silent', () => {
  const spec = buildPropertySceneSpec({ title: 'Imóvel' });
  assert.equal(spec.fidelity, 'illustrative');
  assert.match(spec.disclaimer, /ilustrativ/i);
});
