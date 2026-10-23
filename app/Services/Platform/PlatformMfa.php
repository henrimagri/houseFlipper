<?php

namespace App\Services\Platform;

use App\Models\PlatformMfaCredential;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class PlatformMfa
{
    public function token(Request $r): PersonalAccessToken
    {
        $token = $r->user()->currentAccessToken();
        abort_unless($token instanceof PersonalAccessToken && $token->exists && $token->tokenable_type === User::class && (int) $token->tokenable_id === (int) $r->user()->id, 403, 'Use uma sessão autenticada por token.');

        return $token;
    }

    public function verified(Request $r, bool $recent = false): bool
    {
        $token = $this->token($r)->fresh();
        if ($this->usesLocalQaExemption($r)) {
            return true;
        }
        $field = $recent ? 'platform_recent_auth_at' : 'platform_mfa_at';
        $at = $token->getAttribute($field);

        return $at && Carbon::parse($at)->betweenIncluded(now()->subMinutes($recent ? 10 : 480), now())
            && PlatformMfaCredential::where('user_id', $r->user()->id)->whereNotNull('confirmed_at')->exists();
    }

    public function usesLocalQaExemption(Request $r): bool
    {
        if (! app()->environment('local') || config('database.connections.'.config('database.default').'.driver') !== 'sqlite') {
            return false;
        }
        $user = $r->user();
        $settings = config('platform.local_qa_mfa', []);
        $fingerprint = (string) ($settings['email_sha256'] ?? '');
        $expires = strtotime((string) ($settings['expires_at'] ?? ''));

        return $user && (int) ($settings['user_id'] ?? 0) > 0
            && (int) $user->id === (int) $settings['user_id']
            && preg_match('/\A[a-f0-9]{64}\z/', $fingerprint) === 1
            && hash_equals($fingerprint, hash('sha256', (string) $user->email))
            && $expires !== false && $expires > now()->timestamp
            && $user->ativo_flg && $user->hasVerifiedEmail() && $user->isPlatformAdmin();
    }

    public function password(Request $r): void
    {
        $r->validate(['password' => 'required|string|max:200']);
        abort_unless(Hash::check($r->string('password')->toString(), $r->user()->password), 403, 'Reautenticação inválida.');
    }

    public function consume(Request $r, bool $enrolment = false): PlatformMfaCredential
    {
        $r->validate(['code' => 'required|string|max:80']);
        $credential = PlatformMfaCredential::where('user_id', $r->user()->id)->lockForUpdate()->first();
        $valid = $credential && ($enrolment ? ! $credential->confirmed_at && $credential->pending_expires_at?->isFuture() : (bool) $credential->confirmed_at);
        $hashes = $credential?->recovery_hashes ?? [];
        $step = $valid ? app(Totp::class)->matchingStep($credential->secret, $r->string('code')->toString(), now()->timestamp) : null;
        $recovery = false;
        if ($valid && ! $enrolment && $step === null) {
            foreach ($hashes as $i => $hash) {
                if (Hash::check($r->string('code')->toString(), $hash)) {
                    unset($hashes[$i]);
                    $recovery = true;
                    break;
                }
            }
        }
        abort_unless($valid && ($recovery || ($step !== null && $step > $credential->last_step)), 422, 'Código inválido, expirado ou já utilizado.');
        // CAS also prevents replay when row locks are unavailable (e.g. SQLite).
        $changed = PlatformMfaCredential::whereKey($credential->id)->where('version', $credential->version)->update([
            'last_step' => $recovery ? $credential->last_step : $step,
            'recovery_hashes' => json_encode(array_values($hashes)), 'version' => $credential->version + 1,
            'updated_at' => now(),
        ]);
        abort_unless($changed === 1, 409, 'O código já foi utilizado.');
        $token = $this->token($r);
        $token->forceFill(['platform_mfa_at' => now(), 'platform_recent_auth_at' => now()])->save();
        app(EventRecorder::class)->audit($r, $recovery ? 'admin.mfa.recovery_used' : 'admin.mfa.success', 'session', $token->id);

        return $credential->fresh();
    }

    public function recoveryCodes(PlatformMfaCredential $credential): array
    {
        $codes = array_map(fn () => bin2hex(random_bytes(10)), range(1, 10));
        $credential->forceFill(['recovery_hashes' => array_map(fn ($c) => Hash::make($c), $codes)])->save();

        return $codes;
    }
}
