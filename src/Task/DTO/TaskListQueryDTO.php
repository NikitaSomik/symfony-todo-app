<?php

declare(strict_types=1);

namespace App\Task\DTO;

use App\Shared\Http\PageQueryDTO;
use App\Shared\Http\QueryPayload;
use App\Shared\Query\Sort;
use App\Shared\Query\SortDirection;
use App\Task\Enum\TaskSortField;
use Symfony\Component\Validator\Constraints as Assert;

readonly class TaskListQueryDTO implements QueryPayload
{
    public function __construct(
        #[Assert\Valid]
        public PageQueryDTO $page = new PageQueryDTO(),

        #[Assert\Choice(callback: [TaskSortField::class, 'values'])]
        public string $sort = TaskSortField::CREATED_AT->value,

        #[Assert\Choice(choices: ['asc', 'desc'])]
        public string $direction = 'desc',

        #[Assert\Valid]
        public TaskFilterDTO $filter = new TaskFilterDTO(),
    ) {
    }

    public function sort(): Sort
    {
        return new Sort($this->sort, SortDirection::from($this->direction));
    }
}
