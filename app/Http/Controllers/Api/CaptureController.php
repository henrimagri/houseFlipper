<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuthorizationResource;
use App\Models\CommercializationAuthorization;
use App\Models\Opportunity;
use App\Services\Marketplace\CaptureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CaptureController extends Controller
{
    public function __construct(private readonly CaptureService $captures) {}

    public function showForOpportunity(Request $request, Opportunity $opportunity): JsonResponse
    {
        $this->authorize('view', $opportunity);
        $authorization = $this->captures->statusForOpportunity($request->user(), $opportunity);

        return response()->json([
            'data' => $authorization ? (new AuthorizationResource($authorization))->resolve() : null,
        ]);
    }

    public function start(Request $request, Opportunity $opportunity): JsonResponse
    {
        $this->authorize('update', $opportunity);
        $authorization = $this->captures->startFromOpportunity($request->user(), $opportunity);

        return (new AuthorizationResource($authorization))->response()->setStatusCode(201);
    }

    public function grant(Request $request, CommercializationAuthorization $capture): AuthorizationResource
    {
        $this->authorize('update', $capture);
        $data = $request->validate([
            'owner_name' => ['required', 'string', 'max:180'],
            'owner_contact' => ['nullable', 'string', 'max:180'],
            'owner_document' => ['nullable', 'string', 'max:20'],
            'scope' => ['nullable', 'in:divulgar,intermediar,fotografar'],
            'evidence_notes' => ['nullable', 'string', 'max:2000'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'evidence' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,webp'],
        ]);

        return new AuthorizationResource(
            $this->captures->grant($request->user(), $capture, $data, $request->file('evidence'))
        );
    }

    public function revoke(Request $request, CommercializationAuthorization $capture): AuthorizationResource
    {
        $this->authorize('update', $capture);

        return new AuthorizationResource($this->captures->revoke($request->user(), $capture));
    }
}
