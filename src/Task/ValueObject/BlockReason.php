<?php

declare(strict_types=1);

namespace App\Task\ValueObject;

final readonly class BlockReason
{
    public const int MIN_LENGTH = 3;
    public const int MAX_LENGTH = 500;

    public string $value;

    public function __construct(string $reason)
    {
        $reason = trim($reason);
        $length = mb_strlen($reason);

        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            throw new \InvalidArgumentException(sprintf('A block reason must be %d to %d characters long, %d given.', self::MIN_LENGTH, self::MAX_LENGTH, $length));
        }

        $this->value = $reason;
    }
}
