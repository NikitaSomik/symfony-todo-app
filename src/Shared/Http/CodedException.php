<?php

declare(strict_types=1);

namespace App\Shared\Http;

/**
 * A client-facing exception that also says what happened in a form a client can branch on,
 * unlike its message, which may be reworded.
 */
interface CodedException extends ClientFacingException
{
    public function errorCode(): string;
}
