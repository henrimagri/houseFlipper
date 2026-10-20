<?php

namespace App\Support;

class BrokerThemeSanitizer
{
    public static function clean(mixed $theme): array
    {
        $input = is_array($theme) ? $theme : [];
        $color = static function (mixed $value): ?string {
            if (! is_string($value) || preg_match('/\A#[0-9A-Fa-f]{6}\z/', $value) !== 1) {
                return null;
            }

            return strtoupper($value);
        };

        $text = static function (mixed $value, int $max): string {
            $value = is_string($value) ? strip_tags($value) : '';
            $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? '';

            return mb_substr(trim($value), 0, $max);
        };

        $whatsapp = $text($input['whatsapp'] ?? '', 20);
        $whatsapp = preg_replace('/\D+/', '', $whatsapp) ?? '';

        $sections = [];
        if (is_array($input['sections'] ?? null)) {
            foreach (array_slice($input['sections'], 0, 12) as $section) {
                if (! is_array($section)) {
                    continue;
                }
                $type = $text($section['type'] ?? 'custom', 40);
                $allowedTypes = ['hero', 'search', 'featured', 'about', 'team', 'branches', 'contact', 'footer', 'custom'];
                $sections[] = [
                    'id' => $text($section['id'] ?? $type, 40),
                    'type' => in_array($type, $allowedTypes, true) ? $type : 'custom',
                    'visible' => (bool) ($section['visible'] ?? true),
                    'title' => $text($section['title'] ?? '', 120),
                    'body' => $text($section['body'] ?? '', 1200),
                ];
            }
        }

        return [
            'display_name' => $text($input['display_name'] ?? '', 80),
            'headline' => $text($input['headline'] ?? '', 180),
            'bio' => $text($input['bio'] ?? '', 800),
            'creci' => $text($input['creci'] ?? '', 40),
            'phone' => $text($input['phone'] ?? '', 40),
            'whatsapp' => mb_substr($whatsapp, 0, 20),
            'instagram' => $text($input['instagram'] ?? '', 80),
            'facebook' => $text($input['facebook'] ?? '', 80),
            'primary_color' => $color($input['primary_color'] ?? null) ?? '#4F46E5',
            'accent_color' => $color($input['accent_color'] ?? null) ?? '#0F172A',
            'background_color' => $color($input['background_color'] ?? null) ?? '#F8FAFC',
            'surface_color' => $color($input['surface_color'] ?? null) ?? '#FFFFFF',
            'text_color' => $color($input['text_color'] ?? null) ?? '#0F172A',
            'font_family' => in_array($input['font_family'] ?? '', ['sans', 'serif', 'modern'], true) ? $input['font_family'] : 'sans',
            'hero_style' => in_array($input['hero_style'] ?? '', ['solid', 'gradient', 'minimal'], true) ? $input['hero_style'] : 'gradient',
            'card_style' => in_array($input['card_style'] ?? '', ['soft', 'elevated', 'square'], true) ? $input['card_style'] : 'soft',
            'button_style' => in_array($input['button_style'] ?? '', ['rounded', 'pill', 'square'], true) ? $input['button_style'] : 'rounded',
            'footer_text' => $text($input['footer_text'] ?? '', 160),
            'published' => array_key_exists('published', $input) ? (bool) $input['published'] : true,
            'sections' => $sections,
        ];
    }
}
