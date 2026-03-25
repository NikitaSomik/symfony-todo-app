<?php

declare(strict_types=1);

namespace App\Shared\Api;

final readonly class PaginatedCollection
{
    /**
     * @param ResourceItem[]                                                                $items
     * @param array{first: string, last: string, prev?: string|null, next?: string|null} $links
     */
    public function __construct(
        public array $items,
        public int $pageNumber,
        public int $pageSize,
        public int $total,
        public array $links,
    ) {
    }

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / max(1, $this->pageSize)));
    }
}
