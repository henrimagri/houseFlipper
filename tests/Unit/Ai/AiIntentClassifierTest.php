<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\AiIntentClassifier;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AiIntentClassifierTest extends TestCase
{
    #[DataProvider('intentCases')]
    public function test_it_classifies_supported_conversation_intents(string $message, string $product, string $expected): void
    {
        $result = app(AiIntentClassifier::class)->classify($message, $product);

        $this->assertSame($expected, $result['intent']);
        $this->assertGreaterThan(0, $result['confidence']);
        $this->assertNotSame('', $result['reason']);
    }

    public static function intentCases(): array
    {
        return [
            'general' => ['Olá, como você pode me ajudar?', 'corretor', 'general'],
            'business' => ['Ajude a definir metas e uma estratégia comercial', 'corretor', 'business'],
            'marketing' => ['Monte uma campanha de marketing para redes sociais', 'imobiliaria', 'marketing'],
            'text creation' => ['Escreva um e-mail para apresentar meus serviços', 'corretor', 'text_creation'],
            'property search' => ['Encontre apartamento em Fortaleza com três quartos', 'corretor', 'property_search'],
            'product specific' => ['Resuma o CRM e o funil da imobiliária', 'imobiliaria', 'product_specific'],
        ];
    }

    public function test_short_follow_up_keeps_the_previous_intent(): void
    {
        $result = app(AiIntentClassifier::class)->classify('Faça outra versão mais curta', 'corretor', 'text_creation');

        $this->assertSame('text_creation', $result['intent']);
        $this->assertSame('continuidade_da_conversa', $result['reason']);
    }
}
