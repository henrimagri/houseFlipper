<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SaasLead;
use App\Models\SaasLeadEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class SaasLeadController extends Controller
{
    private const STATUSES = [
        'novo',
        'em_contato',
        'qualificado',
        'demonstracao_agendada',
        'proposta_enviada',
        'convertido',
        'perdido',
        'arquivado',
    ];

    private const INTENTS = ['demo', 'contact', 'proposal', 'question'];

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'email' => ['required', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:40'],
            'product_slug' => ['nullable', 'in:corretor,imobiliaria,house_flipper'],
            'business_type' => ['nullable', 'string', 'max:80'],
            'intent' => ['nullable', Rule::in(self::INTENTS)],
            'message' => ['nullable', 'string', 'max:4000'],
            'source_path' => ['nullable', 'string', 'max:180'],
            'consent' => ['accepted'],
            'website' => ['nullable', 'string', 'max:200'],
        ]);

        if (filled($data['website'] ?? null)) {
            return response()->json([
                'message' => 'Solicitação registrada.',
                'email_confirmation' => false,
            ], 201);
        }

        $lead = SaasLead::query()->create([
            'name' => $data['name'],
            'email' => mb_strtolower(trim($data['email'])),
            'phone' => $data['phone'] ?? null,
            'product_slug' => $data['product_slug'] ?? null,
            'business_type' => $data['business_type'] ?? null,
            'intent' => $data['intent'] ?? 'demo',
            'message' => $data['message'] ?? null,
            'source_path' => $data['source_path'] ?? $request->headers->get('referer'),
            'status' => 'novo',
            'consent' => true,
            'ip_hash' => hash('sha256', $request->ip().'|'.config('app.key')),
        ]);
        SaasLeadEvent::query()->create([
            'saas_lead_id' => $lead->id,
            'to_status' => 'novo',
            'body' => 'Solicitação recebida pelo site público.',
        ]);

        return response()->json([
            'message' => 'Solicitação registrada. Nossa equipe comercial consulta a fila interna.',
            'email_confirmation' => $this->maybeConfirm($lead),
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $query = SaasLead::query()->with(['events' => fn ($events) => $events->latest()->limit(8)])->latest();
        if ($product = $request->string('product')->toString()) {
            $query->where('product_slug', $product);
        }
        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }
        if ($intent = $request->string('intent')->toString()) {
            $query->where('intent', $intent);
        }

        return response()->json(['data' => $query->limit(200)->get()]);
    }

    public function update(Request $request, SaasLead $lead): JsonResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', Rule::in(self::STATUSES)],
            'notes' => ['nullable', 'string', 'max:4000'],
            'assigned_user_id' => ['nullable', 'integer'],
        ]);
        $from = $lead->status;
        $lead->fill($data)->save();
        if (($data['status'] ?? $from) !== $from || filled($data['notes'] ?? null)) {
            SaasLeadEvent::query()->create([
                'saas_lead_id' => $lead->id,
                'user_id' => $request->user()->id,
                'from_status' => $from,
                'to_status' => $lead->status,
                'body' => $data['notes'] ?? null,
            ]);
        }

        return response()->json(['data' => $lead->fresh()->load('events')]);
    }

    private function maybeConfirm(SaasLead $lead): bool
    {
        if (in_array(config('mail.default'), ['log', 'array'], true)) {
            return false;
        }
        try {
            Mail::raw(
                "Olá {$lead->name},\n\nRecebemos sua solicitação sobre a plataforma FlipControl. Nossa equipe comercial retornará pelo e-mail informado.\n\nEsta mensagem não cria assinatura nem inicia teste.",
                function ($message) use ($lead) {
                    $message->to($lead->email)->subject('Recebemos sua solicitação — FlipControl');
                }
            );

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
