<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Ai\ProductEntitlement;
use Illuminate\Http\JsonResponse;

class PublicCatalogController extends Controller
{
    public function __invoke(ProductEntitlement $entitlement): JsonResponse
    {
        return response()->json(['data' => $entitlement->publicCatalog()]);
    }
}
