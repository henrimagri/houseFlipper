<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CategoriaDespesa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoriaDespesaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = CategoriaDespesa::ativos()->orderBy('nome');

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->string('tipo'));
        }

        return response()->json(['data' => $query->get(['id', 'nome', 'tipo'])]);
    }
}
