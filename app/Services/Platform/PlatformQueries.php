<?php

namespace App\Services\Platform;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlatformQueries
{
    public function filters(Request $r): array
    {

        $f = $r->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from', 'product' => 'nullable|in:corretor,imobiliaria,house_flipper', 'tenant_id' => 'nullable|integer|min:1', 'user_id' => 'nullable|integer|min:1', 'status' => 'nullable|string|max:40', 'severity' => 'nullable|in:info,low,medium,high,critical', 'search' => 'nullable|string|max:180', 'plan' => 'nullable|string|max:80', 'category' => 'nullable|in:api,auth,security,audit,analytics,email', 'page' => 'nullable|integer|min:1|max:10000', 'per_page' => 'nullable|integer|between:1,100']);

        $f['from'] = Carbon::parse($f['from'] ?? now()->subDays(29)->toDateString())->startOfDay();

        $f['to'] = Carbon::parse($f['to'] ?? now()->toDateString())->endOfDay();

        abort_if($f['from']->diffInDays($f['to']) > config('observability.max_window_days'), 422, 'Selecione até 93 dias por consulta.');

        return $f;

    }

    public function scope(Builder $q, array $f, string $product = 'product', string $date = 'created_at'): Builder
    {

        $q->whereBetween($date, [$f['from'], $f['to']]);

        foreach (['tenant_id', 'user_id'] as $key) {
            if (! empty($f[$key])) {
                $q->where($key, $f[$key]);
            }
        }

        if (! empty($f['product'])) {
            $q->where($product, $f['product']);
        }

        return $q;

    }

    public function page(Builder $q, Request $r): array
    {

        $p = $q->simplePaginate((int) $r->input('per_page', 25));

        return ['data' => $p->items(), 'page' => $p->currentPage(), 'has_more' => $p->hasMorePages(), 'per_page' => $p->perPage()];

    }

    public function events(Request $r, ?string $category = null): array
    {

        $f = $this->filters($r);
        $q = $this->scope(DB::table('platform_events'), $f);

        if ($category ?? ($f['category'] ?? null)) {
            $q->where('category', $category ?? $f['category']);
        }

        if (! empty($f['severity'])) {
            $q->where('severity', $f['severity']);
        }

        if (! empty($f['status'])) {
            $q->where('status', $f['status']);
        }

        if (! empty($f['search'])) {
            $s = $f['search'];
            if (Str::isUuid($s)) {
                $q->where(fn ($q) => $q->where('request_id', $s)->orWhere('trace_id', $s));
            } elseif (ctype_digit($s)) {
                $q->where('id', $s);
            } elseif (preg_match('/^[a-f0-9]{64}$/D', $s)) {
                $q->where('ip_hash', $s);
            } else {
                $q->where('event', 'like', '%'.addcslashes($s, '%_').'%');
            }
        }

        return $this->page($q->orderByDesc('id'), $r);

    }

    public function clients(Request $r): array
    {

        $f = $this->filters($r);
        $q = DB::table('users')->select('id', 'tenant_id', 'name', 'email', 'ativo_flg', 'email_verified_at', 'created_at');

        $q->whereBetween('created_at', [$f['from'], $f['to']]);

        if (! empty($f['tenant_id'])) {
            $q->where('tenant_id', $f['tenant_id']);
        }

        if (! empty($f['user_id'])) {
            $q->where('id', $f['user_id']);
        }

        if (! empty($f['search'])) {
            $s = addcslashes($f['search'], '%_');
            $q->where(fn ($q) => $q->where('name', 'like', '%'.$s.'%')->orWhere('email', 'like', '%'.$s.'%')->orWhere('id', ctype_digit($s) ? $s : 0));
        }

        if (($f['status'] ?? '') === 'suspended') {
            $q->where('ativo_flg', false);
        }

        if (! empty($f['product']) || ! empty($f['plan']) || in_array($f['status'] ?? '', ['trial', 'active', 'cancelled', 'past_due'])) {
            $q->whereExists(function ($sq) use ($f) {
                $sq->selectRaw('1')->from('tenant_product_subscriptions as s')->whereColumn('s.tenant_id', 'users.tenant_id');
                foreach (['product' => 'product_slug', 'plan' => 'plan_slug'] as $k => $col) {
                    if (! empty($f[$k])) {
                        $sq->where('s.'.$col, $f[$k]);
                    }
                } if (in_array($f['status'] ?? '', ['trial', 'active', 'cancelled', 'past_due'])) {
                    $sq->where('s.status', $f['status']);
                }
            });
        }

        $result = $this->page($q->orderByDesc('id'), $r);

        $tenantIds = array_unique(array_filter(array_column($result['data'], 'tenant_id')));

        $subscriptions = DB::table('tenant_product_subscriptions')->whereIn('tenant_id', $tenantIds)->select('tenant_id', 'product_slug', 'plan_slug', 'status', 'trial_ends_at')->get()->groupBy('tenant_id');

        $activity = DB::table('platform_events')->whereIn('user_id', array_column($result['data'], 'id'))->where('category', 'api')->selectRaw('user_id,MAX(created_at) as last_activity')->groupBy('user_id')->pluck('last_activity', 'user_id');
        foreach ($result['data'] as $row) {
            $row->subscriptions = $subscriptions->get($row->tenant_id, collect());
            $row->last_activity = $activity->get($row->id);
        }

        return $result;

    }

