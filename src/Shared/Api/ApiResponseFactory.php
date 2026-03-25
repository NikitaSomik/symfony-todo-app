<?php

declare(strict_types=1);

namespace App\Shared\Api;

use Symfony\Component\HttpFoundation\JsonResponse;

final class ApiResponseFactory
{
    /**
     * @param ResourceItem[] $included
     */
    public function one(ResourceItem $item, int $status = JsonResponse::HTTP_OK, array $included = []): JsonApiResponse
    {
        return JsonApiResponse::one($item, $status, $included);
    }

    /**
     * @param ResourceItem[] $included
     */
    public function collection(PaginatedCollection $collection, int $status = JsonResponse::HTTP_OK, array $included = []): JsonApiResponse
    {
        return JsonApiResponse::collection($collection, $status, $included);
    }

    public function empty(int $status = JsonResponse::HTTP_NO_CONTENT): JsonApiResponse
    {
        return JsonApiResponse::empty($status);
    }

    public function error(string $title, int $status, ?string $detail = null): JsonApiResponse
    {
        return JsonApiResponse::error(
            [new JsonApiError((string) $status, $title, $detail)],
            $status,
        );
    }
}
