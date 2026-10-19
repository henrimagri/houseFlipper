export type PropertyForm = {
  apelido: string;
  tipo: string;
  area_m2: string;
  quartos: string;
  banheiros: string;
  vagas: string;
  valor_aquisicao: string;
  data_aquisicao: string;
  valor_venda_alvo: string;
  status: string;
  observacoes: string;
  cep: string;
  logradouro: string;
  numero: string;
  complemento: string;
  bairro: string;
  cidade: string;
  estado: string;
  pais: string;
};

export const emptyPropertyForm: PropertyForm = {
  apelido: '', tipo: '', area_m2: '', quartos: '', banheiros: '', vagas: '', valor_aquisicao: '',
  data_aquisicao: '', valor_venda_alvo: '', status: 'prospeccao', observacoes: '', cep: '', logradouro: '',
  numero: '', complemento: '', bairro: '', cidade: '', estado: '', pais: 'Brasil',
};

const nullableNumber = (value: string) => value.trim() === '' ? null : Number(value);
const nullableText = (value: string) => value.trim() || null;

export function propertyPayload(form: PropertyForm) {
  return {
    apelido: form.apelido.trim(),
    tipo: nullableText(form.tipo),
    area_m2: nullableNumber(form.area_m2),
    quartos: nullableNumber(form.quartos),
    banheiros: nullableNumber(form.banheiros),
    vagas: nullableNumber(form.vagas),
    valor_aquisicao: nullableNumber(form.valor_aquisicao),
    data_aquisicao: nullableText(form.data_aquisicao),
    valor_venda_alvo: nullableNumber(form.valor_venda_alvo),
    status: form.status,
    observacoes: nullableText(form.observacoes),
    endereco: {
      cep: nullableText(form.cep.replace(/\D/g, '')),
      logradouro: nullableText(form.logradouro),
      numero: nullableText(form.numero),
      complemento: nullableText(form.complemento),
      bairro: nullableText(form.bairro),
      cidade: nullableText(form.cidade),
      estado: nullableText(form.estado.toUpperCase()),
      pais: form.pais.trim() || 'Brasil',
    },
  };
}
