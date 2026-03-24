<?php

declare(strict_types=1);

namespace App\Auth\Exception;

final class InvalidRefreshTokenException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Invalid or expired refresh token.');
    }
}
