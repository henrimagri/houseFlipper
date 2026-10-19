<?php

namespace App\Http\Requests;

use App\Enums\OpportunitySource;
use App\Enums\OpportunityStatus;
use App\Enums\PropertyType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OpportunityIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:120'],
            'status' => ['sometimes', Rule::enum(OpportunityStatus::class)],
            'city' => ['sometimes', 'string', 'max:120'],
            'neighborhood' => ['sometimes', 'string', 'max:120'],
            'property_type' => ['sometimes', Rule::enum(PropertyType::class)],
            'source' => ['sometimes', Rule::enum(OpportunitySource::class)],
            'favorite' => ['sometimes', 'boolean'],
            'archived' => ['sometimes', 'boolean'],
            'min_price' => ['sometimes', 'numeric', 'min:0', 'max:999999999999'],
            'max_price' => ['sometimes', 'numeric', 'gte:min_price', 'max:999999999999'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
