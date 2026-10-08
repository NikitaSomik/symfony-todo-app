<?php

declare(strict_types=1);

namespace App\Task\Exception;

use App\Shared\Http\ClientFacingException;

final class AssigneeCannotWorkException extends \DomainException implements ClientFacingException
{
    public function __construct()
    {
        parent::__construct('A task is assigned to an owner or a member of its workspace.');
    }
}
