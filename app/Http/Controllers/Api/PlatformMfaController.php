<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlatformMfaCredential;
use App\Services\Platform\EventRecorder;
use App\Services\Platform\PlatformMfa;
use App\Services\Platform\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlatformMfaController extends Controller
{
    public function __construct(private PlatformMfa $mfa, private EventRecorder $events) {}

    private function response(array $data)
    {
        return response()->json($data)->header('Cache-Control', 'no-store, private');
    }

    public function status(Request $r)
    {
        $credential = PlatformMfaCredential::where('user_id', $r->user()->id)->first();

        return $this->response(['enrolled' => (bool) $credential?->confirmed_at, 'verified' => $this->mfa->verified($r), 'recent_auth' => $this->mfa->verified($r, true), 'local_qa_exemption' => $this->mfa->usesLocalQaExemption($r), 'recovery_remaining' => count($credential?->recovery_hashes ?? []), 'methods' => ['totp']]);
    }

    public function enrol(Request $r)
    {
        $this->mfa->password($r);
        $this->mfa->token($r);

        return DB::transaction(function () use ($r) {
            // Lock user as well, including initial enrolment with no credential row.
            DB::table('users')->where('id', $r->user()->id)->lockForUpdate()->first();
            abort_if(PlatformMfaCredential::where('user_id', $r->user()->id)->whereNotNull('confirmed_at')->exists(), 409, 'MFA já cadastrado.');
            $secret = app(Totp::class)->secret();
            PlatformMfaCredential::updateOrCreate(['user_id' => $r->user()->id], ['secret' => $secret, 'pending_expires_at' => now()->addMinutes(10), 'last_step' => -1]);
            $this->events->audit($r, 'admin.mfa.enrolment_started', 'user', $r->user()->id);

            return $this->response(['secret' => $secret, 'uri' => 'otpauth://totp/FlipControl:'.rawurlencode((string) $r->user()->id).'?secret='.$secret.'&issuer=FlipControl&algorithm=SHA1&digits=6&period=30', 'expires_in' => 600]);
        });
    }

    public function confirm(Request $r)
    {
        return DB::transaction(function () use ($r) {
            $c = $this->mfa->consume($r, true);
            $c->forceFill(['confirmed_at' => now(), 'pending_expires_at' => null])->save();
            $codes = $this->mfa->recoveryCodes($c);
            $this->events->audit($r, 'admin.mfa.enrolled', 'user', $r->user()->id);

            return $this->response(['verified' => true, 'recovery_codes' => $codes]);
        });
    }

    public function challenge(Request $r)
    {
        return DB::transaction(function () use ($r) {
            $this->mfa->consume($r);

            return $this->response(['verified' => true]);
        });
    }

    public function recoveries(Request $r)
    {
        $this->mfa->password($r);

        return DB::transaction(function () use ($r) {
            $c = $this->mfa->consume($r);
            $codes = $this->mfa->recoveryCodes($c);
            $this->events->audit($r, 'admin.mfa.recovery_regenerated', 'user', $r->user()->id);

            return $this->response(['recovery_codes' => $codes]);
        });
    }

    public function disable(Request $r)
    {
        $this->mfa->password($r);

        return DB::transaction(function () use ($r) {
            $c = $this->mfa->consume($r);
            $c->delete();
            $r->user()->tokens()->update(['platform_mfa_at' => null, 'platform_recent_auth_at' => null]);
            $this->events->audit($r, 'admin.mfa.reset', 'user', $r->user()->id);

            return $this->response(['message' => 'MFA removido. Recadastre para recuperar acesso administrativo.']);
        });
    }
}