    public function client(int $id, Request $r): array
    {

        $user = DB::table('users')->select('id', 'tenant_id', 'name', 'email', 'ativo_flg', 'email_verified_at', 'created_at')->where('id', $id)->first();
        abort_unless($user, 404);
        $user->is_platform_admin = User::findOrFail($id)->isPlatformAdmin();

        return ['user' => $user, 'financial' => $user->tenant_id ? app(BillingLedger::class)->timeline(['tenant_id' => (int) $user->tenant_id]) : [], 'subscriptions' => DB::table('tenant_product_subscriptions')->where('tenant_id', $user->tenant_id)->select('id', 'product_slug', 'plan_slug', 'status', 'trial_starts_at', 'trial_ends_at', 'current_period_end', 'created_at')->get(),

            'sessions' => DB::table('personal_access_tokens')->where('tokenable_type', User::class)->where('tokenable_id', $id)->select('id', 'created_at', 'last_used_at', 'expires_at')->orderByDesc('id')->limit(100)->get(),

            'activity' => DB::table('platform_events')->where('user_id', $id)->orderByDesc('id')->limit(100)->get(),

            'ai' => DB::table('ai_tool_runs')->where('user_id', $id)->selectRaw('tool,status,count(*) as total,avg(latency_ms) as latency_ms')->groupBy('tool', 'status')->limit(100)->get(),

            'jobs' => DB::table('property_processing_jobs')->where('user_id', $id)->select('id', 'property_visualization_id', 'kind', 'status', 'started_at', 'completed_at', 'created_at')->latest()->limit(50)->get(),

            'notes' => DB::table('platform_controls')->where('kind', 'support')->where('key', (string) $id)->value('value'),

            'revenue' => null, 'country' => null, 'message' => 'Conteúdo de conversas, fotos, tokens e resultados privados não são exibidos. Atividade é limitada à telemetria coletada.'];

    }

