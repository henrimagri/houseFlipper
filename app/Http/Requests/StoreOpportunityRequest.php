<?php

namespace App\Http\Requests;

use App\Enums\OpportunitySource;
use App\Enums\OpportunityStatus;
use App\Enums\PropertyType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOpportunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string'],
            'property_type' => ['required', Rule::enum(PropertyType::class)], 'source' => ['required', Rule::enum(OpportunitySource::class)],
            'source_url' => ['nullable', 'url', 'max:2048'], 'cover_image_url' => ['nullable', 'url', 'max:2048'], 'status' => ['nullable', Rule::enum(OpportunityStatus::class)],
            'address' => ['nullable', 'string', 'max:255'], 'neighborhood' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'], 'state' => ['nullable', 'string', 'size:2'], 'zip_code' => ['nullable', 'string', 'max:12'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'], 'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'asking_price' => ['nullable', 'numeric', 'min:0'], 'estimated_market_price' => ['nullable', 'numeric', 'min:0'],
            'area_m2' => ['nullable', 'numeric', 'min:0'], 'bedrooms' => ['nullable', 'integer', 'min:0', 'max:100'],
            'bathrooms' => ['nullable', 'integer', 'min:0', 'max:100'], 'parking_spaces' => ['nullable', 'integer', 'min:0', 'max:100'],
            'conservation_status' => ['nullable', 'string', 'max:50'], 'notes' => ['nullable', 'string'],
            'photos' => ['nullable', 'array', 'max:20'], 'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'cover_photo' => ['nullable', 'integer', 'min:0'],
            'existing_cover_photo_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
