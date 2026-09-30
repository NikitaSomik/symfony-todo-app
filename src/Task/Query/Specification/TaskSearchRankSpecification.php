<?php

declare(strict_types=1);

namespace App\Task\Query\Specification;

use App\Shared\Persistence\Doctrine\QueryBuilderSpecification;
use App\Shared\Query\SearchQuery;
use App\Shared\Query\SortDirection;
use App\Task\Entity\Task;
use Doctrine\ORM\QueryBuilder;

final readonly class TaskSearchRankSpecification implements QueryBuilderSpecification
{
    public function __construct(
        private ?SearchQuery $search,
        private SortDirection $direction,
    ) {
    }

    public function apply(QueryBuilder $queryBuilder): void
    {
        if (null === $this->search) {
            return;
        }

        $queryBuilder
            ->addSelect('TS_RANK_CD(t.searchVector, WEBSEARCH_TO_TSQUERY(:searchConfig, :search)) AS HIDDEN search_rank')
            ->addOrderBy('search_rank', $this->direction->uppercased())
            ->setParameter('searchConfig', Task::SEARCH_CONFIG)
            ->setParameter('search', $this->search->value);
    }
}
