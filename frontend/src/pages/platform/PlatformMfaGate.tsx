import { useEffect, useState, type FormEvent, type ReactNode } from 'react';
import { Link, Navigate } from 'react-router-dom';
import api from '@/lib/api';
import { useSession } from '@/lib/session';

const field='mt-2 w-full rounded-lg border border-slate-600 bg-slate-950 p-3 text-white';
const button='rounded-lg border border-slate-600 bg-slate-800 px-4 py-3 disabled:opacity-40';

export default function PlatformMfaGate({children,management=false}:{children?:ReactNode;management?:boolean}) {
 const session=useSession();
 const [status,setStatus]=useState<any>(null),[error,setError]=useState(''),[busy,setBusy]=useState(false);
 const [secret,setSecret]=useState(''),[code,setCode]=useState(''),[password,setPassword]=useState(''),[codes,setCodes]=useState<string[]>([]);
 const [action,setAction]=useState('challenge');
 async function load(){try {setStatus((await api.get('/platform/mfa/status',{timeout:15000})).data);setError('');}catch(e:any){setStatus(null);setError(e.code==='ECONNABORTED'?'A verificação de acesso excedeu 15 segundos. Tente novamente.':e.response?.data?.message??'Não foi possível verificar o segundo fator. Tente novamente.');}}
 useEffect(()=>{if(!session.loading&&session.isPlatformAdmin)void load();const required=()=>{setStatus(null);void load();};window.addEventListener('platform-mfa-required',required);return()=>window.removeEventListener('platform-mfa-required',required);},[session.loading,session.isPlatformAdmin]);
 if(session.loading)return <p role="status">Validando acesso…</p>;
 if(!session.isPlatformAdmin)return <Navigate to="/app/inicio" replace/>;
 if(status?.verified&&!management&&!codes.length)return <>{status.local_qa_exemption&&<p role="status" className="border-b border-amber-400/20 bg-slate-950 px-4 py-2 text-xs text-amber-200 lg:pl-72">QA local · MFA dispensado temporariamente apenas para esta conta.</p>}{children}</>;
 async function submit(e:FormEvent){e.preventDefault();if(busy)return;setBusy(true);setError('');try{
  const method=!status?.enrolled?(secret?'confirm':'enrol'):action;
  const response=await api.post('/platform/mfa/'+method,{...(method==='enrol'||['disable','recoveries'].includes(method)?{password}:{}),...(method!=='enrol'?{code}:{})},{timeout:15000});
  setCode('');if(response.data.secret)setSecret(response.data.secret);
  if(method==='confirm')setSecret('');
  if(response.data.recovery_codes)setCodes(response.data.recovery_codes);
  await load();setAction('challenge');
 }catch(e:any){setError(e.code==='ECONNABORTED'?'Tempo esgotado. Verifique o estado antes de repetir.':e.response?.data?.message??'Não foi possível validar.');}finally{setPassword('');setBusy(false);}}
 return <main className="min-h-screen bg-slate-950 p-5 text-slate-100 sm:p-12"><section className="mx-auto max-w-xl space-y-5 rounded-2xl border border-slate-700 bg-slate-900 p-6"><p className="text-xs uppercase tracking-widest text-cyan-300">FlipControl · acesso administrativo</p><h1 className="text-2xl font-semibold">{!status?'Verifique o acesso administrativo':management?'Segurança do segundo fator':status.enrolled?'Confirme seu segundo fator':'Cadastre seu autenticador'}</h1>
 <p className="text-sm text-slate-300">O segundo fator protege somente a central da plataforma. Use um autenticador TOTP; cada código vale por 30 segundos. A sessão administrativa dura até 8 horas e ações sensíveis exigem validação nos últimos 10 minutos.</p>
 {error&&<p role="alert" className="text-rose-300">{error}</p>}
 {!status?<button className={button} onClick={()=>void load()}>Verificar acesso</button>:codes.length?<><h2 className="font-semibold">Guarde os códigos de recuperação</h2><p className="text-sm text-amber-200">Exibidos somente agora. Cada código pode ser usado uma vez. Guarde em local seguro; não envie ao suporte.</p><ul className="grid gap-2 font-mono sm:grid-cols-2">{codes.map(c=><li key={c}>{c}</li>)}</ul><button className={button} onClick={()=>{setCodes([]);void load();}}>Guardei os códigos</button></>:<form onSubmit={submit} className="space-y-4">
 {secret&&<><p className="text-sm">Adicione uma conta por chave manual no autenticador. Algoritmo SHA1, 6 dígitos, período de 30 segundos. A configuração expira em 10 minutos.</p><label className="block text-sm">Chave do autenticador<input className={field+' font-mono'} readOnly value={secret} autoComplete="off"/></label></>}
 {(!status.enrolled&&!secret||management&&['disable','recoveries'].includes(action))&&<label className="block text-sm">Sua senha atual<input className={field} type="password" autoComplete="current-password" required maxLength={200} value={password} onChange={e=>setPassword(e.target.value)}/></label>}
 {(status.enrolled||secret)&&<label className="block text-sm">Código TOTP ou de recuperação<input className={field} autoComplete="one-time-code" autoCapitalize="none" spellCheck={false} required maxLength={80} value={code} onChange={e=>setCode(e.target.value.trim())}/></label>}
 {management&&status.enrolled&&<label className="block text-sm">Operação<select className={field} value={action} onChange={e=>{setAction(e.target.value);setCode('');setPassword('');}}><option value="challenge">Reautenticar esta sessão</option><option value="recoveries">Regenerar códigos (invalida os anteriores)</option><option value="disable">Remover MFA e exigir novo cadastro</option></select></label>}
 {action==='disable'&&<p className="text-sm text-amber-200">Todas as sessões perderão acesso à central. O login dos produtos continua disponível. Será necessário cadastrar o segundo fator novamente.</p>}
 <button className={button} disabled={busy}>{busy?'Validando…':!status.enrolled&&!secret?'Gerar chave':'Confirmar'}</button>
 </form>}
 <div className="flex gap-4 text-sm text-cyan-300"><Link to="/app/inicio">Voltar à conta</Link>{management&&<Link to="/platform">Voltar à central</Link>}</div>
 </section></main>;
}
