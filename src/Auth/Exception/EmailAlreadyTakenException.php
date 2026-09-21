<?php

declare(strict_types=1);

namespace App\Auth\Exception;

use App\Shared\Http\ClientFacingException;

final class EmailAlreadyTakenException extends \DomainException implements ClientFacingException
{
    public function __construct()
    {
        parent::__construct('Email is already taken.');
    }
}
