import assert from 'node:assert/strict';
import test from 'node:test';
import { isAllowedHttpsUrl, isPublicHttpUrl, isPublicNetworkTarget } from '../scripts/network-policy.mjs';

test('allows public HTTP and HTTPS destinations', () => {
  assert.equal(isPublicHttpUrl('https://www.olx.com.br/imoveis/123'), true);
  assert.equal(isPublicHttpUrl('http://example.com/image.jpg'), true);
});

test('blocks local, private, link-local and cloud metadata destinations', () => {
  for (const url of [
    'http://localhost/admin',
    'http://api.local/private',
    'http://127.0.0.1:8000/api/auth/me',
    'http://10.0.0.1/',
    'http://172.16.0.1/',
    'http://192.168.1.1/',
    'http://169.254.169.254/latest/meta-data/',
    'http://100.64.0.1/',
    'http://[::1]/',
    'http://[fd00::1]/',
    'http://[fe80::1]/',
    'http://[::ffff:127.0.0.1]/',
    'http://metadata.google.internal/',
  ]) {
    assert.equal(isPublicHttpUrl(url), false, url);
  }
});

test('rejects public-looking hostnames when DNS resolves to a private address', async () => {
  const privateResolver = async () => [{ address: '169.254.169.254', family: 4 }];
  const mixedResolver = async () => [
    { address: '93.184.216.34', family: 4 },
    { address: '10.0.0.5', family: 4 },
  ];
  const publicResolver = async () => [{ address: '93.184.216.34', family: 4 }];

  assert.equal(await isPublicNetworkTarget('https://attacker.example/', privateResolver), false);
  assert.equal(await isPublicNetworkTarget('https://attacker.example/', mixedResolver), false);
  assert.equal(await isPublicNetworkTarget('https://example.com/', publicResolver), true);
});

test('blocks unsupported schemes, credentials and normalized loopback forms', () => {
  for (const url of [
    'file:///etc/passwd',
    'ftp://example.com/file',
    'data:text/plain,secret',
    'javascript:alert(1)',
    'http://user:password@example.com/',
    'http://2130706433/',
    'not a URL',
  ]) {
    assert.equal(isPublicHttpUrl(url), false, url);
  }
});

test('allowed URL requires an exact host or subdomain boundary', () => {
  const allowed = ['olx.com.br', 'zapimoveis.com.br'];

  assert.equal(isAllowedHttpsUrl('https://olx.com.br/imoveis', allowed), true);
  assert.equal(isAllowedHttpsUrl('https://www.olx.com.br/imoveis', allowed), true);
  assert.equal(isAllowedHttpsUrl('https://login.olx.com.br/redirect', allowed), true);
  assert.equal(isAllowedHttpsUrl('http://olx.com.br/imoveis', allowed), false);
  assert.equal(isAllowedHttpsUrl('https://olx.com.br.attacker.test/', allowed), false);
  assert.equal(isAllowedHttpsUrl('https://attacker.test/?next=olx.com.br', allowed), false);
  assert.equal(isAllowedHttpsUrl('file:///etc/passwd', allowed), false);
  assert.equal(isAllowedHttpsUrl('https://olx.com.br@attacker.test/', allowed), false);
});
