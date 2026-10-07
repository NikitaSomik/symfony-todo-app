<?php

declare(strict_types=1);

namespace App\Task\Exception;

use App\Shared\Http\ClientFacingException;
use App\Task\Enum\TaskStatus;

final class FinishedTaskAssigneeException extends \DomainException implements ClientFacingException
{
    public function __construct(TaskStatus $status)
    {
        parent::__construct(sprintf('A task in status "%s" keeps its assignee.', $status->value));
    }
}
