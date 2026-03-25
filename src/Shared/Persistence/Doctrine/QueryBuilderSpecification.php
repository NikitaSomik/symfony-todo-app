<?php

declare(strict_types=1);

namespace App\Shared\Persistence\Doctrine;

use Doctrine\ORM\QueryBuilder;

interface QueryBuilderSpecification
{
    public function apply(QueryBuilder $queryBuilder): void;
}
