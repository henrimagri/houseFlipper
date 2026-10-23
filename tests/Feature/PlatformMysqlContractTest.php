<?php

namespace Tests\Feature;

use App\Models\BillingTransaction;
use App\Models\Tenant;
use App\Models\TenantProductSubscription;
use App\Models\User;
use App\Services\Platform\BillingLedger;
use App\Services\Platform\PlatformQueries;
use App\Services\Platform\ResourceQuota;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

/** Optional destructive-schema work is restricted to an explicitly disposable CI database. */
class PlatformMysqlContractTest extends TestCase
{
    public function createApplication(): Application
    {
        if (getenv('PLATFORM_MYSQL_TEST') !== '1') {
            $this->markTestSkipped('Opt-in MySQL contract: run phpunit -c phpunit.mysql.xml.');
        }

        // Check before Laravel boot: never trust a local .env or cached production configuration.
        foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'platform_test_ci', 'DB_USERNAME' => 'platform_test', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3306', 'DB_SOCKET' => '', 'DB_URL' => ''] as $key => $value) {
            if (getenv($key) !== $value) {
                throw new \RuntimeException('Unsafe MySQL contract environment: '.$key);
            }
        }

        $app = parent::createApplication();
        if (! $app->environment('testing') || $app->configurationIsCached() || config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'platform_test_ci' || config('database.connections.mysql.host') !== '127.0.0.1' || (int) config('database.connections.mysql.port') !== 3306 || config('database.connections.mysql.username') !== 'platform_test') {
            throw new \RuntimeException('Refusing MySQL contract against a non-isolated configuration.');
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('mysql', DB::connection()->getDriverName());
        $this->assertSame('platform_test_ci', DB::selectOne('SELECT DATABASE() AS db')->db);
        $this->assertMatchesRegularExpression('/^8\./', DB::selectOne('SELECT VERSION() AS version')->version);
        Artisan::call('migrate', ['--force' => true]);
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->app && DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    public function test_additive_migrations_indexes_and_json_date_grouping_on_mysql(): void
    {
        $this->assertTrue(Schema::hasTable('users'));
        foreach (['platform_events', 'platform_incidents', 'platform_controls', 'platform_mfa_credentials'] as $table) {
            $this->assertTrue(Schema::hasTable($table), $table);
        }
        $indexes = array_column(DB::select('SHOW INDEX FROM platform_events'), 'Key_name');
        $this->assertContains('platform_category_status_time', $indexes);
        DB::table('platform_events')->insert([
            ['category' => 'analytics', 'event' => 'mysql.contract', 'metadata' => '{"feature":"paint"}', 'created_at' => '2026-10-22 10:00:00'],
            ['category' => 'analytics', 'event' => 'mysql.contract', 'metadata' => '{"feature":"paint"}', 'created_at' => '2026-10-22 11:00:00'],
        ]);
        $rows = DB::table('platform_events')->where('event', 'mysql.contract')->selectRaw('DATE(created_at) as day, count(*) as total')->groupByRaw('DATE(created_at)')->get();
        $this->assertSame('2026-10-22', $rows[0]->day);
        $this->assertSame(2, (int) $rows[0]->total);
        $this->assertSame(1, DB::table('platform_events')->where('event', 'mysql.contract')->distinct()->count('metadata->feature'));
    }

    public function test_server_pagination_is_bounded_and_invalid_window_is_rejected(): void
    {
        foreach (range(1, 4) as $n) {
            DB::table('platform_events')->insert(['category' => 'api', 'event' => 'mysql.page', 'created_at' => now()]);
        }
        $r = Request::create('/api/platform/events', 'GET', ['per_page' => 2, 'search' => 'mysql.page']);
        $result = app(PlatformQueries::class)->events($r);
        $this->assertCount(2, $result['data']);
        $this->assertTrue($result['has_more']);
        $this->expectException(ValidationException::class);
        app(PlatformQueries::class)->filters(Request::create('/', 'GET', ['per_page' => 101]));
    }

    public function test_unique_controls_and_atomic_rollback_on_mysql(): void
    {
        DB::table('platform_controls')->insert(['kind' => 'contract', 'key' => 'unique', 'value' => '{}']);
        $duplicate = false;
        try {
            DB::table('platform_controls')->insert(['kind' => 'contract', 'key' => 'unique', 'value' => '{}']);
        } catch (QueryException) {
            $duplicate = true;
        }
        $this->assertTrue($duplicate, 'Unique(kind,key) must be enforced by MySQL.');
        try {
            DB::transaction(function () {
                DB::table('platform_controls')->insert(['kind' => 'contract', 'key' => 'rollback', 'value' => '{}']);
                throw new \RuntimeException('intentional rollback');
            });
        } catch (\RuntimeException) {
        }
        $this->assertSame(0, DB::table('platform_controls')->where('key', 'rollback')->count());
    }

    public function test_provider_ids_remain_case_sensitive_in_ledger_and_receipts(): void
    {
        $tenant = Tenant::create(['nome' => 'MySQL contract', 'slug' => 'mysql-case-'.bin2hex(random_bytes(6))]);
        $subscription = TenantProductSubscription::create(['tenant_id' => $tenant->id, 'product_slug' => 'corretor', 'plan_slug' => 'mysql_contract', 'status' => 'active', 'active' => true]);
        $base = [
            'provider' => 'reconciled_import', 'tenant_id' => (int) $tenant->id,
            'subscription_id' => (int) $subscription->id, 'product' => 'corretor', 'plan' => 'mysql_contract',
            'amount_minor' => 14900, 'currency' => 'BRL', 'type' => 'payment', 'status' => 'paid',
            'evidence_source' => 'reconciled_import', 'effective_at' => now()->toIso8601String(),
        ];
        $ledger = app(BillingLedger::class);
        $upper = $ledger->recordVerified($base + ['external_id' => 'Invoice_Case']);
        $lower = $ledger->recordVerified($base + ['external_id' => 'invoice_case']);
        $this->assertNotSame($upper->id, $lower->id);
        $this->assertSame($upper->id, $ledger->recordVerified($base + ['external_id' => 'Invoice_Case'])->id);
        $this->assertSame(2, BillingTransaction::where('subscription_id', $subscription->id)->count());
        $this->assertSame($upper->id, BillingTransaction::where('provider', 'reconciled_import')->where('external_id', 'Invoice_Case')->value('id'));
        $this->assertSame($lower->id, BillingTransaction::where('provider', 'reconciled_import')->where('external_id', 'invoice_case')->value('id'));

        $receipt = ['provider' => 'stripe', 'event_type' => 'invoice.paid', 'status' => 'received', 'created_at' => now()];
        $this->assertSame(1, DB::table('platform_provider_receipts')->insertOrIgnore($receipt + ['external_id' => 'Event_Case']));
        $this->assertSame(1, DB::table('platform_provider_receipts')->insertOrIgnore($receipt + ['external_id' => 'event_case']));
        $this->assertSame(0, DB::table('platform_provider_receipts')->insertOrIgnore($receipt + ['external_id' => 'Event_Case']));
        foreach (['Event_Case', 'event_case'] as $external) {
            $this->assertSame(1, DB::table('platform_provider_receipts')->where('provider', 'stripe')->where('external_id', $external)->count());
        }
    }

    public function test_quota_admissions_are_idempotent_scoped_and_rollback_atomically(): void
    {
        config(['platform_resources.plans.mysql_contract.ai_requests' => 1]);
        $tenant = Tenant::create(['nome' => 'Quota contract A', 'slug' => 'mysql-quota-a-'.bin2hex(random_bytes(6))]);
        $other = Tenant::create(['nome' => 'Quota contract B', 'slug' => 'mysql-quota-b-'.bin2hex(random_bytes(6))]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $otherUser = User::factory()->create(['tenant_id' => $other->id]);
        foreach ([[$tenant, 'corretor'], [$tenant, 'house_flipper'], [$other, 'corretor']] as [$owner, $product]) {
            TenantProductSubscription::create(['tenant_id' => $owner->id, 'product_slug' => $product, 'plan_slug' => 'mysql_contract', 'status' => 'active', 'active' => true]);
        }
        $quota = app(ResourceQuota::class);
        $operation = (string) Str::uuid();
        $quota->admit($user, 'corretor', 'ai_requests', $operation);
        $quota->admit($user, 'corretor', 'ai_requests', $operation);
        $scope = ['tenant_id' => $tenant->id, 'product' => 'corretor', 'resource' => 'ai_requests', 'period' => now()->format('Y-m')];
        $this->assertSame(1, (int) DB::table('platform_resource_usage')->where($scope)->value('used'));
        $this->assertSame(1, DB::table('platform_resource_admissions')->where('tenant_id', $tenant->id)->count());
        $denied = (string) Str::uuid();
        try {
            $quota->admit($user, 'corretor', 'ai_requests', $denied);
            $this->fail('A different operation must not exceed the monthly budget.');
        } catch (HttpExceptionInterface $exception) {
            $this->assertSame(429, $exception->getStatusCode());
        }
        $this->assertSame(1, (int) DB::table('platform_resource_usage')->where($scope)->value('used'));
        $this->assertFalse(DB::table('platform_resource_admissions')->where('operation_id', $denied)->exists());

        $rolledBack = (string) Str::uuid();
        try {
            DB::transaction(function () use ($quota, $user, $rolledBack) {
                $quota->admit($user, 'house_flipper', 'ai_requests', $rolledBack);
                throw new \RuntimeException('intentional quota rollback');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('intentional quota rollback', $exception->getMessage());
        }
        $this->assertFalse(DB::table('platform_resource_usage')->where('tenant_id', $tenant->id)->where('product', 'house_flipper')->exists());
        $this->assertFalse(DB::table('platform_resource_admissions')->where('operation_id', $rolledBack)->exists());
        $quota->admit($user, 'house_flipper', 'ai_requests', $rolledBack);
        $quota->admit($otherUser, 'corretor', 'ai_requests', (string) Str::uuid());
        $this->assertSame(1, (int) DB::table('platform_resource_usage')->where('tenant_id', $other->id)->where('product', 'corretor')->value('used'));
        $this->assertSame(1, (int) DB::table('platform_resource_usage')->where('tenant_id', $tenant->id)->where('product', 'house_flipper')->value('used'));
    }
}
