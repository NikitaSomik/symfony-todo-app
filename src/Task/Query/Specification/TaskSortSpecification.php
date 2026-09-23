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

    public function __construct(
        private Sort $sort,
    ) {
    }

    public function apply(QueryBuilder $queryBuilder): void
    {
        if (TaskSortField::DUE_DATE->value === $this->sort->field) {
            $queryBuilder
                ->addOrderBy('CASE WHEN t.dueDate IS NULL THEN 1 ELSE 0 END', 'ASC')
                ->addOrderBy('t.dueDate', $this->sort->direction->uppercased());

            return;
        }

        $queryBuilder->addOrderBy(
            self::SORT_FIELDS[$this->sort->field],
            $this->sort->direction->uppercased(),
        );
    }
}
