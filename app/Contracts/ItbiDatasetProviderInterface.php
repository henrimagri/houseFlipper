<?php

namespace App\Contracts;

interface ItbiDatasetProviderInterface
{
    public function providerKey(): string;

    /**
     * @return array<int, array{url:string, filename:string, reference_period:?string, published_at:?string, metadata?:array}>
     */
    public function discover(): array;
}
