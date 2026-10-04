<?php

declare(strict_types=1);

namespace App\Workspace\Exception;

use App\Shared\Http\ClientFacingException;

final class MemberNotFoundException extends \DomainException implements ClientFacingException
{
    public function __construct()
    {
        parent::__construct('This user is not a member of the workspace.');
    }
}
