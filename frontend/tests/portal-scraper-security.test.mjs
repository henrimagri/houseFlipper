import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const source = await readFile(new URL('../scripts/portal-scraper.mjs', import.meta.url), 'utf8');

test('headless scraper restricts main-frame navigation and detail enrichment to allowed HTTPS portals', () => {
  assert.match(source, /request\.isNavigationRequest\(\)/);
  assert.match(source, /request\.frame\(\) === page\.mainFrame\(\)/);
  assert.match(source, /isAllowedHttpsUrl\(url, allowedPortalHosts\)/);
  assert.match(source, /isAllowedHttpsUrl\(item\.source_url, allowedPortalHosts\)/);
});

test('every scraper page explicitly denies downloads before navigation', () => {
  const pages = source.match(/await browser\.newPage\(\);/g) || [];
  const downloadGuards = source.match(/await denyDownloads\(page\);/g) || [];

  assert.ok(pages.length > 0);
  assert.equal(downloadGuards.length, pages.length);
  assert.match(source, /Page\.setDownloadBehavior', \{ behavior: 'deny' \}/);
});
