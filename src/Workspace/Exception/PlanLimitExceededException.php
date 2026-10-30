<?php

declare(strict_types=1);

namespace App\Workspace\Exception;

use App\Shared\Http\CodedException;

final class PlanLimitExceededException extends \DomainException implements CodedException
{
    public static function forMembers(): self
    {
        return new self('This workspace has all the members its plan includes. Move to a higher plan to add more.');
    }

    public static function forWorkspaces(): self
    {
        return new self('The Free plan includes one team workspace. Move it to a paid plan to create another.');
    }

    public function errorCode(): string
    {
        return 'plan_limit_exceeded';
    }
}
