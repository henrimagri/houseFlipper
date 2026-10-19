import test from 'node:test';
import assert from 'node:assert/strict';
import { parseResetFragment } from '../src/lib/passwordReset.ts';

test('password reset fragment decodes token and normalizes email', () => {
  assert.deepEqual(parseResetFragment('#token=abc%2B123&email=USER%40Example.Test'), { token: 'abc+123', email: 'user@example.test' });
});

test('password reset fragment rejects missing, malformed and oversized credentials', () => {
  assert.equal(parseResetFragment('#email=user%40example.test'), null);
  assert.equal(parseResetFragment('#token=abc&email=invalid'), null);
  assert.equal(parseResetFragment(`#token=${'x'.repeat(256)}&email=user%40example.test`), null);
});
