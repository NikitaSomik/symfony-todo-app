<?php

declare(strict_types=1);

namespace App\Tests\Unit\Task\DTO;

use App\Shared\Query\Sort;
use App\Shared\Query\SortDirection;
use App\Task\DTO\TaskListQueryDTO;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TaskListQueryDTOTest extends TestCase
{
    #[Test]
    public function sortsShouldReadFieldsInOrderWithTheirDirection(): void
    {
        self::assertEquals(
            [new Sort('status', SortDirection::DESC), new Sort('due_date', SortDirection::ASC)],
            (new TaskListQueryDTO(sort: '-status,due_date'))->sorts(),
        );
    }

    #[Test]
    public function sortsShouldDefaultToNewestFirst(): void
    {
        self::assertEquals(
            [new Sort('created_at', SortDirection::DESC)],
            (new TaskListQueryDTO())->sorts(),
        );
    }
}
