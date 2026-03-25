<?php

declare(strict_types=1);

namespace App\Shared\Query;

readonly class Sort
{
    public function __construct(
        public string $field,
        public SortDirection $direction,
    ) {
    }
}
