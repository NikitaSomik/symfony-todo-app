<?php

declare(strict_types=1);

namespace App\Task\Query\Specification;

use App\Shared\Persistence\Doctrine\QueryBuilderSpecification;
use App\Shared\Query\Sort;
use Doctrine\ORM\QueryBuilder;

final readonly class TaskSortSpecification implements QueryBuilderSpecification
{
    private const SORT_FIELDS = [
        'created_at' => 't.createdAt',
        'title' => 't.title',
        'status' => 't.status',
    ];

    public function __construct(
        private Sort $sort,
    ) {
    }

    public function apply(QueryBuilder $queryBuilder): void
    {
        $queryBuilder->orderBy(
            self::SORT_FIELDS[$this->sort->field],
            $this->sort->direction->uppercased(),
        );
    }
}
