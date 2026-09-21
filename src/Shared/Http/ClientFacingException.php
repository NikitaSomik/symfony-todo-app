<?php

declare(strict_types=1);

namespace App\Shared\Http;

/**
 * Marks an exception whose message is written for API clients and may be returned as-is.
 * Its HTTP status comes from the framework.exceptions mapping; without one the exception
 * is treated as internal and answered with a plain 500.
 */
interface ClientFacingException extends \Throwable
{
}
