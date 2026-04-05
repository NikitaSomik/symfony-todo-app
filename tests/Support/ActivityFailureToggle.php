<?php

declare(strict_types=1);

namespace App\Tests\Support;

final class ActivityFailureToggle
{
    private bool $enabled = false;

    public function enable(): void
    {
        $this->enabled = true;
    }

    public function disable(): void
    {
        $this->enabled = false;
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }
}
