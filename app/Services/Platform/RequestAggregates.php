<?php

namespace App\Services\Platform;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RequestAggregates
{
    // Upper-bound estimates, not interpolated exact percentiles. Last bin is overflow.
    public const BOUNDS_MS = [10, 25, 50, 100, 250, 500, 1000, 2500, 5000, 10000, 30000, 60000, 120000, 4294967295];

    /** Incremental ID cursor includes late arrivals in their original minute. */
    public function run(int $limit = 5000): array
    {
        if ($limit < 1 || $limit > 10000) {
            throw new InvalidArgumentException('Batch limit must be 1..10000.');
        }

        return DB::transaction(function () use ($limit) {
            DB::table('platform_aggregate_cursors')->insertOrIgnore(['name' => 'api_minutes_v1', 'last_event_id' => 0, 'updated_at' => now()]);
            $cursor = DB::table('platform_aggregate_cursors')->where('name', 'api_minutes_v1')->lockForUpdate()->first();
            $cutoff = CarbonImmutable::now('UTC')->startOfMinute();
            $events = DB::table('platform_events')->where('category', 'api')->where('id', '>', $cursor->last_event_id)->orderBy('id')->limit($limit)->get(['id', 'created_at', 'route', 'method', 'tenant_id', 'product', 'status', 'duration_ms']);
            $groups = [];
            $processed = 0;
            $last = $cursor->last_event_id;
            foreach ($events as $event) {
                $at = CarbonImmutable::parse($event->created_at, 'UTC');
                // Stop before open minutes. Never advance over an unprocessed event.
                if ($at->greaterThanOrEqualTo($cutoff)) {
                    break;
                }
                $dims = ['bucket_at' => $at->startOfMinute()->format('Y-m-d H:i:s'), 'route' => $event->route ?? '[unmatched]', 'method' => $event->method ?? '[unknown]', 'tenant_id' => $event->tenant_id, 'product' => $event->product, 'status' => $event->status];
                $key = hash('sha256', json_encode($dims, JSON_THROW_ON_ERROR));
                if (! isset($groups[$key])) {
                    $groups[$key] = $dims + ['request_count' => 0, 'error_count' => 0, 'latency_samples' => 0, 'duration_sum_ms' => 0, 'histogram' => array_fill(0, count(self::BOUNDS_MS), 0)];
                }
                $g = &$groups[$key];
                $g['request_count']++;
                $g['error_count'] += $event->status >= 500 ? 1 : 0;
                if ($event->duration_ms !== null) {
                    $duration = max(0, (int) $event->duration_ms);
                    $g['latency_samples']++;
                    $g['duration_sum_ms'] += $duration;
                    foreach (self::BOUNDS_MS as $i => $bound) {
                        if ($duration <= $bound) {
                            $g['histogram'][$i]++;
                            break;
                        }
                    }
                }
                unset($g);
                $last = $event->id;
                $processed++;
            }
            $existingGroups = [];
            foreach (array_chunk(array_keys($groups), 500) as $keys) {
                foreach (DB::table('platform_request_aggregates')->whereIn('group_key', $keys)->get() as $existing) {
                    $existingGroups[$existing->group_key] = $existing;
                }
            }
            $updates = [];
            foreach ($groups as $key => $group) {
                $existing = $existingGroups[$key] ?? null;
                if ($existing) {
                    foreach (['request_count', 'error_count', 'latency_samples', 'duration_sum_ms'] as $field) {
                        $group[$field] += $existing->$field;
                    }
                    $old = json_decode($existing->histogram, true, flags: JSON_THROW_ON_ERROR);
                    foreach ($group['histogram'] as $i => &$count) {
                        $count += $old[$i];
                    }
                    unset($count);
                }
                $group['histogram'] = json_encode($group['histogram'], JSON_THROW_ON_ERROR);
                $group['updated_at'] = now();
                $updates[] = ['group_key' => $key] + $group;
            }
            foreach (array_chunk($updates, 100) as $batch) {
                DB::table('platform_request_aggregates')->upsert($batch, ['group_key'], ['request_count', 'error_count', 'latency_samples', 'duration_sum_ms', 'histogram', 'updated_at']);
            }
            DB::table('platform_aggregate_cursors')->where('name', 'api_minutes_v1')->update(['last_event_id' => $last, 'updated_at' => now()]);

            return ['processed' => $processed, 'groups_updated' => count($groups), 'last_event_id' => (int) $last, 'finalized_before' => $cutoff->toIso8601String(), 'batch_limit' => $limit];
        });
    }

    /** Bounded route summary; apply the same authorized filters as other admin reads. */
    public function summary(string $from, string $to, ?int $tenantId = null, ?string $product = null): array
    {
        $start = CarbonImmutable::parse($from, 'UTC');
        $end = CarbonImmutable::parse($to, 'UTC');
        if ($end->lessThanOrEqualTo($start) || $start->diffInDays($end) > 93) {
            throw new InvalidArgumentException('Aggregate range must be positive and at most 93 days.');
        }
        $query = DB::table('platform_request_aggregates')->where('bucket_at', '>=', $start)->where('bucket_at', '<', $end);
        $query->when($tenantId !== null, fn (Builder $q) => $q->where('tenant_id', $tenantId))->when($product !== null, fn (Builder $q) => $q->where('product', $product));
        $rows = $query->orderByDesc('bucket_at')->orderBy('id')->limit(10001)->get();
        $groups = [];
        foreach ($rows->take(10000) as $row) {
            $key = $row->route.'|'.$row->method;
            $g = $groups[$key] ?? ['route' => $row->route, 'method' => $row->method, 'count' => 0, 'errors' => 0, 'latency_samples' => 0, 'histogram' => array_fill(0, count(self::BOUNDS_MS), 0)];
            $g['count'] += $row->request_count;
            $g['errors'] += $row->error_count;
            $g['latency_samples'] += $row->latency_samples;
            foreach (json_decode($row->histogram, true, flags: JSON_THROW_ON_ERROR) as $i => $count) {
                $g['histogram'][$i] += $count;
            }
            $groups[$key] = $g;
        }
        foreach ($groups as &$g) {
            foreach ([50, 75, 95, 99] as $percentile) {
                $target = (int) ceil($g['latency_samples'] * $percentile / 100);
                $sum = 0;
                $estimate = null;
                if ($target > 0) {
                    foreach ($g['histogram'] as $i => $count) {
                        $sum += $count;
                        if ($sum >= $target) {
                            $estimate = $i === count(self::BOUNDS_MS) - 1 ? null : self::BOUNDS_MS[$i];
                            break;
                        }
                    }
                }
                $g['p'.$percentile.'_ms_estimate_upper_bound'] = $estimate;
            }
            $g['error_rate'] = $g['count'] ? $g['errors'] / $g['count'] : null;
            unset($g['histogram']);
        }
        unset($g);

        return ['data' => array_values($groups), 'truncated' => $rows->count() > 10000, 'bucket_seconds' => 60, 'error_definition' => 'HTTP >= 500', 'percentile_method' => 'fixed_histogram_upper_bound_overflow_null', 'bounds_ms' => self::BOUNDS_MS, 'raw_retention_changed' => false];
    }
}
