<?php

declare(strict_types=1);

namespace App\Workspace\Contract;

use Symfony\Component\Uid\Uuid;

interface WorkspaceReference
{
    public function getId(): Uuid;
}
