<?php

declare(strict_types=1);

namespace App\Workspace\Exception;

use App\Shared\Http\ClientFacingException;

final class MemberAlreadyExistsException extends \DomainException implements ClientFacingException
{
    public function __construct()
    {
        parent::__construct('This user is already a member of the workspace.');
    }
}
