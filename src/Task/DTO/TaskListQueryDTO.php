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

        /** Null orders a search by relevance and anything else by creation time; see sort(). */
        #[Assert\Choice(callback: [TaskSortField::class, 'values'])]
        public ?string $sort = null,

        #[Assert\Choice(choices: ['asc', 'desc'])]
        public string $direction = 'desc',

        #[Assert\Valid]
        public TaskFilterDTO $filter = new TaskFilterDTO(),
    ) {
    }

    /**
     * The sort field the client chose, or the default one. Null when tasks are ordered by relevance:
     * a search without an explicit sort ranks the best matches first, as search engines do by default.
     */
    public function sort(): ?Sort
    {
        if (null === $this->sort && null !== $this->filter->searchQuery()) {
            return null;
        }

        return new Sort($this->sort ?? TaskSortField::CREATED_AT->value, $this->direction());
    }

    public function direction(): SortDirection
    {
        return SortDirection::from($this->direction);
    }
}
