<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuthorizationStatus;
use App\Enums\CommercialListingStatus;
use App\Http\Controllers\Controller;
use App\Models\BrokerSite;
use App\Models\CommercializationAuthorization;
use App\Models\CommercialListing;
use App\Models\CrmContact;
use App\Models\CrmProposal;
use App\Models\EventoCalendario;
use App\Models\Opportunity;
use App\Services\Ai\ProductEntitlement;
use App\Support\MetricPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrokerDashboardController extends Controller
{
    public function __construct(private readonly ProductEntitlement $entitlement) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->entitlement->assertProduct($user, 'corretor');
        [$from, $to] = MetricPeriod::fromRequest($request);
        $wallet = CommercialListing::query()->where('user_id', $user->id);
        $contacts = CrmContact::query()->where(function ($query) use ($user) {
            $query->where('user_id', $user->id)->orWhere('assigned_user_id', $user->id);
        });
        $site = BrokerSite::query()->where('user_id', $user->id)->first();
        $events = EventoCalendario::query()->where('criado_por', $user->id)->where('deletado_flg', false);
        $topViews = (clone $wallet)->orderByDesc('views_count')->limit(5)->get(['id', 'title', 'views_count', 'status']);

        return response()->json([
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'definitions' => [
                'wallet' => 'Imóveis da carteira comercial do corretor autenticado.',
                'published' => 'Imóveis com status published e autorização vigente no momento da consulta.',
                'capturing' => 'Imóveis em rascunho ou prontos, ainda não publicados.',
                'pending_authorization' => 'Autorizações de captação com status pending.',
                'new_leads' => 'Contatos criados no intervalo selecionado.',
                'unanswered' => 'Contatos com status new, independentemente do período.',
                'visits' => 'Eventos de calendário futuros com categoria visita ou título contendo visita.',
                'site_views' => 'Contador persistido no site do corretor. Zero se o site ainda não existe.',
            ],
            'kpis' => [
                'wallet' => (clone $wallet)->count(),
                'published' => (clone $wallet)->where('status', CommercialListingStatus::Published->value)->count(),
                'capturing' => (clone $wallet)->whereIn('status', [CommercialListingStatus::Draft->value, CommercialListingStatus::Ready->value])->count(),
                'pending_authorization' => CommercializationAuthorization::query()->where('user_id', $user->id)->where('status', AuthorizationStatus::Pending->value)->count(),
                'unavailable' => (clone $wallet)->whereIn('status', [CommercialListingStatus::Withdrawn->value, CommercialListingStatus::Paused->value])->count(),
                'favorites' => Opportunity::query()->where('user_id', $user->id)->where('is_favorite', true)->count(),
                'new_leads' => (clone $contacts)->whereBetween('created_at', [$from, $to])->count(),
                'unanswered' => (clone $contacts)->where('status', 'new')->count(),
                'visits_scheduled' => (clone $events)->where('inicio_em', '>=', now())->where(function ($query) {
                    $query->where('categoria', 'visita')->orWhere('titulo', 'like', '%visita%');
                })->count(),
                'open_proposals' => CrmProposal::query()->where('user_id', $user->id)->whereNull('deleted_at')->whereNotIn('status', ['won', 'lost', 'cancelled'])->count(),
                'site_views' => $site?->views_count ?? 0,
                'contacts_received' => (clone $contacts)->whereBetween('created_at', [$from, $to])->count(),
            ],
            'activity' => [
                'upcoming_visits' => (clone $events)->where('inicio_em', '>=', now())->orderBy('inicio_em')->limit(6)->get(['id', 'titulo', 'inicio_em', 'status', 'categoria']),
                'overdue_returns' => (clone $contacts)->whereNotNull('next_action_at')->where('next_action_at', '<', now())->whereNotIn('status', ['won', 'lost'])->limit(6)->get(['id', 'name', 'next_action_at', 'status']),
                'pending_captures' => CommercializationAuthorization::query()->where('user_id', $user->id)->where('status', AuthorizationStatus::Pending->value)->latest('id')->limit(6)->get(['id', 'status', 'opportunity_id', 'created_at']),
            ],
            'top_listings' => $topViews,
            'recent_opportunities' => Opportunity::query()->where('user_id', $user->id)->latest('id')->limit(6)->get(['id', 'title', 'city', 'status', 'asking_price']),
            'pending_leads' => (clone $contacts)->where('status', 'new')->latest('id')->limit(6)->get(['id', 'name', 'status', 'created_at', 'commercial_listing_id']),
            'recent_listings' => (clone $wallet)->latest('id')->limit(6)->get(['id', 'title', 'status', 'city', 'asking_price_cents']),
            'empty' => [
                'message' => 'Indicadores usam apenas registros da sua conta. Sem invenção de volume.',
            ],
        ]);
    }
}
