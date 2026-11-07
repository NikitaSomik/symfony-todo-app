<?php

declare(strict_types=1);

namespace App\Billing\Payment;

final readonly class CheckoutPage
{
    public function __construct(
        public string $id,
        public string $url,
    ) {
    }
}
