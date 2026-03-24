<?php

declare(strict_types=1);

namespace App\Story;

use App\Auth\DataFixtures\UserStory;
use App\Task\DataFixtures\TaskStory;
use Zenstruck\Foundry\Attribute\AsFixture;
use Zenstruck\Foundry\Story;

#[AsFixture(name: 'main')]
final class AppStory extends Story
{
    public function build(): void
    {
        UserStory::load();
        TaskStory::load();
    }
}
