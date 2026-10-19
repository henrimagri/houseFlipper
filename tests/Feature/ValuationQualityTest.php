<?php

namespace Tests\Feature;

use App\Models\RealEstateTransaction;
use App\Services\PropertyValuationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ValuationQualityTest extends TestCase
{
    use RefreshDatabase;

    private function listing(string $id, array $overrides = []): array
    {
        return array_replace(['id' => $id, 'source_url' => 'https://listing.test/'.$id, 'source' => 'Fixture', 'title' => 'Apartamento', 'address' => '', 'property_type' => 'apartment', 'city' => 'São Paulo', 'state' => 'SP', 'neighborhood' => 'Centro', 'asking_price' => 500000, 'area_m2' => 100, 'bedrooms' => 2], $overrides);
    }

    private function estimate(array $listings): array
    {
        return app(PropertyValuationService::class)->applyComparableMarketEstimates(collect($listings))->first();
    }

    private function transaction(string $reference, array $overrides = []): void
    {
        RealEstateTransaction::create(array_replace(['source_name' => 'Audit', 'source_reference' => $reference, 'city' => 'São Paulo', 'state' => 'SP', 'neighborhood' => 'Centro', 'property_type' => 'Apartamento', 'transaction_date' => now()->subMonth()->toDateString(), 'declared_transaction_value' => 600000, 'calculation_area_m2' => 100], $overrides));
    }

    public function test_unknown_future_and_stale_transactions_are_not_current_market_evidence(): void
    {
        $this->travelTo(Carbon::parse('2026-10-16 12:00:00'));
        config()->set('real_estate_data.avm.max_transaction_age_months', 24);
        foreach (['unknown' => null, 'future' => now()->addDay(), 'stale' => now()->subMonths(25), 'boundary' => now()->subMonths(24), 'recent' => now()->subMonth()] as $reference => $date) {
            $this->transaction($reference, ['transaction_date' => $date?->toDateString()]);
        }
        $this->assertSame(2, $this->estimate([$this->listing('subject')])['confirmed_transactions_count']);
    }

    public function test_same_street_different_number_is_not_the_same_building(): void
    {
        for ($index = 0; $index < 3; $index++) {
            $this->transaction('other-building-'.$index, ['street_name' => 'Rua Aurora', 'street_number' => '200', 'normalized_address' => 'rua aurora 200 centro sao paulo']);
        }
        $result = $this->estimate([$this->listing('subject', ['address' => 'Rua Aurora, 100'])]);
        $this->assertSame('itbi_neighborhood_median_m2', $result['market_estimate_method']);
        $this->assertSame(3, $result['confirmed_transactions_count']);
    }

    public function test_street_without_number_does_not_claim_building_precision(): void
    {
        for ($index = 0; $index < 3; $index++) {
            $this->transaction('unspecified-building-'.$index, ['street_name' => 'Rua Aurora', 'street_number' => '100', 'normalized_address' => 'rua aurora 100 centro sao paulo']);
        }
        $this->assertSame('itbi_neighborhood_median_m2', $this->estimate([$this->listing('subject', ['address' => 'Rua Aurora'])])['market_estimate_method']);
    }

    public function test_matching_street_and_number_preserve_building_reference(): void
    {
        for ($index = 0; $index < 3; $index++) {
            $this->transaction('same-building-'.$index, ['street_name' => 'Rua Aurora', 'street_number' => '100', 'normalized_address' => 'rua aurora 100 centro sao paulo']);
        }
        $this->assertSame('itbi_same_building_median_m2', $this->estimate([$this->listing('subject', ['address' => 'Rua Aurora, 100'])])['market_estimate_method']);
    }

    public function test_radius_lookup_works_in_sqlite_and_excludes_distant_transactions(): void
    {
        for ($index = 0; $index < 3; $index++) {
            $this->transaction('near-'.$index, ['latitude' => -23.55 + $index * 0.0001, 'longitude' => -46.63]);
        }
        $this->transaction('far', ['latitude' => -23.65, 'longitude' => -46.73]);
        $result = $this->estimate([$this->listing('subject', ['latitude' => -23.55, 'longitude' => -46.63])]);
        $this->assertSame('itbi_radius_500m_median_m2', $result['market_estimate_method']);
        $this->assertSame(3, $result['confirmed_transactions_count']);
    }

    public function test_discarded_price_outliers_do_not_inflate_evidence_counts(): void
    {
        $references = array_map(fn ($index) => $this->listing('reference-'.$index, ['asking_price' => $index === 5 ? 5000000 : 600000]), range(1, 5));
        $result = $this->estimate([$this->listing('subject'), ...$references]);
        $this->assertSame(4, $result['market_reference_count']);
        for ($index = 1; $index <= 5; $index++) {
            $this->transaction('transaction-'.$index, ['declared_transaction_value' => $index === 5 ? 5000000 : 600000]);
        }
        $result = $this->estimate([$this->listing('subject')]);
        $this->assertSame(4, $result['confirmed_transactions_count']);
        $this->assertEqualsWithDelta(6000, $result['valuation_explanation']['confirmed_median_price_m2'], 0.01);
    }

    public function test_radius_uses_nearest_eighty_instead_of_newest_eighty(): void
    {
        for ($index = 0; $index < 80; $index++) {
            $this->transaction('new-far-'.$index, ['latitude' => -23.547, 'longitude' => -46.63, 'declared_transaction_value' => 900000]);
            $this->transaction('old-near-'.$index, ['latitude' => -23.55 + $index * 0.000001, 'longitude' => -46.63, 'transaction_date' => now()->subMonths(2)->toDateString()]);
        }
        $result = $this->estimate([$this->listing('subject', ['latitude' => -23.55, 'longitude' => -46.63])]);
        $this->assertSame(80, $result['confirmed_transactions_count']);
        $this->assertEqualsWithDelta(6000, $result['valuation_explanation']['confirmed_median_price_m2'], 0.01);
    }

    public function test_radius_nine_hundred_meters_remains_available(): void
    {
        for ($index = 0; $index < 3; $index++) {
            $this->transaction('radius-900-'.$index, ['latitude' => -23.544, 'longitude' => -46.63]);
        }
        $result = $this->estimate([$this->listing('subject', ['latitude' => -23.55, 'longitude' => -46.63])]);
        $this->assertSame('itbi_radius_900m_median_m2', $result['market_estimate_method']);
        $this->assertSame(3, $result['confirmed_transactions_count']);
    }

    public function test_zero_latitude_is_valid_and_does_not_disable_radius_search(): void
    {
        for ($index = 0; $index < 3; $index++) {
            $this->transaction('equator-'.$index, ['city' => 'Macapá', 'state' => 'AP', 'latitude' => 0, 'longitude' => -51.05]);
        }
        $result = $this->estimate([$this->listing('subject', ['city' => 'Macapá', 'state' => 'AP', 'latitude' => 0, 'longitude' => -51.05])]);
        $this->assertSame('itbi_radius_500m_median_m2', $result['market_estimate_method']);
    }

    public function test_subject_and_duplicate_url_are_not_independent_comparables(): void
    {
        $subject = $this->listing('subject');
        $result = $this->estimate([$subject, $this->listing('copy', ['source_url' => $subject['source_url']])]);
        $this->assertSame(0, $result['market_reference_count']);
        $this->assertSame('fallback_heuristic', $result['market_estimate_method']);
        $this->assertLessThanOrEqual(40, $result['confidence_percentage']);
        $this->assertSame('Atenção', $result['signal']);
    }

    public function test_unrelated_city_type_and_area_cannot_raise_local_reference_count(): void
    {
        $result = $this->estimate([
            $this->listing('subject'), $this->listing('foreign', ['city' => 'Curitiba', 'state' => 'PR']),
            $this->listing('house', ['property_type' => 'house']), $this->listing('huge', ['area_m2' => 1000]),
        ]);
        $this->assertSame(0, $result['market_reference_count']);
        $this->assertSame('fallback_heuristic', $result['market_estimate_method']);
    }

    public function test_independent_local_listings_still_generate_an_estimate(): void
    {
        $result = $this->estimate([$this->listing('subject'), $this->listing('a', ['asking_price' => 600000]), $this->listing('b', ['asking_price' => 620000]), $this->listing('c', ['asking_price' => 580000])]);
        $this->assertSame(3, $result['market_reference_count']);
        $this->assertSame('comparables_neighborhood_median_m2', $result['market_estimate_method']);
        $this->assertGreaterThan(500000, $result['estimated_market_price']);
    }

    public function test_more_identically_priced_references_do_not_inflate_the_estimate(): void
    {
        $subject = $this->listing('subject');
        $references = array_map(fn ($index) => $this->listing('reference-'.$index, ['asking_price' => 600000]), range(1, 30));
        $small = $this->estimate([$subject, ...array_slice($references, 0, 3)]);
        $large = $this->estimate([$subject, ...$references]);
        $this->assertEqualsWithDelta($small['estimated_market_price'], $large['estimated_market_price'], 0.01);
        $this->assertEqualsWithDelta(600000, $small['estimated_market_price'], 0.01);
        $this->assertGreaterThan($small['market_reference_count'], $large['market_reference_count']);
    }

    public function test_itbi_same_neighborhood_name_in_other_city_is_excluded_and_accents_match(): void
    {
        foreach ([['foreign', 'Curitiba', 'PR', 900000], ['local', 'SÃO PAULO', 'SP', 600000]] as [$ref, $city, $state, $price]) {
            RealEstateTransaction::create(['source_name' => 'Audit', 'source_reference' => $ref, 'city' => $city, 'state' => $state, 'neighborhood' => 'Centro', 'property_type' => 'Apartamento', 'transaction_date' => now()->subMonth()->toDateString(), 'declared_transaction_value' => $price, 'calculation_area_m2' => 100]);
        }
        $result = $this->estimate([$this->listing('subject')]);
        $this->assertSame(1, $result['confirmed_transactions_count']);
        $this->assertEqualsWithDelta(6000, $result['valuation_explanation']['confirmed_median_price_m2'], 0.01);
    }
}
