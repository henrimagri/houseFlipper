<?php

namespace App\Services\Ai;

use App\Models\Opportunity;
use App\Models\Propriedade;
use App\Models\User;
use Illuminate\Support\Str;

class InventoryListingSearch
{
    public function search(User $user, array $filters): array
    {
        $properties = Propriedade::query()->ativos()->with('endereco')->get()
            ->map(fn (Propriedade $property) => $this->fromProperty($property))
            ->all();

        $opportunities = Opportunity::query()
            ->where('user_id', $user->id)
            ->where('is_archived', false)
            ->get()
            ->map(fn (Opportunity $opportunity) => $this->fromOpportunity($opportunity))
            ->all();

        return array_values(array_filter([...$properties, ...$opportunities], function (array $item) use ($filters): bool {
            if (($filters['city'] ?? '') !== '' && ! $this->contains($item, (string) $filters['city'])) {
                return false;
            }
            if (($filters['state'] ?? '') !== '' && strtoupper((string) ($item['state'] ?? '')) !== strtoupper((string) $filters['state'])) {
                return isset($item['state']) ? false : true;
            }
            if (($filters['property_type'] ?? null) && ! $this->typeMatches($item, (string) $filters['property_type'])) {
                return false;
            }

            return true;
        }));
    }

    private function fromProperty(Propriedade $property): array
    {
        $address = $property->endereco;

        return [
            'id' => 'property:'.$property->id,
            'origin' => 'inventory_property',
            'subject_type' => 'propriedade',
            'subject_id' => $property->id,
            'title' => $property->apelido,
            'property_type' => $property->tipo,
            'address' => trim(($address?->logradouro ?? '').' '.($address?->numero ?? '')),
            'city' => $address?->cidade,
            'neighborhood' => $address?->bairro,
            'state' => $address?->estado,
            'source' => 'Carteira',
            'source_key' => 'inventory',
            'source_url' => null,
            'thumbnail' => null,
            'asking_price' => (float) ($property->valor_aquisicao ?? 0),
            'estimated_market_price' => (float) ($property->valor_venda_alvo ?? 0),
            'discount_percentage' => 0,
            'area_m2' => $property->area_m2 !== null ? (float) $property->area_m2 : null,
            'bedrooms' => $property->quartos,
            'bathrooms' => $property->banheiros,
            'parking_spaces' => $property->vagas,
            'detail_path' => '/imoveis',
            'visualization_path' => '/imoveis/'.$property->id.'/3d',
            'model_note' => 'Maquete paramétrica da ficha. GLB enviado, se houver, aparece na ficha 3D deste imóvel.',
            'source_kind' => 'inventory',
            'score' => 60,
            'signal' => 'Carteira',
        ];
    }

    private function fromOpportunity(Opportunity $opportunity): array
    {
        return [
            'id' => 'opportunity:'.$opportunity->id,
            'origin' => 'inventory_opportunity',
            'subject_type' => 'opportunity',
            'subject_id' => $opportunity->id,
            'title' => $opportunity->title,
            'property_type' => $opportunity->property_type?->value ?? null,
            'address' => $opportunity->address,
            'city' => $opportunity->city,
            'neighborhood' => $opportunity->neighborhood,
            'state' => $opportunity->state,
            'source' => 'Lead',
            'source_key' => $opportunity->source?->value ?? 'manual',
            'source_url' => $opportunity->source_url,
            'thumbnail' => null,
            'asking_price' => (float) ($opportunity->asking_price ?? 0),
            'estimated_market_price' => (float) ($opportunity->estimated_market_price ?? 0),
            'discount_percentage' => 0,
            'area_m2' => $opportunity->area_m2 !== null ? (float) $opportunity->area_m2 : null,
            'bedrooms' => $opportunity->bedrooms,
            'bathrooms' => $opportunity->bathrooms,
            'parking_spaces' => $opportunity->parking_spaces,
            'detail_path' => '/leads',
            'visualization_path' => '/leads/'.$opportunity->id.'/3d',
            'model_note' => 'Maquete paramétrica deste lead. GLB enviado, se houver, aparece na ficha 3D.',
            'source_kind' => 'inventory',
            'score' => $opportunity->is_favorite ? 70 : 55,
            'signal' => $opportunity->is_favorite ? 'Favorito' : 'Lead',
        ];
    }

    private function typeMatches(array $item, string $type): bool
    {
        $raw = $this->normalize(($item['property_type'] ?? '').' '.($item['title'] ?? ''));

        return match ($type) {
            'house' => Str::contains($raw, ['casa', 'sobrado', 'house']),
            'commercial' => Str::contains($raw, ['comercial', 'loja', 'sala']),
            default => Str::contains($raw, ['apartamento', 'apto', 'apartment', 'cobertura']) || ! Str::contains($raw, ['casa', 'sobrado', 'comercial']),
        };
    }

    private function contains(array $item, string $needle): bool
    {
        $needle = $this->normalize($needle);
        $text = $this->normalize(implode(' ', [$item['title'] ?? '', $item['city'] ?? '', $item['neighborhood'] ?? '', $item['address'] ?? '']));

        return $needle === '' || Str::contains($text, $needle);
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->toString();
    }
}
