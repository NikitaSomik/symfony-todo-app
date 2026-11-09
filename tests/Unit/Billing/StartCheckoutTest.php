<?php

declare(strict_types=1);

namespace App\Tests\Unit\Billing;

use App\Billing\Enum\Plan;
use App\Billing\Exception\AlreadySubscribedException;
use App\Billing\Repository\PayerRepository;
use App\Billing\Repository\SubscriptionRepository;
use App\Billing\Service\StartCheckout;
use App\Tests\Support\PaymentProviderStub;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class StartCheckoutTest extends TestCase
{
    private PaymentProviderStub $stripe;

    protected function setUp(): void
    {
        $this->stripe = new PaymentProviderStub();
    }

    private function startCheckout(string $savedCustomerId): StartCheckout
    {
        $subscriptions = $this->createStub(SubscriptionRepository::class);
        $subscriptions->method('planOf')->willReturn(Plan::FREE);

        $payers = $this->createStub(PayerRepository::class);
        $payers->method('findCustomerId')->willReturn(null);
        $payers->method('saveOrGetExisting')->willReturn($savedCustomerId);

        return new StartCheckout($subscriptions, $payers, $this->stripe);
    }

    #[Test]
    public function theCustomerThisPurchaseCreatedShouldNotBeCheckedForSubscriptions(): void
    {
        $this->startCheckout(savedCustomerId: 'cus_stub_1')->handle(Uuid::v7(), Plan::TEAM, 'owner@example.com');

        self::assertSame([], $this->stripe->checkedCustomers);
        self::assertSame('cus_stub_1', $this->stripe->opened[0]['customerId']);
    }

    #[Test]
    public function aCustomerSavedFirstByAParallelPurchaseShouldBeChecked(): void
    {
        $this->stripe->subscribedCustomers = ['cus_parallel'];

        try {
            $this->startCheckout(savedCustomerId: 'cus_parallel')->handle(Uuid::v7(), Plan::TEAM, 'owner@example.com');
            self::fail('A customer that already subscribed bought again.');
        } catch (AlreadySubscribedException) {
        }

        self::assertSame(['cus_parallel'], $this->stripe->checkedCustomers);
        self::assertSame([], $this->stripe->opened);
    }
}
