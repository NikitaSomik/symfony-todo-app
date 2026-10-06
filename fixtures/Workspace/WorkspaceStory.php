<?php

declare(strict_types=1);

namespace App\Fixtures\Workspace;

use App\Auth\Entity\User;
use App\Workspace\Contract\WorkspaceRole;
use App\Workspace\Listener\CreatePersonalWorkspace;
use Doctrine\ORM\EntityManagerInterface;
use Zenstruck\Foundry\Story;

use function Zenstruck\Foundry\Persistence\flush_after;

final class WorkspaceStory extends Story
{
    private const int BATCH_SIZE = 500;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function build(): void
    {
        $lastId = 0;

        while (true) {
            /** @var list<User> $users */
            $users = $this->em->createQuery('SELECT u FROM App\Auth\Entity\User u WHERE u.id > :lastId ORDER BY u.id ASC')
                ->setParameter('lastId', $lastId)
                ->setMaxResults(self::BATCH_SIZE)
                ->getResult();

            if ([] === $users) {
                break;
            }

            // One flush per batch of users instead of one per workspace.
            flush_after(static function () use ($users): void {
                foreach ($users as $index => $user) {
                    WorkspaceFactory::createOne(['name' => CreatePersonalWorkspace::NAME, 'owner' => $user]);

                    if (0 !== $index % 10) {
                        continue;
                    }

                    $colleagues = [];

                    foreach (array_slice($users, $index + 1, random_int(2, 9)) as $position => $colleague) {
                        $colleagues[] = [$colleague, 0 === $position % 4 ? WorkspaceRole::VIEWER : WorkspaceRole::MEMBER];
                    }

                    WorkspaceFactory::new()->withMembers($colleagues)->create(['owner' => $user]);
                }
            });

            $lastId = $users[array_key_last($users)]->id();
            $this->em->clear();
        }
    }
}
