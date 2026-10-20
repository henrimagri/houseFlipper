<?php

namespace Tests\Unit;

use App\Support\BrokerThemeSanitizer;
use PHPUnit\Framework\TestCase;

class BrokerThemeSanitizerTest extends TestCase
{
    public function test_it_preserves_supported_visual_options_and_section_order(): void
    {
        $theme = BrokerThemeSanitizer::clean([
            'background_color' => '#07111f',
            'surface_color' => '#101c2c',
            'text_color' => '#f8fafc',
            'font_family' => 'serif',
            'hero_style' => 'minimal',
            'card_style' => 'square',
            'button_style' => 'pill',
            'sections' => [
                ['id' => 'contact', 'type' => 'contact', 'visible' => true, 'title' => 'Contato', 'body' => 'Fale comigo'],
                ['id' => 'hero', 'type' => 'hero', 'visible' => false, 'title' => 'Início', 'body' => ''],
            ],
        ]);

        $this->assertSame('#07111F', $theme['background_color']);
        $this->assertSame('serif', $theme['font_family']);
        $this->assertSame('minimal', $theme['hero_style']);
        $this->assertSame('square', $theme['card_style']);
        $this->assertSame('pill', $theme['button_style']);
        $this->assertSame(['contact', 'hero'], array_column($theme['sections'], 'id'));
        $this->assertFalse($theme['sections'][1]['visible']);
    }

    public function test_it_rejects_unsupported_styles_and_markup(): void
    {
        $theme = BrokerThemeSanitizer::clean([
            'display_name' => '<script>alert(1)</script>Corretor',
            'background_color' => 'javascript:alert(1)',
            'font_family' => 'remote-font',
            'sections' => [['type' => 'iframe', 'body' => '<b>Conteúdo</b>']],
        ]);

        $this->assertSame('alert(1)Corretor', $theme['display_name']);
        $this->assertSame('#F8FAFC', $theme['background_color']);
        $this->assertSame('sans', $theme['font_family']);
        $this->assertSame('custom', $theme['sections'][0]['type']);
        $this->assertSame('Conteúdo', $theme['sections'][0]['body']);
    }
}
