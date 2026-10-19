<?php

namespace Tests\Feature;

use App\Services\MarketData\CartorioTransactionProvider;
use App\Services\MarketData\ItbiMunicipalProvider;
use App\Services\MarketData\PrefeituraTransactionProvider;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketProviderTest extends TestCase
{
    public function test_provider_requests_disable_redirects_and_enforce_transfer_limits(): void
    {
        Http::preventStrayRequests();
        config()->set('services.cartorios.endpoint', 'https://audit.test/cartorios');
        $observed = [];
        Http::fake(function ($request, $options) use (&$observed) {
            $observed = [
                'encoding' => $request->header('Accept-Encoding')[0] ?? null,
                'redirects' => $options['allow_redirects'] ?? null,
                'decode_content' => $options['decode_content'] ?? null,
            ];
            $options['on_headers'](new PsrResponse(200, ['Content-Length' => '2097153']));

            return Http::response(['data' => []]);
        });

        $result = app(CartorioTransactionProvider::class)->search([]);

        $this->assertSame('error', $result['status']);
        $this->assertSame('identity', $observed['encoding']);
        $this->assertFalse($observed['redirects']);
        $this->assertFalse($observed['decode_content']);
    }

    public function test_providers_reject_oversized_final_response_bodies(): void
    {
        Http::preventStrayRequests();
        config()->set('services.cartorios.endpoint', 'https://audit.test/cartorios');
        config()->set('services.prefeituras.endpoint', 'https://audit.test/prefeituras');
        config()->set('services.itbi.endpoint', 'https://audit.test/itbi');
        Http::fake(['audit.test/*' => Http::response(str_repeat('x', 2_097_153))]);

        foreach ([CartorioTransactionProvider::class, PrefeituraTransactionProvider::class, ItbiMunicipalProvider::class] as $provider) {
            $result = app($provider)->search([]);
            $this->assertSame('error', $result['status']);
            $this->assertSame([], $result['transactions']);
        }
    }

    public function test_provider_aborts_when_transfer_progress_exceeds_limit(): void
    {
        Http::preventStrayRequests();
        config()->set('services.cartorios.endpoint', 'https://audit.test/cartorios');
        Http::fake(function ($request, $options) {
            $options['progress'](0, 2_097_153, 0, 0);

            return Http::response(['data' => []]);
        });

        $result = app(CartorioTransactionProvider::class)->search([]);

        $this->assertSame('error', $result['status']);
        $this->assertSame([], $result['transactions']);
    }

    public function test_providers_reject_private_or_insecure_configured_endpoints_before_egress(): void
    {
        Http::preventStrayRequests();

        foreach (['http://market.example.test/data', 'https://127.0.0.1/data', 'https://169.254.169.254/latest/meta-data'] as $endpoint) {
            config()->set('services.cartorios.endpoint', $endpoint);
            config()->set('services.prefeituras.endpoint', $endpoint);
            config()->set('services.itbi.endpoint', $endpoint);

            foreach ([CartorioTransactionProvider::class, PrefeituraTransactionProvider::class, ItbiMunicipalProvider::class] as $provider) {
                $result = app($provider)->search([]);
                $this->assertSame('error', $result['status']);
                $this->assertSame([], $result['transactions']);
            }
        }

        Http::assertNothingSent();
    }

    public function test_providers_normalize_valid_rows_and_ignore_malformed_entries(): void
    {
        Http::preventStrayRequests();
        config()->set('services.cartorios.endpoint', 'https://audit.test/cartorios');
        config()->set('services.prefeituras.endpoint', 'https://audit.test/prefeituras');
        config()->set('services.itbi.endpoint', 'https://audit.test/itbi');
        $payload = ['data' => [null, 'invalid', ['price' => ['invalid']], ['price' => 'R$ 735.000,00', 'area_m2' => 98, 'title' => 'Audit', 'city' => 'Teste']]];
        Http::fake(['audit.test/*' => Http::response($payload)]);
        foreach ([CartorioTransactionProvider::class, PrefeituraTransactionProvider::class, ItbiMunicipalProvider::class] as $provider) {
            $result = app($provider)->search(['city' => 'Teste']);
            $this->assertSame('connected', $result['status']);
            $this->assertCount(1, $result['transactions']);
            $this->assertSame(735000.0, $result['transactions'][0]['price']);
            $this->assertSame(7500.0, $result['transactions'][0]['price_m2']);
        }
    }

    public function test_provider_http_failure_is_reported_without_inventing_transactions(): void
    {
        Http::preventStrayRequests();
        config()->set('services.cartorios.endpoint', 'https://audit.test/cartorios');
        config()->set('services.prefeituras.endpoint', 'https://audit.test/prefeituras');
        config()->set('services.itbi.endpoint', 'https://audit.test/itbi');
        Http::fake(['audit.test/*' => Http::response([], 503)]);
        foreach ([CartorioTransactionProvider::class, PrefeituraTransactionProvider::class, ItbiMunicipalProvider::class] as $provider) {
            $result = app($provider)->search([]);
            $this->assertSame('error', $result['status']);
            $this->assertSame([], $result['transactions']);
        }
    }

    public function test_municipal_csv_with_extra_columns_does_not_crash_valid_rows(): void
    {
        Http::preventStrayRequests();
        config()->set('services.itbi.endpoint', 'https://audit.test/itbi');
        Http::fake(['audit.test/*' => Http::response("price;area_m2\n100000;50;unexpected\n200000;100", 200, ['Content-Type' => 'text/csv'])]);
        $result = app(ItbiMunicipalProvider::class)->search([]);
        $this->assertCount(1, $result['transactions']);
        $this->assertSame(200000.0, $result['transactions'][0]['price']);
    }

    public function test_municipal_provider_requires_every_supplied_location_filter(): void
    {
        Http::preventStrayRequests();
        config()->set('services.itbi.endpoint', 'https://audit.test/itbi');
        Http::fake(['audit.test/*' => Http::response([
            'data' => [
                ['id' => 'right', 'price' => 500000, 'area_m2' => 50, 'city' => 'São Paulo', 'neighborhood' => 'Perdizes'],
                ['id' => 'wrong-neighborhood', 'price' => 600000, 'area_m2' => 60, 'city' => 'São Paulo', 'neighborhood' => 'Moema'],
                ['id' => 'wrong-city', 'price' => 700000, 'area_m2' => 70, 'city' => 'Campinas', 'neighborhood' => 'Perdizes'],
            ],
        ])]);

        $result = app(ItbiMunicipalProvider::class)->search([
            'city' => 'São Paulo',
            'state' => 'SP',
            'neighborhood' => 'Perdizes',
        ]);

        $this->assertSame('connected', $result['status']);
        $this->assertCount(1, $result['transactions']);
        $this->assertSame('right', $result['transactions'][0]['source_reference']);
    }

    public function test_provider_exceptions_and_unconfigured_states_are_neutral(): void
    {
        Http::preventStrayRequests();
        config()->set('services.cartorios.endpoint', null);
        config()->set('services.prefeituras.endpoint', null);
        config()->set('services.itbi.endpoint', null);
        config()->set('services.itbi.sp_endpoint', null);

        $this->assertSame('no_dataset', app(CartorioTransactionProvider::class)->search([])['status']);
        $this->assertSame('no_dataset', app(PrefeituraTransactionProvider::class)->search(['city' => 'Campinas', 'state' => 'SP'])['status']);
        $this->assertSame('no_dataset', app(PrefeituraTransactionProvider::class)->search(['city' => 'São Paulo', 'state' => 'SP'])['status']);
        $this->assertSame('no_dataset', app(ItbiMunicipalProvider::class)->search(['city' => 'Campinas', 'state' => 'SP'])['status']);
        $this->assertSame('no_dataset', app(ItbiMunicipalProvider::class)->search(['city' => 'São Paulo', 'state' => 'SP'])['status']);

        config()->set('services.cartorios.endpoint', 'https://audit.test/cartorios');
        config()->set('services.prefeituras.endpoint', 'https://audit.test/prefeituras');
        config()->set('services.itbi.endpoint', 'https://audit.test/itbi');
        Http::fake(fn () => throw new \RuntimeException('private upstream detail'));

        foreach ([CartorioTransactionProvider::class, PrefeituraTransactionProvider::class, ItbiMunicipalProvider::class] as $provider) {
            $result = app($provider)->search([]);
            $this->assertSame('error', $result['status']);
            $this->assertSame([], $result['transactions']);
            $this->assertStringNotContainsString('private upstream detail', $result['message']);
        }
    }
}
