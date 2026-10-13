<?php

declare(strict_types=1);

namespace App\Task\DTO;

use App\Shared\Query\SearchQuery;
use App\Task\Enum\TaskStatus;
use App\Task\Query\AssigneeFilter;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

readonly class TaskFilterDTO
{
    public function __construct(
        #[Assert\Choice(callback: [TaskStatus::class, 'values'])]
        public ?string $status = null,

        #[Assert\NotBlank(allowNull: true)]
        #[Assert\Date(message: 'This value is not a valid date. Use the YYYY-MM-DD format.')]
        public ?string $due_from = null,

        #[Assert\NotBlank(allowNull: true)]
        #[Assert\Date(message: 'This value is not a valid date. Use the YYYY-MM-DD format.')]
        public ?string $due_to = null,

        #[Assert\NotBlank(allowNull: true, normalizer: 'trim')]
        #[Assert\Length(max: 100)]
        public ?string $search = null,

        #[Assert\NotBlank(allowNull: true)]
        // At most nine digits: any such id fits the 32-bit assignee_id column.
        #[Assert\Regex(pattern: '/^(me|none|[1-9]\d{0,8})\z/', message: 'Use "me", "none" or the id of a user.')]
        public ?string $assignee = null,
    ) {
    }

    public function assignee(int $currentUserId): ?AssigneeFilter
    {
        return match ($this->assignee) {
            null => null,
            'me' => AssigneeFilter::assignedTo($currentUserId),
            'none' => AssigneeFilter::unassigned(),
            default => AssigneeFilter::assignedTo((int) $this->assignee),
        };
    }

    public function searchQuery(): ?SearchQuery
    {
        return null === $this->search ? null : new SearchQuery(trim($this->search));
    }

    public function dueFrom(): ?\DateTimeImmutable
    {
        return self::parseDueDate($this->due_from);
    }

    public function dueTo(): ?\DateTimeImmutable
    {
        return self::parseDueDate($this->due_to);
    }

    #[Assert\Callback]
    public function validateDueRange(ExecutionContextInterface $context): void
    {
        $dueFrom = $this->dueFrom();
        $dueTo = $this->dueTo();

        if (null !== $dueFrom && null !== $dueTo && $dueTo < $dueFrom) {
            $context->buildViolation('This value should be greater than or equal to due_from.')
                ->atPath('due_to')
                ->addViolation();
        }
    }

    private static function parseDueDate(?string $value): ?\DateTimeImmutable
    {
        if (null === $value) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if (false === $date || $date->format('Y-m-d') !== $value) {
            return null;
        }

        return $date;
    }
}
