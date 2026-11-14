<?php

declare(strict_types=1);

namespace App\Workspace\Contract;

/** What a plan allows one meeting of a workspace. */
final readonly class MeetingLimits
{
    public function __construct(
        public int $participants,
        public int $minutes,
    ) {
    }
}
