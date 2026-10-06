<?php

declare(strict_types=1);

namespace App\Task\Repository;

use App\Shared\Persistence\Doctrine\SpecificationApplier;
use App\Shared\Query\Sort;
use App\Shared\Query\SortDirection;
use App\Task\DTO\TaskListQueryDTO;
use App\Task\Entity\Task;
use App\Task\Enum\TaskSortField;
use App\Task\Query\Specification\TaskDueRangeSpecification;
use App\Task\Query\Specification\TaskSearchRankSpecification;
use App\Task\Query\Specification\TaskSearchSpecification;
use App\Task\Query\Specification\TaskSortSpecification;
use App\Task\Query\Specification\TaskStatusSpecification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

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
     * @param list<Uuid> $workspaceIds
     *
     * @return Task[]
     */
    public function findForWorkspacesList(array $workspaceIds, TaskListQueryDTO $query): array
    {
        if ([] === $workspaceIds) {
            return [];
        }

        $search = $query->filter->searchQuery();
        $field = $query->sortField();
        $direction = $query->direction();

        // Without a chosen field a search is ordered by relevance and a plain list by creation time.
        if (null === $field && null === $search) {
            $field = TaskSortField::CREATED_AT;
        }

        $queryBuilder = $this->createQueryBuilder('t')
            ->where('IDENTITY(t.workspace) IN (:workspaceIds)')
            ->setParameter('workspaceIds', array_map(static fn (Uuid $id): string => $id->toRfc4122(), $workspaceIds), ArrayParameterType::STRING)
            ->setFirstResult(($query->page->number - 1) * $query->page->size)
            ->setMaxResults($query->page->size);

        $this->specificationApplier->apply($queryBuilder, [
            new TaskSearchSpecification($search),
            new TaskStatusSpecification($query->filter->status),
            new TaskDueRangeSpecification($query->filter->dueFrom(), $query->filter->dueTo()),
            // The chosen field comes first; relevance then breaks its ties, best match first. Without
            // a chosen field relevance is the sort itself, in the requested direction.
            new TaskSortSpecification(null === $field ? null : new Sort($field->value, $direction)),
            new TaskSearchRankSpecification($search, null === $field ? $direction : SortDirection::DESC),
        ]);

        // The last sort key: tasks that tie on the sort field and on relevance keep one fixed order across pages.
        // It follows the requested direction: UUIDv7 grows with creation time, so ascending lists oldest first.
        $queryBuilder->addOrderBy('t.id', $direction->uppercased());

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    /**
     * @param list<Uuid> $workspaceIds
     */
    public function countForWorkspacesList(array $workspaceIds, TaskListQueryDTO $query): int
    {
        if ([] === $workspaceIds) {
            return 0;
        }

        $queryBuilder = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('IDENTITY(t.workspace) IN (:workspaceIds)')
            ->setParameter('workspaceIds', array_map(static fn (Uuid $id): string => $id->toRfc4122(), $workspaceIds), ArrayParameterType::STRING);

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
