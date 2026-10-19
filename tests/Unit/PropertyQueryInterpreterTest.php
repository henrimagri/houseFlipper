<?php

namespace Tests\Unit;

use App\Services\Ai\PropertyQueryInterpreter;
use PHPUnit\Framework\TestCase;

class PropertyQueryInterpreterTest extends TestCase
{
    public function test_parses_fortaleza_query_with_required_and_preferred_filters(): void
    {
        $criteria = (new PropertyQueryInterpreter)->interpret(
            'Quero um apartamento em Fortaleza, de preferência na Aldeota ou Meireles, até R$ 650 mil, com pelo menos dois quartos e duas vagas.'
        );

        $this->assertSame('search', $criteria['intent']);
        $this->assertSame('apartment', $criteria['required']['property_type']);
        $this->assertSame('Fortaleza', $criteria['required']['city']);
        $this->assertSame('CE', $criteria['required']['state']);
        $this->assertSame(650000.0, $criteria['required']['max_price']);
        $this->assertSame(2, $criteria['required']['min_bedrooms']);
        $this->assertSame(2, $criteria['required']['min_parking']);
        $this->assertEqualsCanonicalizing(['Aldeota', 'Meireles'], $criteria['preferred']['neighborhoods']);
    }

    public function test_follow_up_keeps_required_filters_and_adds_preferred_feature(): void
    {
        $interpreter = new PropertyQueryInterpreter;
        $first = $interpreter->interpret('Quero um apartamento em Fortaleza até R$ 650 mil com dois quartos.');
        $second = $interpreter->interpret('Agora procure opções parecidas, mas com varanda e condomínio mais barato.', $first);

        $this->assertSame('refine', $second['intent']);
        $this->assertSame(650000.0, $second['required']['max_price']);
        $this->assertContains('balcony', $second['preferred']['features']);
        $this->assertTrue($second['preferred']['cheaper_condo']);
        $this->assertNotEmpty($second['unknowns']);
    }
}
