import test from 'node:test';
import assert from 'node:assert/strict';
import { build } from 'esbuild';
import { mkdtemp, writeFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve, basename, sep } from 'node:path';
import { createRequire } from 'node:module';

// Render actual React components. API is a stub: these tests make no HTTP calls.
const folder = await mkdtemp(join(tmpdir(), 'platform-widgets-'));
let render;
try {
  const compiled = await build({
    stdin: { contents: `import React from 'react'; import {renderToStaticMarkup} from 'react-dom/server'; import {DataTable,Fields,Controls,ResolveIncident,Overview} from './src/pages/platform/PlatformWidgets'; import {WorkflowCreate,PrivacyUpdate,SignalReview} from './src/pages/platform/PlatformWorkflowForms'; export function render(kind,props){return renderToStaticMarkup(React.createElement({DataTable,Fields,Controls,ResolveIncident,Overview,WorkflowCreate,PrivacyUpdate,SignalReview}[kind],props));}`,
      resolveDir: resolve('.'), sourcefile: 'platform-render.tsx', loader: 'tsx' },
    bundle: true, platform: 'node', format: 'cjs', write: false,
    plugins: [{ name: 'api-unit-boundary', setup(builder) {
      builder.onResolve({ filter: /^@\/lib\/api$/ }, () => ({ path: 'api', namespace: 'test-api' }));
      builder.onLoad({ filter: /.*/, namespace: 'test-api' }, () => ({ contents: 'export default {};' }));
    } }],
  });
  const file = join(folder, 'render.cjs');
  await writeFile(file, compiled.outputFiles[0].contents);
  ({ render } = createRequire(import.meta.url)(file));
} finally { const target=resolve(folder); assert.ok(target.startsWith(resolve(tmpdir())+sep) && basename(target).startsWith('platform-widgets-')); await rm(target, { recursive: true, force: true }); }

test('admin table escapes hostile strings and omits event metadata from the summary', () => {
  const html = render('DataTable', { rows: [{ id: 1, name: '<script>alert(1)</script>', metadata: 'PRIVATE_DETAIL_ONLY' }] });
  assert.ok(html.includes('&lt;script&gt;'));
  assert.ok(!html.includes('<script>'));
  assert.ok(!html.includes('PRIVATE_DETAIL_ONLY'));
});
test('unknown administrative metrics remain distinct from measured zero and false', () => {
  const html = render('Fields', { data: { precision: null, errors: 0, eligible: false } });
  assert.ok(html.includes('Não medido'));
  assert.match(html, />0</);
  assert.match(html, />Não</);
});
test('empty event table does not assert that the system has never had an incident', () => {
  assert.ok(render('DataTable', { rows: [] }).includes('antes da coleta'));
});
test('controls show unset rules and the actual enabled reconstruction default', () => {
  const html = render('Controls', { data: { data: [], available_alerts: ['login_failures'], available_flags: ['reconstruction_enabled'] }, onSave() {} });
  assert.ok(html.includes('Não configurado'));
  assert.ok(html.includes('sem bloqueio automático'));
  assert.ok(html.includes('Habilitada'));
  assert.ok(html.includes('Pausar'));
});

test('missing account state stays unknown rather than implying an inactive account', () => {
  const html = render('DataTable', { rows: [{ id: 1 }], columns: ['ativo_flg'] });
  assert.ok(html.includes('Não medido'));
  assert.ok(!html.includes('>Não<'));
});

test('async export form explains ownership, expiration and required worker',()=>{
 const html=render('WorkflowCreate',{section:'exports',onDone(){},onAction(){}});
 for(const text of ['fila privada','24 horas','somente ao administrador solicitante','platform-ops'])assert.ok(html.includes(text));
 assert.ok(!html.includes('password'));
});
test('privacy and security review forms expose explicit human workflows',()=>{
 const privacy=render('WorkflowCreate',{section:'privacy',onDone(){},onAction(){}});
 assert.ok(privacy.includes('não exclui dados automaticamente'));
 const review=render('SignalReview',{id:1,onDone(){}});
 assert.ok(review.includes('Falso positivo'));assert.ok(review.includes('Não treina modelos'));
});
test('incident form supports bounded notes, linked events and assignment',()=>{
 const html=render('ResolveIncident',{id:1,onDone(){}});
 for(const label of ['Nota do incidente','Eventos vinculados','Responsável pelo incidente','Prioridade do incidente'])assert.ok(html.includes(label));
 assert.match(html,/maxlength="1000"/i);
});

test('critical flags expose owner and environment without bypassing confirmation',()=>{
 const html=render('Controls',{data:{data:[],available_flags:['reconstruction_enabled'],flag_catalog:[{key:'reconstruction_enabled',critical:true,owner:'platform_operations',environment:'testing',default:true,description:'Permite novos jobs'}]},onSave(){}});
 for(const text of ['Flag crítica','platform_operations','testing','Pausar'])assert.ok(html.includes(text));
});

test('overview uses server readiness and preserves a measured zero score',()=>{
 const html=render('Overview',{data:{readiness:{score:0,blocking_checks:10},activity_series:[],security:[]}});
 assert.ok(html.includes('0%'));assert.ok(html.includes('10 bloqueios'));assert.ok(html.includes('/platform/readiness'));
});
test('one observed day is identified as insufficient rather than no observations',()=>{
 const html=render('Overview',{data:{activity_series:[{day:'2026-10-22',total:1}],security:[]}});
 assert.ok(html.includes('Poucos dados no período'));assert.ok(!html.includes('Sem observações no período'));
});
