import { useState } from 'react';
import api from '@/lib/api';

export function readAnalyticsSession():string|undefined {
 try{return localStorage.getItem('flipcontrol-analytics-consent')==='yes'?sessionStorage.getItem('flipcontrol-analytics-session')??undefined:undefined;}catch{return undefined;}
}

export default function AnalyticsConsent(){
 const [choice,setChoice]=useState<string|null>(()=>{try{return localStorage.getItem('flipcontrol-analytics-consent');}catch{return 'no';}});
 const [error,setError]=useState('');
 async function choose(allow:boolean){
  try{
   localStorage.setItem('flipcontrol-analytics-consent',allow?'yes':'no');setChoice(allow?'yes':'no');
   if(!allow){sessionStorage.removeItem('flipcontrol-analytics-session');return;}
   const id=crypto.randomUUID();sessionStorage.setItem('flipcontrol-analytics-session',id);
   const query=new URLSearchParams(location.search);const utm:Record<string,string>={};
   for(const key of ['utm_source','utm_medium','utm_campaign','utm_term','utm_content']){const value=query.get(key);if(value&&/^[A-Za-z0-9_. -]{1,80}$/.test(value)&&!/\d{7,}/.test(value))utm[key]=value;}
   await api.post('/analytics/visit',{session_id:id,consented:true,policy:'analytics-v1',utm},{timeout:10000});
  }catch{setError('Sua preferência foi mantida; a medição não foi confirmada.');}
 }
 if(choice)return <div className="px-5 py-3 text-center text-xs text-slate-500"><button onClick={()=>setChoice(null)}>Preferências de analytics</button>{error&&<p role="status">{error}</p>}</div>;
 return <section aria-label="Preferências de analytics" className="border-t border-slate-200 bg-slate-50 p-5 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"><div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4"><p className="max-w-3xl">Com sua permissão, medimos a origem desta sessão para melhorar o cadastro. Sem fingerprinting, conteúdo de conversas ou publicidade externa. A recusa mantém todas as funções disponíveis. Você pode desativar futuras medições aqui.</p><div className="flex gap-3"><button className="rounded-lg border border-slate-400 px-4 py-2" onClick={()=>void choose(false)}>Recusar analytics</button><button className="rounded-lg bg-slate-900 px-4 py-2 text-white dark:bg-cyan-700" onClick={()=>void choose(true)}>Permitir analytics</button></div></div></section>;
}
