<?php

declare(strict_types=1);

namespace App\Shared\Http;

/**
 * A #[MapQueryString] DTO that accepts the JSON:API "sort" parameter. Sorting by a field outside
 * sortFields() is answered with a 400, as the specification requires for an unsupported sort.
 */
interface SortableQuery
{
    /**
     * @return list<string>
     */
    public static function sortFields(): array;
}
