<?php

declare(strict_types=1);

namespace App\Workspace\DTO;

use App\Workspace\Contract\MeetingLimits;
use App\Workspace\Contract\WorkspacePermission;

final readonly class MeetingAccess
{
    /** @param list<WorkspacePermission> $permissions */
    public function __construct(
        public array $permissions,
        public ?MeetingLimits $limits,
    ) {
    }

    public static function ofSomeoneWhoIsNotAMember(): self
    {
        return new self(permissions: [], limits: null);
    }
}
