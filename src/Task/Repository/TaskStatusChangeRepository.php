<?php

declare(strict_types=1);

namespace App\Task\Repository;

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
}
