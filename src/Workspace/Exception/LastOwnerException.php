<?php

declare(strict_types=1);

namespace App\Workspace\Exception;

use App\Shared\Http\ClientFacingException;

final class LastOwnerException extends \DomainException implements ClientFacingException
{
    public function __construct()
    {
        parent::__construct('A workspace must keep at least one owner.');
    }
}
