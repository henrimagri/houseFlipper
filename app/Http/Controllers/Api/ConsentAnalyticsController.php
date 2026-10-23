<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConsentAnalyticsController extends Controller
{
    public function visit(Request $r)
    {
        $rules = ['session_id' => 'required|uuid', 'consented' => 'required|accepted', 'policy' => 'required|in:analytics-v1', 'utm' => 'array'];
        foreach (['source', 'medium', 'campaign', 'term', 'content'] as $key) {
            $rules['utm.utm_'.$key] = ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9_. -]{1,80}$/D', 'not_regex:/\d{7,}/'];
        }
        $data = $r->validate($rules);
        $utm = array_intersect_key($data['utm'] ?? [], array_flip(['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content']));
        DB::table('platform_consent_attributions')->insertOrIgnore(['session_hash' => hash_hmac('sha256', $data['session_id'], config('app.key')), 'policy' => $data['policy'], 'utm' => json_encode($utm), 'consented_at' => now(), 'created_at' => now()]);

        return response()->json(['recorded' => true, 'scope' => 'optional_consented_session_not_unique_person'])->header('Cache-Control', 'no-store');
    }
}
