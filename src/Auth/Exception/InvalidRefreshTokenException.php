<?php

declare(strict_types=1);

namespace App\Auth\Exception;

// Not final: a reused token answers the client the same way, see RefreshTokenReusedException.
class InvalidRefreshTokenException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Invalid or expired refresh token.');
    }
}
