<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\PlatformExportReport;
use App\Models\PlatformAdmin;
use App\Models\PlatformEvent;
use App\Models\PlatformMfaCredential;
use App\Models\User;
use App\Services\Platform\EventRecorder;
use App\Services\Platform\PlatformMfa;
use App\Services\Platform\PlatformQueries;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** All routes require auth:sanctum + active account + verified + platform-admin/MFA. */
class PlatformWorkflowController extends Controller
{
    public function __construct(private PlatformQueries $queries, private EventRecorder $events, private PlatformMfa $mfa) {}

    public function exports(Request $request)
    {
        $this->queries->filters($request);

        return response()->json($this->queries->page(DB::table('platform_export_jobs')->where('created_by', $request->user()->id)->select('id', 'report', 'status', 'rows', 'attempts', 'error_type', 'expires_at', 'created_at', 'updated_at')->latest('created_at'), $request));
    }

    public function storeExport(Request $request)
    {
        $request->validate(['report' => 'required|in:clients,trials,churn,events,jobs']);
        $filters = $this->queries->filters($request);
        $this->queueAvailable();
        unset($filters['page'], $filters['per_page']);
        foreach (['from', 'to'] as $date) {
            $filters[$date] = $filters[$date]->toIso8601String();
        }

        return DB::transaction(function () use ($request, $filters) {
            // A per-owner user lock serializes concurrent quota checks.
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_if(DB::table('platform_export_jobs')->where('created_by', $request->user()->id)->whereIn('status', ['queued', 'processing'])->where('expires_at', '>', now())->count() >= 3, 429, 'Limite de três exportações pendentes por administrador.');
            $id = (string) Str::uuid();
            DB::table('platform_export_jobs')->insert(['id' => $id, 'created_by' => $request->user()->id, 'report' => $request->string('report')->toString(), 'filters' => json_encode($filters, JSON_THROW_ON_ERROR), 'status' => 'queued', 'attempts' => 0, 'expires_at' => now()->addDay(), 'created_at' => now(), 'updated_at' => now()]);
            $this->events->audit($request, 'admin.export.queued', 'export', $id);
            PlatformExportReport::dispatch($id);

            return response()->json(['data' => ['id' => $id, 'status' => 'queued', 'expires_at' => now()->addDay()->toIso8601String()]], 202);
        });
    }

