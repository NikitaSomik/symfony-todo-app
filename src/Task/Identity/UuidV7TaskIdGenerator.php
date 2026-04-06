<?php

declare(strict_types=1);

namespace App\Task\Identity;

use Symfony\Component\Uid\Uuid;

final readonly class UuidV7TaskIdGenerator implements TaskIdGenerator
{
    public function generate(): Uuid
    {
        return Uuid::v7();
    }
}