    public function overview(Request $r): array
    {

        $f = $this->filters($r);
        $key = 'platform:overview:'.hash('sha256', json_encode($f));

        return Cache::remember($key, 30, function () use ($f) {

            $users = DB::table('users');
            if (! empty($f['tenant_id'])) {
                $users->where('tenant_id', $f['tenant_id']);
            }

            if (! empty($f['user_id'])) {
                $users->where('id', $f['user_id']);
            }

            if (! empty($f['product'])) {
                $users->whereExists(fn ($q) => $q->selectRaw('1')->from('tenant_product_subscriptions as s')->whereColumn('s.tenant_id', 'users.tenant_id')->where('s.product_slug', $f['product']));
            }

            $counts = (clone $users)->selectRaw('count(*) as total, sum(case when email_verified_at is not null then 1 else 0 end) as verified, sum(case when ativo_flg = 0 then 1 else 0 end) as suspended')->first();

            $e = DB::table('platform_events')->where('category', 'api')->where('status', '<', 400)->where('created_at', '>=', now()->subDays(30));
            foreach (['tenant_id', 'user_id', 'product'] as $key) {
                if (! empty($f[$key])) {
                    $e->where($key, $f[$key]);
                }
            }
            $countsActive = $e->selectRaw('COUNT(DISTINCT CASE WHEN created_at >= ? THEN user_id END) as d1, COUNT(DISTINCT CASE WHEN created_at >= ? THEN user_id END) as d7, COUNT(DISTINCT user_id) as d30', [now()->subDays(1), now()->subDays(7)])->first();
            $active = [1 => (int) $countsActive->d1, 7 => (int) $countsActive->d7, 30 => (int) $countsActive->d30];

            $sub = DB::table('tenant_product_subscriptions');
            if (! empty($f['user_id'])) {
                $sub->whereIn('tenant_id', DB::table('users')->select('tenant_id')->where('id', $f['user_id']));
            } if (! empty($f['tenant_id'])) {
                $sub->where('tenant_id', $f['tenant_id']);
            }if (! empty($f['product'])) {
                $sub->where('product_slug', $f['product']);
            }

            $events = $this->scope(DB::table('platform_events'), $f);

            return ['users' => $counts, 'active_observed' => $active, 'new_users' => (clone $users)->whereBetween('created_at', [$f['from'], $f['to']])->count(),

                'subscriptions' => $sub->selectRaw('product_slug,status,count(*) as total')->groupBy('product_slug', 'status')->get(),

                'readiness' => Cache::remember('platform:readiness', 30, fn () => app(ProductionReadiness::class)->snapshot()),
                'requests' => (clone $events)->where('category', 'api')->count(), 'critical_open' => DB::table('platform_incidents')->whereIn('status', ['open', 'investigating'])->where('severity', 'critical')->count(),

                'activity_series' => (clone $events)->where('category', 'api')->where('status', '<', 400)->selectRaw('DATE(created_at) as day,count(distinct user_id) as total')->groupByRaw('DATE(created_at)')->orderBy('day')->get(),

                'security' => (clone $events)->where('category', 'security')->selectRaw('severity,count(*) as total')->groupBy('severity')->get(),

                'revenue' => ['status' => 'not_configured', 'mrr' => null, 'arr' => null, 'reason' => 'Não há ledger de recebimentos conciliados. Assinatura ativa não comprova pagamento.'],

                'observed_since' => DB::table('platform_events')->min('created_at'), 'checked_at' => now()->toIso8601String()];

        });

    }

    public function subscriptions(Request $r, string $mode = 'all'): array
    {

        $f = $this->filters($r);
        $q = DB::table('tenant_product_subscriptions')->select('id', 'tenant_id', 'product_slug', 'plan_slug', 'active', 'status', 'trial_starts_at', 'trial_ends_at', 'current_period_end', 'created_at', 'updated_at');

        $sf = $f;
        unset($sf['user_id']);
        $this->scope($q, $sf, 'product_slug', $mode === 'trials' ? 'trial_ends_at' : ($mode === 'churn' ? 'updated_at' : 'created_at'));
        if (! empty($f['user_id'])) {
            $q->whereIn('tenant_id', DB::table('users')->select('tenant_id')->where('id', $f['user_id']));
        } if (! empty($f['plan'])) {
            $q->where('plan_slug', $f['plan']);
        }

        if ($mode === 'trials') {
            $q->whereNotNull('trial_ends_at')->where('trial_ends_at', '<', now())->where('status', '!=', 'active');
        } elseif ($mode === 'churn') {
            $q->where('status', 'cancelled');
        } elseif (! empty($f['status'])) {
            $q->where('status', $f['status']);
        }

        if ($mode === 'trials') {
            $feature = DB::connection()->getQueryGrammar()->wrap('metadata->feature');
            $q->selectSub(function ($events) use ($feature) {
                $events->from('platform_events')->selectRaw('COUNT(DISTINCT '.$feature.')')
                    ->whereColumn('platform_events.tenant_id', 'tenant_product_subscriptions.tenant_id')
                    ->whereColumn('platform_events.product', 'tenant_product_subscriptions.product_slug')
                    ->whereColumn('platform_events.created_at', '>=', 'tenant_product_subscriptions.trial_starts_at')
                    ->whereColumn('platform_events.created_at', '<=', 'tenant_product_subscriptions.trial_ends_at')
                    ->where('platform_events.event', 'product.feature.used');
            }, 'observed_features');
        }
        $result = $this->page($q->latest('id'), $r);

        foreach ($result['data'] as $row) {
            $row->reason = null;
            $row->paid_revenue = null;
            if ($mode === 'trials') {
                $used = (int) $row->observed_features;
                $row->observed_features = $used;
                $row->classification = $used ? 'expired_with_observed_usage' : 'expired_usage_not_measured';
            }
        }

        return $result;

    }

