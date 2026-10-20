<?php

namespace App\Services\Marketplace;

use App\Enums\AuthorizationStatus;
use App\Enums\CommercialListingStatus;
use App\Models\CommercializationAuthorization;
use App\Models\ListingSource;
use App\Models\Opportunity;
use App\Models\User;
use App\Services\Ai\ProductEntitlement;
use App\Support\CommercialPhotoPathPolicy;
use App\Support\ExternalUrlPolicy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CaptureService
{
    public function __construct(
        private readonly ProductEntitlement $entitlement,
        private readonly MarketplaceAudit $audit,
        private readonly CommercialListingService $listings,
    ) {}

    public function startFromOpportunity(User $user, Opportunity $opportunity): CommercializationAuthorization
    {
        $this->entitlement->assert($user, 'capture');
        $this->assertOwner($user, $opportunity);

        return DB::transaction(function () use ($user, $opportunity) {
            $source = $this->snapshotOpportunity($user, $opportunity);
            $existing = CommercializationAuthorization::query()
                ->where('user_id', $user->id)
                ->where('opportunity_id', $opportunity->id)
                ->whereIn('status', [AuthorizationStatus::Pending->value, AuthorizationStatus::Granted->value])
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            $authorization = CommercializationAuthorization::query()->create([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'listing_source_id' => $source->id,
                'opportunity_id' => $opportunity->id,
                'status' => AuthorizationStatus::Pending,
                'scope' => 'divulgar',
            ]);
            $this->audit->record($user, 'capture.started', $authorization, [
                'opportunity_id' => $opportunity->id,
                'source_id' => $source->id,
            ]);

            return $authorization;
        });
    }

    public function grant(User $user, CommercializationAuthorization $authorization, array $data, ?UploadedFile $evidence): CommercializationAuthorization
    {
        $this->entitlement->assert($user, 'capture');
        $this->assertAuthorizationOwner($user, $authorization);

        if ($authorization->status === AuthorizationStatus::Revoked) {
            throw ValidationException::withMessages(['status' => 'Autorização revogada. Inicie uma nova captação.']);
        }

        $document = preg_replace('/\D+/', '', (string) ($data['owner_document'] ?? '')) ?? '';
        if ($document !== '' && (strlen($document) < 11 || strlen($document) > 14)) {
            throw ValidationException::withMessages(['owner_document' => 'Informe CPF ou CNPJ apenas com dígitos.']);
        }

        return DB::transaction(function () use ($user, $authorization, $data, $evidence, $document) {
            $locked = CommercializationAuthorization::query()->lockForUpdate()->findOrFail($authorization->id);
            $path = $locked->evidence_path;
            if ($evidence) {
                $path = $this->storeEvidence($user, $locked, $evidence);
            }

            $scope = in_array($data['scope'] ?? 'divulgar', ['divulgar', 'intermediar', 'fotografar'], true)
                ? ($data['scope'] ?? 'divulgar')
                : 'divulgar';
            $locked->fill([
                'owner_name' => mb_substr(trim((string) $data['owner_name']), 0, 180),
                'owner_contact' => mb_substr(trim((string) ($data['owner_contact'] ?? '')), 0, 180),
                'owner_document_hash' => $document === '' ? $locked->owner_document_hash : hash('sha256', $user->tenant_id.'|'.$document),
                'scope' => $scope,
                'evidence_notes' => mb_substr(trim((string) ($data['evidence_notes'] ?? '')), 0, 2000),
                'evidence_path' => $path,
                'status' => AuthorizationStatus::Granted,
                'granted_at' => now(),
                'expires_at' => isset($data['expires_at']) && $data['expires_at'] ? $data['expires_at'] : $locked->expires_at,
                'revoked_at' => null,
            ])->save();

            $this->audit->record($user, 'capture.granted', $locked);

            return $locked->fresh(['listingSource']);
        });
    }

    public function revoke(User $user, CommercializationAuthorization $authorization): CommercializationAuthorization
    {
        $this->entitlement->assert($user, 'capture');
        $this->assertAuthorizationOwner($user, $authorization);

        return DB::transaction(function () use ($user, $authorization) {
            $locked = CommercializationAuthorization::query()->lockForUpdate()->findOrFail($authorization->id);
            $locked->fill([
                'status' => AuthorizationStatus::Revoked,
                'revoked_at' => now(),
            ])->save();
            $this->listings->unpublishForAuthorization($user, $locked, 'authorization_revoked');
            $this->audit->record($user, 'capture.revoked', $locked);

            return $locked;
        });
    }

    public function statusForOpportunity(User $user, Opportunity $opportunity): ?CommercializationAuthorization
    {
        $this->assertOwner($user, $opportunity);

        return CommercializationAuthorization::query()
            ->where('user_id', $user->id)
            ->where('opportunity_id', $opportunity->id)
            ->latest('id')
            ->first();
    }

    private function snapshotOpportunity(User $user, Opportunity $opportunity): ListingSource
    {
        $payload = [
            'title' => $opportunity->title,
            'description' => $opportunity->description,
            'property_type' => $opportunity->property_type->value,
            'source' => $opportunity->source->value,
            'source_url' => ExternalUrlPolicy::allows($opportunity->source_url) ? $opportunity->source_url : null,
            'address' => $opportunity->address,
            'neighborhood' => $opportunity->neighborhood,
            'city' => $opportunity->city,
            'state' => $opportunity->state,
            'asking_price' => $opportunity->asking_price,
            'area_m2' => $opportunity->area_m2,
            'bedrooms' => $opportunity->bedrooms,
            'bathrooms' => $opportunity->bathrooms,
            'parking_spaces' => $opportunity->parking_spaces,
            'photos_copied' => false,
        ];
        $hash = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return ListingSource::query()->firstOrCreate(
            ['user_id' => $user->id, 'content_hash' => $hash],
            [
                'tenant_id' => $user->tenant_id,
                'opportunity_id' => $opportunity->id,
                'provider' => $opportunity->source->value,
                'external_id' => null,
                'source_url' => $payload['source_url'],
                'title' => $opportunity->title,
                'price_cents' => $opportunity->asking_price !== null ? (int) round(((float) $opportunity->asking_price) * 100) : null,
                'area_m2' => $opportunity->area_m2,
                'payload_json' => $payload,
                'captured_at' => now(),
            ],
        );
    }

    private function storeEvidence(User $user, CommercializationAuthorization $authorization, UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['pdf', 'jpg', 'jpeg', 'png', 'webp'], true)) {
            throw ValidationException::withMessages(['evidence' => 'Envie PDF ou imagem.']);
        }
        $path = 'capture-evidence/'.$user->tenant_id.'/'.$authorization->id.'/'.Str::uuid().'.'.$extension;
        if (! CommercialPhotoPathPolicy::evidenceAllows($path)) {
            throw ValidationException::withMessages(['evidence' => 'Caminho de evidência inválido.']);
        }
        Storage::disk('local')->put($path, $file->getContent());

        return $path;
    }

    private function assertOwner(User $user, Opportunity $opportunity): void
    {
        if ($user->id !== $opportunity->user_id) {
            abort(404);
        }
    }

    private function assertAuthorizationOwner(User $user, CommercializationAuthorization $authorization): void
    {
        if ($user->id !== $authorization->user_id || (int) $user->tenant_id !== (int) $authorization->tenant_id) {
            abort(404);
        }
    }
}
