<?php

declare(strict_types=1);

namespace App\Task\Query\Specification;

use App\Shared\Persistence\Doctrine\QueryBuilderSpecification;
use App\Shared\Query\Sort;
use App\Task\Enum\TaskSortField;
use Doctrine\ORM\QueryBuilder;

final readonly class TaskSortSpecification implements QueryBuilderSpecification
{
    private const array SORT_FIELDS = [
        TaskSortField::CREATED_AT->value => 't.createdAt',
        TaskSortField::STATUS->value => 't.status',
        TaskSortField::DUE_DATE->value => 't.dueDate',
    ];

    /**
     * @param list<Sort> $sorts applied in order, each one breaking ties left by the previous
     */
    public function __construct(
        private array $sorts,
    ) {
    }

    public function apply(QueryBuilder $queryBuilder): void
    {
        foreach ($this->sorts as $sort) {
            $this->applySort($queryBuilder, $sort);
        }
    }

    private function applySort(QueryBuilder $queryBuilder, Sort $sort): void
    {
        if (TaskSortField::DUE_DATE->value === $sort->field) {
            $queryBuilder
                ->addOrderBy('CASE WHEN t.dueDate IS NULL THEN 1 ELSE 0 END', 'ASC')
                ->addOrderBy('t.dueDate', $sort->direction->uppercased());

            return;
        }

        $queryBuilder->addOrderBy(
            self::SORT_FIELDS[$sort->field],
            $sort->direction->uppercased(),
        );
    }
}
