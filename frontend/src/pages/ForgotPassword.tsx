import { type FormEvent, useState } from 'react';
import { ArrowLeft, CheckCircle2, Mail } from 'lucide-react';
import { Link } from 'react-router-dom';
import api from '@/lib/api';
import AuthShell from '@/components/public/AuthShell';

export default function ForgotPassword() {
  const [email,setEmail]=useState(''); const [busy,setBusy]=useState(false); const [error,setError]=useState(''); const [sent,setSent]=useState(false);
  async function submit(event:FormEvent){event.preventDefault();if(busy)return;setBusy(true);setError('');try{await api.post('/auth/password/forgot',{email});setSent(true);}catch(exception:any){setError(exception.response?.status===429?'Muitas solicitações. Aguarde antes de tentar novamente.':exception.response?.data?.message||'Não foi possível processar a solicitação agora.');}finally{setBusy(false);}}
  return <AuthShell eyebrow="Recuperação protegida" title="Retome o acesso com segurança." description="O processo mantém uma resposta uniforme para proteger a existência das contas e envia instruções somente quando aplicável.">
    <section className="rounded-[2rem] border border-white/10 bg-white/[.075] p-6 shadow-2xl shadow-black/30 backdrop-blur-xl sm:p-9">
      {sent?<div className="text-center"><span className="mx-auto grid size-14 place-items-center rounded-2xl bg-emerald-400/10"><CheckCircle2 className="size-7 text-emerald-300"/></span><h1 className="mt-5 text-2xl font-black">Confira seu e-mail</h1><p role="status" className="mt-3 text-sm leading-6 text-slate-400">Se a conta estiver apta, enviaremos as instruções. Verifique também a caixa de spam.</p><button type="button" onClick={()=>{setSent(false);setError('');}} className="mt-6 font-bold text-cyan-300">Solicitar novamente</button></div>:<><span className="grid size-12 place-items-center rounded-2xl bg-cyan-400/10"><Mail className="size-6 text-cyan-300"/></span><h1 className="mt-5 text-3xl font-black">Recuperar senha</h1><p className="mt-2 text-sm leading-6 text-slate-400">Informe o e-mail da conta para receber o link de redefinição.</p><form onSubmit={submit} className="mt-7 space-y-4"><label htmlFor="forgot-email" className="block text-sm font-bold text-slate-200">E-mail<input id="forgot-email" autoFocus autoComplete="email" type="email" required maxLength={150} value={email} onChange={event=>setEmail(event.target.value)} className="input mt-2 py-3" placeholder="voce@empresa.com.br"/></label>{error&&<p role="alert" className="rounded-xl border border-red-400/20 bg-red-500/10 p-3 text-sm text-red-200">{error}</p>}<button type="submit" disabled={busy} className="w-full rounded-xl bg-gradient-to-r from-violet-600 to-blue-600 py-3.5 font-black text-white disabled:opacity-60">{busy?'Enviando…':'Enviar instruções'}</button></form></>}
      <Link to="/login" className="mt-7 flex items-center justify-center gap-2 text-sm font-bold text-cyan-300"><ArrowLeft className="size-4"/>Voltar ao login</Link>
    </section>
  </AuthShell>;
}
