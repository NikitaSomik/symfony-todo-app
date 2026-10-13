<?php

declare(strict_types=1);

namespace App\Task\Query\Specification;

use App\Shared\Persistence\Doctrine\QueryBuilderSpecification;
use App\Task\Query\AssigneeFilter;
use Doctrine\ORM\QueryBuilder;

final readonly class TaskAssigneeSpecification implements QueryBuilderSpecification
{
    public function __construct(
        private ?AssigneeFilter $assignee,
    ) {
    }

    public function apply(QueryBuilder $queryBuilder): void
    {
        if (null === $this->assignee) {
            return;
        }

        if (null === $this->assignee->userId) {
            $queryBuilder->andWhere('t.assigneeId IS NULL');

            return;
        }

        $queryBuilder
            ->andWhere('t.assigneeId = :assignee_id')
            ->setParameter('assignee_id', $this->assignee->userId);
    }
}
