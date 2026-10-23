<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantProductSubscription;
use App\Models\User;
use App\Services\RegistrationVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformProviderWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function stripe(array $data, ?int $time = null, bool $valid = true)
    {
        $raw = json_encode($data);
        $time ??= now()->timestamp;
        $signature = hash_hmac('sha256', $time.'.'.$raw, $valid ? 'fixture-signing-key' : 'wrong');

        return $this->call('POST', '/api/platform-provider-webhooks/stripe', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => 't='.$time.',v1='.$signature], $raw);
    }

    public function test_stripe_disabled_stale_invalid_and_duplicate_callbacks_do_not_invent_receipts(): void
    {
        $data = ['id' => 'evt_test', 'type' => 'invoice.paid', 'data' => ['object' => []]];
        $this->stripe($data)->assertStatus(503);
        config(['services.stripe.native_webhooks_enabled' => true, 'services.stripe.webhook_secret' => 'fixture-signing-key']);
        $this->stripe($data, now()->subMinutes(6)->timestamp)->assertUnauthorized();
        $this->stripe($data, valid: false)->assertUnauthorized();
        $this->stripe($data)->assertUnprocessable();
        $this->assertDatabaseCount('billing_transactions', 0);
        $this->assertDatabaseCount('platform_provider_receipts', 0);
        $tenant = Tenant::create(['nome' => 'QA', 'slug' => 'webhook-qa']);
        TenantProductSubscription::create(['tenant_id' => $tenant->id, 'product_slug' => 'corretor', 'plan_slug' => 'corretor_monthly', 'payment_provider' => 'stripe', 'external_subscription_id' => 'sub_owned', 'status' => 'trial', 'active' => true]);
        $data['data']['object'] = ['id' => 'in_owned', 'subscription' => 'sub_owned', 'status' => 'paid', 'paid' => true, 'amount_paid' => 14900, 'currency' => 'brl', 'status_transitions' => ['paid_at' => now()->timestamp], 'customer_email' => 'PRIVATE_CUSTOMER', 'metadata' => ['tenant_id' => 9999]];
        $this->stripe($data)->assertOk()->assertJsonPath('status', 'paid_recorded_access_unchanged');
        $this->stripe($data)->assertOk()->assertJsonPath('duplicate', true);
        $data['id'] = 'evt_another_same_invoice';
        $this->stripe($data)->assertOk();
        $this->assertDatabaseCount('billing_transactions', 1);
        $this->assertDatabaseHas('billing_transactions', ['tenant_id' => $tenant->id, 'amount_minor' => 14900]);
        $this->assertStringNotContainsString('PRIVATE_CUSTOMER', json_encode(DB::table('platform_provider_receipts')->get()));
        $this->assertDatabaseHas('tenant_product_subscriptions', ['tenant_id' => $tenant->id, 'status' => 'trial']);
    }

    public function test_resend_uses_installed_sdk_signature_and_updates_only_correlated_metadata(): void
    {
        config(['services.resend.native_webhooks_enabled' => true, 'services.resend.webhook_secret' => 'whsec_'.base64_encode('fixture-svix-key')]);
        $message = 'email_uuid_fixture';
        $delivery = (string) Str::uuid();
        DB::table('platform_email_deliveries')->insert(['id' => $delivery, 'provider' => 'resend', 'message_hash' => hash_hmac('sha256', $message, config('app.key')), 'status' => 'accepted', 'created_at' => now(), 'updated_at' => now()]);
        $raw = json_encode(['type' => 'email.bounced', 'created_at' => now()->subSeconds(2)->toIso8601String(), 'data' => ['email_id' => $message, 'bounce' => ['type' => 'Permanent'], 'to' => ['PRIVATE_EMAIL'], 'subject' => 'PRIVATE_SUBJECT']]);
        $time = time();
        $id = 'msg_fixture';
        $sig = 'v1,'.base64_encode(hash_hmac('sha256', $id.'.'.$time.'.'.$raw, 'fixture-svix-key', true));
        $send = fn ($signature) => $this->call('POST', '/api/platform-provider-webhooks/resend', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_SVIX_ID' => $id, 'HTTP_SVIX_TIMESTAMP' => (string) $time, 'HTTP_SVIX_SIGNATURE' => $signature], $raw);
        $send('v1,invalid')->assertUnauthorized();
        $send($sig)->assertOk()->assertJsonPath('status', 'bounced');
        $send($sig)->assertOk()->assertJsonPath('duplicate', true);
        $this->assertDatabaseHas('platform_email_deliveries', ['id' => $delivery, 'status' => 'bounced', 'bounce_type' => 'permanent']);
        $stored = json_encode(DB::table('platform_email_deliveries')->get()).json_encode(DB::table('platform_provider_receipts')->get());
        foreach (['PRIVATE_EMAIL', 'PRIVATE_SUBJECT', $message] as $private) {
            $this->assertStringNotContainsString($private, $stored);
        }
    }

    public function test_resend_early_callback_retries_and_equal_timestamp_delivery_advances(): void
    {
        config(['services.resend.native_webhooks_enabled' => true, 'services.resend.webhook_secret' => 'whsec_'.base64_encode('fixture-svix-key')]);
        $message = 'early_message';
        $at = now()->subSeconds(2)->startOfSecond();
        $send = function (string $id, string $type) use ($message, $at) {
            $raw = json_encode(['type' => $type, 'created_at' => $at->toIso8601String(), 'data' => ['email_id' => $message]]);
            $time = time();
            $sig = 'v1,'.base64_encode(hash_hmac('sha256', $id.'.'.$time.'.'.$raw, 'fixture-svix-key', true));

            return $this->call('POST', '/api/platform-provider-webhooks/resend', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_SVIX_ID' => $id, 'HTTP_SVIX_TIMESTAMP' => (string) $time, 'HTTP_SVIX_SIGNATURE' => $sig], $raw);
        };
        $send('msg_early', 'email.delivered')->assertStatus(503);
        $this->assertDatabaseCount('platform_provider_receipts', 0);
        $id = (string) Str::uuid();
        DB::table('platform_email_deliveries')->insert(['id' => $id, 'provider' => 'resend', 'message_hash' => hash_hmac('sha256', $message, config('app.key')), 'status' => 'accepted', 'occurred_at' => $at, 'created_at' => now(), 'updated_at' => now()]);
        $send('msg_early', 'email.delivered')->assertOk()->assertJsonPath('status', 'delivered');
        $send('msg_late_sent', 'email.sent')->assertOk()->assertJsonPath('status', 'state_preserved');
        $this->assertDatabaseHas('platform_email_deliveries', ['id' => $id, 'status' => 'delivered']);
    }

    public function test_verification_delivery_belongs_to_recipient_instead_of_current_operator(): void
    {
        $recipient = User::factory()->create();
        $operator = User::factory()->create();
        $this->actingAs($operator);
        $this->assertSame('local', app(RegistrationVerificationService::class)->send($recipient));
        $this->assertDatabaseHas('platform_email_deliveries', ['user_id' => $recipient->id, 'status' => 'accepted']);
        $this->assertDatabaseMissing('platform_email_deliveries', ['user_id' => $operator->id]);
        $this->assertFalse(request()->attributes->has('platform_email_recipient'));
    }

    public function test_actual_array_mail_transport_acceptance_does_not_claim_delivery(): void
    {
        Mail::raw('PRIVATE_BODY', fn ($m) => $m->to('qa@example.test')->subject('PRIVATE_TITLE'));
        $this->assertDatabaseHas('platform_email_deliveries', ['provider' => 'array', 'status' => 'accepted']);
        $stored = json_encode(DB::table('platform_email_deliveries')->get());
        foreach (['PRIVATE_BODY', 'PRIVATE_TITLE', 'qa@example.test', 'delivered'] as $private) {
            $this->assertStringNotContainsString($private, $stored);
        }
    }
}
