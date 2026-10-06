<?php

declare(strict_types=1);

namespace App\Workspace\Exception;

use App\Shared\Http\ClientFacingException;

final class UserNotRegisteredException extends \DomainException implements ClientFacingException
{
    public function __construct()
    {
        parent::__construct('No user is registered with this email.');
    }
}
