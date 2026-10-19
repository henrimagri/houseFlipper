import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const source = await readFile(new URL('../src/lib/deviceName.ts', import.meta.url), 'utf8');
const runnable = source.replace('export function deviceName', 'function deviceName');
const deviceName = Function(`${runnable}; return deviceName;`)();

test('device labels identify common browser and operating system combinations', () => {
  assert.equal(deviceName('Mozilla/5.0 (Windows NT 10.0) Chrome/120 Safari/537.36', 'Win32'), 'Chrome · Windows');
  assert.equal(deviceName('Mozilla/5.0 (Macintosh) Version/17 Safari/605.1.15', 'MacIntel'), 'Safari · macOS');
  assert.equal(deviceName('Mozilla/5.0 (Linux; Android 14) Firefox/120', 'Linux armv8l'), 'Firefox · Android');
  assert.equal(deviceName('Mozilla/5.0 (Windows NT 10.0) Edg/120 Chrome/120', 'Win32'), 'Edge · Windows');
});
