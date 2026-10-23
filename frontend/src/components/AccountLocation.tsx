import { useEffect, useRef, useState, type FormEvent } from 'react';
import { MapPin, Search, Loader2, X } from 'lucide-react';
import { Link } from 'react-router-dom';
import { useSession } from '@/lib/session';
import api from '@/lib/api';
import { digits, formatCep } from '@/lib/brFields';

export default function AccountLocation() {
  const { locationLabel, refresh } = useSession();
  const [open,setOpen] = useState(false), [cep,setCep] = useState(''), [city,setCity] = useState(''), [state,setState] = useState(''), [busy,setBusy] = useState(false), [error,setError] = useState('');
  const root = useRef<HTMLDivElement>(null), trigger = useRef<HTMLButtonElement>(null);
  useEffect(() => {
    if (!open) return;
    const close = (event: MouseEvent) => { if (!root.current?.contains(event.target as Node)) setOpen(false); };
    const key = (event: KeyboardEvent) => { if (event.key === 'Escape') { setOpen(false); trigger.current?.focus(); } };
    document.addEventListener('mousedown',close); document.addEventListener('keydown',key);
    return () => { document.removeEventListener('mousedown',close); document.removeEventListener('keydown',key); };
  },[open]);
  async function search() {
    if (digits(cep).length !== 8 || busy) return;
    setBusy(true);setError('');
    try { const {data} = await api.get(`/auth/postal-code/${digits(cep)}`); const row=data.data??data; setCity(row.city??row.localidade??'');setState(row.state??row.uf??''); }
    catch { setError('CEP não encontrado. Você também pode informar cidade e UF manualmente.'); }
    finally { setBusy(false); }
  }
  async function save(event: FormEvent) {
    event.preventDefault();if (busy) return;setBusy(true);setError('');
    try { await api.put('/auth/location',{city:city.trim(),state});await refresh();setOpen(false);trigger.current?.focus(); }
    catch { setError('Não foi possível salvar a localização. Confira cidade e UF e tente novamente.'); }
    finally { setBusy(false); }
  }
  return <div ref={root} className="fc-location">
    <button ref={trigger} type="button" aria-expanded={open} aria-label={`Localização de trabalho: ${locationLabel || 'não definida'}`} title="Definir cidade de trabalho" onClick={() => setOpen(value=>!value)} className="fc-location-trigger"><MapPin size={15} aria-hidden="true" /><span>{locationLabel || 'Definir localização'}</span></button>
    {open && <section className="fc-location-panel" aria-label="Definir localização de trabalho"><div className="flex items-center justify-between gap-2"><strong>Onde você trabalha?</strong><button type="button" aria-label="Fechar localização" onClick={()=>setOpen(false)}><X size={16}/></button></div><p className="mt-2 text-xs app-muted">Busque a cidade pelo CEP ou preencha abaixo. Salvamos apenas cidade e UF; não usamos GPS.</p>
      <div className="mt-4 flex gap-2"><input autoFocus aria-label="CEP para buscar cidade" inputMode="numeric" value={cep} onChange={event=>setCep(formatCep(event.target.value))} onKeyDown={event=>{if(event.key==='Enter'){event.preventDefault();void search();}}} maxLength={9} placeholder="00000-000" className="input"/><button type="button" aria-label="Buscar cidade pelo CEP" disabled={busy || digits(cep).length!==8} onClick={()=>void search()} className="rounded-lg border p-2 disabled:opacity-40">{busy?<Loader2 size={17} className="animate-spin"/>:<Search size={17}/>}</button></div>
      <form onSubmit={save} className="mt-3 space-y-3"><label className="block text-xs">Cidade *<input required maxLength={120} className="input mt-1" value={city} onChange={event=>setCity(event.target.value)}/></label><label className="block text-xs">UF *<select required className="input mt-1" value={state} onChange={event=>setState(event.target.value)}><option value="">Selecione</option>{'AC AL AP AM BA CE DF ES GO MA MT MS MG PA PB PR PE PI RJ RN RS RO RR SC SP SE TO'.split(' ').map(uf=><option key={uf}>{uf}</option>)}</select></label>{error&&<p role="alert" className="text-xs text-red-600 dark:text-red-300">{error}</p>}<button disabled={busy} className="fc-button w-full px-3 py-2 text-sm">{busy?'Aguarde…':'Salvar localização'}</button></form><Link className="mt-3 block text-xs text-primary-600" to="/app/configuracoes#perfil">Ver dados do perfil</Link></section>}
  </div>;
}
