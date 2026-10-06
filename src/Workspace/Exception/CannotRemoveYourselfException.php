<?php

declare(strict_types=1);

namespace App\Workspace\Exception;

use App\Shared\Http\ClientFacingException;

final class CannotRemoveYourselfException extends \DomainException implements ClientFacingException
{
    public function __construct()
    {
        parent::__construct('A member is removed by someone else; to stop being a member, leave the workspace.');
    }
}
