<?php

declare(strict_types=1);

namespace App\Shared\Api;

use Symfony\Component\HttpFoundation\JsonResponse;

final class JsonApiResponse extends JsonResponse
{
    /**
     * @param ResourceItem[] $included
     */
    public static function one(ResourceItem $item, int $status = self::HTTP_OK, array $included = []): self
    {
        $payload = ['data' => $item->toArray()];

        if ([] !== $included) {
            $payload['included'] = array_map(static fn (ResourceItem $resourceItem): array => $resourceItem->toArray(), $included);
        }

        return new self($payload, $status);
    }

    /**
     * @param ResourceItem[] $included
     */
    public static function collection(ResourceCollection $collection, int $status = self::HTTP_OK, array $included = []): self
    {
        $payload = [
            'links' => $collection->links,
            'data' => array_map(fn (ResourceItem $item) => $item->toArray(), $collection->items),
        ];

        if ($collection instanceof PaginatedCollection) {
            $payload['meta'] = [
                'page' => [
                    'current' => $collection->pageNumber,
                    'size' => $collection->pageSize,
                    'total' => $collection->total,
                    'last' => $collection->lastPage(),
                ],
            ];
        }

        if ([] !== $included) {
            $payload['included'] = array_map(static fn (ResourceItem $resourceItem): array => $resourceItem->toArray(), $included);
        }

        return new self($payload, $status);
    }

    public static function noContent(): self
    {
        return new self(null, self::HTTP_NO_CONTENT);
    }

    /**
     * @param JsonApiError[] $errors
     */
    public static function error(array $errors, int $status): self
    {
        return new self(
            [
                'errors' => array_map(static fn (JsonApiError $error): array => $error->toArray(), $errors),
            ],
            $status,
        );
    }
}
