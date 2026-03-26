<?php

declare(strict_types=1);

namespace App\Task\Repository;

use App\Auth\Entity\User;
use App\Shared\Persistence\Doctrine\SpecificationApplier;
use App\Task\DTO\TaskListQueryDTO;
use App\Task\Entity\Task;
use App\Task\Query\Specification\TaskSearchSpecification;
use App\Task\Query\Specification\TaskSortSpecification;
use App\Task\Query\Specification\TaskStatusSpecification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Task>
 */
class TaskRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly SpecificationApplier $specificationApplier,
    ) {
        parent::__construct($registry, Task::class);
    }

    /**
     * @return Task[]
     */
    public function findForUserList(User $user, TaskListQueryDTO $query): array
    {
        $queryBuilder = $this->createQueryBuilder('t')
            ->where('t.user = :user')
            ->setParameter('user', $user)
            ->setFirstResult(($query->page->number - 1) * $query->page->size)
            ->setMaxResults($query->page->size);

        $this->specificationApplier->apply($queryBuilder, [
            new TaskStatusSpecification($query->filter->status),
            new TaskSearchSpecification($query->searchQuery()),
            new TaskSortSpecification($query->sort()),
        ]);

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    public function countForUserList(User $user, TaskListQueryDTO $query): int
    {
        $queryBuilder = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.user = :user')
            ->setParameter('user', $user);

        $this->specificationApplier->apply($queryBuilder, [
            new TaskStatusSpecification($query->filter->status),
            new TaskSearchSpecification($query->searchQuery()),
        ]);

        return (int) $queryBuilder
            ->getQuery()
            ->getSingleScalarResult();
    }
}
