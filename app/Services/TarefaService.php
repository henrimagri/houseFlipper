<?php

namespace App\Services;

use App\Models\Projeto;
use App\Models\Tarefa;
use Illuminate\Support\Facades\DB;

class TarefaService
{
    public function listar(array $filters)
    {
        $query = Tarefa::query()->ativos()->with(['projeto', 'etapa', 'responsavel']);
        foreach (['projeto_id', 'status', 'prioridade'] as $filter) {
            if (isset($filters[$filter]) && $filters[$filter] !== '') {
                $query->where($filter, $filters[$filter]);
            }
        }
        if (! empty($filters['busca'])) {
            $query->where(function ($builder) use ($filters): void {
                $builder->where('titulo', 'like', '%'.$filters['busca'].'%')
                    ->orWhere('descricao', 'like', '%'.$filters['busca'].'%');
            });
        }
        if (! empty($filters['data_inicio'])) {
            $query->whereDate('data_prevista', '>=', $filters['data_inicio']);
        }
        if (! empty($filters['data_fim'])) {
            $query->whereDate('data_prevista', '<=', $filters['data_fim']);
        }

        return $query->orderByRaw('CASE prioridade WHEN ? THEN 1 WHEN ? THEN 2 WHEN ? THEN 3 ELSE 4 END', ['critica', 'alta', 'media'])
            ->orderByRaw('CASE WHEN data_prevista IS NULL THEN 1 ELSE 0 END')->orderBy('data_prevista')->orderBy('id')
            ->paginate($filters['per_page'] ?? 50);
    }

    public function obter(int $id): ?Tarefa
    {
        return Tarefa::ativos()->with(['projeto', 'etapa', 'responsavel'])->find($id);
    }

    public function criar(array $data): Tarefa
    {
        return DB::transaction(function () use ($data): Tarefa {
            $this->lockProjects($data);
            $task = Tarefa::create($this->normalizeCompletion($data));

            return $task->load(['projeto', 'etapa', 'responsavel']);
        });
    }

    public function atualizar(Tarefa $task, array $data): Tarefa
    {
        return DB::transaction(function () use ($task, $data): Tarefa {
            $this->lockProjects($data, $task);
            $locked = Tarefa::ativos()->lockForUpdate()->findOrFail($task->id);

            // A tarefa pode ter sido carregada antes de outra transação terminar.
            // Revalide os pais e derive os campos usando sempre a linha bloqueada.
            $this->lockProjects($data, $locked);
            $locked->update($this->normalizeCompletion($data, $locked));

            return $locked->fresh(['projeto', 'etapa', 'responsavel']);
        });
    }

    public function excluir(Tarefa $task): void
    {
        $task->deletado_flg = true;
        $task->save();
    }

    private function normalizeCompletion(array $data, ?Tarefa $task = null): array
    {
        $status = (string) ($data['status'] ?? $task?->status ?? 'pendente');
        if ($status === 'concluida') {
            if (! array_key_exists('data_conclusao', $data) || ! filled($data['data_conclusao'])) {
                $data['data_conclusao'] = $task?->status === 'concluida' && $task->data_conclusao
                    ? $task->data_conclusao->toDateString()
                    : now()->toDateString();
            }
        } else {
            $data['data_conclusao'] = null;
        }

        return $data;
    }

    private function lockProjects(array $data, ?Tarefa $task = null): void
    {
        $newProjectId = array_key_exists('projeto_id', $data) ? $data['projeto_id'] : $task?->projeto_id;
        $projectIds = array_values(array_unique(array_filter([$task?->projeto_id, $newProjectId])));
        Projeto::query()->whereIn('id', $projectIds)->orderBy('id')->lockForUpdate()->get();
        Projeto::ativos()->lockForUpdate()->findOrFail((int) $newProjectId);
    }
}
