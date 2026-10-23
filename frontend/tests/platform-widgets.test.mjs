import test from 'node:test';
import assert from 'node:assert/strict';
import { build } from 'esbuild';
import { mkdtemp, writeFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve, basename, sep } from 'node:path';
import { createRequire } from 'node:module';

// Render actual React components. API is a stub: these tests make no HTTP calls.
const folder = await mkdtemp(join(tmpdir(), 'platform-widgets-'));
let render, presetPeriod;
try {
  const compiled = await build({
    stdin: { contents: `import React from 'react'; import {renderToStaticMarkup} from 'react-dom/server'; import {DataTable,Fields,Controls,ResolveIncident} from './src/pages/platform/PlatformWidgets'; import Overview from './src/pages/platform/PlatformOverview'; import {MemoryRouter} from 'react-router-dom'; import PlatformShell from './src/pages/platform/PlatformShell'; import PlatformFilterBar,{presetPeriod} from './src/pages/platform/PlatformFilterBar'; export {presetPeriod}; import {StatusBadge,ClientProfile} from './src/pages/platform/PlatformWidgets'; import {PageSummary,JobsPanel,FunnelPanel,HealthGrid} from './src/pages/platform/PlatformModulePanels'; import {WorkflowCreate,PrivacyUpdate,SignalReview} from './src/pages/platform/PlatformWorkflowForms'; export function render(kind,props){return renderToStaticMarkup(React.createElement(MemoryRouter,null,React.createElement({PlatformShell,PlatformFilterBar,StatusBadge,ClientProfile,PageSummary,JobsPanel,FunnelPanel,HealthGrid,DataTable,Fields,Controls,ResolveIncident,Overview,WorkflowCreate,PrivacyUpdate,SignalReview}[kind],props)));}`,
      resolveDir: resolve('.'), sourcefile: 'platform-render.tsx', loader: 'tsx' },
    bundle: true, platform: 'node', format: 'cjs', write: false,
    plugins: [{ name: 'api-unit-boundary', setup(builder) {
      builder.onResolve({ filter: /^@\/lib\/api$/ }, () => ({ path: 'api', namespace: 'test-api' }));
      builder.onLoad({ filter: /.*/, namespace: 'test-api' }, () => ({ contents: 'export default {};' }));
    } }],
  });
  const file = join(folder, 'render.cjs');
  await writeFile(file, compiled.outputFiles[0].contents);
  ({ render, presetPeriod } = createRequire(import.meta.url)(file));
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

test('executive overview does not claim live presence, reconciled revenue or protection', () => {
 const html=render('Overview',{data:{users:{total:0,verified:0,suspended:0},active_observed:{1:0,7:0,30:0},requests:0,new_users:0,critical_open:0,security:[],subscriptions:[],revenue:{mrr:null,arr:null},activity_series:[]}});
 for(const text of ['Ainda não há indicadores financeiros conciliados','sem inferência de presença online','Contagem global da plataforma','Sem comparação disponível'])assert.ok(html.includes(text));
 assert.ok(!html.includes('Protegido'));assert.ok(!html.includes('R$'));assert.ok(!html.includes('NaN'));
 assert.match(html,/>0</);
});

test('product cards retain every actual subscription status without counting users as accounts', () => {
 const html=render('Overview',{data:{subscriptions:[{product_slug:'corretor',status:'trial',total:2},{product_slug:'corretor',status:'active',total:3},{product_slug:'house_flipper',status:'past_due',total:1}]}});
 for(const text of ['Corretor','Imobiliária','House Flipper','Em teste','Ativas','Inadimplentes','Nenhuma assinatura neste escopo.'])assert.ok(html.includes(text));
 assert.match(html,/>5<span/);assert.ok(html.includes('Não medido'));
});

test('overview leaves absent subscription and signal datasets unknown', () => {
 const html=render('Overview',{data:{}});
 assert.ok(html.includes('Consulta indisponível.'));
 assert.ok(!html.includes('Nenhuma assinatura neste escopo.'));
 assert.ok(!html.includes('0 sinais no período'));
});

const baseFilters={from:'',to:'',product:'imobiliaria',tenant_id:'7',user_id:'30',status:'failed',severity:'',search:'',plan:''};
test('period shortcuts use inclusive calendar days across month and leap boundaries and preserve scope',()=>{
 const actual=presetPeriod(baseFilters,7,new Date(2024,2,1,12));
 assert.equal(actual.from,'2024-02-24');assert.equal(actual.to,'2024-03-01');assert.equal(actual.tenant_id,'7');assert.equal(actual.product,'imobiliaria');assert.equal(actual.status,'failed');assert.equal(baseFilters.from,'');
 assert.equal(presetPeriod(baseFilters,1,new Date(2026,0,1)).from,'2026-01-01');
});
test('unknown service states and HTTP failures are explicit',()=>{
 assert.match(render('StatusBadge',{value:502}),/HTTP 502/);assert.match(render('StatusBadge',{value:'not_configured'}),/Não configurado/);assert.match(render('StatusBadge',{value:null}),/Não medido/);
 const html=render('HealthGrid',{services:{qdrant:{status:'offline'},queue:{status:'not_configured'}}});assert.match(html,/Offline/);assert.match(html,/Não configurado/);
});
test('paged summaries identify their denominator and jobs reject invalid progress',()=>{
 const html=render('PageSummary',{rows:[{status:'failed'}],field:'status',values:['failed','queued']});assert.match(html,/não são totais do período/);assert.match(html,/>0</);
 const jobs=render('JobsPanel',{rows:[{id:1,status:'failed',progress:120,actual_method:null}],onOpen(){}});assert.ok(!jobs.includes('<progress'));assert.match(jobs,/Progresso não medido/);assert.match(jobs,/GPU indisponíveis/);
});
test('unmeasured funnel stage never becomes a paid conversion count',()=>{
 const html=render('FunnelPanel',{data:{stages:[{stage:'Cadastro',value:0},{stage:'Pago',value:null}]}});assert.match(html,/>0</);assert.match(html,/Não medido/);assert.match(html,/não representa zero/);
});
test('shell retains every supplied destination and identifies active route',()=>{
 const html=render('PlatformShell',{groups:[{label:'OPERAÇÃO',items:[['overview','Visão geral'],['clients','Clientes']]}],section:'clients',title:'Clientes',name:'Operador QA',search:'',onSearchChange(){},onSearch(){},onRefresh(){},loading:false,receivedAt:null});
 assert.match(html,/href="\/platform\/clients"/);assert.match(html,/aria-current="page"/);assert.match(html,/Recolher sidebar/);assert.match(html,/Aguardando consulta/);assert.ok(!html.includes('há 0s'));
});

test('incident editor preserves a resolved status and resolution when opening the current record',()=>{
 const html=render('ResolveIncident',{id:3,current:{status:'false_positive',resolution:'Validação local concluída'},onDone(){}});assert.match(html,/<option selected="">false_positive<\/option>/);assert.match(html,/Validação local concluída/);assert.ok(!html.includes('<option selected="">investigating</option>'));
});

test('malformed overview lists fail independently and never become measured zero',()=>{
 const html=render('Overview',{data:{users:{total:4},security:{low:3},subscriptions:{},activity_series:{}}});assert.match(html,/Dados de segurança indisponíveis nesta consulta/);assert.match(html,/Consulta indisponível/);assert.match(html,/Atividade indisponível/);assert.ok(!html.includes('0 sinais no período'));assert.ok(!html.includes('Nenhuma assinatura neste escopo'));assert.match(html,/>4</);
});
