<?php

declare(strict_types=1);

namespace App\Task\Identity;

use Symfony\Component\Uid\Uuid;

interface TaskIdGenerator
{
    public function generate(): Uuid;
}
