<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Platform\EventRecorder;
use App\Services\Platform\PlatformQueries;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class PlatformExportReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 150;

    public bool $failOnTimeout = true;

    public int $expectedAttempt;

    public function __construct(public string $exportId)
    {
        $this->expectedAttempt = (int) DB::table('platform_export_jobs')->where('id', $exportId)->value('attempts') + 1;
        $this->onConnection('platform_ops');
        $this->onQueue('platform-ops');
        $this->afterCommit();
    }

    public static function path(string $id): string
    {
        if (! Str::isUuid($id)) {
            throw new RuntimeException('Invalid export identifier.');
        }

        return 'platform-exports/'.$id.'.csv';
    }

    public function handle(PlatformQueries $queries): void
    {
        $claimed = DB::table('platform_export_jobs')->where('id', $this->exportId)->where('status', 'queued')->where('expires_at', '>', now())->where('attempts', $this->expectedAttempt - 1)->where('attempts', '<', 3)->update(['status' => 'processing', 'attempts' => DB::raw('attempts + 1'), 'error_type' => null, 'updated_at' => now()]);
        if ($claimed !== 1) {
            return;
        }
        $record = DB::table('platform_export_jobs')->where('id', $this->exportId)->first();
        $attempt = (int) $record->attempts;
        $temporary = self::path($this->exportId).'.attempt-'.$attempt;
        $disk = Storage::disk('local');
        $stream = null;
        $rows = 0;
        try {
            $owner = User::find($record->created_by);
            if (! $owner || ! $owner->isPlatformAdmin() || ! $owner->ativo_flg || ! $owner->email_verified_at) {
                throw new RuntimeException('Export owner is no longer authorized.');
            }
            $request = Request::create('/platform-export-worker', 'GET', json_decode($record->filters, true, 32, JSON_THROW_ON_ERROR));
            $filters = $queries->filters($request);
            $query = $this->query($record->report, $filters, $queries);
            $columns = $this->columns($record->report);
            $stream = tmpfile();
            if ($stream === false) {
                throw new RuntimeException('Export temporary storage unavailable.');
            }
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, $columns, ',', '"', '');
            $deadline = microtime(true) + 120;
            // Snapshot upper ID avoids an unbounded moving export while users
            // keep generating data. Membership updates remain read-committed.
            $maximumId = (clone $query)->max('id');
            if ($maximumId !== null) {
                $query->where('id', '<=', $maximumId)->chunkById(500, function ($batch) use ($stream, $columns, &$rows, $attempt, $deadline) {
                    if (! $this->running($attempt)) {
                        throw new RuntimeException('Export was cancelled or expired.');
                    }
                    if (microtime(true) > $deadline) {
                        throw new RuntimeException('Export runtime limit exceeded.');
                    }
                    foreach ($batch as $row) {
                        if (++$rows > 100000) {
                            throw new RuntimeException('Export row limit exceeded.');
                        }
                        $values = (array) $row;
                        if (in_array('duration_seconds', $columns, true)) {
                            $values['duration_seconds'] = $row->started_at && $row->completed_at ? max(0, strtotime($row->completed_at) - strtotime($row->started_at)) : null;
                        }
                        fputcsv($stream, array_map(fn ($column) => self::csvCell($values[$column] ?? null), $columns), ',', '"', '');
                    }
                    // 100 MiB ceiling independent of row count.
                    if (ftell($stream) > 100 * 1024 * 1024) {
                        throw new RuntimeException('Export byte limit exceeded.');
                    }
                }, 'id');
            }
            rewind($stream);
            if (! $disk->put($temporary, $stream, ['visibility' => 'private'])) {
                throw new RuntimeException('Export storage write failed.');
            }
            DB::transaction(function () use ($attempt, $temporary, $disk, $rows, $owner) {
                $current = DB::table('platform_export_jobs')->where('id', $this->exportId)->lockForUpdate()->first();
                if (! $current || $current->status !== 'processing' || (int) $current->attempts !== $attempt || now()->gte($current->expires_at)) {
                    throw new RuntimeException('Export was cancelled or expired.');
                }
                $freshOwner = User::find($owner->id);
                if (! $freshOwner || ! $freshOwner->isPlatformAdmin() || ! $freshOwner->ativo_flg || ! $freshOwner->email_verified_at) {
                    throw new RuntimeException('Export owner authorization was revoked.');
                }
                if (! $disk->move($temporary, self::path($this->exportId))) {
                    throw new RuntimeException('Export promotion failed.');
                }
                DB::table('platform_export_jobs')->where('id', $this->exportId)->update(['status' => 'completed', 'rows' => $rows, 'updated_at' => now()]);
                $audit = Request::create('/platform-export-worker');
                $audit->setUserResolver(fn () => $freshOwner);
                app(EventRecorder::class)->record('audit', 'admin.export.completed', ['target_type' => 'export', 'target_id' => $this->exportId, 'count' => $rows], $audit);
            });
        } catch (Throwable $exception) {
            $disk->delete($temporary);
            // An obsolete attempt cannot overwrite a cancelled/new generation.
            $changed = DB::table('platform_export_jobs')->where('id', $this->exportId)->where('status', 'processing')->where('attempts', $attempt)->update(['status' => 'failed', 'error_type' => substr(class_basename($exception), 0, 80), 'updated_at' => now()]);
            if ($changed) {
                $disk->delete(self::path($this->exportId));
            }
            // No exception message/path/payload is persisted or handed to a
            // queue logger; the domain record is the administrative dead job.
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        DB::table('platform_export_jobs')->where('id', $this->exportId)->where('status', 'processing')->where('attempts', $this->expectedAttempt)->update(['status' => 'failed', 'error_type' => 'WorkerInterrupted', 'updated_at' => now()]);
    }

    private function running(int $attempt): bool
    {
        return DB::table('platform_export_jobs')->where('id', $this->exportId)->where('status', 'processing')->where('attempts', $attempt)->where('expires_at', '>', now())->exists();
    }

    public static function csvCell(mixed $value): string
    {
        $value = is_scalar($value) || $value === null ? (string) $value : '';

        return preg_match('/^[\s\x00-\x1F]*[=+@\-]/u', $value) || preg_match('/^[\t\r\n]/', $value) ? "'".$value : $value;
    }

    private function columns(string $report): array
    {
        return match ($report) {
            'clients' => ['id', 'tenant_id', 'name', 'ativo_flg', 'created_at'],
            'events' => ['id', 'category', 'event', 'severity', 'request_id', 'status', 'created_at'],
            'jobs' => ['id', 'tenant_id', 'kind', 'status', 'actual_method', 'duration_seconds', 'created_at'],
            'trials', 'churn' => ['id', 'tenant_id', 'product_slug', 'plan_slug', 'status', 'trial_starts_at', 'trial_ends_at', 'created_at'],
            default => throw new RuntimeException('Unsupported export report.'),
        };
    }

    private function query(string $report, array $filters, PlatformQueries $queries): Builder
    {
        if ($report === 'events') {
            $query = $queries->scope(DB::table('platform_events'), $filters);
            foreach (['category', 'severity', 'status'] as $field) {
                if (! empty($filters[$field])) {
                    $query->where($field, $filters[$field]);
                }
            }
            if (! empty($filters['search'])) {
                $search = $filters['search'];
                $query->where(fn ($q) => $q->where('request_id', $search)->orWhere('trace_id', $search)->orWhere('id', ctype_digit($search) ? $search : 0)->orWhere('event', 'like', '%'.addcslashes($search, '%_').'%')->orWhere('ip_hash', $search));
            }

            return $query->select($this->columns($report));
        }
        if ($report === 'jobs') {
            $scoped = $filters;
            unset($scoped['product']);
            $query = $queries->scope(DB::table('property_processing_jobs'), $scoped);
            if (! empty($filters['product']) && $filters['product'] !== 'house_flipper') {
                $query->whereRaw('1=0');
            }
            if (! empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }
            $grammar = DB::connection()->getQueryGrammar();

            return $query->select('id', 'tenant_id', 'kind', 'status', 'started_at', 'completed_at', 'created_at')->selectRaw('COALESCE('.$grammar->wrap('result->actual_method').','.$grammar->wrap('result->geometry_quality->method').') as actual_method');
        }
        if (in_array($report, ['trials', 'churn'], true)) {
            $scope = $filters;
            unset($scope['user_id']);
            $query = $queries->scope(DB::table('tenant_product_subscriptions'), $scope, 'product_slug', $report === 'trials' ? 'trial_ends_at' : 'updated_at');
            if (! empty($filters['user_id'])) {
                $query->whereIn('tenant_id', DB::table('users')->select('tenant_id')->where('id', $filters['user_id']));
            }
            if (! empty($filters['plan'])) {
                $query->where('plan_slug', $filters['plan']);
            }
            if ($report === 'trials') {
                $query->whereNotNull('trial_ends_at')->where('trial_ends_at', '<', now())->where('status', '!=', 'active');
            } else {
                $query->where('status', 'cancelled');
            }

            return $query->select($this->columns($report));
        }
        if ($report !== 'clients') {
            throw new RuntimeException('Unsupported export report.');
        }
        $query = DB::table('users')->whereBetween('created_at', [$filters['from'], $filters['to']]);
        foreach (['tenant_id', 'user_id' => 'id'] as $key => $column) {
            $filter = is_int($key) ? $column : $key;
            if (! empty($filters[$filter])) {
                $query->where($column, $filters[$filter]);
            }
        }
        if (! empty($filters['search'])) {
            $search = addcslashes($filters['search'], '%_');
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')->orWhere('id', ctype_digit($filters['search']) ? $filters['search'] : 0));
        }
        if (($filters['status'] ?? null) === 'suspended') {
            $query->where('ativo_flg', false);
        }
        if (! empty($filters['product']) || ! empty($filters['plan']) || in_array($filters['status'] ?? '', ['trial', 'active', 'cancelled', 'past_due'], true)) {
            $query->whereExists(function ($sub) use ($filters) {
                $sub->selectRaw('1')->from('tenant_product_subscriptions as s')->whereColumn('s.tenant_id', 'users.tenant_id');
                foreach (['product' => 'product_slug', 'plan' => 'plan_slug'] as $key => $column) {
                    if (! empty($filters[$key])) {
                        $sub->where('s.'.$column, $filters[$key]);
                    }
                }
                if (in_array($filters['status'] ?? '', ['trial', 'active', 'cancelled', 'past_due'], true)) {
                    $sub->where('s.status', $filters['status']);
                }
            });
        }

        return $query->select($this->columns($report));
    }
}
