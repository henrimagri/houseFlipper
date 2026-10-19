<?php

namespace App\Http\Requests;

use App\Enums\OpportunitySource;
use App\Enums\OpportunityStatus;
use App\Enums\PropertyType;
use App\Support\ExternalImageUrlPolicy;
use App\Support\ExternalUrlPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreOpportunityRequest extends FormRequest
{
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $photos = $this->file('photos', []);
            if ($this->filled('cover_photo') && (! is_array($photos) || ! array_key_exists($this->integer('cover_photo'), $photos))) {
                $validator->errors()->add('cover_photo', 'Selecione uma das fotos enviadas como capa.');
            }
        }];
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:5000'],
            'property_type' => ['required', Rule::enum(PropertyType::class)], 'source' => ['required', Rule::enum(OpportunitySource::class)],
            'source_url' => ['nullable', 'string', 'max:2048', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! ExternalUrlPolicy::allows($value)) {
                    $fail('A URL da origem deve usar HTTPS público e sem credenciais.');
                }
            }],
            'cover_image_url' => ['nullable', 'string', 'max:2048', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! ExternalImageUrlPolicy::allows($value)) {
                    $fail('A imagem de capa deve usar uma URL HTTPS pública e sem credenciais.');
                }
            }],
            'status' => ['sometimes', 'required', Rule::enum(OpportunityStatus::class)],
            'address' => ['nullable', 'string', 'max:255'], 'neighborhood' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'], 'state' => ['nullable', 'string', 'size:2'], 'zip_code' => ['nullable', 'string', 'max:12'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'], 'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'asking_price' => ['nullable', 'numeric', 'min:0', 'max:999999999999'], 'estimated_market_price' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'area_m2' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'], 'bedrooms' => ['nullable', 'integer', 'min:0', 'max:100'],
            'bathrooms' => ['nullable', 'integer', 'min:0', 'max:100'], 'parking_spaces' => ['nullable', 'integer', 'min:0', 'max:100'],
            'conservation_status' => ['nullable', 'string', 'max:50'], 'notes' => ['nullable', 'string', 'max:5000'],
            'photos' => ['nullable', 'array', 'max:20'], 'photos.*' => [
                'image', 'mimes:jpg,jpeg,png,webp', 'max:5120',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $value instanceof UploadedFile || ! $value->isValid()) {
                        return;
                    }

                    $size = @getimagesize($value->getRealPath());
                    if (! is_array($size)) {
                        return;
                    }

                    $width = (int) ($size[0] ?? 0);
                    $height = (int) ($size[1] ?? 0);
                    if ($width > 12000 || $height > 12000 || $width * $height > 40_000_000) {
                        $fail('A foto excede o limite seguro de dimensões.');
                    }
                },
            ],
            'cover_photo' => ['nullable', 'integer', 'min:0'],
            'existing_cover_photo_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
