<?php

declare(strict_types=1);

namespace App\Shared\Query;

readonly class SearchTerm
{
    public function __construct(
        public string $value,
    ) {
    }
}
