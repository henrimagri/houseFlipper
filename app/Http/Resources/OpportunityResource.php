<?php

namespace App\Http\Resources;

use App\Support\ExternalUrlPolicy;
use App\Support\OpportunityPhotoUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpportunityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'property_type' => $this->property_type->value,
            'property_type_label' => $this->property_type->label(),
            'source' => $this->source->value,
            'source_label' => $this->source->label(),
            'source_url' => ExternalUrlPolicy::allows($this->source_url) ? $this->source_url : null,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'address' => $this->address,
            'neighborhood' => $this->neighborhood,
            'city' => $this->city,
            'state' => $this->state,
            'zip_code' => $this->zip_code,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'asking_price' => $this->asking_price,
            'estimated_market_price' => $this->estimated_market_price,
            'area_m2' => $this->area_m2,
            'bedrooms' => $this->bedrooms,
            'bathrooms' => $this->bathrooms,
            'parking_spaces' => $this->parking_spaces,
            'conservation_status' => $this->conservation_status,
            'is_favorite' => $this->is_favorite,
            'is_archived' => $this->is_archived,
            'discarded_reason' => $this->discarded_reason,
            'notes' => $this->notes,
            'cover_url' => $this->whenLoaded('coverPhoto', fn () => $this->coverPhoto?->path ? $this->storageUrl($this->coverPhoto->path) : null),
            'photos' => $this->whenLoaded('photos', fn () => $this->photos
                ->map(fn ($photo) => $this->photoPayload($photo))
                ->filter()
                ->values()),
            'status_histories' => $this->whenLoaded('statusHistories', fn () => $this->statusHistories->map(fn ($history) => ['id' => $history->id, 'old_status' => $history->old_status?->value, 'new_status' => $history->new_status->value, 'new_status_label' => $history->new_status->label(), 'notes' => $history->notes, 'user' => $history->user->name, 'created_at' => $history->created_at->toIso8601String()])),
            'primary_analysis_id' => $this->investmentAnalyses()->where('is_primary', true)->value('id'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function photoPayload(mixed $photo): ?array
    {
        $url = $this->storageUrl((string) $photo->path);
        if ($url === null) {
            return null;
        }

        return [
            'id' => $photo->id,
            'url' => $url,
            'name' => $photo->original_name,
            'is_cover' => $photo->is_cover,
        ];
    }

    private function storageUrl(string $path): ?string
    {
        return OpportunityPhotoUrl::for($path);
    }
}
