<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Doctrine\ORM\Event\OnFlushEventArgs;

/**
 * Makes the next flush fail, as a broken connection or a violated constraint would at commit.
 */
final class FailFlushToggle
{
    private bool $enabled = false;

    public function enable(): void
    {
        $this->enabled = true;
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        if ($this->enabled) {
            throw new \RuntimeException('Simulated failure at flush.');
        }
    }
}
