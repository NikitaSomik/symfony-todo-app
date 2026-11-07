<?php

declare(strict_types=1);

namespace App\Tests\Unit\Billing\Stripe;

use App\Billing\Enum\Plan;
use App\Billing\Exception\PaymentProviderUnavailableException;
use App\Billing\Stripe\StripePaymentProvider;
use App\Tests\Support\FakeStripeHttp;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Stripe\ApiRequestor;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\InvalidRequestException;
use Stripe\StripeClient;
use Symfony\Component\Uid\Uuid;

final class StripePaymentProviderTest extends TestCase
{
    private const string WORKSPACE_ID = '019a4f2e-7c1b-7d3a-9e5f-1a2b3c4d5e6f';

    private const int NOW = 1793967000;

    private FakeStripeHttp $stripe;

    protected function setUp(): void
    {
        $this->stripe = new FakeStripeHttp();
        ApiRequestor::setHttpClient($this->stripe);
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);
    }

    private function provider(): StripePaymentProvider
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('@'.self::NOW));

        return new StripePaymentProvider(new StripeClient(['api_key' => 'sk_test_fake', 'max_network_retries' => 0]), $clock, 'https://app.test/billing');
    }

    private function openCheckout(Plan $plan = Plan::TEAM): void
    {
        $this->provider()->openCheckout('cus_workspace', Uuid::fromString(self::WORKSPACE_ID), $plan);
    }

    #[Test]
    public function aCustomerShouldBeCreatedWithTheBuyersEmailAndTheWorkspace(): void
    {
        $customerId = $this->provider()->createCustomer(Uuid::fromString(self::WORKSPACE_ID), 'owner@example.com');

        self::assertSame('cus_new', $customerId);
        self::assertSame(['email' => 'owner@example.com', 'metadata' => ['workspace_id' => self::WORKSPACE_ID]], $this->stripe->sentTo('/v1/customers'));
    }

    #[Test]
    public function allTheCustomersSubscriptionsShouldBeAskedFor(): void
    {
        $this->provider()->hasSubscription('cus_workspace');

        self::assertSame(['customer' => 'cus_workspace', 'status' => 'all', 'limit' => 100], $this->stripe->sentTo('/v1/subscriptions'));
    }

    #[Test]
    #[TestWith(['active'])]
    #[TestWith(['trialing'])]
    #[TestWith(['past_due'])]
    #[TestWith(['unpaid'])]
    #[TestWith(['paused'])]
    #[TestWith(['incomplete'])]
    public function aSubscriptionThatIsNotOverShouldCount(string $status): void
    {
        $this->stripe->subscriptions = [['id' => 'sub_1', 'object' => 'subscription', 'status' => 'canceled'], ['id' => 'sub_2', 'object' => 'subscription', 'status' => $status]];

        self::assertTrue($this->provider()->hasSubscription('cus_workspace'));
    }

    #[Test]
    #[TestWith([[]])]
    #[TestWith([['canceled']])]
    #[TestWith([['canceled', 'incomplete_expired']])]
    public function subscriptionsThatAreOverShouldNotCount(array $statuses): void
    {
        $this->stripe->subscriptions = array_map(static fn (string $status): array => ['id' => 'sub_'.$status, 'object' => 'subscription', 'status' => $status], $statuses);

        self::assertFalse($this->provider()->hasSubscription('cus_workspace'));
    }

    #[Test]
    public function aPageShouldBeOpenedForThePriceWithThePlansLookupKey(): void
    {
        $page = $this->provider()->openCheckout('cus_workspace', Uuid::fromString(self::WORKSPACE_ID), Plan::BUSINESS);

        self::assertSame(['lookup_keys' => ['business'], 'active' => 'true', 'limit' => 1], $this->stripe->sentTo('/v1/prices'));
        self::assertSame([['price' => 'price_found', 'quantity' => 1]], $this->stripe->sentTo('/v1/checkout/sessions')['line_items']);
        self::assertSame('cs_test_page', $page->id);
        self::assertSame('https://checkout.stripe.com/c/pay/cs_test_page', $page->url);
    }

    #[Test]
    public function aPageShouldBeForTheWorkspacesCustomerAndCarryTheWorkspace(): void
    {
        $this->openCheckout();

        $params = $this->stripe->sentTo('/v1/checkout/sessions');
        self::assertSame('subscription', $params['mode']);
        self::assertSame('cus_workspace', $params['customer']);
        self::assertArrayNotHasKey('customer_email', $params);
        self::assertSame(self::WORKSPACE_ID, $params['client_reference_id']);
        self::assertSame(['workspace_id' => self::WORKSPACE_ID], $params['metadata']);
        self::assertSame(['metadata' => ['workspace_id' => self::WORKSPACE_ID]], $params['subscription_data']);
    }

    #[Test]
    public function aPageShouldExpireInAnHourAndBringTheCustomerBackWithTheSessionId(): void
    {
        $this->openCheckout();

        $params = $this->stripe->sentTo('/v1/checkout/sessions');
        self::assertSame(self::NOW + 3600, $params['expires_at']);
        self::assertSame('https://app.test/billing?checkout=success&session_id={CHECKOUT_SESSION_ID}', $params['success_url']);
        self::assertSame('https://app.test/billing?checkout=cancelled', $params['cancel_url']);
    }

    #[Test]
    public function aPlanWithoutAPriceInStripeShouldNotBeSold(): void
    {
        $this->stripe->prices = [];

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Stripe has no active price with the lookup key "team".');

        $this->openCheckout();
    }

    #[Test]
    public function aStripeThatCannotBeReachedShouldBeReportedAsUnavailable(): void
    {
        $this->stripe->failure = new ApiConnectionException('Could not connect to Stripe.');

        $this->expectException(PaymentProviderUnavailableException::class);

        $this->provider()->createCustomer(Uuid::fromString(self::WORKSPACE_ID), 'owner@example.com');
    }

    #[Test]
    #[TestWith([429])]
    #[TestWith([500])]
    #[TestWith([503])]
    public function aStripeThatIsBusyOrFailingShouldBeReportedAsUnavailable(int $status): void
    {
        $this->stripe->status = $status;

        $this->expectException(PaymentProviderUnavailableException::class);

        $this->provider()->hasSubscription('cus_workspace');
    }

    #[Test]
    public function aRequestStripeRefusesShouldStayOurError(): void
    {
        $this->stripe->status = 400;

        $this->expectException(InvalidRequestException::class);

        $this->openCheckout();
    }
}