    public function downloadExport(Request $request, string $id)
    {
        $record = $this->ownedExport($request, $id);
        abort_if(now()->gte($record->expires_at), 410, 'Exportação expirada.');
        abort_unless($record->status === 'completed', 409, 'Exportação ainda não está disponível.');
        $path = PlatformExportReport::path($id);
        abort_unless(Storage::disk('local')->exists($path), 404);
        $this->events->audit($request, 'admin.export.downloaded', 'export', $id);

        return Storage::disk('local')->download($path, 'platform-'.$record->report.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function retryExport(Request $request, string $id)
    {
        $this->queueAvailable();

        return DB::transaction(function () use ($request, $id) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $record = $this->ownedExport($request, $id, true);
            abort_if(now()->gte($record->expires_at), 410, 'Exportação expirada.');
            abort_unless(in_array($record->status, ['failed', 'cancelled'], true) && $record->attempts < 3, 409, 'Somente exportações falhas/canceladas com tentativas restantes podem ser retomadas.');
            abort_if(DB::table('platform_export_jobs')->where('created_by', $request->user()->id)->whereIn('status', ['queued', 'processing'])->where('expires_at', '>', now())->count() >= 3, 429, 'Limite de três exportações pendentes por administrador.');
            DB::table('platform_export_jobs')->where('id', $id)->update(['status' => 'queued', 'rows' => null, 'error_type' => null, 'updated_at' => now()]);
            $this->events->audit($request, 'admin.export.retried', 'export', $id);
            PlatformExportReport::dispatch($id);

            return response()->json(['data' => ['id' => $id, 'status' => 'queued']], 202);
        });
    }

    public function cancelExport(Request $request, string $id)
    {
        return DB::transaction(function () use ($request, $id) {
            $record = $this->ownedExport($request, $id, true);
            abort_unless(in_array($record->status, ['queued', 'processing'], true), 409, 'Exportação não está em execução.');
            DB::table('platform_export_jobs')->where('id', $id)->update(['status' => 'cancelled', 'updated_at' => now()]);
            $this->events->audit($request, 'admin.export.cancelled', 'export', $id);

            return response()->json(['data' => ['id' => $id, 'status' => 'cancelled']]);
        });
    }

    private function ownedExport(Request $request, string $id, bool $lock = false): object
    {
        abort_unless(Str::isUuid($id), 404);
        $query = DB::table('platform_export_jobs')->where('id', $id)->where('created_by', $request->user()->id);
        if ($lock) {
            $query->lockForUpdate();
        }
        $record = $query->first();
        abort_unless($record, 404);

        return $record;
    }

    private function queueAvailable(): void
    {
        $connection = config('queue.default');
        $driver = config('queue.connections.'.$connection.'.driver');
        abort_unless(in_array($driver, ['database', 'redis', 'sqs', 'beanstalkd'], true), 503, 'Configure uma fila assíncrona e worker platform-ops; exportações grandes não rodam no request HTTP.');
        abort_unless(config('queue.connections.platform_ops.driver') === 'database' && (int) config('queue.connections.platform_ops.retry_after', 0) > 150, 503, 'Configure a conexão platform_ops com retry_after superior a 150 segundos.');
    }

    public function privacy(Request $request)
    {
        $filters = $this->queries->filters($request);
        $query = DB::table('platform_privacy_requests')->whereBetween('created_at', [$filters['from'], $filters['to']]);
        foreach (['tenant_id', 'user_id', 'status'] as $key) {
            if (! empty($filters[$key])) {
                $query->where($key, $filters[$key]);
            }
        }

        return response()->json($this->queries->page($query->orderByDesc('created_at'), $request));
    }

    public function storePrivacy(Request $request)
    {
        $data = $request->validate(['user_id' => 'required|integer|exists:users,id', 'type' => 'required|in:access,correction,deletion,export', 'notes' => 'nullable|string|max:2000']);
        $user = User::findOrFail($data['user_id']);

        return DB::transaction(function () use ($request, $data, $user) {
            $id = (string) Str::uuid();
            DB::table('platform_privacy_requests')->insert(['id' => $id, 'user_id' => $user->id, 'tenant_id' => $user->tenant_id, 'type' => $data['type'], 'status' => 'open', 'notes' => $data['notes'] ?? null, 'created_at' => now(), 'updated_at' => now()]);
            $this->events->audit($request, 'admin.privacy.created', 'privacy_request', $id, [], ['status' => 'open']);

            return response()->json(['data' => ['id' => $id, 'status' => 'open', 'tenant_id' => $user->tenant_id], 'note' => 'Solicitação registrada para análise humana; nenhum dado foi excluído ou exportado automaticamente.'], 201);
        });
    }

    public function updatePrivacy(Request $request, string $id)
    {
        abort_unless(Str::isUuid($id), 404);
        $data = $request->validate(['status' => ['required', Rule::in(['open', 'reviewing', 'awaiting_customer', 'approved', 'rejected', 'completed'])], 'assigned_user_id' => 'nullable|integer|exists:users,id', 'notes' => 'nullable|string|max:2000']);
        if (in_array($data['status'], ['rejected', 'completed'], true)) {
            $request->validate(['notes' => 'required|string|min:5|max:2000']);
        }
        if (! empty($data['assigned_user_id'])) {
            $assignee = User::findOrFail($data['assigned_user_id']);
            abort_unless($assignee->isPlatformAdmin() && $assignee->ativo_flg && $assignee->email_verified_at, 422, 'Responsável deve ser administrador ativo e verificado.');
        }

        return DB::transaction(function () use ($request, $id, $data) {
            $record = DB::table('platform_privacy_requests')->where('id', $id)->lockForUpdate()->first();
            abort_unless($record, 404);
            $transitions = ['open' => ['reviewing', 'rejected'], 'reviewing' => ['awaiting_customer', 'approved', 'rejected'], 'awaiting_customer' => ['reviewing', 'rejected'], 'approved' => ['completed', 'reviewing'], 'rejected' => ['reviewing'], 'completed' => []];
            abort_unless($record->status === $data['status'] || in_array($data['status'], $transitions[$record->status] ?? [], true), 422, 'Transição de solicitação inválida.');
            DB::table('platform_privacy_requests')->where('id', $id)->update($data + ['updated_at' => now()]);
            $this->events->audit($request, 'admin.privacy.updated', 'privacy_request', $id, ['status' => $record->status], ['status' => $data['status'], 'assigned_user_id' => $data['assigned_user_id'] ?? $record->assigned_user_id]);

            return response()->json(['data' => ['id' => $id, 'status' => $data['status']], 'note' => 'Atualização do workflow; nenhuma exclusão automática.']);
        });
    }

    public function reviewSignal(Request $request, int $id)
    {
        $data = $request->validate(['classification' => 'required|in:confirmed,probable,false_positive,expected']);

        return DB::transaction(function () use ($request, $id, $data) {
            $event = PlatformEvent::whereKey($id)->where('category', 'security')->lockForUpdate()->firstOrFail();
            $existing = DB::table('platform_signal_reviews')->where('event_id', $event->id)->first();
            DB::table('platform_signal_reviews')->updateOrInsert(['event_id' => $event->id], ['reviewed_by' => $request->user()->id, 'classification' => $data['classification'], 'created_at' => $existing?->created_at ?? now(), 'updated_at' => now()]);
            $this->events->record('audit', 'admin.signal.reviewed', ['target_type' => 'security_event', 'target_id' => (string) $id, 'outcome' => $data['classification']], $request);

            return response()->json(['data' => ['event_id' => $id, 'classification' => $data['classification']]]);
        });
    }

    public function administrators(Request $request)
    {
        $this->queries->filters($request);
        $query = User::query()->where(function ($users) {
            $users->whereIn('id', PlatformAdmin::query()->select('user_id'));
            $allowlist = config('platform.admin_emails', []);
            if ($allowlist) {
                $users->orWhereIn(DB::raw('LOWER(email)'), $allowlist);
            }
        })->select('id', 'name', 'email', 'ativo_flg', 'email_verified_at');
        $page = $query->orderBy('id')->simplePaginate((int) $request->input('per_page', 25));
        $ids = $page->getCollection()->pluck('id');
        $mfa = DB::table('platform_mfa_credentials')->whereIn('user_id', $ids)->pluck('confirmed_at', 'user_id');
        $sessions = DB::table('personal_access_tokens')->where('tokenable_type', User::class)->whereIn('tokenable_id', $ids)->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->selectRaw('tokenable_id, COUNT(*) as sessions')->groupBy('tokenable_id')->pluck('sessions', 'tokenable_id');
        $logins = DB::table('platform_events')->whereIn('user_id', $ids)->where('event', 'auth.login.success')->selectRaw('user_id, MAX(created_at) as at')->groupBy('user_id')->pluck('at', 'user_id');
        $actions = DB::table('platform_events')->whereIn('user_id', $ids)->where('category', 'audit')->where('event', 'like', 'admin.%')->selectRaw('user_id, MAX(created_at) as at')->groupBy('user_id')->pluck('at', 'user_id');
        $data = $page->getCollection()->map(fn ($user) => ['user_id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'active' => (bool) $user->ativo_flg, 'email_verified' => (bool) $user->email_verified_at, 'mfa_confirmed' => (bool) $mfa->get($user->id), 'sessions' => (int) $sessions->get($user->id, 0), 'last_observed_login' => $logins->get($user->id), 'last_observed_admin_action' => $actions->get($user->id), 'configured_access' => in_array(mb_strtolower($user->email), config('platform.admin_emails', []), true)]);

        return response()->json(['data' => $data, 'page' => $page->currentPage(), 'per_page' => $page->perPage(), 'has_more' => $page->hasMorePages()]);
    }

    public function adminAction(Request $request)
    {
        $data = $request->validate(['action' => 'required|in:grant,remove,reset_mfa', 'user_id' => 'required|integer|exists:users,id', 'confirmed' => 'required|accepted', 'reason' => 'required|in:security_review,customer_request,policy_violation,qa_validation']);
        $this->mfa->password($request);
        abort_unless($this->mfa->verified($request, true), 403, 'Valide novamente o MFA para esta ação sensível.');

        return DB::transaction(function () use ($request, $data) {
            // Lock registry in deterministic order: concurrent removals cannot
            // independently conclude that each is leaving another admin alive.
            $registry = PlatformAdmin::query()->orderBy('id')->lockForUpdate()->get();
            $actor = User::findOrFail($request->user()->id);
            abort_unless($actor->isPlatformAdmin() && $actor->ativo_flg && $actor->email_verified_at && $actor->tokens()->whereKey($request->user()->currentAccessToken()->id)->exists(), 403, 'Acesso administrativo foi revogado.');
            $target = User::whereKey($data['user_id'])->lockForUpdate()->firstOrFail();
            $configured = in_array(mb_strtolower($target->email), config('platform.admin_emails', []), true);
            if ($data['action'] === 'grant') {
                abort_unless($target->ativo_flg && $target->email_verified_at, 422, 'Administrador precisa de conta ativa e e-mail verificado.');
                PlatformAdmin::firstOrCreate(['user_id' => $target->id], ['email' => $target->email]);
            } else {
                abort_unless($target->isPlatformAdmin(), 422, 'Usuário não é administrador da plataforma.');
                abort_if((int) $target->id === (int) $request->user()->id, 422, 'Não remova nem redefina seu próprio acesso por este fluxo.');
                $eligible = User::query()->where('ativo_flg', true)->whereNotNull('email_verified_at')->where(function ($users) use ($registry) {
                    $users->whereIn('id', $registry->pluck('user_id'));
                    if ($allowlist = config('platform.admin_emails', [])) {
                        $users->orWhereIn(DB::raw('LOWER(email)'), $allowlist);
                    }
                })->count();
                abort_if($eligible <= 1 && $target->ativo_flg && $target->email_verified_at, 422, 'Operação não pode remover o último administrador elegível.');
                if ($data['action'] === 'remove') {
                    abort_if($configured, 422, 'Acesso definido na configuração deve ser removido na implantação autorizada.');
                    PlatformAdmin::where('user_id', $target->id)->delete();
                }
                PlatformMfaCredential::where('user_id', $target->id)->delete();
                $target->tokens()->delete();
            }
            $this->events->audit($request, 'admin.registry.'.$data['action'], 'user', $target->id, [], [], $data['reason']);

            return response()->json(['message' => 'Ação sobre administrador concluída e auditada.']);
        });
    }
}
