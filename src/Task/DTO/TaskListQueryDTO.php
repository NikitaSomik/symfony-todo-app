<?php

declare(strict_types=1);

namespace App\Task\DTO;

use App\Shared\Http\PageQueryDTO;
use App\Shared\Http\QueryPayload;
use App\Shared\Query\SortDirection;
use App\Task\Enum\TaskSortField;
use Symfony\Component\Validator\Constraints as Assert;

readonly class TaskListQueryDTO implements QueryPayload
{
    public function __construct(
        #[Assert\Valid]
        public PageQueryDTO $page = new PageQueryDTO(),

        /** Null: a search is ordered by relevance, a plain list by creation time. */
        #[Assert\Choice(callback: [TaskSortField::class, 'values'])]
        public ?string $sort = null,

        #[Assert\Choice(choices: ['asc', 'desc'])]
        public string $direction = 'desc',

        #[Assert\Valid]
        public TaskFilterDTO $filter = new TaskFilterDTO(),
    ) {
    }

    /** The field the client chose to sort by; null when it chose none. */
    public function sortField(): ?TaskSortField
    {
        return null === $this->sort ? null : TaskSortField::from($this->sort);
    }

    public function direction(): SortDirection
    {
        return SortDirection::from($this->direction);
    }
}
