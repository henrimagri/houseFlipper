<?php

namespace App\Services;

use App\Models\InvestmentAnalysis;

class OpportunityScoreService
{
    public function calculate(InvestmentAnalysis $analysis): array
    {
        $opportunity = $analysis->opportunity;
        $asking = (float) ($opportunity->asking_price ?: $analysis->purchase_price);
        $market = (float) ($opportunity->estimated_market_price ?: $analysis->expected_sale_price);
        $discount = $market > 0 ? (($market - $asking) / $market) * 100 : 0;
        $hasLocation = filled($opportunity->city) && filled($opportunity->state);
        $hasAreaReference = (float) $analysis->average_price_per_m2 > 0 && (float) $opportunity->area_m2 > 0;
        $hasSoldReference = (float) $analysis->average_sold_value > 0;

        $score = (int) round(max(0, min(100,
            35 + min(35, max(0, $discount) * 1.4)
            + ($hasLocation ? 10 : 0)
            + ($hasAreaReference ? 10 : 0)
            + ($hasSoldReference ? 10 : 0)
        )));
        $label = $score >= 85 ? 'Excelente oportunidade de mercado' : ($score >= 70 ? 'Boa oportunidade de mercado' : ($score >= 50 ? 'Oportunidade requer atenção' : 'Baixo potencial de mercado'));

        return [
            'score' => $score,
            'label' => $label,
            'potential' => $score >= 85 ? 'Excelente' : ($score >= 70 ? 'Bom' : ($score >= 50 ? 'Atenção' : 'Baixo')),
            'discount_percentage' => round($discount, 2),
            'factors' => ['Preço pedido', 'Valor de mercado', 'Preço por m²', 'Liquidez', 'Localização', 'Desconto', 'Valorização'],
            'disclaimer' => 'Esta nota considera apenas fatores de mercado. Os custos da operação ainda não foram analisados.',
        ];
    }
}
