<?php

declare(strict_types=1);

namespace App\Shared\Http;

readonly class SortQueryDTO
{
    public function __construct(
        public string $field,
        public string $direction,
    ) {
    }
}
