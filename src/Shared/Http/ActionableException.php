<?php

declare(strict_types=1);

namespace App\Shared\Http;

/**
 * A client-facing exception that tells a client what happened in a form it can act on:
 * a code to branch on and the facts behind it, unlike a message, which may be reworded.
 */
interface ActionableException extends ClientFacingException
{
    public function errorCode(): string;

    public function errorTitle(): string;

    /** @return array<string, int|string> */
    public function errorMeta(): array;
}
