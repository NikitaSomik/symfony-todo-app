<?php

declare(strict_types=1);

namespace App\Task\Repository;

use App\Auth\Entity\User;
use App\Shared\Persistence\Doctrine\SpecificationApplier;
use App\Shared\Query\SortDirection;
use App\Task\DTO\TaskListQueryDTO;
use App\Task\Entity\Task;
use App\Task\Query\Specification\TaskDueRangeSpecification;
use App\Task\Query\Specification\TaskSearchRankSpecification;
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
        $search = $query->filter->searchQuery();
        $sort = $query->sort();

        $queryBuilder = $this->createQueryBuilder('t')
            ->where('t.user = :user')
            ->setParameter('user', $user)
            ->setFirstResult(($query->page->number - 1) * $query->page->size)
            ->setMaxResults($query->page->size);

        $this->specificationApplier->apply($queryBuilder, [
            new TaskSearchSpecification($search),
            new TaskStatusSpecification($query->filter->status),
            new TaskDueRangeSpecification($query->filter->dueFrom(), $query->filter->dueTo()),
            // The chosen field comes first; relevance then breaks its ties, best match first. Without
            // a chosen field relevance is the sort itself, in the requested direction.
            new TaskSortSpecification($sort),
            new TaskSearchRankSpecification($search, null === $sort ? $query->direction() : SortDirection::DESC),
        ]);

        // The last sort key: tasks that tie on the sort field and on relevance keep one fixed order across pages.
        // It follows the requested direction: UUIDv7 grows with creation time, so ascending lists oldest first.
        $queryBuilder->addOrderBy('t.id', $query->direction()->uppercased());

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
            new TaskSearchSpecification($query->filter->searchQuery()),
            new TaskStatusSpecification($query->filter->status),
            new TaskDueRangeSpecification($query->filter->dueFrom(), $query->filter->dueTo()),
        ]);

        return (int) $queryBuilder
            ->getQuery()
            ->getSingleScalarResult();
    }
}
