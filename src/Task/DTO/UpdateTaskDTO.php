<?php

declare(strict_types=1);

namespace App\Task\DTO;

use App\Task\Enum\TaskStatus;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[OA\Schema(
    required: ['title'],
    properties: [
        new OA\Property(property: 'title', type: 'string', example: 'Buy milk'),
        new OA\Property(property: 'description', type: 'string', maxLength: 2000, minLength: 3, example: '2 liters', nullable: true),
        new OA\Property(property: 'status', ref: new Model(type: TaskStatus::class), description: 'Task status'),
        new OA\Property(property: 'cancellation_reason', type: 'string', maxLength: 500, minLength: 3, example: 'Task is no longer needed', nullable: true),
        new OA\Property(property: 'due_date', type: 'string', format: 'date', example: '2026-04-01', nullable: true),
    ]
)]
readonly class UpdateTaskDTO
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $title,

        #[Assert\Length(min: 3, max: 2000)]
        public ?string $description = null,

        #[Assert\Choice(callback: [TaskStatus::class, 'values'])]
        public string $status = TaskStatus::TODO->value,

        #[Assert\Length(min: 3, max: 500)]
        public ?string $cancellation_reason = null,

        #[Assert\Date(message: 'This value is not a valid date. Use the YYYY-MM-DD format.')]
        public ?string $due_date = null,
    ) {
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if (TaskStatus::CANCELLED->value === $this->status && null === $this->cancellation_reason) {
            $context->buildViolation('Cancellation reason is required when status is cancelled.')
                ->atPath('cancellation_reason')
                ->addViolation();
        }

        if (TaskStatus::CANCELLED->value !== $this->status && null !== $this->cancellation_reason) {
            $context->buildViolation('Cancellation reason can only be provided when status is cancelled.')
                ->atPath('cancellation_reason')
                ->addViolation();
        }
    }

    public function status(): TaskStatus
    {
        return TaskStatus::from($this->status);
    }

    public function dueDate(): ?\DateTimeImmutable
    {
        return self::parseDueDate($this->due_date);
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
