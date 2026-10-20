<?php

namespace App\Services\Marketplace;

use App\Models\CommercialListing;
use App\Models\User;
use App\Services\Ai\ProductEntitlement;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MarketingCopyService
{
    private const STYLES = ['premium', 'luxo', 'popular', 'investimento', 'primeiro_imovel', 'lancamento', 'oportunidade'];

    public function __construct(private readonly ProductEntitlement $entitlement) {}

    public function compose(User $user, CommercialListing $listing, string $style): array
    {
        $this->entitlement->assert($user, 'marketing_copy');
        if ($user->id !== $listing->user_id) {
            abort(404);
        }
        if (! in_array($style, self::STYLES, true)) {
            throw ValidationException::withMessages(['style' => 'Estilo não reconhecido.']);
        }
        $facts = $this->facts($listing);
        $headline = match ($style) {
            'luxo', 'premium' => $this->join(['Imóvel', $facts['type'], $facts['place']]),
            'investimento' => $this->join(['Oportunidade de investimento', $facts['place']]),
            'primeiro_imovel' => $this->join(['Primeiro imóvel', $facts['place']]),
            'lancamento' => $this->join(['Imóvel em destaque', $facts['place']]),
            default => $listing->title,
        };
        $lines = array_values(array_filter([
            $headline,
            $facts['rooms'],
            $facts['area'],
            $facts['price'],
            $listing->description ? mb_substr(strip_tags($listing->description), 0, 280) : null,
        ]));
        $hashtags = array_values(array_filter([
            $facts['cityTag'],
            '#imoveis',
            $style === 'investimento' ? '#investimentoimobiliario' : null,
        ]));

        return [
            'style' => $style,
            'invented' => false,
            'used_fields' => array_keys(array_filter($facts)),
            'instagram' => implode("\n", $lines),
            'whatsapp' => implode(' · ', array_filter([$listing->title, $facts['place'], $facts['price'], $facts['rooms']])),
            'ad' => implode('. ', $lines),
            'hashtags' => $hashtags,
            'missing' => array_values(array_keys(array_filter([
                'preço' => $listing->asking_price_cents === null,
                'área' => $listing->area_m2 === null,
                'descrição' => ! $listing->description,
            ]))),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function facts(CommercialListing $listing): array
    {
        $place = trim(implode(', ', array_filter([$listing->neighborhood, $listing->city, $listing->state])));
        $rooms = $listing->bedrooms ? $listing->bedrooms.' quartos' : null;
        $area = $listing->area_m2 ? number_format((float) $listing->area_m2, 0, ',', '.').' m²' : null;
        $price = $listing->asking_price_cents !== null
            ? 'R$ '.number_format($listing->asking_price_cents / 100, 2, ',', '.')
            : null;
        $cityTag = $listing->city ? '#'.Str::slug($listing->city, '') : null;

        return [
            'type' => $listing->property_type->label(),
            'place' => $place !== '' ? $place : null,
            'rooms' => $rooms,
            'area' => $area,
            'price' => $price,
            'cityTag' => $cityTag,
        ];
    }

    /**
     * @param  array<int, string|null>  $parts
     */
    private function join(array $parts): string
    {
        return trim(implode(' ', array_filter($parts)));
    }
}
