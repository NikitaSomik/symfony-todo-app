<?php

declare(strict_types=1);

namespace App\Task\Query;

use App\Shared\Query\Sort;
use App\Task\DTO\TaskListQueryDTO;
use App\Task\Enum\TaskStatus;
use Doctrine\ORM\QueryBuilder;

final class TaskListQueryApplier
{
    private const array SORT_FIELDS = [
        'created_at' => 't.createdAt',
        'title' => 't.title',
        'status' => 't.status',
    ];

    public function apply(QueryBuilder $queryBuilder, TaskListQueryDTO $query): void
    {
        $this->applyStatusFilter($queryBuilder, $query);
        $this->applySearch($queryBuilder, $query);
        $this->applySorting($queryBuilder, $query);
    }

    public function applyFilters(QueryBuilder $queryBuilder, TaskListQueryDTO $query): void
    {
        $this->applyStatusFilter($queryBuilder, $query);
        $this->applySearch($queryBuilder, $query);
    }

    private function applyStatusFilter(QueryBuilder $queryBuilder, TaskListQueryDTO $query): void
    {
        if (null === $query->filter->status) {
            return;
        }

        $queryBuilder->andWhere('t.status = :status')
            ->setParameter('status', TaskStatus::from($query->filter->status));
    }

    private function applySorting(QueryBuilder $queryBuilder, TaskListQueryDTO $query): void
    {
        $sort = $query->sort();

        $queryBuilder->orderBy(
            $this->sortField($sort),
            $sort->direction->uppercased(),
        );
    }

    private function applySearch(QueryBuilder $queryBuilder, TaskListQueryDTO $query): void
    {
        $searchTerm = $query->searchTerm();
        if (null === $searchTerm) {
            return;
        }

        $queryBuilder->andWhere('(LOWER(t.title) LIKE :search OR LOWER(t.description) LIKE :search)')
            ->setParameter('search', '%'.mb_strtolower($searchTerm->value).'%');
    }

    private function sortField(Sort $sort): string
    {
        return self::SORT_FIELDS[$sort->field];
    }
}
