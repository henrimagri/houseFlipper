<?php

namespace App\Services\Platform;

use Illuminate\Http\Request;

class SecuritySignals
{
    public function inspect(Request $r): array
    {

        $signals = [];

        // Excludes passwords, login identifiers, cookies, prompts and upload contents.

        foreach (['search', 'url', 'source_url', 'path', 'message'] as $field) {

            $value = $r->input($field);
            if (! is_string($value) || strlen($value) > 8000) {
                continue;
            }

            if (preg_match('/<script\b|javascript:|onerror\s*=/i', $value)) {
                $signals[] = 'possible_xss';
            }

            if (str_contains(rawurldecode($value), '../') || str_contains(rawurldecode($value), '..'.chr(92))) {
                $signals[] = 'possible_path_traversal';
            }

            if (preg_match('/union\s+select|or\s+1\s*=\s*1|sleep\s*\(/i', $value)) {
                $signals[] = 'possible_sql_input';
            }

            if (in_array($field, ['url', 'source_url'])) {
                $host = parse_url($value, PHP_URL_HOST);
                if ($host && ($host === 'localhost' || (filter_var($host, FILTER_VALIDATE_IP) && ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)))) {
                    $signals[] = 'possible_private_url';
                }
            }

            if ($field === 'message' && $r->is('api/ai/*') && preg_match('/ignore (?:all |previous )?instructions|system prompt|outro tenant|api.?key/i', $value)) {
                $signals[] = 'possible_prompt_injection';
            }

        }

        return array_values(array_unique($signals));

    }
}
