export type ManualProjectForm = {
  propriedade_id: string;
  nome: string;
  descricao: string;
  orcamento_total: number;
  data_inicio: string;
  data_termino_prevista: string;
  status: string;
};

export function manualProjectPayload(form: ManualProjectForm) {
  return {
    propriedade_id: Number(form.propriedade_id),
    nome: form.nome.trim(),
    orcamento_total: Number(form.orcamento_total || 0),
    status: form.status,
    ...(form.descricao.trim() ? { descricao: form.descricao.trim() } : {}),
    ...(form.data_inicio ? { data_inicio: form.data_inicio } : {}),
    ...(form.data_termino_prevista ? { data_termino_prevista: form.data_termino_prevista } : {}),
  };
}
