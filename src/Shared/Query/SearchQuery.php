<?php

declare(strict_types=1);

namespace App\Shared\Query;

readonly class SearchQuery
{
    public function __construct(
        public string $value,
    ) {
    }
}
