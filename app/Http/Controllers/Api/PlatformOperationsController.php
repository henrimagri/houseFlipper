<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Platform\HealthProbe;
use App\Services\Platform\PlatformQueries;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlatformOperationsController extends Controller
{
    public function __construct(private PlatformQueries $queries) {}

    public function overview(Request $r)
    {
        return response()->json($this->queries->overview($r));
    }

    public function clients(Request $r)
    {
        return response()->json($this->queries->clients($r));
    }

    public function client(Request $r, int $id)
    {
        return response()->json($this->queries->client($id, $r));
    }

    public function subscriptions(Request $r)
    {
        return response()->json($this->queries->subscriptions($r));
    }

    public function trials(Request $r)
    {
        return response()->json($this->queries->subscriptions($r, 'trials'));
    }

    public function churn(Request $r)
    {
        return response()->json($this->queries->subscriptions($r, 'churn'));
    }

    public function funnel(Request $r)
    {
        return response()->json($this->queries->funnel($r));
    }

    public function cohorts(Request $r)
    {
        return response()->json($this->queries->cohorts($r));
    }

    public function events(Request $r)
    {
        return response()->json($this->queries->events($r));
    }

    public function health(Request $r)
    {
        return response()->json(app(HealthProbe::class)->snapshot());
    }

    public function performance(Request $r)
    {
        $f = $this->queries->filters($r);
        $q = $this->queries->scope(DB::table('platform_events'), $f)->where('category', 'api');
        $rows = (clone $q)->orderByDesc('id')->limit(10000)->get(['route', 'status', 'duration_ms']);
        $groups = $rows->groupBy('route')->map(function ($group, $route) {
            $times = $group->pluck('duration_ms')->filter(fn ($v) => $v !== null)->sort()->values();
            $pct = fn ($p) => $times->isEmpty() ? null : $times[(int) floor(($times->count() - 1) * $p)];

            return ['route' => $route, 'requests' => $group->count(), 'errors' => $group->where('status', '>=', 400)->count(), 'p50_ms' => $pct(.5), 'p95_ms' => $pct(.95), 'p99_ms' => $pct(.99)];
        })->sortByDesc('p95_ms')->take(100)->values();

        return response()->json(['data' => $groups, 'sample_size' => $rows->count(), 'sample_limit' => 10000, 'definition' => 'Últimas até 10 mil requisições no filtro; percentis de amostra, não APM completo.', 'frontend_metrics' => null]);
    }

    public function errors(Request $r)
    {
        $f = $this->queries->filters($r);
        $q = $this->queries->scope(DB::table('platform_events'), $f)->where('category', 'api')->where('status', '>=', 400);
        if (! empty($f['status'])) {
            $q->where('status', $f['status']);
        }

        return response()->json($this->queries->page($q->selectRaw('route,status,count(*) as total,min(created_at) as first_seen,max(created_at) as last_seen')->groupBy('route', 'status')->orderByDesc('last_seen'), $r) + ['note' => 'Detalhes saneados por request ID nos eventos. Stack traces não são coletados.']);
    }

    public function search(Request $r)
    {
        $r->validate(['search' => 'required|string|min:1|max:180']);
        $s = $r->string('search')->toString();
        $db = DB::class;

        return response()->json(['users' => $db::table('users')->where(fn ($q) => $q->where('email', $s)->orWhere('id', ctype_digit($s) ? $s : 0))->limit(20)->get(['id', 'name', 'tenant_id']),
            'tenants' => $db::table('tenants')->where(fn ($q) => $q->where('slug', $s)->orWhere('id', ctype_digit($s) ? $s : 0))->limit(20)->get(['id', 'nome', 'slug']),
            'events' => $db::table('platform_events')->where(fn ($q) => $q->where('request_id', $s)->orWhere('trace_id', $s))->limit(30)->get(['id', 'request_id', 'event', 'created_at']),
            'jobs' => $db::table('property_processing_jobs')->where(fn ($q) => $q->where('worker_job_id', $s)->orWhere('id', ctype_digit($s) ? $s : 0))->limit(20)->get(['id', 'worker_job_id', 'status', 'tenant_id'])]);
    }
}
