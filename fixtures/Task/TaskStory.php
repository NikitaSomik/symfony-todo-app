<?php

declare(strict_types=1);

namespace App\Fixtures\Task;

use App\Workspace\Entity\Membership;
use App\Workspace\Entity\Workspace;
use App\Workspace\Enum\WorkspaceRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Story;

use function Zenstruck\Foundry\Persistence\flush_after;

final class TaskStory extends Story
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
            /** @var list<array{id: int, workspaceId: string, userId: int}> $owners */
            $owners = $this->em->createQuery(
                'SELECT m.id, IDENTITY(m.workspace) AS workspaceId, m.userId FROM '.Membership::class.' m WHERE m.role = :owner AND m.id > :lastId ORDER BY m.id ASC'
            )
                ->setParameter('owner', WorkspaceRole::OWNER)
                ->setParameter('lastId', $lastId)
                ->setMaxResults(self::BATCH_SIZE)
                ->getArrayResult();

            if ([] === $owners) {
                break;
            }

            // One flush per batch of workspaces instead of one per task.
            flush_after(function () use ($owners): void {
                foreach ($owners as $owner) {
                    TaskFactory::createMany(random_int(1, 3), [
                        'workspace' => $this->em->getReference(Workspace::class, Uuid::fromString($owner['workspaceId'])),
                        'creatorId' => (int) $owner['userId'],
                        'assigneeId' => 0 === random_int(0, 1) ? (int) $owner['userId'] : null,
                    ]);
                }
            });

            $lastId = $owners[array_key_last($owners)]['id'];
            $this->em->clear();
        }
    }
}
