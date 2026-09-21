<?php

declare(strict_types=1);

namespace App\Auth\ValueObject;

final readonly class Email
{
    public string $value;

    public function __construct(string $email)
    {
        $this->value = mb_strtolower(trim($email));
    }
}
