import test from 'node:test';
import assert from 'node:assert/strict';
import { browserRuntime } from '../scripts/browser-runtime.mjs';

test('detects Windows Edge and keeps sandbox enabled', () => {
  const edge = 'C:/Programs (x86)/Microsoft/Edge/Application/msedge.exe';
  assert.deepEqual(browserRuntime({ 'PROGRAMFILES(X86)': 'C:/Programs (x86)' }, 'win32', p => p === edge), {
    executablePath: edge, args: [],
  });
});

test('explicit browser path is honored, sandbox opt out requires true', () => {
  assert.deepEqual(browserRuntime({ CHROME_PATH: '/custom/browser', SCRAPER_DISABLE_SANDBOX: 'true' }, 'linux', () => true), {
    executablePath: '/custom/browser', args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });
  assert.deepEqual(browserRuntime({ CHROME_PATH: '/custom/browser', SCRAPER_DISABLE_SANDBOX: 'false' }, 'linux', () => true).args, []);
});

test('missing browser fails explicitly, Linux fallback is detected', () => {
  assert.throws(() => browserRuntime({}, 'linux', () => false), /CHROME_PATH/);
  assert.throws(() => browserRuntime({ CHROME_PATH: '/missing' }, 'linux', p => p === '/usr/bin/chromium'), /CHROME_PATH/);
  assert.equal(browserRuntime({}, 'linux', p => p === '/usr/bin/chromium').executablePath, '/usr/bin/chromium');
});
