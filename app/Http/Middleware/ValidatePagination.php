<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ValidatePagination
{
    public function handle(Request $request, Closure $next)
    {
        $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'between:1,1000000'],
        ]);
        $rules = [];
        foreach (['search', 'busca', 'city', 'neighborhood', 'state', 'status', 'tipo', 'categoria', 'property_type', 'source', 'bank', 'system', 'status_pagamento'] as $key) {
            $rules[$key] = ['sometimes', 'nullable', 'string', 'max:255'];
        }
        if ($request->is('api/fornecedores')) {
            $rules['status'] = ['sometimes', 'nullable', 'in:ativo,inativo'];
        }
        if ($request->is('api/documentos')) {
            $rules['status'] = ['sometimes', 'nullable', 'in:ativo,em_revisao,aprovado,arquivado'];
        }
        if ($request->is('api/projetos')) {
            $rules['status'] = ['sometimes', 'nullable', 'in:planejamento,em_andamento,pausado,concluido,cancelado'];
        }
        if ($request->is('api/vendas')) {
            $rules['status'] = ['sometimes', 'nullable', 'in:proposta,negociacao,fechada,escriturada,cancelada'];
        }
        if ($request->is('api/despesas')) {
            $rules['status_pagamento'] = ['sometimes', 'nullable', 'in:pendente,pago,atrasado,cancelado'];
        }
        if ($request->is('api/categorias-despesa')) {
            $rules['tipo'] = ['sometimes', 'nullable', 'in:aquisicao,reforma,holding,taxas,venda,outros'];
        }
        if ($request->is('api/opportunities')) {
            $rules['status'] = ['sometimes', 'nullable', 'in:new,analyzing,interested,visit_scheduled,negotiating,proposal_sent,purchased,discarded,archived'];
            $rules['property_type'] = ['sometimes', 'nullable', 'in:house,apartment,land,commercial,auction,other'];
            $rules['source'] = ['sometimes', 'nullable', 'in:manual,olx,zap,vivareal,quintoandar,facebook,realtor,auction,other'];
            $rules['favorite'] = ['sometimes', 'nullable', 'boolean'];
            $rules['archived'] = ['sometimes', 'nullable', 'boolean'];
        }
        foreach (['propriedade_id', 'projeto_id', 'etapa_id', 'tarefa_id', 'categoria_despesa_id'] as $key) {
            $rules[$key] = ['sometimes', 'nullable', 'integer', 'min:1'];
        }
        foreach (['min_price', 'max_price', 'principal'] as $key) {
            $rules[$key] = ['sometimes', 'nullable', 'numeric', 'between:0,999999999999'];
        }
        foreach (['data_inicio', 'data_fim'] as $key) {
            $rules[$key] = ['sometimes', 'nullable', 'date', 'max:40'];
        }
        $rules['installments'] = ['sometimes', 'integer', 'between:1,600'];
        $rules['paid_installments'] = ['sometimes', 'integer', 'between:0,600'];
        $validator = Validator::make($request->query(), $rules);
        $validator->after(function ($validator) use ($request): void {
            if ($request->is('api/opportunities')
                && is_numeric($request->query('min_price'))
                && is_numeric($request->query('max_price'))
                && (float) $request->query('min_price') > (float) $request->query('max_price')) {
                $validator->errors()->add('max_price', 'O preço máximo deve ser maior ou igual ao preço mínimo.');
            }
        });
        $validator->validate();

        return $next($request);
    }
}
