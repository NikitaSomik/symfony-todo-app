<?php

declare(strict_types=1);

namespace App\Fixtures;

use App\Fixtures\Auth\UserStory;
use App\Fixtures\Task\TaskStory;
use App\Fixtures\Workspace\WorkspaceStory;
use Zenstruck\Foundry\Attribute\AsFixture;
use Zenstruck\Foundry\Story;

#[AsFixture(name: 'main')]
final class AppStory extends Story
{
    public function build(): void
    {
        UserStory::load();
        WorkspaceStory::load();
        TaskStory::load();
    }
}
