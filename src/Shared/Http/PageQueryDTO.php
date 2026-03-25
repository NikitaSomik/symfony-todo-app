<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Symfony\Component\Validator\Constraints as Assert;

readonly class PageQueryDTO
{
    public function __construct(
        #[Assert\GreaterThanOrEqual(1)]
        public int $number = 1,

        #[Assert\Range(min: 1, max: 100)]
        public int $size = 20,
    ) {
    }
}
