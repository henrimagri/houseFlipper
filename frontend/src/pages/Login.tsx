import { FormEvent, useState } from 'react';
import { ArrowRight, Eye, EyeOff, LockKeyhole, Mail } from 'lucide-react';
import { Link } from 'react-router-dom';
import api from '@/lib/api';
import { setAuthToken } from '@/lib/authToken';
import { deviceName } from '@/lib/deviceName';
import { afterLoginPath } from '@/lib/postAuth';
import { ProductSlug } from '@/lib/products';
import PublicSeo from '@/components/public/PublicSeo';
import AuthShell from '@/components/public/AuthShell';

export default function Login() {
  const [email,setEmail]=useState(()=>localStorage.getItem('flipcontrol:remembered-email')??''); const [password,setPassword]=useState('');
  const [showPassword,setShowPassword]=useState(false); const [remember,setRemember]=useState(Boolean(localStorage.getItem('flipcontrol:remembered-email'))); const [loading,setLoading]=useState(false); const [erro,setErro]=useState<string|null>(null);
  async function handleSubmit(event:FormEvent){event.preventDefault();if(loading)return;setLoading(true);setErro(null);try{const{data}=await api.post('/auth/login',{email,password,device_name:deviceName()});if(remember)localStorage.setItem('flipcontrol:remembered-email',email);else localStorage.removeItem('flipcontrol:remembered-email');setAuthToken(data.token);const next=data.requires_email_verification?'/confirmar-email':afterLoginPath((data.products??[])as ProductSlug[],data.last_product_slug??null);window.location.assign(next);}catch(error:any){setErro(error.response?.status===429?'Muitas tentativas. Aguarde um momento e tente novamente.':error.response?.data?.message??'Não foi possível entrar. Confira seus dados.');}finally{setLoading(false);}}
  return <AuthShell eyebrow="Seu espaço de trabalho" title="Uma conta para conduzir toda a sua operação." description="Acesse apenas os produtos contratados, com permissões próprias e dados separados por operação.">
    <PublicSeo title="Entrar" description="Acesse sua conta FlipControl." path="/entrar" noindex />
    <section className="rounded-[2rem] border border-white/10 bg-white/[.075] p-6 shadow-2xl shadow-black/30 backdrop-blur-xl sm:p-9">
      <p className="text-xs font-black uppercase tracking-[.22em] text-cyan-300">Acesso seguro</p><h2 className="mt-3 text-3xl font-black tracking-tight">Bem-vindo de volta</h2><p className="mt-2 text-sm leading-6 text-slate-400">Entre para continuar no produto autorizado para sua conta.</p>
      {erro&&<div role="alert" className="mt-5 rounded-xl border border-rose-400/20 bg-rose-500/10 p-3 text-sm text-rose-200">{erro}</div>}
      <form onSubmit={handleSubmit} className="mt-7 space-y-5">
        <label htmlFor="login-email" className="block text-sm font-bold text-slate-200">E-mail<span className="relative mt-2 flex items-center"><Mail className="pointer-events-none absolute left-3.5 size-4 text-slate-500"/><input id="login-email" type="email" autoComplete="username" value={email} onChange={event=>setEmail(event.target.value)} required className="w-full rounded-xl border border-white/10 bg-slate-950/65 py-3 pl-10 pr-4 text-sm text-white outline-none transition placeholder:text-slate-600 focus:border-cyan-400/60 focus:ring-4 focus:ring-cyan-400/10" placeholder="voce@empresa.com.br"/></span></label>
        <label htmlFor="login-password" className="block text-sm font-bold text-slate-200"><span className="flex items-center justify-between gap-3"><span>Senha</span><Link to="/esqueci-senha" className="text-xs text-cyan-300 hover:text-cyan-200">Esqueci minha senha</Link></span><span className="relative mt-2 flex items-center"><LockKeyhole className="pointer-events-none absolute left-3.5 size-4 text-slate-500"/><input id="login-password" type={showPassword?'text':'password'} autoComplete="current-password" value={password} onChange={event=>setPassword(event.target.value)} required className="w-full rounded-xl border border-white/10 bg-slate-950/65 py-3 pl-10 pr-11 text-sm text-white outline-none transition focus:border-cyan-400/60 focus:ring-4 focus:ring-cyan-400/10"/><button type="button" onClick={()=>setShowPassword(value=>!value)} className="absolute right-2.5 rounded-lg p-2 text-slate-400 hover:bg-white/5 hover:text-white" aria-label={showPassword?'Ocultar senha':'Mostrar senha'}>{showPassword?<EyeOff className="size-4"/>:<Eye className="size-4"/>}</button></span></label>
        <label className="flex cursor-pointer items-center gap-2.5 text-xs text-slate-400"><input type="checkbox" checked={remember} onChange={event=>setRemember(event.target.checked)} className="size-4 rounded border-white/20 bg-slate-950 text-cyan-500 focus:ring-cyan-400"/>Lembrar meu e-mail neste dispositivo</label>
        <button type="submit" disabled={loading} className="flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-violet-600 to-blue-600 py-3.5 text-sm font-black text-white shadow-lg shadow-violet-950/30 transition hover:-translate-y-0.5 hover:brightness-110 disabled:pointer-events-none disabled:opacity-60">{loading?'Entrando…':<>Entrar <ArrowRight className="size-4"/></>}</button>
      </form>
      <p className="mt-6 text-center text-sm text-slate-400">Ainda não tem conta? <Link to="/cadastro" className="font-bold text-cyan-300 hover:text-cyan-200">Criar conta</Link></p><p className="mt-3 text-center"><Link to="/" className="text-xs font-semibold text-slate-500 hover:text-slate-300">Voltar ao site</Link></p>
    </section>
  </AuthShell>;
}
