import api from './api';

export type ResumoProjeto = {
  projeto_id: number;
  orcamento_total: number;
  custo_real: number;
  economia: number;
  consumido_pct: number;
  progresso_pct: number;
  etapas: {
    id: number;
    nome: string;
    orcamento: number;
    custo_real: number;
    economia: number;
    status: string;
    progresso_pct: number;
  }[];
  roi: {
    valor_aquisicao: number;
    total_despesas: number;
    custo_total: number;
    valor_liquido_venda: number;
    lucro: number;
    roi_pct: number;
    tem_venda: boolean;
  };
};

export type NovaObraPayload = {
  propriedade_id: number;
  nome: string;
  descricao?: string;
  data_inicio?: string;
  data_termino_prevista?: string;
  status?: string;
  orcamento_total?: number;
  orcamento_categorias?: {
    demolicao?: number;
    eletrica?: number;
    hidraulica?: number;
    acabamento?: number;
  };
  etapas?: Array<{
    nome: string;
    ordem?: number;
    orcamento?: number;
    status?: string;
  }>;
};

export type NovaDespesaPayload = {
  propriedade_id: number;
  projeto_id?: number;
  etapa_id?: number;
  categoria_despesa_id: number;
  descricao: string;
  valor: number;
  data_despesa: string;
  status_pagamento?: 'pendente' | 'pago' | 'atrasado';
  forma_pagamento?: string;
  observacoes?: string;
};

export const obrasApi = {
  listar: () => api.get('/projetos').then((r) => r.data),
  criar: (payload: NovaObraPayload) => api.post('/projetos', payload).then((r) => r.data.data),
  obter: (id: number) => api.get(`/projetos/${id}`).then((r) => r.data.data),
  resumo: (id: number) => api.get<ResumoProjeto>(`/projetos/${id}/resumo`).then((r) => r.data),
};

export const despesasApi = {
  criar: (payload: NovaDespesaPayload) => api.post('/despesas', payload).then((r) => r.data.data),
};

export const propriedadesApi = {
  listar: () => api.get('/propriedades').then((r) => r.data),
};
