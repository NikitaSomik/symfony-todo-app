<?php

declare(strict_types=1);

namespace App\Shared\Api;

use Symfony\Component\HttpFoundation\JsonResponse;

final class JsonApiResponse extends JsonResponse
{
    public const string MEDIA_TYPE = 'application/vnd.api+json';

    /**
     * The top-level "jsonapi" member every document carries.
     */
    public const array JSONAPI = ['version' => '1.1'];

    /**
     * @param ResourceItem[] $included
     */
    public static function one(ResourceItem $item, int $status = self::HTTP_OK, array $included = []): self
    {
        $payload = ['jsonapi' => self::JSONAPI, 'data' => $item->toArray()];

        if ([] !== $included) {
            $payload['included'] = array_map(static fn (ResourceItem $resourceItem): array => $resourceItem->toArray(), $included);
        }

        return new self($payload, $status);
    }

    /**
     * A 201 for a resource the server created, pointing to it with the Location header.
     */
    public static function created(ResourceItem $item, string $location): self
    {
        $response = self::one($item, self::HTTP_CREATED);
        $response->headers->set('Location', $location);

        return $response;
    }

    /**
     * @param ResourceItem[] $included
     */
    public static function collection(ResourceCollection $collection, int $status = self::HTTP_OK, array $included = []): self
    {
        $payload = [
            'jsonapi' => self::JSONAPI,
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
            ['jsonapi' => self::JSONAPI, 'errors' => array_map(static fn (JsonApiError $error): array => $error->toArray(), $errors)],
            $status,
        );
    }
}
