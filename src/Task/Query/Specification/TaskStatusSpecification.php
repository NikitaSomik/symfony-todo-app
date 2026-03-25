<?php

declare(strict_types=1);

namespace App\Task\Query\Specification;

use App\Shared\Persistence\Doctrine\QueryBuilderSpecification;
use App\Task\Enum\TaskStatus;
use Doctrine\ORM\QueryBuilder;

final readonly class TaskStatusSpecification implements QueryBuilderSpecification
{
    public function __construct(
        private ?string $status,
    ) {
    }

    public function apply(QueryBuilder $queryBuilder): void
    {
        if (null === $this->status) {
            return;
        }

        $queryBuilder->andWhere('t.status = :status')
            ->setParameter('status', TaskStatus::from($this->status));
    }
}
