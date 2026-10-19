<?php

namespace App\Http\Requests;

use Illuminate\Support\Facades\Gate;

class UpdateOpportunityRequest extends StoreOpportunityRequest
{
    public function authorize(): bool
    {
        $opportunity = $this->route('opportunity');
        if (! $opportunity || ! $this->user()) {
            return false;
        }

        Gate::forUser($this->user())->authorize('update', $opportunity);

        return true;
    }

    public function rules(): array
    {
        $rules = parent::rules();
        if ($this->isMethod('PATCH')) {
            foreach (['title', 'property_type', 'source'] as $field) {
                $rules[$field][0] = 'sometimes';
            }
        }

        return $rules;
    }
}
