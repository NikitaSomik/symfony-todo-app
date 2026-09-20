<?php

declare(strict_types=1);

namespace App\Task\Query\Specification;

use App\Shared\Persistence\Doctrine\QueryBuilderSpecification;
use App\Shared\Query\SearchQuery;
use App\Task\Entity\Task;
use Doctrine\ORM\QueryBuilder;

final readonly class TaskSearchSpecification implements QueryBuilderSpecification
{
    public function __construct(
        private ?SearchQuery $search,
    ) {
    }

    public function apply(QueryBuilder $queryBuilder): void
    {
        if (null === $this->search) {
            return;
        }

        $queryBuilder
            ->andWhere('TSMATCH(t.searchVector, WEBSEARCH_TO_TSQUERY(:searchConfig, :search)) = TRUE')
            ->setParameter('searchConfig', Task::SEARCH_CONFIG)
            ->setParameter('search', $this->search->value);
    }
}
