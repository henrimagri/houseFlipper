<?php

namespace App\Services\Ai;

use App\Models\User;
use Illuminate\Support\Str;

class AiAgentCatalog
{
    public function select(User $user, string $product, string $message, string $intent): array
    {
        $normalized = Str::of($message)->lower()->ascii()->toString();
        $agent = match (true) {
            $intent === 'general' => 'general_assistant',
            $intent === 'business' => 'business_assistant',
            $intent === 'marketing' => 'marketing_assistant',
            $intent === 'text_creation' => 'text_creation_assistant',
            Str::contains($normalized, ['foto', 'imagem', 'reforma', 'decor', '3d', 'planta']) && $product === 'house_flipper' => 'vision_renovation',
            $product === 'imobiliaria' && Str::contains($normalized, ['crm', 'lead', 'funil', 'equipe', 'corretor']) => 'agency_intelligence',
            $product === 'house_flipper' && Str::contains($normalized, ['roi', 'retorno', 'lucro', 'reforma', 'investimento', 'margem']) => 'house_flipper_analyst',
            $product === 'corretor' => 'broker_assistant',
            $product === 'imobiliaria' => 'agency_intelligence',
            $product === 'house_flipper' => 'house_flipper_analyst',
            default => 'real_estate_search',
        };
        $definition = config('ai.agents.'.$agent, config('ai.agents.real_estate_search'));
        $allowedForProduct = collect(config('ai.product_agents.'.$product, []))->contains($agent);
        if (! $allowedForProduct) {
            $agent = 'real_estate_search';
            $definition = config('ai.agents.real_estate_search');
        }

        $workflow = match (true) {
            $intent === 'general' => 'general_assistance',
            $intent === 'business' => 'business_advice',
            $intent === 'marketing' => 'marketing_advice',
            $intent === 'text_creation' => 'text_creation',
            $agent === 'agency_intelligence' && Str::contains($normalized, ['crm', 'lead', 'funil', 'equipe', 'corretor']) => 'crm_summary',
            $agent === 'house_flipper_analyst' && Str::contains($normalized, ['roi', 'retorno', 'lucro', 'investimento', 'margem', 'carteira']) => 'investment_summary',
            Str::contains($normalized, ['itbi', 'cartorio', 'registro', 'matricula', 'lei', 'juridic', 'como funciona o produto']) => 'knowledge',
            default => 'property_search',
        };

        return ['key' => $agent, 'label' => $definition['label'], 'tools' => $definition['tools'], 'intent' => $intent, 'workflow' => $workflow];
    }

    public function assertToolAllowed(string $product, string $agent, string $tool): void
    {
        abort_unless(in_array($agent, config('ai.product_agents.'.$product, []), true), 403, 'Agente não liberado para este produto.');
        abort_unless(in_array($tool, config('ai.agents.'.$agent.'.tools', []), true), 403, 'Ferramenta não liberada para este agente.');
    }
}
