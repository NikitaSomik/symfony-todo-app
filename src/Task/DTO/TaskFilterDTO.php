<?php

declare(strict_types=1);

namespace App\Task\DTO;

use App\Task\Enum\TaskStatus;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

readonly class TaskFilterDTO
{
    public function __construct(
        #[Assert\Choice(callback: [TaskStatus::class, 'values'])]
        public ?string $status = null,

        #[Assert\Date(message: 'This value is not a valid date. Use the YYYY-MM-DD format.')]
        public ?string $due_from = null,

        #[Assert\Date(message: 'This value is not a valid date. Use the YYYY-MM-DD format.')]
        public ?string $due_to = null,
    ) {
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
