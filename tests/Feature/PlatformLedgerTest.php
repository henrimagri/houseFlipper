<?php

namespace Tests\Feature;

use App\Models\BillingTransaction;
use App\Models\Tenant;
use App\Models\TenantProductSubscription;
use App\Services\Platform\BillingLedger;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class PlatformLedgerTest extends TestCase
{
    use RefreshDatabase;

    private function entry(array $changes = []): array
    {
        $tenant = Tenant::create(['nome' => 'Ledger QA', 'slug' => 'ledger-'.str()->uuid()]);
        $subscription = TenantProductSubscription::create(['tenant_id' => $tenant->id, 'product_slug' => 'corretor', 'plan_slug' => 'professional', 'status' => 'active', 'active' => true]);

        return array_replace([
            'provider' => 'stripe', 'external_id' => 'event-'.str()->uuid(),
            'tenant_id' => $tenant->id, 'subscription_id' => $subscription->id,
            'product' => 'corretor', 'plan' => 'professional', 'amount_minor' => 178800,
            'currency' => 'BRL', 'type' => 'payment', 'status' => 'paid', 'evidence_source' => 'reconciled_import',
            'effective_at' => '2026-01-01T00:00:00Z', 'service_period_start' => '2026-01-01T00:00:00Z',
            'service_period_end' => '2027-01-01T00:00:00Z', 'recurring_months' => 12,
        ], $changes);
    }

    private function reject(callable $action): void
    {
        try {
            $action();
            $this->fail('Expected rejected ledger input.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }

    public function test_absence_of_paid_evidence_never_invents_revenue(): void
    {
        $ledger = app(BillingLedger::class);
        $this->assertSame([], $ledger->metrics()['groups']);
        $this->assertFalse($ledger->metrics()['has_evidence']);
        $this->assertNull($ledger->metrics()['mrr']);
        $ledger->recordVerified($this->entry(['status' => 'pending']));
        $group = $ledger->metrics([], CarbonImmutable::parse('2026-05-01'))['groups'][0];
        $this->assertSame(0, $group['received_minor']);
        $this->assertSame(178800, $group['pending_minor']);
        $this->assertNull($group['mrr_minor']);
    }

    public function test_replay_is_idempotent_and_conflicting_replay_is_rejected(): void
    {
        $ledger = app(BillingLedger::class);
        $input = $this->entry();
        $record = $ledger->recordVerified($input);
        $this->assertSame($record->id, $ledger->recordVerified(array_reverse($input, true))->id);
        $this->assertDatabaseCount('billing_transactions', 1);
        $this->reject(fn () => $ledger->recordVerified(array_replace($input, ['amount_minor' => 999])));
        $this->assertDatabaseCount('billing_transactions', 1);
    }

    public function test_minor_units_ownership_and_payload_allowlist_are_enforced(): void
    {
        $ledger = app(BillingLedger::class);
        $input = $this->entry();
        foreach ([['amount_minor' => 149.00], ['amount_minor' => '14900'], ['amount_minor' => -10], ['amount_minor' => 0], ['currency' => 'brl'], ['product' => 'imobiliaria'], ['tenant_id' => $input['tenant_id'] + 20], ['payload' => ['card' => 'private']], ['evidence_source' => 'unverified_webhook'], ['recurring_months' => 1]] as $changes) {
            $this->reject(fn () => $ledger->recordVerified(array_replace($input, $changes)));
        }
        $this->assertDatabaseCount('billing_transactions', 0);
    }

    public function test_annual_and_quarterly_mrr_are_normalized_scoped_and_expire(): void
    {
        $ledger = app(BillingLedger::class);
        $annual = $this->entry();
        $ledger->recordVerified($annual);
        $quarter = $this->entry(['currency' => 'USD', 'amount_minor' => 42000, 'plan' => 'quarterly', 'service_period_end' => '2026-04-01T00:00:00Z', 'recurring_months' => 3]);
        $ledger->recordVerified($quarter);
        $brl = $ledger->metrics(['currency' => 'BRL', 'tenant_id' => $annual['tenant_id']], CarbonImmutable::parse('2026-02-01'))['groups'][0];
        $this->assertSame(178800, $brl['received_minor']);
        $this->assertSame(14900, $brl['mrr_minor']);
        $this->assertSame(178800, $brl['arr_minor']);
        $usd = $ledger->metrics(['plan' => 'quarterly'], CarbonImmutable::parse('2026-02-01'))['groups'][0];
        $this->assertSame('USD', $usd['currency']);
        $this->assertSame(14000, $usd['mrr_minor']);
        $this->assertNull($ledger->metrics(['currency' => 'USD'], CarbonImmutable::parse('2026-05-01'))['groups'][0]['mrr_minor']);
        $this->assertSame([], $ledger->metrics(['product' => 'imobiliaria'])['groups']);
    }

    public function test_status_changes_append_and_do_not_duplicate_payment(): void
    {
        $ledger = app(BillingLedger::class);
        $input = $this->entry(['status' => 'pending']);
        $pending = $ledger->recordVerified($input);
        $paidInput = array_replace($input, ['external_id' => 'paid-event', 'supersedes_id' => $pending->id, 'status' => 'paid', 'effective_at' => '2026-01-02T00:00:00Z']);
        $paid = $ledger->recordVerified($paidInput);
        $this->assertDatabaseCount('billing_transactions', 2);
        $group = $ledger->metrics([], CarbonImmutable::parse('2026-02-01'))['groups'][0];
        $this->assertSame(178800, $group['received_minor']);
        $this->assertSame(0, $group['pending_minor']);
        $this->reject(fn () => $ledger->recordVerified(array_replace($paidInput, ['external_id' => 'branch-event'])));
        $this->reject(fn () => $ledger->recordVerified(array_replace($paidInput, ['external_id' => 'regress-event', 'supersedes_id' => $paid->id, 'status' => 'failed'])));
        try {
            $paid->update(['amount_minor' => 1]);
            $this->fail('Ledger update must fail.');
        } catch (LogicException) {
            $this->assertSame(178800, $paid->fresh()->amount_minor);
        }
        try {
            $paid->delete();
            $this->fail('Ledger delete must fail.');
        } catch (LogicException) {
            $this->assertDatabaseCount('billing_transactions', 2);
        }
    }

    public function test_refunds_are_linked_conserved_and_net_recurring_revenue(): void
    {
        $ledger = app(BillingLedger::class);
        $input = $this->entry();
        $payment = $ledger->recordVerified($input);
        $refund = array_replace($input, ['external_id' => 'refund-1', 'type' => 'refund', 'amount_minor' => 89400, 'refund_of_id' => $payment->id, 'effective_at' => '2026-02-01T00:00:00Z', 'service_period_start' => null, 'service_period_end' => null, 'recurring_months' => null]);
        $ledger->recordVerified($refund);
        $group = $ledger->metrics([], CarbonImmutable::parse('2026-03-01'))['groups'][0];
        $this->assertSame(89400, $group['refunds_minor']);
        $this->assertSame(89400, $group['net_received_minor']);
        $this->assertSame(7450, $group['mrr_minor']);
        $this->reject(fn () => $ledger->recordVerified(array_replace($refund, ['external_id' => 'refund-excess', 'amount_minor' => 89401])));
        $this->reject(fn () => $ledger->recordVerified(array_replace($refund, ['external_id' => 'refund-currency', 'currency' => 'USD'])));
        $ledger->recordVerified(array_replace($refund, ['external_id' => 'refund-2']));
        $ledger->recordVerified(array_replace($input, ['external_id' => 'payment-refunded', 'status' => 'refunded', 'supersedes_id' => $payment->id, 'effective_at' => '2026-02-02T00:00:00Z']));
        $group = $ledger->metrics([], CarbonImmutable::parse('2026-03-01'))['groups'][0];
        $this->assertSame(0, $group['net_received_minor']);
    }

    public function test_timeline_is_sanitized_limited_and_tenant_isolated(): void
    {
        $ledger = app(BillingLedger::class);
        $input = $this->entry();
        $ledger->recordVerified($input);
        $ledger->recordVerified($this->entry());
        $timeline = $ledger->timeline(['tenant_id' => $input['tenant_id']], 1);
        $this->assertCount(1, $timeline);
        foreach (['external_id', 'record_hash', 'payload', 'card', 'secret'] as $field) {
            $this->assertArrayNotHasKey($field, $timeline[0]);
        }
        $this->assertSame($input['tenant_id'], $timeline[0]['tenant_id']);
        $this->assertCount(2, BillingTransaction::all());
    }

    public function test_as_of_ignores_future_lifecycle_events_and_refunds(): void
    {
        $ledger = app(BillingLedger::class);
        $input = $this->entry(['status' => 'pending']);
        $pending = $ledger->recordVerified($input);
        $paid = $ledger->recordVerified(array_replace($input, ['external_id' => 'paid-later', 'status' => 'paid', 'supersedes_id' => $pending->id, 'effective_at' => '2026-02-01T00:00:00Z']));
        $ledger->recordVerified(array_replace($input, ['external_id' => 'refund-later', 'status' => 'paid', 'type' => 'refund', 'refund_of_id' => $pending->id, 'amount_minor' => 12000, 'effective_at' => '2026-04-01T00:00:00Z', 'service_period_start' => null, 'service_period_end' => null, 'recurring_months' => null]));
        $january = $ledger->metrics([], CarbonImmutable::parse('2026-01-15'))['groups'][0];
        $this->assertSame(178800, $january['pending_minor']);
        $this->assertSame(0, $january['received_minor']);
        $march = $ledger->metrics([], CarbonImmutable::parse('2026-03-01'))['groups'][0];
        $this->assertSame(14900, $march['mrr_minor']);
        $this->assertSame(0, $march['refunds_minor']);
        $this->assertSame(178800, $paid->amount_minor);
    }

    public function test_full_refund_preserves_original_receipt_period(): void
    {
        $ledger = app(BillingLedger::class);
        $input = $this->entry();
        $payment = $ledger->recordVerified($input);
        $ledger->recordVerified(array_replace($input, ['external_id' => 'refund-all', 'type' => 'refund', 'refund_of_id' => $payment->id, 'effective_at' => '2026-02-01T00:00:00Z', 'service_period_start' => null, 'service_period_end' => null, 'recurring_months' => null]));
        $ledger->recordVerified(array_replace($input, ['external_id' => 'refunded-status', 'status' => 'refunded', 'supersedes_id' => $payment->id, 'effective_at' => '2026-02-02T00:00:00Z']));
        $january = $ledger->metrics(['from' => '2026-01-01', 'to' => '2026-01-31T23:59:59Z'], CarbonImmutable::parse('2026-03-01'))['groups'][0];
        $this->assertSame(178800, $january['received_minor']);
        $this->assertSame(0, $january['refunds_minor']);
        $this->assertSame(0, $january['mrr_minor']);
        $february = $ledger->metrics(['from' => '2026-02-01', 'to' => '2026-02-28T23:59:59Z'], CarbonImmutable::parse('2026-03-01'))['groups'][0];
        $this->assertSame(0, $february['received_minor']);
        $this->assertSame(178800, $february['refunds_minor']);
        $this->assertSame(-178800, $february['net_received_minor']);
    }

    public function test_ambiguous_overlapping_terms_do_not_inflate_mrr(): void
    {
        $ledger = app(BillingLedger::class);
        $input = $this->entry();
        $ledger->recordVerified($input);
        $ledger->recordVerified(array_replace($input, ['external_id' => 'overlapping-payment']));
        $group = $ledger->metrics([], CarbonImmutable::parse('2026-03-01'))['groups'][0];
        $this->assertNull($group['mrr_minor']);
        $this->assertSame(357600, $group['received_minor']);
        $this->assertSame(0, $group['recurring_evidence_count']);
    }
}
