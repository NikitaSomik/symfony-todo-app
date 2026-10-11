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
        /** @var array{title: string, assigneeId: int|null}|null $row */
        $row = $this->tasks->createQueryBuilder('t')
            ->select('t.title', 't.assigneeId')
            ->where('t.id = :id')
            ->setParameter('id', $taskId, UuidType::NAME)
            ->getQuery()
            ->getOneOrNullResult();

        return null === $row ? null : new TaskSummary($taskId, $row['title'], $row['assigneeId']);
    }
}
