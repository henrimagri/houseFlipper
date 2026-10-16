<?php

namespace App\Contracts;

interface PropertyTransactionProviderInterface
{
    public function key(): string;

    public function label(): string;

    public function search(array $subject): array;
}
