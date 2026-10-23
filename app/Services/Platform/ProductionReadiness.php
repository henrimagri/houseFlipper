<?php

namespace App\Services\Platform;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProductionReadiness
{
    public function snapshot(): array
    {
        $admins = User::where(fn ($q) => $q->whereIn('id', DB::table('platform_admins')->select('user_id'))->orWhereIn('email', config('platform.admin_emails', [])));
        $count = (clone $admins)->count();
        $enrolled = (clone $admins)->whereIn('id', DB::table('platform_mfa_credentials')->whereNotNull('confirmed_at')->select('user_id'))->count();
        $beats = app(OperationalHeartbeats::class)->snapshot()['data'];
        $health = Cache::get('platform:health');
        $checks = [
            ['key' => 'admin_mfa', 'status' => $count > 0 && $count === $enrolled ? 'approved' : 'blocked', 'evidence' => "$enrolled/$count administradores cadastrados; MFA exigido por token"],
            ['key' => 'scheduler', 'status' => $beats['scheduler']['status'] === 'online' ? 'approved' : ($beats['scheduler']['status'] === 'unconfigured' ? 'not_configured' : 'blocked'), 'evidence' => 'Heartbeat emitido apenas por execução observada, expira após 90s'],
            ['key' => 'queue', 'status' => $beats['queue']['status'] === 'online' ? 'partial' : ($beats['queue']['status'] === 'unconfigured' ? 'not_configured' : 'blocked'), 'evidence' => 'Último loop observado; cobertura de todas as filas e jobs longos não comprovada'],
            ['key' => 'mysql_validation', 'status' => 'blocked', 'evidence' => DB::connection()->getDriverName() === 'mysql' ? 'Conexão MySQL não comprova homologação; exigir relatório da suíte isolada' : 'Host atual validado em SQLite; homologação MySQL pendente'],
            ['key' => 'billing_ledger', 'status' => DB::table('billing_transactions')->exists() ? 'partial' : 'not_configured', 'evidence' => 'Fundação implementada; conciliação e checkout reais requerem validação de provedor'],
            ['key' => 'provider_webhooks', 'status' => DB::table('platform_provider_receipts')->exists() ? 'partial' : 'not_configured', 'evidence' => 'Callbacks autenticados observados não comprovam fluxo comercial completo'],
            ['key' => 'backup_restore', 'status' => 'not_configured', 'evidence' => 'Sem fonte verificável de backup e restore drill'],
            ['key' => 'ai_worker', 'status' => ($health['services']['fastapi']['status'] ?? null) === 'online' ? 'partial' : 'blocked', 'evidence' => 'Health protegido; ausência de heartbeat do processo não é substituída por configuração'],
            ['key' => 'quotas', 'status' => 'not_configured', 'evidence' => 'Nenhum limite comercial de plano é presumido'],
            ['key' => '3d', 'status' => 'partial', 'evidence' => 'Fase 8 e gate preservados; densificação CUDA ainda pendente'],
        ];
        $approved = count(array_filter($checks, fn ($c) => $c['status'] === 'approved'));

        return ['data' => $checks, 'score' => round(100 * $approved / count($checks), 1), 'blocking_checks' => count($checks) - $approved, 'method' => 'Checklist de pesos iguais; somente approved recebe 1, demais 0. Indicador operacional, não certificação comercial.', 'checked_at' => now()->toIso8601String()];
    }
}
