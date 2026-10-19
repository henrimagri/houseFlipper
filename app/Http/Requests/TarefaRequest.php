<?php

namespace App\Http\Requests;

use App\Models\ProjetoEtapa;
use App\Models\Tarefa;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TarefaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->tenant_id !== null;
    }

    public function rules(): array
    {
        $tenantId = auth()->user()->tenant_id;
        $required = $this->isMethod('PATCH') ? 'sometimes' : 'required';

        return [
            'projeto_id' => [$required, 'integer', Rule::exists('projetos', 'id')->where('tenant_id', $tenantId)->where('deletado_flg', false)],
            'etapa_id' => ['nullable', 'integer', Rule::exists('projetos_etapas', 'id')->where('tenant_id', $tenantId)->where('deletado_flg', false)],
            'responsavel_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)->where('ativo_flg', true)->where('deletado_flg', false)],
            'titulo' => [$required, 'string', 'max:200'],
            'descricao' => ['nullable', 'string', 'max:5000'],
            'data_prevista' => ['nullable', 'date', 'max:40'],
            'data_conclusao' => ['nullable', 'date', 'max:40'],
            'prioridade' => [$required, 'in:baixa,media,alta,critica'],
            'status' => [$required, 'in:pendente,em_andamento,concluida,cancelada'],
            'custo_estimado' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'custo_real' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $task = $this->persistedTask();
            $projectId = $this->exists('projeto_id') ? $this->integer('projeto_id') : $task?->projeto_id;
            $stageId = $this->exists('etapa_id') && filled($this->input('etapa_id'))
                ? $this->integer('etapa_id')
                : ($this->exists('etapa_id') ? null : $task?->etapa_id);
            if ($stageId && $projectId) {
                $stage = ProjetoEtapa::ativos()->find($stageId);
                if ($stage && $stage->projeto_id !== $projectId) {
                    $validator->errors()->add('etapa_id', 'A etapa precisa pertencer ao projeto selecionado.');
                }
            }
            $status = $this->exists('status') ? $this->input('status') : $task?->status;
            if ($this->filled('data_conclusao') && $status !== 'concluida') {
                $validator->errors()->add('data_conclusao', 'A data de conclusão exige status concluída.');
            }
        }];
    }

    private function persistedTask(): ?Tarefa
    {
        $id = $this->route('tarefa');

        return is_numeric($id) ? Tarefa::ativos()->find((int) $id) : null;
    }
}
