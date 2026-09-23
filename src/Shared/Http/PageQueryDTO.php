<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Symfony\Component\Validator\Constraints as Assert;

readonly class PageQueryDTO
{
    /**
     * Keeps the offset, (number - 1) * size, far from integer overflow and away from very deep scans.
     */
    public const int MAX_NUMBER = 10_000;

    public function __construct(
        #[Assert\Range(min: 1, max: self::MAX_NUMBER)]
        public int $number = 1,

        #[Assert\Range(min: 1, max: 100)]
        public int $size = 20,
    ) {
    }
}
