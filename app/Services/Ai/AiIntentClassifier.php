<?php

namespace App\Services\Ai;

use Illuminate\Support\Str;

class AiIntentClassifier
{
    public const INTENTS = [
        'general',
        'business',
        'marketing',
        'text_creation',
        'property_search',
        'product_specific',
    ];

    public function classify(string $message, string $product, ?string $previousIntent = null): array
    {
        $normalized = Str::of($message)->lower()->ascii()->squish()->toString();
        $previousIntent = in_array($previousIntent, self::INTENTS, true) ? $previousIntent : null;

        if ($previousIntent && $this->isContinuation($normalized)) {
            return $this->result($previousIntent, 0.86, 'continuidade_da_conversa');
        }

        if (Str::contains($normalized, [
            'escreva', 'redija', 'reescreva', 'corrija o texto', 'melhore o texto', 'crie um texto',
            'crie uma legenda', 'crie uma descricao', 'crie uma descrição', 'rascunho', 'e-mail',
            'faca uma descricao', 'faça uma descrição', 'email para', 'mensagem para', 'roteiro',
            'anuncio para', 'anúncio para',
        ])) {
            return $this->result('text_creation', 0.95, 'pedido_explicito_de_texto');
        }

        if (Str::contains($normalized, [
            'marketing', 'campanha', 'publico alvo', 'público alvo', 'persona', 'redes sociais',
            'trafego pago', 'tráfego pago', 'funil de marketing', 'seo', 'calendario editorial',
            'calendário editorial', 'posicionamento de marca', 'captar mais clientes',
        ])) {
            return $this->result('marketing', 0.93, 'estrategia_de_marketing');
        }

        if (Str::contains($normalized, [
            'plano de negocio', 'plano de negócio', 'estrategia comercial', 'estratégia comercial',
            'gestao', 'gestão', 'processo comercial', 'vendas', 'negociacao', 'negociação',
            'metas', 'receita', 'custos da empresa', 'produtividade', 'modelo de negocio',
            'modelo de negócio',
        ])) {
            return $this->result('business', 0.90, 'orientacao_empresarial');
        }

        if ($this->isProductSpecific($normalized, $product)) {
            return $this->result('product_specific', 0.94, 'recurso_do_produto_ativo');
        }

        if ($this->isPropertySearch($normalized, $previousIntent)) {
            return $this->result('property_search', 0.93, 'pesquisa_ou_comparacao_de_imoveis');
        }

        return $this->result('general', 0.72, 'conversa_geral');
    }

    private function isContinuation(string $message): bool
    {
        return Str::contains($message, [
            'continue', 'continue por favor', 'explique melhor', 'detalhe mais', 'de outra forma',
            'faca outra versao', 'faça outra versão', 'mais curto', 'mais longo', 'mais formal',
            'mais informal', 'melhore isso', 'ajuste isso', 'e depois', 'e sobre isso',
        ]);
    }

    private function isProductSpecific(string $message, string $product): bool
    {
        if (Str::contains($message, [
            'como funciona o produto', 'como usar a plataforma', 'meu plano', 'minha assinatura',
            'itbi', 'cartorio', 'cartório', 'matricula', 'matrícula', 'registro do imovel',
            'registro do imóvel', 'meu crm', 'os leads', 'funil da imobiliaria', 'funil da imobiliária',
            'analise de investimento', 'análise de investimento', 'analise financeira',
            'análise financeira', 'modelo 3d', 'importar itbi', 'leiloes', 'leilões',
        ])) {
            return true;
        }

        return match ($product) {
            'corretor' => Str::contains($message, ['captacao', 'captação', 'meu site', 'minha carteira', 'publicar imovel', 'publicar imóvel']),
            'imobiliaria' => Str::contains($message, ['crm', 'lead', 'funil', 'equipe', 'corretores', 'imobiliaria', 'imobiliária']),
            'house_flipper' => Str::contains($message, ['roi', 'retorno', 'lucro', 'margem', 'reforma', 'projeto 3d', 'carteira de investimento']),
            default => false,
        };
    }

    private function isPropertySearch(string $message, ?string $previousIntent): bool
    {
        if ($previousIntent === 'property_search' && Str::contains($message, [
            'agora', 'mas com', 'mas sem', 'mais barato', 'mais caro', 'somente com', 'tambem', 'também',
        ])) {
            return true;
        }

        return preg_match('/\b(casa|casas)\b/', $message) === 1 || Str::contains($message, [
            'apartamento', 'imovel', 'imóvel', 'imoveis', 'imóveis', 'cobertura',
            'terreno', 'sala comercial', 'galpao', 'galpão', 'quartos', 'dormitorios', 'dormitórios',
            'vagas', 'comprar', 'alugar', 'encontre', 'buscar', 'procuro', 'compare os',
            'compare', 'diferenca', 'diferença', 'vantagens', 'recomenda', 'qual desses',
            'preco por m2', 'preço por m2',
        ]);
    }

    private function result(string $intent, float $confidence, string $reason): array
    {
        return ['intent' => $intent, 'confidence' => $confidence, 'reason' => $reason];
    }
}
