<?php

declare(strict_types=1);

namespace App\Task\DTO;

use App\Shared\Http\PageQueryDTO;
use App\Shared\Http\QueryPayload;
use App\Shared\Query\Sort;
use App\Shared\Query\SortDirection;
use App\Task\Enum\TaskSortField;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

readonly class TaskListQueryDTO implements QueryPayload
{
    public function __construct(
        #[Assert\Valid]
        public PageQueryDTO $page = new PageQueryDTO(),

        public string $sort = '-'.TaskSortField::CREATED_AT->value,

        #[Assert\Valid]
        public TaskFilterDTO $filter = new TaskFilterDTO(),
    ) {
    }

    /**
     * Reads the JSON:API "sort" value: comma-separated fields, each descending when prefixed with "-".
     * "-status,due_date" is status DESC, then due_date ASC.
     *
     * @return list<Sort>
     */
    public function sorts(): array
    {
        return array_map(
            static fn (string $field): Sort => str_starts_with($field, '-')
                ? new Sort(substr($field, 1), SortDirection::DESC)
                : new Sort($field, SortDirection::ASC),
            explode(',', $this->sort),
        );
    }

    #[Assert\Callback]
    public function validateSort(ExecutionContextInterface $context): void
    {
        foreach ($this->sorts() as $sort) {
            if (!\in_array($sort->field, TaskSortField::values(), true)) {
                $context->buildViolation('Sorting by "{{ field }}" is not supported.')
                    ->setParameter('{{ field }}', $sort->field)
                    ->atPath('sort')
                    ->addViolation();
            }
        }
    }
}
