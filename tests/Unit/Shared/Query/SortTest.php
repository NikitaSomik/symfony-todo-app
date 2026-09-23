<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Query;

use App\Shared\Query\Sort;
use App\Shared\Query\SortDirection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SortTest extends TestCase
{
    #[Test]
    public function listFromQueryShouldReadFieldsInOrderWithTheirDirection(): void
    {
        $sorts = Sort::listFromQuery('-status,due_date');

        self::assertEquals(
            [new Sort('status', SortDirection::DESC), new Sort('due_date', SortDirection::ASC)],
            $sorts,
        );
    }

    #[Test]
    public function listFromQueryShouldKeepAnEmptyFieldForTheCallerToReject(): void
    {
        self::assertEquals(
            [new Sort('status', SortDirection::ASC), new Sort('', SortDirection::ASC)],
            Sort::listFromQuery('status,'),
        );
    }
}
