<?php

declare(strict_types=1);

namespace App\Auth\DataFixtures;

use Zenstruck\Foundry\Story;

final class UserStory extends Story
{
    public function build(): void
    {
        UserFactory::createMany(10_000);
    }
}
