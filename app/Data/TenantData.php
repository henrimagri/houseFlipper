<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class TenantData extends Data
{
    public function __construct(
        public string $nome,
        public string $slug,
        public ?string $documento = null,
        public ?string $email_contato = null,
    ) {}
}
