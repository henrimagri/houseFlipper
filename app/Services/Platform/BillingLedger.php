<?php

namespace App\Services\Platform;

use App\Models\BillingTransaction;
use App\Models\TenantProductSubscription;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BillingLedger
{
    /**
     * Internal trust boundary: only a signature-verified provider adapter or an
     * authorized reconciled import may call this. It does not verify webhooks.
     * external_id identifies this immutable event, not a mutable invoice ID.
     */
    public function recordVerified(array $input): BillingTransaction
    {
        $data = $this->normalize($input);
        $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));

        try {
            return DB::transaction(function () use ($data, $hash) {
                // Serialize updates/refunds against this subscription, including
                // competing refunds whose individual amounts would otherwise pass.
                $subscription = TenantProductSubscription::query()->lockForUpdate()->find($data['subscription_id']);
                if (! $subscription || (int) $subscription->tenant_id !== $data['tenant_id'] || $subscription->product_slug !== $data['product']) {
                    $this->reject('subscription_id', 'Assinatura não pertence ao tenant e produto informados.');
                }

                $existing = BillingTransaction::query()->where('provider', $data['provider'])->where('external_id', $data['external_id'])->first();
                if ($existing) {
                    if (! hash_equals($existing->record_hash, $hash)) {
                        $this->reject('external_id', 'Identificador já registrado com conteúdo diferente.');
                    }

                    return $existing;
                }

                $previous = null;
                if ($data['supersedes_id']) {
                    $previous = BillingTransaction::query()->find($data['supersedes_id']);
                    $this->validateSuccessor($data, $previous);
                }

                if ($data['type'] === 'refund') {
                    $this->validateRefund($data, $previous);
                } elseif ($data['refund_of_id']) {
                    $this->reject('refund_of_id', 'Somente estornos podem vincular pagamento.');
                }

                if ($data['status'] === 'refunded') {
                    if (! $previous || $data['type'] !== 'payment' || $this->refundedMinor($this->rootId($previous)) !== $data['amount_minor']) {
                        $this->reject('status', 'Pagamento estornado exige evidência de estorno integral.');
                    }
                }

                return BillingTransaction::query()->create([...$data, 'record_hash' => $hash, 'received_at' => now()->utc()]);
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            // A different subscription lock cannot serialize a repeated global
            // provider ID. The database uniqueness constraint is the arbiter.
            $existing = BillingTransaction::query()->where('provider', $data['provider'])->where('external_id', $data['external_id'])->first();
            if (! $existing) {
                throw $exception;
            }
            if (! hash_equals($existing->record_hash, $hash)) {
                $this->reject('external_id', 'Identificador já registrado com conteúdo diferente.');
            }

            return $existing;
        }
    }

    private function normalize(array $input): array
    {
        $allowed = ['provider', 'external_id', 'tenant_id', 'subscription_id', 'product', 'plan', 'amount_minor', 'currency', 'type', 'status', 'evidence_source', 'supersedes_id', 'refund_of_id', 'effective_at', 'service_period_start', 'service_period_end', 'recurring_months'];
        if (array_diff(array_keys($input), $allowed)) {
            $this->reject('record', 'Campos não permitidos no ledger.');
        }
        foreach (['amount_minor', 'tenant_id', 'subscription_id'] as $key) {
            if (! isset($input[$key]) || ! is_int($input[$key])) {
                $this->reject($key, 'Utilize um inteiro, nunca valores decimais ou strings.');
            }
        }
        $data = Validator::make($input, [
            'provider' => ['required', Rule::in(['stripe', 'mercado_pago', 'reconciled_import'])],
            'external_id' => ['required', 'string', 'max:160', 'regex:/^[A-Za-z0-9_.:\-]+$/'],
            'tenant_id' => ['required', 'integer', 'min:1'],
            'subscription_id' => ['required', 'integer', 'min:1'],
            'product' => ['required', Rule::in(['corretor', 'imobiliaria', 'house_flipper'])],
            'plan' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_.\-]+$/'],
            'amount_minor' => ['required', 'integer', 'min:1', 'max:1000000000000'],
            'currency' => ['required', 'string', 'regex:/^[A-Z]{3}$/'],
            'type' => ['required', Rule::in(['payment', 'refund'])],
            'status' => ['required', Rule::in(['pending', 'paid', 'failed', 'refunded', 'cancelled'])],
            'evidence_source' => ['required', Rule::in(['verified_provider', 'reconciled_import'])],
            'supersedes_id' => ['nullable', 'integer', 'min:1'],
            'refund_of_id' => ['nullable', 'integer', 'min:1'],
            'effective_at' => ['required', 'date'],
            'service_period_start' => ['nullable', 'date'],
            'service_period_end' => ['nullable', 'date'],
            'recurring_months' => ['nullable', 'integer', Rule::in([1, 3, 12])],
        ])->validate();

        foreach (['supersedes_id', 'refund_of_id', 'recurring_months'] as $key) {
            $data[$key] = isset($data[$key]) ? (int) $data[$key] : null;
        }
        foreach (['effective_at', 'service_period_start', 'service_period_end'] as $key) {
            $data[$key] = isset($data[$key]) ? CarbonImmutable::parse($data[$key])->utc()->format('Y-m-d H:i:s') : null;
        }
        if (($data['service_period_start'] === null) !== ($data['service_period_end'] === null)) {
            $this->reject('service_period_end', 'Período de serviço exige início e fim.');
        }
        if ($data['service_period_start'] && $data['service_period_start'] >= $data['service_period_end']) {
            $this->reject('service_period_end', 'Fim do período deve ser posterior ao início.');
        }
        if ($data['recurring_months']) {
            if (! $data['service_period_start'] || $data['type'] !== 'payment' || CarbonImmutable::parse($data['service_period_start'])->addMonthsNoOverflow($data['recurring_months'])->format('Y-m-d H:i:s') !== $data['service_period_end']) {
                $this->reject('recurring_months', 'Recorrência exige um período completo de 1, 3 ou 12 meses.');
            }
        }
        if ($data['type'] === 'refund' && ($data['service_period_start'] || $data['status'] === 'refunded')) {
            $this->reject('type', 'Estornos não possuem período recorrente nem estado refunded.');
        }
        // Fixed order gives stable hashes even when a provider changes key order.
        $ordered = [];
        foreach ($allowed as $key) {
            $ordered[$key] = $data[$key] ?? null;
        }

        return $ordered;
    }

    private function validateSuccessor(array $data, ?BillingTransaction $previous): void
    {
        if (! $previous || BillingTransaction::query()->where('supersedes_id', $previous->id)->exists()) {
            $this->reject('supersedes_id', 'Registro anterior inexistente ou já sucedido.');
        }
        foreach (['provider', 'tenant_id', 'subscription_id', 'product', 'plan', 'amount_minor', 'currency', 'type', 'refund_of_id', 'recurring_months'] as $key) {
            if ((string) $previous->getAttribute($key) !== (string) $data[$key]) {
                $this->reject('supersedes_id', 'Transição não pode alterar a identidade ou o valor do lançamento.');
            }
        }
        foreach (['service_period_start', 'service_period_end'] as $key) {
            if ($previous->getAttribute($key)?->utc()->format('Y-m-d H:i:s') !== $data[$key]) {
                $this->reject('supersedes_id', 'Transição não pode alterar o período de serviço.');
            }
        }
        $transitions = ['pending' => ['paid', 'failed', 'cancelled'], 'failed' => ['paid', 'cancelled'], 'paid' => ['refunded']];
        if (! in_array($data['status'], $transitions[$previous->status] ?? [], true) || $data['effective_at'] < $previous->effective_at->format('Y-m-d H:i:s')) {
            $this->reject('status', 'Transição inválida ou anterior ao evento precedente.');
        }
    }

    private function validateRefund(array $data, ?BillingTransaction $previous): void
    {
        $payment = BillingTransaction::query()->find($data['refund_of_id']);
        if (! $payment || $payment->type !== 'payment' || $payment->supersedes_id || $payment->provider !== $data['provider'] || (int) $payment->tenant_id !== $data['tenant_id'] || (int) $payment->subscription_id !== $data['subscription_id'] || $payment->product !== $data['product'] || $payment->currency !== $data['currency'] || $payment->plan !== $data['plan']) {
            $this->reject('refund_of_id', 'Estorno exige raiz do pagamento na mesma assinatura, moeda e provedor.');
        }
        $currentPayment = $this->latest($payment);
        if ($currentPayment->status !== 'paid' || $data['effective_at'] < $currentPayment->effective_at->format('Y-m-d H:i:s')) {
            $this->reject('refund_of_id', 'Estorno exige pagamento confirmado anterior.');
        }
        $already = $this->refundedMinor($payment->id);
        $previousAmount = $previous?->status === 'paid' ? $previous->amount_minor : 0;
        if ($data['status'] === 'paid' && $already - $previousAmount + $data['amount_minor'] > $payment->amount_minor) {
            $this->reject('amount_minor', 'Estornos não podem superar o pagamento recebido.');
        }
    }

    private function rootId(BillingTransaction $record): int
    {
        while ($record->supersedes_id) {
            $record = BillingTransaction::query()->findOrFail($record->supersedes_id);
        }

        return $record->id;
    }

    private function latest(BillingTransaction $record): BillingTransaction
    {
        while ($next = BillingTransaction::query()->where('supersedes_id', $record->id)->first()) {
            $record = $next;
        }

        return $record;
    }

    private function leaves(?CarbonImmutable $asOf = null): Builder
    {
        return BillingTransaction::query()->whereNotExists(function ($query) use ($asOf) {
            $query->selectRaw('1')->from('billing_transactions as next_record')->whereColumn('next_record.supersedes_id', 'billing_transactions.id');
            if ($asOf) {
                $query->where('next_record.effective_at', '<=', $asOf);
            }
        });
    }

    private function refundedMinor(int $paymentRoot, ?CarbonImmutable $asOf = null): int
    {
        return (int) $this->leaves($asOf)->where('type', 'refund')->where('status', 'paid')->where('refund_of_id', $paymentRoot)
            ->when($asOf, fn ($query) => $query->where('effective_at', '<=', $asOf))->sum('amount_minor');
    }

    /** Read-only platform metrics; caller MUST authorize platform admin. */
    public function metrics(array $filters = [], ?CarbonImmutable $asOf = null): array
    {
        $asOf ??= CarbonImmutable::now('UTC');
        $groups = [];
        $roots = [];
        $initialize = function (BillingTransaction $record) use (&$groups): string {
            $key = $record->currency.'|'.$record->product.'|'.$record->plan;
            $groups[$key] ??= ['currency' => $record->currency, 'product' => $record->product, 'plan' => $record->plan, 'received_minor' => 0, 'pending_minor' => 0, 'failed_minor' => 0, 'refunds_minor' => 0, 'net_received_minor' => 0, 'mrr_minor' => null, 'arr_minor' => null, 'recurring_evidence_count' => 0];

            return $key;
        };
        // Cash evidence is counted at the immutable paid event date, even if
        // the payment has since become fully refunded. Never move historical
        // receipts into the month in which a later status event arrived.
        foreach ($this->filtered(BillingTransaction::query(), $filters)->where('status', 'paid')->where('effective_at', '<=', $asOf)->orderBy('id')->cursor() as $record) {
            $key = $initialize($record);
            $groups[$key][$record->type === 'refund' ? 'refunds_minor' : 'received_minor'] += $record->amount_minor;
        }
        foreach ($this->filtered($this->leaves($asOf), $filters)->where('effective_at', '<=', $asOf)->where('type', 'payment')->whereIn('status', ['pending', 'failed'])->orderBy('id')->cursor() as $record) {
            $key = $initialize($record);
            $groups[$key][$record->status === 'pending' ? 'pending_minor' : 'failed_minor'] += $record->amount_minor;
        }
        // MRR is a stock at as_of, separate from cashflow from/to filters.
        $snapshotFilters = array_diff_key($filters, array_flip(['from', 'to']));
        foreach ($this->filtered($this->leaves($asOf), $snapshotFilters)->where('effective_at', '<=', $asOf)->where('type', 'payment')->whereIn('status', ['paid', 'refunded'])->whereNotNull('recurring_months')->where('service_period_start', '<=', $asOf)->where('service_period_end', '>', $asOf)->orderBy('id')->cursor() as $record) {
            $key = $initialize($record);
            // At most one current reliable term per subscription. Ambiguous
            // overlapping terms are withheld instead of double-counted.
            $roots[$record->subscription_id][] = [$key, $record];
        }
        $parents = [];
        $waiting = [];
        foreach ($roots as $terms) {
            foreach ($terms as [, $record]) {
                $parents[$record->id] = $record->supersedes_id;
                if ($record->supersedes_id) {
                    $waiting[] = $record->supersedes_id;
                }
            }
        }
        // A lifecycle chain has at most pending -> failed -> paid -> refunded.
        // Read ancestors in batches; never issue a query for every subscriber.
        while ($waiting) {
            $next = [];
            foreach (array_chunk(array_unique($waiting), 500) as $batch) {
                foreach (BillingTransaction::query()->whereIn('id', $batch)->get(['id', 'supersedes_id']) as $ancestor) {
                    $parents[$ancestor->id] = $ancestor->supersedes_id;
                    if ($ancestor->supersedes_id && ! array_key_exists($ancestor->supersedes_id, $parents)) {
                        $next[] = $ancestor->supersedes_id;
                    }
                }
            }
            $waiting = $next;
        }
        $refunds = [];
        $rootIds = array_keys(array_filter($parents, fn ($parent) => $parent === null));
        foreach (array_chunk($rootIds, 500) as $batch) {
            foreach ($this->leaves($asOf)->where('type', 'refund')->where('status', 'paid')->where('effective_at', '<=', $asOf)->whereIn('refund_of_id', $batch)->selectRaw('refund_of_id, SUM(amount_minor) as refunded_minor')->groupBy('refund_of_id')->get() as $refund) {
                $refunds[$refund->refund_of_id] = (int) $refund->refunded_minor;
            }
        }
        foreach ($roots as $terms) {
            if (count($terms) !== 1) {
                continue;
            }
            [$key, $record] = $terms[0];
            $rootId = $record->id;
            while ($parents[$rootId] ?? null) {
                $rootId = $parents[$rootId];
            }
            $net = $record->amount_minor - ($refunds[$rootId] ?? 0);
            $monthly = intdiv($net, $record->recurring_months);
            if (($net % $record->recurring_months) * 2 >= $record->recurring_months) {
                $monthly++;
            }
            $groups[$key]['mrr_minor'] = ($groups[$key]['mrr_minor'] ?? 0) + $monthly;
            $groups[$key]['recurring_evidence_count']++;
        }
        foreach ($groups as &$group) {
            $group['net_received_minor'] = $group['received_minor'] - $group['refunds_minor'];
            $group['arr_minor'] = $group['mrr_minor'] === null ? null : $group['mrr_minor'] * 12;
        }

        return ['has_evidence' => count($groups) > 0, 'as_of' => $asOf->toIso8601String(), 'groups' => array_values($groups), 'mrr' => null, 'arr' => null, 'method' => 'currency_product_plan_minor_units; paid_current_complete_month_terms; net_of_confirmed_refunds; rounded_half_up; ambiguous_terms_excluded'];
    }

    /** Sanitized financial events only; no original payload or card data. */
    public function timeline(array $filters = [], int $limit = 50): array
    {
        return $this->filtered(BillingTransaction::query(), $filters)->orderByDesc('effective_at')->orderByDesc('id')->limit(max(1, min(100, $limit)))->get(['id', 'tenant_id', 'subscription_id', 'product', 'plan', 'provider', 'type', 'status', 'amount_minor', 'currency', 'effective_at', 'received_at', 'supersedes_id', 'refund_of_id'])->toArray();
    }

    private function filtered(Builder $query, array $filters): Builder
    {
        $valid = Validator::make($filters, [
            'tenant_id' => ['sometimes', 'integer', 'min:1'], 'subscription_id' => ['sometimes', 'integer', 'min:1'],
            'product' => ['sometimes', Rule::in(['corretor', 'imobiliaria', 'house_flipper'])],
            'plan' => ['sometimes', 'string', 'max:100'], 'currency' => ['sometimes', 'regex:/^[A-Z]{3}$/'],
            'from' => ['sometimes', 'date'], 'to' => ['sometimes', 'date', 'after_or_equal:from'],
        ])->validate();
        foreach (['tenant_id', 'subscription_id', 'product', 'plan', 'currency'] as $key) {
            if (isset($valid[$key])) {
                $query->where($key, $valid[$key]);
            }
        }
        foreach (['from' => '>=', 'to' => '<='] as $key => $operator) {
            if (isset($valid[$key])) {
                $query->where('effective_at', $operator, CarbonImmutable::parse($valid[$key])->utc());
            }
        }

        return $query;
    }

    private function reject(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
