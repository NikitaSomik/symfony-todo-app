<?php

declare(strict_types=1);

namespace App\Shared\Http;

readonly class SearchQueryDTO
{
    public function __construct(
        public string $term,
    ) {
    }
}
