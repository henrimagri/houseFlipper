import test from 'node:test';
import assert from 'node:assert/strict';
import { browserSecurityHeaders, contentSecurityPolicy } from '../security-headers.ts';

test('production browser headers prevent framing and restrict active content', () => {
  const headers = browserSecurityHeaders(false);

  assert.equal(headers['X-Frame-Options'], 'DENY');
  assert.equal(headers['X-Content-Type-Options'], 'nosniff');
  assert.equal(headers['Referrer-Policy'], 'no-referrer');
  assert.match(headers['Content-Security-Policy'], /frame-ancestors 'none'/);
  assert.match(headers['Content-Security-Policy'], /object-src 'none'/);
  assert.match(headers['Content-Security-Policy'], /script-src 'self'(;|$)/);
  assert.doesNotMatch(headers['Content-Security-Policy'], /script-src[^;]*'unsafe-inline'/);
});

test('development policy permits only the transport needed by Vite', () => {
  const policy = contentSecurityPolicy(true);

  assert.match(policy, /script-src 'self' 'unsafe-inline'/);
  assert.match(policy, /connect-src[^;]* ws: wss:/);
  assert.doesNotMatch(policy, /'unsafe-eval'/);
});
