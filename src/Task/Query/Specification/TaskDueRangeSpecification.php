<?php

declare(strict_types=1);

namespace App\Task\Query\Specification;

use App\Shared\Persistence\Doctrine\QueryBuilderSpecification;
use Doctrine\ORM\QueryBuilder;

final readonly class TaskDueRangeSpecification implements QueryBuilderSpecification
{
    public function __construct(
        private ?\DateTimeImmutable $dueFrom,
        private ?\DateTimeImmutable $dueTo,
    ) {
    }

    public function apply(QueryBuilder $queryBuilder): void
    {
        if (null !== $this->dueFrom) {
            $queryBuilder
                ->andWhere('t.dueDate >= :due_from')
                ->setParameter('due_from', $this->dueFrom);
        }

        if (null !== $this->dueTo) {
            $queryBuilder
                ->andWhere('t.dueDate <= :due_to')
                ->setParameter('due_to', $this->dueTo);
        }
    }
}
