<?php

namespace App\Http\Requests;

use App\Models\EventoCalendario;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class EventoCalendarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->tenant_id !== null;
    }

    public function rules(): array
    {
        $required = $this->isMethod('PATCH') ? 'sometimes' : 'required';

        return [
            'projeto_id' => [
                'nullable', 'integer',
                Rule::exists('projetos', 'id')->where('tenant_id', auth()->user()->tenant_id)->where('deletado_flg', false),
            ],
            'titulo' => [$required, 'string', 'max:180'],
            'descricao' => ['nullable', 'string', 'max:5000'],
            'categoria' => [$required, 'in:visita,obra,compra,pagamento,entrega,outro'],
            'inicio_em' => [$required, 'date', 'max:40'],
            'fim_em' => ['nullable', 'date', 'max:40'],
            'dia_inteiro' => ['sometimes', 'boolean'],
            'local' => ['nullable', 'string', 'max:180'],
            'endereco' => ['nullable', 'string', 'max:500'],
            'status' => ['sometimes', 'required', 'in:agendado,concluido,cancelado'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('inicio_em') || $validator->errors()->has('fim_em')) {
                return;
            }

            $event = $this->persistedEvent();
            $start = $this->effectiveDate('inicio_em', $event);
            $end = $this->effectiveDate('fim_em', $event);
            if ($start && $end && $end->lt($start)) {
                $validator->errors()->add('fim_em', 'O fim deve ser posterior ou igual ao início.');
            }
        }];
    }

    private function persistedEvent(): ?EventoCalendario
    {
        $id = $this->route('id');

        return is_numeric($id) ? EventoCalendario::ativos()->find((int) $id) : null;
    }

    private function effectiveDate(string $field, ?EventoCalendario $event): ?CarbonImmutable
    {
        $value = $this->exists($field) ? $this->input($field) : $event?->{$field};

        if (! filled($value)) {
            return null;
        }

        $date = CarbonImmutable::parse($value);

        // Eloquent persists these calendar values as the supplied wall-clock time.
        return CarbonImmutable::createFromFormat('Y-m-d H:i:s', $date->format('Y-m-d H:i:s'), 'UTC');
    }
}
