<?php

namespace App\Http\Resources;

use App\Models\CommercialListingPhoto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommercialListingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'description' => $this->description,
            'property_type' => $this->property_type->value,
            'property_type_label' => $this->property_type->label(),
            'asking_price_cents' => $this->asking_price_cents,
            'area_m2' => $this->area_m2,
            'bedrooms' => $this->bedrooms,
            'bathrooms' => $this->bathrooms,
            'parking_spaces' => $this->parking_spaces,
            'address' => $this->address,
            'neighborhood' => $this->neighborhood,
            'city' => $this->city,
            'state' => $this->state,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'highlight' => $this->highlight,
            'views_count' => $this->views_count,
            'authorization_id' => $this->authorization_id,
            'authorization_usable' => $this->whenLoaded('authorization', fn () => $this->authorization?->isUsable()),
            'source' => $this->whenLoaded('listingSource', fn () => $this->listingSource ? [
                'provider' => $this->listingSource->provider,
                'captured_at' => $this->listingSource->captured_at?->toIso8601String(),
                'title' => $this->listingSource->title,
                'price_cents' => $this->listingSource->price_cents,
            ] : null),
            'photos' => $this->whenLoaded('photos', fn () => $this->photos->map(fn (CommercialListingPhoto $photo) => [
                'id' => $photo->id,
                'url' => '/api/commercial-listings/'.$this->id.'/photos/'.$photo->id.'/file',
                'licensed_for_publication' => $photo->licensed_for_publication,
                'is_cover' => $photo->is_cover,
                'name' => $photo->original_name,
            ])),
            'public_url' => '/corretor/'.$this->when(isset($this->broker_slug), $this->broker_slug, '').'/imovel/'.$this->slug,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
