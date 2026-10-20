<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuthorizationStatus;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Models\CommercializationAuthorization;
use App\Models\CrmContact;
use App\Models\CrmTicket;
use App\Models\EventoCalendario;
use App\Models\Projeto;
use App\Models\Tarefa;
use App\Services\Ai\ProductEntitlement;
use App\Services\Workspace\OrganizationService;
use App\Support\ProductContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationCenterController extends Controller
{
    public function __construct(
        private readonly ProductEntitlement $entitlement,
        private readonly ProductContext $context,
        private readonly OrganizationService $organizations,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $product = $this->context->current($request);
        $items = [];

        if ($product === 'corretor' && $this->entitlement->canAccessProduct($user, 'corretor')) {
            $leads = CrmContact::query()->where('user_id', $user->id)->where('status', 'new')->latest('id')->limit(5)->get();
            foreach ($leads as $lead) {
                $items[] = ['id' => 'lead-'.$lead->id, 'product' => 'corretor', 'title' => 'Novo lead', 'body' => $lead->name, 'href' => '/app/corretor/leads/'.$lead->id];
            }
            $captures = CommercializationAuthorization::query()->where('user_id', $user->id)->where('status', AuthorizationStatus::Granted->value)->latest('id')->limit(3)->get();
            foreach ($captures as $capture) {
                $items[] = ['id' => 'cap-'.$capture->id, 'product' => 'corretor', 'title' => 'Captação autorizada', 'body' => 'Autorização #'.$capture->id, 'href' => '/app/corretor/captacao'];
            }
            $visits = EventoCalendario::query()->where('criado_por', $user->id)->where('inicio_em', '>=', now())->orderBy('inicio_em')->limit(3)->get();
            foreach ($visits as $visit) {
                $items[] = ['id' => 'evt-'.$visit->id, 'product' => 'corretor', 'title' => 'Visita agendada', 'body' => $visit->titulo, 'href' => '/app/corretor/agenda'];
            }
        }

        if ($product === 'imobiliaria' && $this->entitlement->canAccessProduct($user, 'imobiliaria')) {
            $membership = $this->organizations->membership($user);
            $open = CrmTicket::query()
                ->when($membership, fn ($query) => $query->where('organization_id', $membership->organization_id), fn ($query) => $query->whereRaw('1=0'))
                ->whereNull('assigned_user_id')->where('status', '!=', TicketStatus::Encerrado->value)->latest('id')->limit(5)->get();
            foreach ($open as $ticket) {
                $items[] = ['id' => 'tkt-'.$ticket->id, 'product' => 'imobiliaria', 'title' => 'Atendimento sem responsável', 'body' => $ticket->subject, 'href' => '/app/imobiliaria/atendimentos'];
            }
            $waiting = CrmContact::query()
                ->when($membership, fn ($query) => $query->where('organization_id', $membership->organization_id), fn ($query) => $query->whereRaw('1=0'))
                ->where('status', 'new')->latest('id')->limit(5)->get();
            foreach ($waiting as $contact) {
                $items[] = ['id' => 'wait-'.$contact->id, 'product' => 'imobiliaria', 'title' => 'Lead aguardando resposta', 'body' => $contact->name, 'href' => '/app/imobiliaria/clientes'];
            }
        }

        if ($product === 'house_flipper' && $this->entitlement->canAccessProduct($user, 'house_flipper')) {
            $overruns = Projeto::ativos()->where('orcamento_total', '>', 0)->whereColumn('custo_real', '>', 'orcamento_total')->limit(5)->get();
            foreach ($overruns as $project) {
                $items[] = ['id' => 'bud-'.$project->id, 'product' => 'house_flipper', 'title' => 'Orçamento ultrapassado', 'body' => $project->nome, 'href' => '/app/house-flipper/projetos'];
            }
            $tasks = Tarefa::query()->where('deletado_flg', false)->whereNotNull('data_prevista')->whereDate('data_prevista', '<=', now()->addDay()->toDateString())->whereNotIn('status', ['concluida', 'cancelada'])->limit(5)->get();
            foreach ($tasks as $task) {
                $items[] = ['id' => 'task-'.$task->id, 'product' => 'house_flipper', 'title' => 'Tarefa de obra', 'body' => $task->titulo, 'href' => '/app/house-flipper/tarefas'];
            }
        }

        return response()->json(['data' => $items, 'product' => $product]);
    }
}
