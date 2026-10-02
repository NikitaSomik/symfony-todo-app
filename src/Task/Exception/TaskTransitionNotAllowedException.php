<?php

declare(strict_types=1);

namespace App\Task\Exception;

use App\Shared\Http\ClientFacingException;
use App\Task\Enum\TaskStatus;

final class TaskTransitionNotAllowedException extends \DomainException implements ClientFacingException
{
    public static function between(TaskStatus $from, TaskStatus $to): self
    {
        return new self(sprintf('A task in status "%s" cannot move to "%s".', $from->value, $to->value));
    }

    /** A guard refused the transition and says why in words written for the client. */
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
