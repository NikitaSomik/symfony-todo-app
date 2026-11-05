<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Billing\Checkout\CheckoutPage;
use App\Billing\Checkout\CheckoutPages;
use App\Billing\Enum\Plan;
use Symfony\Component\Uid\Uuid;

/** Stands in for Stripe Checkout: opens a page that exists nowhere and remembers what was asked. */
final class CheckoutPagesStub implements CheckoutPages
{
    /** @var list<array{workspaceId: Uuid, plan: Plan, buyerEmail: string}> */
    public array $opened = [];

    public ?\Throwable $failure = null;

    public function open(Uuid $workspaceId, Plan $plan, string $buyerEmail): CheckoutPage
    {
        if (null !== $this->failure) {
            throw $this->failure;
        }

        $this->opened[] = ['workspaceId' => $workspaceId, 'plan' => $plan, 'buyerEmail' => $buyerEmail];

        return new CheckoutPage('cs_test_stub', 'https://checkout.stripe.test/c/pay/cs_test_stub');
    }
}
