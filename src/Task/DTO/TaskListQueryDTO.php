<?php

declare(strict_types=1);

namespace App\Task\DTO;

use App\Shared\Http\PageQueryDTO;
use App\Shared\Http\SortableQuery;
use App\Shared\Query\Sort;
use App\Task\Enum\TaskSortField;
use Symfony\Component\Validator\Constraints as Assert;

readonly class TaskListQueryDTO implements SortableQuery
{
    public function __construct(
        #[Assert\Valid]
        public PageQueryDTO $page = new PageQueryDTO(),

        public string $sort = '-'.TaskSortField::CREATED_AT->value,

        #[Assert\Valid]
        public TaskFilterDTO $filter = new TaskFilterDTO(),
    ) {
    }

    public static function sortFields(): array
    {
        return TaskSortField::values();
    }

    /**
     * @return list<Sort>
     */
    public function sorts(): array
    {
        return Sort::listFromQuery($this->sort);
    }
}
