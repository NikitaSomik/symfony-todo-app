<?php

declare(strict_types=1);

namespace App\Workspace\Event;

final readonly class WorkspaceRenamed
{
    public function __construct(
        public string $workspaceId,
        public string $previousName,
        public string $name,
        public int $actorId,
    ) {
    }
}
