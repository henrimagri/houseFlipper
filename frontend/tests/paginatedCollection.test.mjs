import test from 'node:test';
import assert from 'node:assert/strict';
import { PaginatedCollection } from '../src/lib/paginatedCollection.ts';

test('loads more than 100 expenses without violating the API limit or losing later pages', async () => {
  const loader = new PaginatedCollection();
  const source = Array.from({ length: 205 }, (_, id) => ({ id }));
  const calls = [];
  const result = await loader.load(async (page, perPage) => {
    assert.equal(perPage, 100);
    calls.push(page);
    return { data: source.slice((page - 1) * perPage, page * perPage), meta: { last_page: 3 } };
  });
  assert.deepEqual(calls, [1, 2, 3]);
  assert.deepEqual(result, source);
});

test('late response from the previous project never replaces the selected project', async () => {
  const loader = new PaginatedCollection();
  let resolveOld;
  let oldSignal;
  const old = loader.load((page, perPage, signal) => {
    oldSignal = signal;
    return new Promise(resolve => { resolveOld = resolve; });
  });
  const current = await loader.load(async () => ({ data: ['current'], meta: { last_page: 1 } }));
  resolveOld({ data: ['old'], meta: { last_page: 1 } });
  assert.equal(await old, undefined);
  assert.equal(oldSignal.aborted, true);
  assert.deepEqual(current, ['current']);
});

test('failed later page rejects instead of publishing a partial expense total', async () => {
  const loader = new PaginatedCollection();
  await assert.rejects(loader.load(async page => {
    if (page === 2) throw new Error('network failure');
    return { data: [1], meta: { last_page: 2 } };
  }), /network failure/);
});

test('unmount cancels pending success and pending failure', async () => {
  for (const fail of [false, true]) {
    const loader = new PaginatedCollection();
    let settle;
    const pending = loader.load(() => new Promise((resolve, reject) => {
      settle = () => fail ? reject(new Error('aborted network')) : resolve({ data: [1], meta: { last_page: 1 } });
    }));
    loader.cancel();
    settle();
    assert.equal(await pending, undefined);
  }
});

test('invalid pagination is surfaced instead of silently claiming a complete list', async () => {
  const loader = new PaginatedCollection();
  await assert.rejects(loader.load(async () => ({ data: [1] })), /paginação inválida/);
});
