<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Platform\OperationalHeartbeats;
use App\Services\Platform\PlatformQueries;
use App\Services\Platform\ProductionReadiness;
use App\Services\Platform\RequestAggregates;
use App\Services\Platform\ResourceQuota;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlatformRuntimeController extends Controller
{
    public function healthHistory(Request $r)
    {
        $f = app(PlatformQueries::class)->filters($r);
        $data = DB::table('platform_health_samples')->whereBetween('bucket_at', [$f['from'], $f['to']])->selectRaw("service,count(*) as observed_minutes,SUM(case when status='online' then 1 else 0 end) as online_minutes,min(bucket_at) as first_observed,max(bucket_at) as last_observed")->groupBy('service')->get();

        return response()->json(['data' => $data, 'method' => 'Apenas minutos observados; intervalos sem coleta são desconhecidos. Não é SLA.']);
    }

    public function attribution(Request $r)
    {
        $f = app(PlatformQueries::class)->filters($r);
        abort_if(! empty($f['product']), 422, 'A atribuição de sessão não possui dimensão de produto.');
        $q = DB::table('platform_consent_attributions')->whereBetween('consented_at', [$f['from'], $f['to']]);
        if (! empty($f['tenant_id'])) {
            $q->where('tenant_id', $f['tenant_id']);
        }
        if (! empty($f['user_id'])) {
            $q->where('user_id', $f['user_id']);
        }
        $source = DB::connection()->getQueryGrammar()->wrap('utm->utm_source');
        $campaign = DB::connection()->getQueryGrammar()->wrap('utm->utm_campaign');

        return response()->json(['data' => $q->selectRaw($source.' as source, '.$campaign.' as campaign, count(*) as consented_sessions, count(user_id) as linked_registrations')->groupBy('utm->utm_source', 'utm->utm_campaign')->limit(100)->get(), 'method' => 'Somente sessões com consentimento explícito nos últimos 90 dias. Sessões não são pessoas únicas; vínculo ao cadastro não demonstra causalidade. Não mede marketing ou visitantes que recusaram.']);
    }

    public function quotas(Request $r)
    {
        $d = $r->validate(['tenant_id' => 'required|integer|min:1|exists:tenants,id', 'product' => 'required|in:corretor,imobiliaria,house_flipper']);

        return response()->json(app(ResourceQuota::class)->snapshot((int) $d['tenant_id'], $d['product']));
    }

    public function inventory(Request $r)
    {
        $queries = app(PlatformQueries::class);
        $f = $queries->filters($r);
        $q = DB::table('platform_storage_inventory');
        if (! empty($f['tenant_id'])) {
            $q->where('tenant_id', $f['tenant_id']);
        }
        if (! empty($f['product'])) {
            $q->where('product', $f['product']);
        }

        return response()->json(['data' => $q->selectRaw('tenant_id,product,disk,kind,referenced,count(*) as files,sum(bytes) as bytes,min(observed_at) as oldest_observation,max(observed_at) as latest_observation')->groupBy('tenant_id', 'product', 'disk', 'kind', 'referenced')->limit(1000)->get(), 'method' => 'Inventário limitado, fora de requests. Arquivos sem referência são candidatos a revisão, nunca exclusão automática. Datas antigas podem representar arquivos removidos; reconciliação ainda parcial.']);
    }

    public function readiness()
    {
        return response()->json(app(ProductionReadiness::class)->snapshot());
    }

    public function heartbeats()
    {
        return response()->json(app(OperationalHeartbeats::class)->snapshot());
    }

    public function apm(Request $r)
    {
        $f = app(PlatformQueries::class)->filters($r);
        abort_if(! empty($f['user_id']), 422, 'Agregados não possuem dimensão de usuário. Use requests individuais.');

        return response()->json(app(RequestAggregates::class)->summary($f['from']->toIso8601String(), $f['to']->copy()->addSecond()->startOfSecond()->toIso8601String(), isset($f['tenant_id']) ? (int) $f['tenant_id'] : null, $f['product'] ?? null));
    }

    public function slow(Request $r)
    {
        $queries = app(PlatformQueries::class);
        $f = $queries->filters($r);
        $q = $queries->scope(DB::table('platform_events'), $f)->where('category', 'api')->where('duration_ms', '>=', 1000)->select('id', 'route', 'method', 'duration_ms', 'request_id', 'user_id', 'tenant_id', 'status', 'created_at');

        return response()->json($queries->page($q->orderByDesc('id'), $r) + ['threshold_ms' => 1000]);
    }
}
