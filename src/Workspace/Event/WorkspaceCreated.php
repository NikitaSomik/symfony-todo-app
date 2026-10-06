<?php

declare(strict_types=1);

namespace App\Workspace\Event;

final readonly class WorkspaceCreated
{
    public function __construct(
        public string $workspaceId,
        public string $name,
        public int $actorId,
    ) {
    }
}
