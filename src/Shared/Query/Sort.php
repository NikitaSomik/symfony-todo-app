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

    /**
     * Reads a JSON:API "sort" value: comma-separated fields, each descending when prefixed with "-".
     * "-status,due_date" becomes status DESC, then due_date ASC.
     *
     * @return list<self>
     */
    public static function listFromQuery(string $value): array
    {
        return array_map(
            static fn (string $field): self => str_starts_with($field, '-')
                ? new self(substr($field, 1), SortDirection::DESC)
                : new self($field, SortDirection::ASC),
            explode(',', $value),
        );
    }
}
