import { FormEvent, useState } from 'react';
import { Link } from 'react-router-dom';
import api from '@/lib/api';
import { ProductSlug } from '@/lib/products';
import StatefulButton from '@/ui/StatefulButton';

type Props = {
  intent: 'demo' | 'contact' | 'proposal' | 'question';
  product?: ProductSlug | '';
  sourcePath: string;
  submitLabel: string;
};

export default function LeadForm({ intent, product = '', sourcePath, submitLabel }: Props) {
  const [form, setForm] = useState({
    name: '',
    email: '',
    phone: '',
    product_slug: product,
    business_type: '',
    message: '',
    consent: false,
    website: '',
  });
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [done, setDone] = useState<{ email: boolean } | null>(null);

  async function submit(event: FormEvent) {
    event.preventDefault();
    setBusy(true);
    setError('');
    try {
      const { data } = await api.post('/public/saas-leads', {
        ...form,
        product_slug: form.product_slug || null,
        intent,
        source_path: sourcePath,
        consent: form.consent,
      });
      setDone({ email: Boolean(data.email_confirmation) });
    } catch (exception: any) {
      setError(exception.response?.status === 429
        ? 'Muitas solicitações deste endereço. Aguarde e tente novamente.'
        : exception.response?.data?.message ?? 'Não foi possível registrar a solicitação.');
    } finally {
      setBusy(false);
    }
  }

  if (done) {
    return (
      <div role="status" className="rounded-2xl border bg-white p-6 dark:bg-slate-900">
        <p className="font-semibold">Solicitação registrada.</p>
        <p className="mt-2 text-sm text-slate-600">Nossa equipe consulta a fila comercial interna. Isto não cria conta nem inicia teste.</p>
        {done.email && <p className="mt-2 text-sm text-slate-600">Enviamos uma confirmação para o e-mail informado.</p>}
      </div>
    );
  }

  const field = (key: 'name' | 'email' | 'phone', label: string, type = 'text', required = false) => (
    <label className="block text-sm font-medium">{label}
      <input required={required} className="input mt-1" type={type} value={String(form[key])} onChange={(event) => setForm((current) => ({ ...current, [key]: event.target.value }))} />
    </label>
  );

  return (
    <form onSubmit={submit} className="rounded-2xl border bg-white p-6 space-y-4 dark:bg-slate-900">
      {error && <p role="alert" className="text-sm text-red-700">{error}</p>}
      {field('name', 'Nome', 'text', true)}
      {field('email', 'E-mail', 'email', true)}
      {field('phone', 'Telefone (opcional)', 'tel')}
      <label className="block text-sm font-medium">Produto de interesse
        <select className="input mt-1" value={form.product_slug} onChange={(event) => setForm((current) => ({ ...current, product_slug: event.target.value as ProductSlug | '' }))}>
          <option value="">Ainda não sei</option>
          <option value="corretor">Corretor</option>
          <option value="imobiliaria">Imobiliária</option>
          <option value="house_flipper">House Flipper</option>
        </select>
      </label>
      <label className="block text-sm font-medium">Tipo de negócio
        <input className="input mt-1" value={form.business_type} onChange={(event) => setForm((current) => ({ ...current, business_type: event.target.value }))} placeholder="Autônomo, imobiliária, investidor..." />
      </label>
      <label className="block text-sm font-medium">Mensagem
        <textarea className="input mt-1 min-h-28" value={form.message} onChange={(event) => setForm((current) => ({ ...current, message: event.target.value }))} />
      </label>
      <label className="absolute left-[-9999px]" aria-hidden="true">Site
        <input tabIndex={-1} autoComplete="off" value={form.website} onChange={(event) => setForm((current) => ({ ...current, website: event.target.value }))} />
      </label>
      <label className="flex items-start gap-2 text-sm">
        <input type="checkbox" checked={form.consent} onChange={(event) => setForm((current) => ({ ...current, consent: event.target.checked }))} required />
        <span>Autorizo o contato comercial e li a <Link to="/privacidade" className="font-semibold text-primary-700">política de privacidade</Link>.</span>
      </label>
      <StatefulButton type="submit" state={busy ? 'loading' : 'idle'} loadingLabel="Enviando…" className="w-full">{submitLabel}</StatefulButton>
    </form>
  );
}
