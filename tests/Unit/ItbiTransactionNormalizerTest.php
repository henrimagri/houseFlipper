<?php

namespace Tests\Unit;

use App\Services\RealEstateData\ItbiTransactionNormalizerService;
use Tests\TestCase;

class ItbiTransactionNormalizerTest extends TestCase
{
    public function test_normalizes_money_date_area_and_price_per_square_meter(): void
    {
        $row = [
            'SQL' => ' 123.456.7890-1 ',
            'Data da Transacao' => '15/05/2025',
            'Valor da Transacao' => 'R$ 735.000,50',
            'Base de Calculo' => '700000,00',
            'Tipo do Imovel' => 'Apartamento',
            'Logradouro' => 'av paulista',
            'Numero' => '1000',
            'Bairro' => 'Bela Vista',
            'Area Util' => '98,00',
            'Cartorio' => '1º RI',
        ];

        $normalized = app(ItbiTransactionNormalizerService::class)->normalize($row, 2, 'Prefeitura de São Paulo - ITBI');

        $this->assertSame('123.456.7890-1', $normalized['property_sql']);
        $this->assertSame('2025-05-15', $normalized['transaction_date']);
        $this->assertSame(735000.50, $normalized['declared_transaction_value']);
        $this->assertSame(98.0, $normalized['private_area_m2']);
        $this->assertSame('private_area_m2', $normalized['calculation_area_source']);
        $this->assertSame(7500.01, $normalized['price_m2']);
        $this->assertSame('apartamento', $normalized['property_type']);
        $this->assertNotEmpty($normalized['address_hash']);
        $this->assertNotEmpty($normalized['building_key']);
        $this->assertNotEmpty($normalized['source_reference']);
    }

    public function test_uses_land_area_only_for_land_lots(): void
    {
        $row = [
            'Data da Transacao' => '2025-01-10',
            'Valor da Transacao' => '500000',
            'Tipo do Imovel' => 'Terreno',
            'Area do Terreno' => '250',
            'Logradouro' => 'Rua A',
            'Numero' => '10',
        ];

        $normalized = app(ItbiTransactionNormalizerService::class)->normalize($row, 2, 'Prefeitura de São Paulo - ITBI');

        $this->assertSame('terreno', $normalized['property_type']);
        $this->assertSame('land_area_m2', $normalized['calculation_area_source']);
        $this->assertSame(2000.0, $normalized['price_m2']);
    }
}
