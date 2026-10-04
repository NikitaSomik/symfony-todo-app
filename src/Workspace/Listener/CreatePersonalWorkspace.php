<?php

declare(strict_types=1);

namespace App\Workspace\Listener;

use App\Auth\Contract\UserRegistered;
use App\Workspace\Service\CreateWorkspace;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class CreatePersonalWorkspace
{
    public const string NAME = 'Personal';

    public function __construct(
        private CreateWorkspace $createWorkspace,
    ) {
    }

    public function __invoke(UserRegistered $event): void
    {
        $this->createWorkspace->handle(self::NAME, $event->userId);
    }
}
