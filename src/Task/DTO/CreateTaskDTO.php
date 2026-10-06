<?php

declare(strict_types=1);

namespace App\Task\DTO;

use App\Task\ValueObject\TaskDetails;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    required: ['title'],
    properties: [
        new OA\Property(property: 'title', type: 'string', example: 'Buy milk'),
        new OA\Property(property: 'description', type: 'string', maxLength: 2000, minLength: 3, example: '2 liters', nullable: true),
        new OA\Property(property: 'due_date', type: 'string', format: 'date', example: '2026-04-01', nullable: true),
    ]
)]
readonly class CreateTaskDTO
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $title,

        #[Assert\Length(min: 3, max: 2000)]
        public ?string $description = null,

        #[Assert\Date(message: 'This value is not a valid date. Use the YYYY-MM-DD format.')]
        public ?string $due_date = null,
    ) {
    }

    public function details(): TaskDetails
    {
        return new TaskDetails($this->title, $this->description, $this->dueDate());
    }

    private function dueDate(): ?\DateTimeImmutable
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
