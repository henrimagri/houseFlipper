import test from 'node:test';
import assert from 'node:assert/strict';
import { BufferGeometry, Float32BufferAttribute, Mesh, MeshBasicMaterial, Raycaster, Vector3 } from 'three';
import { openingBounds, openingError, wallGeometryData, wallRectangles, wallTransform, WALL_SIDES } from '../src/lib/wallOpenings.mjs';

const windowOpening = { id: 'window-1', room: 'living', side: 'south', kind: 'window', width: 1.2, height: 1, offset: 0.8, sill: 0.9, origin: 'user' };
const doorOpening = { id: 'door-1', room: 'living', side: 'south', kind: 'door', width: 0.9, height: 2.1, offset: -1.1, sill: 0, origin: 'user' };
const near = (actual, expected, tolerance = 1e-9) => assert.ok(Math.abs(actual - expected) < tolerance, `${actual} differs from ${expected}`);

test('a wall with a door and a window has exactly the wall area minus both apertures', () => {
  const rectangles = wallRectangles(5, 2.7, [windowOpening, doorOpening]);
  const area = rectangles.reduce((sum, rect) => sum + rect.width * rect.height, 0);
  near(area, 5 * 2.7 - 1.2 * 1 - 0.9 * 2.1);
  const geometry = wallGeometryData(5, 2.7, [windowOpening, doorOpening]);
  let triangleArea = 0;
  for (let i = 0; i < geometry.positions.length; i += 9) {
    const a = new Vector3().fromArray(geometry.positions, i);
    const b = new Vector3().fromArray(geometry.positions, i + 3);
    const c = new Vector3().fromArray(geometry.positions, i + 6);
    const normal = b.clone().sub(a).cross(c.clone().sub(a));
    assert.ok(normal.z > 0, 'wall winding must face into the room');
    triangleArea += normal.length() / 2;
  }
  near(triangleArea, area);
});

test('no triangle overlaps a hole, including a doorway touching the floor', () => {
  const holes = [windowOpening, doorOpening].map(openingBounds);
  const { positions } = wallGeometryData(5, 2.7, [windowOpening, doorOpening]);
  for (let i = 0; i < positions.length; i += 9) {
    const xs = [positions[i], positions[i + 3], positions[i + 6]];
    const ys = [positions[i + 1], positions[i + 4], positions[i + 7]].map(y => y + 2.7 / 2);
    for (const hole of holes) {
      const overlapWidth = Math.min(Math.max(...xs), hole.right) - Math.max(Math.min(...xs), hole.left);
      const overlapHeight = Math.min(Math.max(...ys), hole.top) - Math.max(Math.min(...ys), hole.bottom);
      assert.ok(overlapWidth <= 1e-9 || overlapHeight <= 1e-9, 'triangle bounding box crosses an aperture');
    }
  }
});

test('solid walls still contain two complete correctly oriented triangles', () => {
  const { positions, uvs } = wallGeometryData(4, 3);
  assert.equal(positions.length, 18);
  assert.equal(uvs.length, 12);
  near(wallRectangles(4, 3).reduce((area, rect) => area + rect.width * rect.height, 0), 12);
  assert.deepEqual(uvs, [0, 0, 1, 0, 1, 1, 0, 0, 1, 1, 0, 1]);
});

test('wall UVs remain continuous in meters across independently cut cells', () => {
  const { positions, uvs } = wallGeometryData(5, 2.7, [windowOpening, doorOpening]);
  for (let i = 0; i < positions.length / 3; i++) {
    near(uvs[i * 2] * 5, positions[i * 3] + 2.5);
    near(uvs[i * 2 + 1] * 2.7, positions[i * 3 + 1] + 1.35);
  }
});

test('actual Three.js rays pass through cutouts on all four walls but hit surrounding wall', () => {
  const dimensions = { width: 5, depth: 4, height: 2.7 };
  for (const side of WALL_SIDES) {
    const transform = wallTransform(side, dimensions);
    const data = wallGeometryData(transform.length, dimensions.height, [doorOpening]);
    const geometry = new BufferGeometry();
    geometry.setAttribute('position', new Float32BufferAttribute(data.positions, 3));
    const material = new MeshBasicMaterial();
    const mesh = new Mesh(geometry, material);
    mesh.position.fromArray(transform.position); mesh.rotation.y = transform.rotationY; mesh.updateMatrixWorld(true);
    const inward = new Vector3(0, 0, 1).transformDirection(mesh.matrixWorld);
    const rayAt = (x, heightAboveFloor) => {
      const point = mesh.localToWorld(new Vector3(x, heightAboveFloor - dimensions.height / 2, 0));
      return new Raycaster(point.clone().add(inward), inward.clone().negate()).intersectObject(mesh);
    };
    assert.equal(rayAt(doorOpening.offset, 0.01).length, 0, `${side}: door must remain open to the floor`);
    assert.equal(rayAt(doorOpening.offset, 1.2).length, 0, `${side}: door center must be empty`);
    assert.ok(rayAt(0.7, 1.2).length > 0, `${side}: uncut wall must remain visible/selectable`);
    const normal = ({ south: [0, 0, 1], north: [0, 0, -1], west: [1, 0, 0], east: [-1, 0, 0] })[side];
    inward.toArray().forEach((value, index) => near(value, normal[index]));
    geometry.dispose(); material.dispose();
  }
});

test('opening validation enforces limits and accepts exactly the contractual gap', () => {
  assert.equal(openingError(windowOpening, 5, 2.7, [doorOpening]), null);
  const first = { ...windowOpening, width: 1, offset: -0.5 };
  const exactGap = { ...windowOpening, id: 'new', width: 1, offset: 0.55 };
  assert.equal(openingError(exactGap, 5, 2.7, [first]), null);
  assert.match(openingError({ ...exactGap, offset: 0.54 }, 5, 2.7, [first]), /0,05/);
  assert.match(openingError({ ...windowOpening, width: 0.2 }, 5, 2.7), /0,30/);
  assert.match(openingError({ ...windowOpening, sill: 0 }, 5, 2.7), /0,10/);
  assert.match(openingError({ ...doorOpening, sill: 0.1 }, 5, 2.7), /piso/);
  assert.match(openingError({ ...windowOpening, offset: 2.1 }, 5, 2.7), /laterais/);
  assert.match(openingError({ ...windowOpening, height: NaN }, 5, 2.7), /números/);
});

test('vertical separation permits stacked windows and self update does not conflict', () => {
  const first = { ...windowOpening, height: 0.5, sill: 0.5 };
  const above = { ...first, id: 'above', sill: 1.05 };
  assert.equal(openingError(above, 5, 2.7, [first]), null);
  assert.match(openingError({ ...above, sill: 1.04 }, 5, 2.7, [first]), /0,05/);
  assert.equal(openingError(first, 5, 2.7, [first]), null);
});

test('invalid persisted geometry fails explicitly rather than clipping or inventing a hole', () => {
  assert.throws(() => wallGeometryData(5, 2.7, [{ ...windowOpening, offset: 10 }]), /fora/);
  assert.throws(() => wallGeometryData(5, 2.7, [{ ...windowOpening, sill: -1 }]), /fora/);
  assert.throws(() => wallTransform('invalid', { width: 5, depth: 4, height: 2.7 }), /inválida/);
});
