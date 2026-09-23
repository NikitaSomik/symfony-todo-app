<?php

declare(strict_types=1);

namespace App\Shared\Api;

use Symfony\Component\HttpFoundation\Request;

final class PaginationLinksBuilder
{
    /**
     * @return array{first: string, last: string, prev?: string|null, next?: string|null}
     */
    public function build(Request $request, int $pageNumber, int $pageSize, int $total): array
    {
        $lastPage = max(1, (int) ceil($total / max(1, $pageSize)));

        return [
            'first' => $this->buildUrl($request, 1, $pageSize),
            'last' => $this->buildUrl($request, $lastPage, $pageSize),
            // Past the last page, the previous page with results is the last one, not the empty one before.
            'prev' => $pageNumber > 1 ? $this->buildUrl($request, min($pageNumber - 1, $lastPage), $pageSize) : null,
            'next' => $pageNumber < $lastPage ? $this->buildUrl($request, $pageNumber + 1, $pageSize) : null,
        ];
    }

    private function buildUrl(Request $request, int $pageNumber, int $pageSize): string
    {
        $query = $request->query->all();
        unset($query['number'], $query['size']);
        $query['page'] = [
            'number' => $pageNumber,
            'size' => $pageSize,
        ];

        $queryString = str_replace(
            ['%5B', '%5D'],
            ['[', ']'],
            http_build_query($query),
        );

        return '' === $queryString
            ? $request->getPathInfo()
            : sprintf('%s?%s', $request->getPathInfo(), $queryString);
    }
}
