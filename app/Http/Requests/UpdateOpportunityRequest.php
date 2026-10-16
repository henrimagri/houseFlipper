<?php

namespace App\Http\Requests;

class UpdateOpportunityRequest extends StoreOpportunityRequest
{
    public function authorize(): bool
    {
        $opportunity = $this->route('opportunity');

        return $opportunity && $this->user()?->can('update', $opportunity);
    }
}
