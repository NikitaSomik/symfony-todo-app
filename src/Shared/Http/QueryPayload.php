<?php

declare(strict_types=1);

namespace App\Shared\Http;

/**
 * Marks a DTO that is mapped from the URI query string rather than from the request body.
 * Its validation errors are reported with "source.parameter" instead of "source.pointer".
 */
interface QueryPayload
{
}
