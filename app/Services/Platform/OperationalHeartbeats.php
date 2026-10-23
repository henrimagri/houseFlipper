<?php

namespace App\Services\Platform;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class OperationalHeartbeats
{
    public const TYPES = ['scheduler', 'queue', 'ai', '3d'];

    /** Server-side trusted producers only. No public ingestion endpoint. */
    public function beat(string $type, string $workerId, string $version, ?DateTimeInterface $startedAt = null, ?DateTimeInterface $seenAt = null, ?string $hostname = null): void
    {
        if (! in_array($type, self::TYPES, true) || ! Str::isUuid($workerId) || ! preg_match('/\A[a-zA-Z0-9._+\/-]{1,80}\z/', $version)) {
            throw new InvalidArgumentException('Invalid heartbeat identity.');
        }
        $now = CarbonImmutable::now('UTC');
        $seen = $seenAt ? CarbonImmutable::instance($seenAt)->utc() : $now;
        $start = $startedAt ? CarbonImmutable::instance($startedAt)->utc() : $seen;
        if ($seen->greaterThan($now) || $start->greaterThan($seen)) {
            throw new InvalidArgumentException('Heartbeat timestamps cannot be in the future or reversed.');
        }
        DB::transaction(function () use ($type, $workerId, $version, $seen, $start, $hostname, $startedAt) {
            $existing = DB::table('platform_heartbeats')->where('worker_id', $workerId)->lockForUpdate()->first();
            if ($existing && $existing->type !== $type) {
                throw new InvalidArgumentException('Worker UUID already belongs to another type.');
            }
            if ($existing && $seen->lessThan(CarbonImmutable::parse($existing->last_seen, 'UTC'))) {
                throw new InvalidArgumentException('Heartbeat cannot move backwards.');
            }
            DB::table('platform_heartbeats')->updateOrInsert(['worker_id' => $workerId], [
                'type' => $type,
                'host_hash' => hash_hmac('sha256', mb_substr($hostname ?? (gethostname() ?: 'unknown'), 0, 255), (string) config('app.key')),
                'version' => $version,
                'started_at' => $existing && $startedAt === null ? $existing->started_at : $start,
                'last_seen' => $seen,
            ]);
        });
    }

    public function snapshot(int $onlineSeconds = 90, int $offlineSeconds = 300): array
    {
        if ($onlineSeconds < 1 || $offlineSeconds <= $onlineSeconds || $offlineSeconds > 86400) {
            throw new InvalidArgumentException('Invalid heartbeat thresholds.');
        }
        $observed = DB::table('platform_heartbeats')->orderBy('type')->orderBy('worker_id')->limit(1001)->get();
        $rows = $observed->take(1000);
        $data = [];
        foreach (self::TYPES as $type) {
            $workers = [];
            foreach ($rows->where('type', $type) as $row) {
                $seen = CarbonImmutable::parse($row->last_seen, 'UTC');
                $age = (int) $seen->diffInSeconds(CarbonImmutable::now('UTC'), false);
                $workers[] = ['worker_id' => $row->worker_id, 'type' => $type, 'host_reference' => substr($row->host_hash, 0, 12), 'version' => $row->version, 'started_at' => $row->started_at, 'last_seen' => $row->last_seen, 'age_seconds' => max(0, $age), 'status' => $age < 0 ? 'offline' : ($age <= $onlineSeconds ? 'online' : ($age <= $offlineSeconds ? 'delayed' : 'offline'))];
            }
            $statuses = array_column($workers, 'status');
            $data[$type] = ['status' => ! $workers ? 'unconfigured' : (in_array('online', $statuses, true) ? 'online' : (in_array('delayed', $statuses, true) ? 'delayed' : 'offline')), 'workers' => $workers];
        }

        return ['data' => $data, 'thresholds_seconds' => ['online' => $onlineSeconds, 'offline' => $offlineSeconds], 'scope' => 'observed_heartbeats_only', 'note' => 'Queue mede o último loop observado. Job longo pode atrasar o heartbeat; offline não confirma encerramento do processo.', 'limit' => 1000, 'truncated' => $observed->count() > 1000];
    }
}
