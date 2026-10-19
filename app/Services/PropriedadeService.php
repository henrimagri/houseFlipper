<?php

namespace App\Services;

use App\Exceptions\PropertyInUseException;
use App\Models\Propriedade;
use App\Models\Venda;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PropriedadeService
{
    public function listar(array $filtros = [])
    {
        $query = Propriedade::query()
            ->ativos()
            ->with('endereco');

        if (! empty($filtros['status'])) {
            $query->where('status', $filtros['status']);
        }

        if (! empty($filtros['busca'])) {
            $query->where('apelido', 'like', '%'.$filtros['busca'].'%');
        }

        return $query->orderByDesc('data_inclusao')->paginate($filtros['per_page'] ?? 15);
    }

    public function obter(int $id): ?Propriedade
    {
        return Propriedade::ativos()->with('endereco')->find($id);
    }

    public function criar(array $dados): Propriedade
    {
        return DB::transaction(function () use ($dados) {
            $endereco = $dados['endereco'] ?? null;
            unset($dados['endereco']);
            $this->assertSoldStatusMatchesFinalSale(null, $dados);

            $propriedade = Propriedade::create($dados);

            if ($endereco) {
                $propriedade->endereco()->create($endereco);
            }

            return $propriedade->load('endereco');
        });
    }

    public function atualizar(Propriedade $propriedade, array $dados): Propriedade
    {
        return DB::transaction(function () use ($propriedade, $dados) {
            $propriedade = Propriedade::ativos()->lockForUpdate()->findOrFail($propriedade->id);
            $endereco = $dados['endereco'] ?? null;
            unset($dados['endereco']);
            $this->assertSoldStatusMatchesFinalSale($propriedade, $dados);

            $propriedade->update($dados);

            if ($endereco) {
                if ($propriedade->endereco) {
                    $propriedade->endereco->update($endereco);
                } else {
                    $propriedade->endereco()->create($endereco);
                }
            }

            return $propriedade->fresh('endereco');
        });
    }

    public function excluir(Propriedade $propriedade): void
    {
        DB::transaction(function () use ($propriedade): void {
            $locked = Propriedade::query()->lockForUpdate()->findOrFail($propriedade->id);
            $hasDependencies = $locked->projetos()->where('deletado_flg', false)->exists()
                || $locked->despesas()->where('deletado_flg', false)->exists()
                || $locked->vendas()->where('deletado_flg', false)->exists();

            if ($hasDependencies) {
                throw new PropertyInUseException('A propriedade possui registros ativos.');
            }

            $locked->forceFill(['deletado_flg' => true])->save();
        });
    }

    private function assertSoldStatusMatchesFinalSale(?Propriedade $property, array $data): void
    {
        if (! array_key_exists('status', $data)) {
            return;
        }

        $hasFinalSale = $property && Venda::ativos()
            ->where('propriedade_id', $property->id)
            ->whereIn('status', ['fechada', 'escriturada'])
            ->exists();
        $requestedSold = $data['status'] === 'vendida';

        if ($requestedSold !== (bool) $hasFinalSale) {
            throw ValidationException::withMessages([
                'status' => 'O status vendida é controlado pela venda final ativa do imóvel.',
            ]);
        }
    }
}
