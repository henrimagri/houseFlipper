<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TenantProductSubscription;
use App\Services\Platform\BillingLedger;
use App\Services\Platform\EventRecorder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Resend\WebhookSignature;

class PlatformProviderWebhookController extends Controller
{
    public function receive(Request $r, string $provider)
    {
        abort_unless(in_array($provider, ['stripe', 'resend'], true), 404);
        abort_unless(config('services.'.$provider.'.native_webhooks_enabled'), 503, 'Callback nativo não habilitado.');
        $secret = (string) config('services.'.$provider.'.webhook_secret');
        abort_if($secret === '', 503, 'Signing secret não configurado.');
        $body = $r->getContent();
        abort_if(strlen($body) > 262144, 413);
        if ($provider === 'stripe') {
            $signature = $r->header('Stripe-Signature', '');
            preg_match('/(?:^|,)t=(\d+)(?:,|$)/', $signature, $time);
            preg_match_all('/(?:^|,)v1=([a-f0-9]{64})(?=,|$)/', $signature, $values);
            abort_unless(isset($time[1]) && abs(now()->timestamp - (int) $time[1]) <= 300, 401);
            $expected = hash_hmac('sha256', $time[1].'.'.$body, $secret);
            abort_unless(count(array_filter($values[1], fn ($v) => hash_equals($expected, $v))) > 0, 401);
        } else {
            try {
                WebhookSignature::verify($body, ['svix-id' => $r->header('svix-id', ''), 'svix-timestamp' => $r->header('svix-timestamp', ''), 'svix-signature' => $r->header('svix-signature', '')], $secret, 300);
            } catch (\Throwable) {
                abort(401, 'Assinatura inválida.');
            }
        }
        try {
            $data = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            abort(422);
        }
        $id = $provider === 'stripe' ? ($data['id'] ?? '') : $r->header('svix-id', '');
        $type = $data['type'] ?? '';
        abort_unless(is_string($id) && preg_match('/^[a-zA-Z0-9_-]{1,120}$/D', $id) && is_string($type) && preg_match('/^[a-zA-Z0-9._-]{1,80}$/D', $type), 422);

        return DB::transaction(function () use ($provider, $id, $type, $data, $r) {
            // Unique insert is atomic on MySQL and SQLite; duplicate callbacks never repeat effects.
            $inserted = DB::table('platform_provider_receipts')->insertOrIgnore(['provider' => $provider, 'external_id' => $id, 'event_type' => $type, 'status' => 'received', 'created_at' => now()]);
            if (! $inserted) {
                return response()->json(['duplicate' => true]);
            }
            $status = $provider === 'stripe' ? $this->stripe($data) : $this->resend($data);
            DB::table('platform_provider_receipts')->where('provider', $provider)->where('external_id', $id)->update(['status' => $status]);
            app(EventRecorder::class)->record('audit', 'provider.callback.verified', ['provider' => $provider, 'status' => $status, 'target_type' => 'provider_event', 'target_id' => $id], $r);

            return response()->json(['duplicate' => false, 'status' => $status]);
        });
    }

    private function stripe(array $data): string
    {
        if (($data['type'] ?? '') !== 'invoice.paid') {
            return 'unsupported_event';
        }
        $invoice = $data['data']['object'] ?? [];
        $external = $invoice['subscription'] ?? $invoice['parent']['subscription_details']['subscription'] ?? null;
        abort_unless(is_string($external) && is_string($invoice['id'] ?? null), 422);
        $subscriptions = TenantProductSubscription::where('payment_provider', 'stripe')->where('external_subscription_id', $external)->limit(2)->get();
        // Never trust tenant/product passed in arbitrary provider metadata.
        abort_unless($subscriptions->count() === 1 && $subscriptions->first()->external_subscription_id === $external, 422, 'Associação local da assinatura não conciliada.');
        $subscription = $subscriptions->first();
        abort_unless(($invoice['status'] ?? '') === 'paid' && ($invoice['paid'] ?? false) === true && is_int($invoice['amount_paid'] ?? null) && $invoice['amount_paid'] > 0 && is_int($invoice['status_transitions']['paid_at'] ?? null), 422);
        $currency = strtoupper((string) ($invoice['currency'] ?? ''));
        abort_unless(preg_match('/^[A-Z]{3}$/D', $currency), 422);
        app(BillingLedger::class)->recordVerified([
            'provider' => 'stripe', 'external_id' => $invoice['id'].':paid', 'tenant_id' => (int) $subscription->tenant_id, 'product' => $subscription->product_slug, 'subscription_id' => (int) $subscription->id, 'plan' => 'provider_invoice_unreconciled',
            'amount_minor' => $invoice['amount_paid'], 'currency' => $currency, 'type' => 'payment', 'status' => 'paid', 'evidence_source' => 'verified_provider', 'effective_at' => Carbon::createFromTimestampUTC($invoice['status_transitions']['paid_at'])->toIso8601String(),
        ]);

        // Invoice without verified recurring line schema cannot prove MRR or activate product access.
        return 'paid_recorded_access_unchanged';
    }

    private function resend(array $data): string
    {
        $status = match ($data['type']) {
            'email.sent' => 'accepted','email.delivered' => 'delivered','email.bounced' => 'bounced','email.complained' => 'complaint','email.failed' => 'failed','email.delivery_delayed' => 'delayed',default => null
        };
        if (! $status) {
            return 'unsupported_event';
        }
        $message = $data['data']['email_id'] ?? null;
        abort_unless(is_string($message) && strlen($message) <= 180, 422);
        $row = DB::table('platform_email_deliveries')->where('provider', 'resend')->where('message_hash', hash_hmac('sha256', $message, (string) config('app.key')))->lockForUpdate()->first();
        abort_unless($row, 503, 'Metadados de envio ainda não disponíveis; tente novamente.');
        try {
            $at = Carbon::parse($data['created_at'] ?? 'invalid');
        } catch (\Throwable) {
            abort(422);
        }
        abort_if($at->isFuture(), 422);
        if ($row->occurred_at && $at->lessThan(Carbon::parse($row->occurred_at))) {
            return 'older_event';
        }
        $rank = ['requested' => 0, 'accepted' => 1, 'delayed' => 2, 'delivered' => 3, 'failed' => 4, 'bounced' => 5, 'complaint' => 6];
        if (($rank[$status] ?? 0) <= ($rank[$row->status] ?? 0)) {
            return 'state_preserved';
        }
        $bounce = $data['data']['bounce']['type'] ?? null;
        $bounce = in_array($bounce, ['Permanent', 'Transient', 'Undetermined'], true) ? strtolower($bounce) : null;
        DB::table('platform_email_deliveries')->where('id', $row->id)->update(['status' => $status, 'bounce_type' => $bounce, 'occurred_at' => $at, 'updated_at' => now()]);

        return $status;
    }
}
