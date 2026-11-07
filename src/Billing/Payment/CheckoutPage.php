<?php

declare(strict_types=1);

namespace App\Billing\Payment;

/** A payment page opened for one purchase; the customer pays there, not with us. */
final readonly class CheckoutPage
{
    public function __construct(
        public string $id,
        public string $url,
    ) {
    }
}
