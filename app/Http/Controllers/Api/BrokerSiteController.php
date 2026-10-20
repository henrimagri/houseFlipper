<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Marketplace\BrokerSiteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrokerSiteController extends Controller
{
    public function __construct(private readonly BrokerSiteService $sites) {}

    public function show(Request $request): JsonResponse
    {
        $site = $this->sites->forUser($request->user());

        return response()->json(['data' => $this->payload($site, $request)]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'slug' => ['nullable', 'string', 'max:80'],
            'theme' => ['nullable', 'array'],
            'theme.display_name' => ['nullable', 'string', 'max:80'],
            'theme.headline' => ['nullable', 'string', 'max:180'],
            'theme.bio' => ['nullable', 'string', 'max:800'],
            'theme.creci' => ['nullable', 'string', 'max:40'],
            'theme.phone' => ['nullable', 'string', 'max:40'],
            'theme.whatsapp' => ['nullable', 'string', 'max:20'],
            'theme.instagram' => ['nullable', 'string', 'max:80'],
            'theme.primary_color' => ['nullable', 'string', 'max:7'],
            'theme.accent_color' => ['nullable', 'string', 'max:7'],
            'theme.background_color' => ['nullable', 'string', 'max:7'],
            'theme.surface_color' => ['nullable', 'string', 'max:7'],
            'theme.text_color' => ['nullable', 'string', 'max:7'],
            'theme.font_family' => ['nullable', 'in:sans,serif,modern'],
            'theme.hero_style' => ['nullable', 'in:solid,gradient,minimal'],
            'theme.card_style' => ['nullable', 'in:soft,elevated,square'],
            'theme.button_style' => ['nullable', 'in:rounded,pill,square'],
            'theme.footer_text' => ['nullable', 'string', 'max:160'],
            'theme.sections' => ['nullable', 'array', 'max:12'],
            'theme.sections.*.id' => ['nullable', 'string', 'max:40'],
            'theme.sections.*.type' => ['nullable', 'string', 'max:40'],
            'theme.sections.*.visible' => ['nullable', 'boolean'],
            'theme.sections.*.title' => ['nullable', 'string', 'max:120'],
            'theme.sections.*.body' => ['nullable', 'string', 'max:1200'],
        ]);
        $site = $this->sites->update($request->user(), $data);

        return response()->json(['data' => $this->payload($site, $request)]);
    }

    private function payload($site, Request $request): array
    {
        return [
            'slug' => $site->slug,
            'status' => $site->status->value,
            'status_label' => $site->status->label(),
            'theme' => $site->theme_json,
            'public_path' => '/corretor/'.$site->slug,
            'live' => $this->sites->isLive($request->user(), $site),
            'views_count' => $site->views_count,
        ];
    }
}
