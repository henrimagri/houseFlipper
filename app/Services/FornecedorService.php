<?php

namespace App\Services;

use App\Models\Fornecedor;

class FornecedorService
{
    public function listar(array $filtros = [])
    {
        $query = Fornecedor::query()->ativos();

        if (! empty($filtros['busca'])) {
            $busca = $filtros['busca'];
            $query->where(function ($builder) use ($busca): void {
                $builder->where('nome', 'like', '%'.$busca.'%')
                    ->orWhere('contato', 'like', '%'.$busca.'%')
                    ->orWhere('email', 'like', '%'.$busca.'%');
            });
        }
        if (! empty($filtros['categoria'])) {
            $query->where('categoria', $filtros['categoria']);
        }
        if (isset($filtros['status']) && $filtros['status'] !== '') {
            $query->where('ativo_flg', $filtros['status'] === 'ativo');
        }

        return $query->orderBy('nome')->orderBy('id')->paginate($filtros['per_page'] ?? 25);
    }

    public function obter(int $id): ?Fornecedor
    {
        return Fornecedor::ativos()->find($id);
    }

    public function criar(array $dados): Fornecedor
    {
        return Fornecedor::create($dados);
    }

    public function atualizar(Fornecedor $fornecedor, array $dados): Fornecedor
    {
        $fornecedor->update($dados);

        return $fornecedor->fresh();
    }

    public function excluir(Fornecedor $fornecedor): void
    {
        $fornecedor->update(['deletado_flg' => true]);
    }
}
