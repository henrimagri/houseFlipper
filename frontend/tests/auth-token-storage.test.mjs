import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import ts from 'typescript';

const helper = await readFile(new URL('../src/lib/authToken.ts', import.meta.url), 'utf8');
const requestPolicy = await readFile(new URL('../src/lib/authRequest.ts', import.meta.url), 'utf8');
const consumers = await Promise.all([
  '../src/App.tsx',
  '../src/lib/api.ts',
  '../src/components/Sidebar.tsx',
  '../src/pages/Configuracoes.tsx',
  '../src/pages/Login.tsx',
  '../src/pages/Register.tsx',
  '../src/pages/ResetPassword.tsx',
  '../src/pages/VerifyEmail.tsx',
].map(path => readFile(new URL(path, import.meta.url), 'utf8')));

async function loadHelper() {
  const javascript = ts.transpileModule(helper, {
    compilerOptions: { module: ts.ModuleKind.ES2022, target: ts.ScriptTarget.ES2022 },
  }).outputText;
  return import(`data:text/javascript;base64,${Buffer.from(javascript).toString('base64')}#${Math.random()}`);
}

async function loadRequestPolicy() {
  const javascript = ts.transpileModule(requestPolicy, {
    compilerOptions: { module: ts.ModuleKind.ES2022, target: ts.ScriptTarget.ES2022 },
  }).outputText;
  return import(`data:text/javascript;base64,${Buffer.from(javascript).toString('base64')}#${Math.random()}`);
}

test('authentication token storage has a non-throwing in-memory fallback', () => {
  assert.match(helper, /let memoryToken: string \| null = null/);
  assert.match(helper, /export function getAuthToken/);
  assert.match(helper, /try \{[\s\S]*localStorage\.getItem\('token'\)[\s\S]*\} catch \{/);
  assert.match(helper, /export function setAuthToken/);
  assert.match(helper, /memoryToken = token;[\s\S]*try \{[\s\S]*localStorage\.setItem/);
  assert.match(helper, /export function clearAuthToken/);
  assert.match(helper, /memoryToken = null;[\s\S]*try \{[\s\S]*localStorage\.removeItem/);
});

test('authentication flows do not access token localStorage directly', () => {
  for (const source of consumers) {
    assert.doesNotMatch(source, /localStorage\.(?:getItem|setItem|removeItem)\(['"]token['"]/);
  }
  assert.match(consumers.join('\n'), /getAuthToken\(\)/);
  assert.match(consumers.join('\n'), /setAuthToken\(data\.token\)/);
  assert.match(consumers.join('\n'), /clearAuthToken\(\)/);
});

test('authentication remains usable in memory when browser storage throws', async () => {
  const previous = globalThis.localStorage;
  globalThis.localStorage = {
    getItem() { throw new DOMException('blocked', 'SecurityError'); },
    setItem() { throw new DOMException('blocked', 'SecurityError'); },
    removeItem() { throw new DOMException('blocked', 'SecurityError'); },
  };

  try {
    const auth = await loadHelper();
    assert.equal(auth.getAuthToken(), null);
    assert.equal(auth.setAuthToken('temporary-token'), false);
    assert.equal(auth.getAuthToken(), 'temporary-token');
    assert.doesNotThrow(() => auth.clearAuthToken());
    assert.equal(auth.getAuthToken(), null);
  } finally {
    globalThis.localStorage = previous;
  }
});

test('public authentication requests neither receive nor clear an existing bearer session', async () => {
  const policy = await loadRequestPolicy();
  for (const url of [
    '/auth/login', '/auth/register', '/auth/password/forgot', '/auth/password/reset',
    '/auth/postal-code/01001000', '/auth/email/verify/1/hash?expires=1&signature=x',
  ]) {
    assert.equal(policy.isPublicAuthRequest(url), true, url);
    assert.equal(policy.shouldClearSessionAfterUnauthorized(url, 'Bearer still-valid'), false, url);
  }
  assert.equal(policy.isPublicAuthRequest('/auth/email/resend'), false);
  assert.equal(policy.shouldClearSessionAfterUnauthorized('/auth/me', 'Bearer expired'), true);
  assert.equal(policy.shouldClearSessionAfterUnauthorized('/auth/me', undefined), false);
  assert.match(consumers[1], /!isPublicAuthRequest\(config\.url\)/);
  assert.match(consumers[1], /shouldClearSessionAfterUnauthorized/);
});
