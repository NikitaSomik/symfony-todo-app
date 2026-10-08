<?php

declare(strict_types=1);

namespace App\Workspace\Contract;

use Symfony\Component\Uid\Uuid;

final readonly class MembershipEnded
{
    public function __construct(
        public Uuid $workspaceId,
        public int $userId,
        public int $actorId,
    ) {
    }
}
