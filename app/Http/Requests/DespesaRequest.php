<?php

namespace App\Http\Requests;

use App\Models\Despesa;
use App\Models\Projeto;
use App\Models\ProjetoEtapa;
use App\Models\Tarefa;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DespesaRequest extends FormRequest
{
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $expense = $this->persistedExpense();
            $propertyId = $this->effectiveId('propriedade_id', $expense);
            $projectId = $this->effectiveId('projeto_id', $expense);
            $stageId = $this->effectiveId('etapa_id', $expense);
            $taskId = $this->effectiveId('tarefa_id', $expense);
            $project = $projectId ? Projeto::ativos()->find($projectId) : null;
            $stage = $stageId ? ProjetoEtapa::ativos()->find($stageId) : null;
            $task = $taskId ? Tarefa::ativos()->find($taskId) : null;
            if ($project && (int) $project->propriedade_id !== $propertyId) {
                $validator->errors()->add('propriedade_id', 'A propriedade deve pertencer ao projeto selecionado.');
            }
            if ($stage && (int) $stage->projeto_id !== $projectId) {
                $validator->errors()->add('etapa_id', 'A etapa deve pertencer ao projeto selecionado.');
            }
            if ($task && ((int) $task->projeto_id !== $projectId || ($task->etapa_id && (int) $task->etapa_id !== $stageId))) {
                $validator->errors()->add('tarefa_id', 'A tarefa deve pertencer ao projeto e à etapa selecionados.');
            }
        }];
    }

    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->tenant_id !== null;
    }

    public function rules(): array
    {
        $tenantId = auth()->user()->tenant_id;
        $required = $this->isMethod('PATCH') ? 'sometimes' : 'required';

        return [
            'categoria_despesa_id' => [
                $required, 'integer',
                Rule::exists('categorias_despesa', 'id')->where('tenant_id', $tenantId)->where('deletado_flg', false),
            ],
            'descricao' => [$required, 'string', 'max:250'],
            'valor' => [$required, 'numeric', 'min:0', 'max:999999999999'],
            'data_despesa' => [$required, 'date', 'max:40'],
            'propriedade_id' => [
                'nullable', 'integer',
                Rule::exists('propriedades', 'id')->where('tenant_id', $tenantId)->where('deletado_flg', false),
            ],
            'projeto_id' => [
                'nullable', 'integer',
                Rule::exists('projetos', 'id')->where('tenant_id', $tenantId)->where('deletado_flg', false),
            ],
            'etapa_id' => [
                'nullable', 'integer',
                Rule::exists('projetos_etapas', 'id')->where('tenant_id', $tenantId)->where('deletado_flg', false),
            ],
            'tarefa_id' => [
                'nullable', 'integer',
                Rule::exists('tarefas', 'id')->where('tenant_id', $tenantId)->where('deletado_flg', false),
            ],
            'data_pagamento' => ['nullable', 'date', 'max:40'],
            'forma_pagamento' => ['nullable', 'string', 'max:40'],
            'status_pagamento' => ['sometimes', 'required', 'in:pendente,pago,atrasado,cancelado'],
            'documento_numero' => ['nullable', 'string', 'max:80'],
            'observacoes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    private function persistedExpense(): ?Despesa
    {
        $id = $this->route('despesa');

        return is_numeric($id) ? Despesa::ativos()->find((int) $id) : null;
    }

    private function effectiveId(string $field, ?Despesa $expense): ?int
    {
        $value = $this->exists($field) ? $this->input($field) : $expense?->{$field};

        return filled($value) ? (int) $value : null;
    }
}
