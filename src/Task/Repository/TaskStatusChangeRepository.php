<?php

declare(strict_types=1);

namespace App\Task\Repository;

use App\Task\Entity\Task;
use App\Task\Entity\TaskStatusChange;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TaskStatusChange>
 */
final class TaskStatusChangeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TaskStatusChange::class);
    }

    /**
     * @return TaskStatusChange[]
     */
    public function findByTaskOrdered(Task $task): array
    {
        return $this->createQueryBuilder('tsc')
            ->where('tsc.task = :task')
            ->setParameter('task', $task)
            ->orderBy('tsc.changedAt', 'ASC')
            ->addOrderBy('tsc.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
