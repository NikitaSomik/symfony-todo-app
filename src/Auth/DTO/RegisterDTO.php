<?php

declare(strict_types=1);

namespace App\Auth\DTO;

use App\Auth\ValueObject\Email;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    required: ['email', 'password'],
    properties: [
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
        new OA\Property(property: 'password', type: 'string', minLength: 8, example: 'plum-anchor-violet-74', description: 'Rejected if it appears in a known data breach. Only the first 5 characters of its SHA-1 hash leave the server (Have I Been Pwned, k-anonymity).'),
    ]
)]
readonly class RegisterDTO
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        #[Assert\Length(max: Email::MAX_LENGTH)]
        public string $email,

        #[Assert\NotBlank]
        #[Assert\Length(min: 8, max: 100)]
        // A lookup that fails lets the password through: signing up matters more than this check.
        #[Assert\NotCompromisedPassword(skipOnError: true)]
        public string $password,
    ) {
    }
}
