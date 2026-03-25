<?php

declare(strict_types=1);

namespace App\Shared\Persistence\Doctrine;

use Doctrine\ORM\QueryBuilder;

final class SpecificationApplier
{
    /**
     * @param iterable<QueryBuilderSpecification> $specifications
     */
    public function apply(QueryBuilder $queryBuilder, iterable $specifications): void
    {
        foreach ($specifications as $specification) {
            $specification->apply($queryBuilder);
        }
    }
}