    public function funnel(Request $r): array
    {

        $f = $this->filters($r);
        $users = DB::table('users')->whereBetween('created_at', [$f['from'], $f['to']]);

        if (! empty($f['tenant_id'])) {
            $users->where('tenant_id', $f['tenant_id']);
        }

        if (! empty($f['user_id'])) {
            $users->where('id', $f['user_id']);
        }

        if (! empty($f['product'])) {
            $users->whereExists(fn ($q) => $q->selectRaw('1')->from('tenant_product_subscriptions as s')->whereColumn('s.tenant_id', 'users.tenant_id')->where('s.product_slug', $f['product']));
        }

        $registered = (clone $users)->count();
        $verified = (clone $users)->whereNotNull('email_verified_at')->count();

        $trial = (clone $users)->whereNotNull('email_verified_at')->whereExists(function ($q) use ($f) {
            $q->selectRaw('1')->from('tenant_product_subscriptions as s')->whereColumn('s.tenant_id', 'users.tenant_id')->whereNotNull('s.trial_starts_at')->whereBetween('s.trial_starts_at', [$f['from'], $f['to']]);
            if (! empty($f['product'])) {
                $q->where('s.product_slug', $f['product']);
            }
        })->count();

        return ['unit' => 'coorte de usuários cadastrados no período; trial por tenant compartilhado', 'stages' => [['stage' => 'Visitantes consentidos', 'value' => null], ['stage' => 'Cadastro', 'value' => $registered], ['stage' => 'E-mail confirmado', 'value' => $verified], ['stage' => 'Trial iniciado', 'value' => $trial], ['stage' => 'Checkout pago', 'value' => null]], 'verification_rate' => $registered ? round(100 * $verified / $registered, 2) : null, 'trial_after_verification_rate' => $verified ? round(100 * $trial / $verified, 2) : null, 'missing' => ['Visitantes/campanha/UTM não instrumentados', 'Checkout/renovação pagos não conciliados'], 'observed_since' => DB::table('platform_events')->min('created_at')];

    }

    public function cohorts(Request $r): array
    {

        $f = $this->filters($r);
        $q = DB::table('users')->select('id', 'created_at');
        $q->whereBetween('created_at', [$f['from'], $f['to']]);
        if (! empty($f['tenant_id'])) {
            $q->where('tenant_id', $f['tenant_id']);
        }if (! empty($f['user_id'])) {
            $q->where('id', $f['user_id']);
        }

        if (! empty($f['product'])) {
            $q->whereExists(fn ($s) => $s->selectRaw('1')->from('tenant_product_subscriptions as sub')->whereColumn('sub.tenant_id', 'users.tenant_id')->where('sub.product_slug', $f['product']));
        }

        $users = $q->orderBy('id')->limit(10001)->get();
        abort_if($users->count() > 10000, 422, 'Reduza o período da coorte (máximo 10 mil usuários).');

        $result = [];
        $since = DB::table('platform_events')->where('category', 'api')->min('created_at');

        $observations = DB::table('platform_events')->whereIn('user_id', $users->pluck('id'))->where('category', 'api')->where('status', '<', 400)->where('created_at', '>=', $f['from'])->where('created_at', '<', now()->startOfDay());
        if (! empty($f['product'])) {
            $observations->where('product', $f['product']);
        }
        $observed = $observations->selectRaw('user_id, DATE(created_at) as day')->groupBy('user_id')->groupByRaw('DATE(created_at)')->limit(100001)->get();
        abort_if($observed->count() > 100000, 422, 'Reduza o período; limite de 100 mil dias-usuário observados.');
        $lookup = [];
        foreach ($observed as $o) {
            $lookup[$o->user_id.'|'.$o->day] = true;
        }
        foreach ($users->groupBy(fn ($u) => substr($u->created_at, 0, 7)) as $month => $group) {
            $entry = ['month' => $month, 'users' => $group->count()];
            foreach ([1, 7, 14, 30, 60, 90] as $day) {
                $eligible = 0;
                $returned = 0;
                foreach ($group as $u) {
                    $target = Carbon::parse($u->created_at)->startOfDay()->addDays($day);
                    if (! $since || $target->isToday() || $target->isFuture() || $target->lt(Carbon::parse($since)->startOfDay()->addDay()) || $target->lt(now()->subDays((int) config('observability.retention.api', 30))->startOfDay())) {
                        continue;
                    }$eligible++;
                    if (isset($lookup[$u->id.'|'.$target->toDateString()])) {
                        $returned++;
                    }
                }$entry['d'.$day] = ['eligible' => $eligible, 'returned' => $returned, 'rate' => $eligible ? round(100 * $returned / $eligible, 2) : null];
            }$result[] = $entry;
        }

        return ['data' => $result, 'definition' => 'Retorno em dia calendário exato Dn; somente dias completos cobertos pela telemetria, sem inferir histórico.'];

    }
}
