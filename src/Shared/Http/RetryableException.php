<?php

declare(strict_types=1);

namespace App\Shared\Http;

/**
 * A failure that passes: the client may send the same request again after this many seconds,
 * told through the Retry-After header.
 */
interface RetryableException
{
    public function retryAfterSeconds(): int;
}
