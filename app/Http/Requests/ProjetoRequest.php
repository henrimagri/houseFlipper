<?php

namespace App\Http\Requests;

use App\Models\Projeto;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProjetoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->tenant_id !== null;
    }

    public function rules(): array
    {
        $updating = $this->isMethod('PUT') || $this->isMethod('PATCH');
        $required = $this->isMethod('PATCH') ? 'sometimes' : 'required';

        return [
            'propriedade_id' => [
                $required, 'integer',
                Rule::exists('propriedades', 'id')->where('tenant_id', auth()->user()->tenant_id)->where('deletado_flg', false),
            ],
            'nome' => [$required, 'string', 'max:150'],
            'descricao' => ['nullable', 'string', 'max:5000'],
            'orcamento_total' => ['sometimes', 'required', 'numeric', 'min:0', 'max:999999999999'],
            'data_inicio' => ['nullable', 'date', 'max:40'],
            'data_termino_prevista' => ['nullable', 'date', 'max:40', 'after_or_equal:data_inicio'],
            'data_termino_real' => ['nullable', 'date', 'max:40'],
            'status' => ['sometimes', 'required', 'in:planejamento,em_andamento,pausado,concluido,cancelado'],

            'etapas' => $updating ? ['prohibited'] : ['sometimes', 'array', 'max:100'],
            'etapas.*' => ['array:nome,descricao,ordem,orcamento,data_inicio_prevista,data_termino_prevista,status'],
            'etapas.*.nome' => ['required_with:etapas', 'string', 'max:150'],
            'etapas.*.descricao' => ['nullable', 'string', 'max:5000'],
            'etapas.*.ordem' => ['sometimes', 'required', 'integer', 'between:0,2147483647'],
            'etapas.*.orcamento' => ['sometimes', 'required', 'numeric', 'min:0', 'max:999999999999'],
            'etapas.*.data_inicio_prevista' => ['nullable', 'date', 'max:40'],
            'etapas.*.data_termino_prevista' => ['nullable', 'date', 'max:40', 'after_or_equal:etapas.*.data_inicio_prevista'],
            'etapas.*.status' => ['sometimes', 'required', 'in:pendente,em_andamento,concluida,bloqueada'],

            'orcamento_categorias' => $updating ? ['prohibited'] : ['sometimes', 'array:demolicao,eletrica,hidraulica,acabamento'],
            'orcamento_categorias.demolicao' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'orcamento_categorias.eletrica' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'orcamento_categorias.hidraulica' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'orcamento_categorias.acabamento' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $project = $this->persistedProject();
            $status = (string) ($this->exists('status') ? $this->input('status') : ($project?->status ?? 'planejamento'));
            $start = $this->effectiveDate('data_inicio', $project);
            $plannedEnd = $this->effectiveDate('data_termino_prevista', $project);
            $actualEnd = $this->effectiveDate('data_termino_real', $project);

            if ($start && $plannedEnd && $plannedEnd->lt($start)) {
                $validator->errors()->add('data_termino_prevista', 'A data de término prevista não pode ser anterior ao início do projeto.');
            }
            if ($this->filled('data_termino_real') && $status !== 'concluido') {
                $validator->errors()->add('data_termino_real', 'A data de término real exige status concluído.');
            }
            if ($status === 'concluido' && $start && ($actualEnd ?? CarbonImmutable::today())->lt($start)) {
                $validator->errors()->add('data_termino_real', 'A data de término real não pode ser anterior ao início do projeto.');
            }
        }];
    }

    private function persistedProject(): ?Projeto
    {
        $id = $this->route('projeto') ?? $this->route('id');

        return is_numeric($id) ? Projeto::ativos()->find((int) $id) : null;
    }

    private function effectiveDate(string $field, ?Projeto $project): ?CarbonImmutable
    {
        $value = $this->exists($field) ? $this->input($field) : $project?->{$field};

        return filled($value) ? CarbonImmutable::parse($value) : null;
    }
}
