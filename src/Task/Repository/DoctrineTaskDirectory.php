<?php

declare(strict_types=1);

namespace App\Task\Repository;

use App\Task\Contract\TaskDirectory;
use App\Task\Contract\TaskSummary;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineTaskDirectory implements TaskDirectory
{
    public function __construct(
        private TaskRepository $tasks,
    ) {
    }

    public function find(Uuid $taskId): ?TaskSummary
    {
        return $this->tasks->createQueryBuilder('t')
            ->select(sprintf('NEW %s(t.id, t.title, t.assigneeId)', TaskSummary::class))
            ->where('t.id = :id')
            ->setParameter('id', $taskId, UuidType::NAME)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
