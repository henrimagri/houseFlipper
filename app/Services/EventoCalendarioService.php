<?php

namespace App\Services;

use App\Models\EventoCalendario;
use App\Models\Projeto;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EventoCalendarioService
{
    public function listar(array $filters)
    {
        $query = EventoCalendario::query()->ativos()->with(['projeto', 'criadoPor']);
        $query->where('inicio_em', '<=', $filters['data_fim'])
            ->where(function ($builder) use ($filters): void {
                $builder->where('fim_em', '>=', $filters['data_inicio'])
                    ->orWhere(function ($single) use ($filters): void {
                        $single->whereNull('fim_em')->where('inicio_em', '>=', $filters['data_inicio']);
                    });
            });
        foreach (['categoria', 'status', 'projeto_id'] as $filter) {
            if (isset($filters[$filter]) && $filters[$filter] !== '') {
                $query->where($filter, $filters[$filter]);
            }
        }

        return $query->orderBy('inicio_em')->orderBy('id')->paginate($filters['per_page'] ?? 100);
    }

    public function obter(int $id): ?EventoCalendario
    {
        return EventoCalendario::ativos()->with(['projeto', 'criadoPor'])->find($id);
    }

    public function criar(array $data, User $user): EventoCalendario
    {
        return DB::transaction(function () use ($data, $user): EventoCalendario {
            $this->lockProjects($data);

            return EventoCalendario::create(array_merge($data, ['criado_por' => $user->id]))
                ->load(['projeto', 'criadoPor']);
        });
    }

    public function atualizar(EventoCalendario $event, array $data): EventoCalendario
    {
        return DB::transaction(function () use ($event, $data): EventoCalendario {
            $this->lockProjects($data, $event);
            $event->update($data);

            return $event->fresh(['projeto', 'criadoPor']);
        });
    }

    public function excluir(EventoCalendario $event): void
    {
        $event->update(['deletado_flg' => true]);
    }

    private function lockProjects(array $data, ?EventoCalendario $event = null): void
    {
        $newProjectId = array_key_exists('projeto_id', $data) ? $data['projeto_id'] : $event?->projeto_id;
        $projectIds = array_values(array_unique(array_filter([$event?->projeto_id, $newProjectId])));
        if ($projectIds !== []) {
            Projeto::query()->whereIn('id', $projectIds)->orderBy('id')->lockForUpdate()->get();
        }
        if (filled($newProjectId)) {
            Projeto::ativos()->lockForUpdate()->findOrFail((int) $newProjectId);
        }
    }
}
