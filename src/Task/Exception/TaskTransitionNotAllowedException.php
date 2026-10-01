<?php

declare(strict_types=1);

namespace App\Task\Exception;

use App\Shared\Http\ClientFacingException;
use App\Task\Enum\TaskStatus;

final class TaskTransitionNotAllowedException extends \DomainException implements ClientFacingException
{
    public function __construct(TaskStatus $from, TaskStatus $to)
    {
        parent::__construct(sprintf('A task in status "%s" cannot move to "%s".', $from->value, $to->value));
    }
}
