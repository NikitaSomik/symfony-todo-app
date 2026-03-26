<?php

declare(strict_types=1);

namespace App\Task\Query\Specification;

use App\Shared\Persistence\Doctrine\QueryBuilderSpecification;
use App\Shared\Query\SearchQuery;
use Doctrine\ORM\QueryBuilder;

final readonly class TaskSearchSpecification implements QueryBuilderSpecification
{
    public function __construct(
        private ?SearchQuery $searchQuery,
    ) {
    }

    public function apply(QueryBuilder $queryBuilder): void
    {
        if (null === $this->searchQuery) {
            return;
        }

        $queryBuilder->andWhere('(LOWER(t.title) LIKE :search OR LOWER(t.description) LIKE :search)')
            ->setParameter('search', '%'.mb_strtolower($this->searchQuery->value).'%');
    }
}
