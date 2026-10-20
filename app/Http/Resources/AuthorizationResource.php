<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthorizationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'opportunity_id' => $this->opportunity_id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'scope' => $this->scope,
            'owner_name' => $this->owner_name,
            'owner_contact' => $this->owner_contact,
            'has_document' => filled($this->owner_document_hash),
            'has_evidence' => filled($this->evidence_path),
            'evidence_notes' => $this->evidence_notes,
            'granted_at' => $this->granted_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'usable' => $this->isUsable(),
        ];
    }
}
