<?php

declare(strict_types=1);

namespace App\Tests\Unit\Billing\Checkout;

use App\Billing\Checkout\StripeCheckoutPages;
use App\Billing\Enum\Plan;
use App\Billing\Exception\PaymentProviderUnavailableException;
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

final class StripeCheckoutPagesTest extends TestCase
{
    private const string WORKSPACE_ID = '019a4f2e-7c1b-7d3a-9e5f-1a2b3c4d5e6f';

    /** 2026-11-06 12:10:00 UTC: ten minutes into a half-hour window. */
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

    private function pages(int $now = self::NOW): StripeCheckoutPages
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('@'.$now));

        return new StripeCheckoutPages(new StripeClient(['api_key' => 'sk_test_fake', 'max_network_retries' => 0]), $clock, 'https://app.test/billing');
    }

    private function open(int $now = self::NOW, string $buyerEmail = 'owner@example.com'): void
    {
        $this->pages($now)->open(Uuid::fromString(self::WORKSPACE_ID), Plan::TEAM, $buyerEmail);
    }

    #[Test]
    public function aPageShouldBeOpenedForThePriceWithThePlansLookupKey(): void
    {
        $page = $this->pages()->open(Uuid::fromString(self::WORKSPACE_ID), Plan::BUSINESS, 'owner@example.com');

        self::assertSame(['lookup_keys' => ['business'], 'active' => 'true', 'limit' => 1], $this->stripe->requests[0]['params']);
        self::assertSame([['price' => 'price_found', 'quantity' => 1]], $this->stripe->sessionParams()['line_items']);
        self::assertSame('cs_test_page', $page->id);
        self::assertSame('https://checkout.stripe.com/c/pay/cs_test_page', $page->url);
    }

    #[Test]
    public function theWorkspaceShouldTravelWithTheSessionAndTheSubscription(): void
    {
        $this->open();

        $params = $this->stripe->sessionParams();
        self::assertSame('subscription', $params['mode']);
        self::assertSame(self::WORKSPACE_ID, $params['client_reference_id']);
        self::assertSame(['workspace_id' => self::WORKSPACE_ID], $params['metadata']);
        self::assertSame(['metadata' => ['workspace_id' => self::WORKSPACE_ID]], $params['subscription_data']);
        self::assertSame('owner@example.com', $params['customer_email']);
    }

    #[Test]
    public function theCustomerShouldComeBackWithTheSessionId(): void
    {
        $this->open();

        $params = $this->stripe->sessionParams();
        self::assertSame('https://app.test/billing?checkout=success&session_id={CHECKOUT_SESSION_ID}', $params['success_url']);
        self::assertSame('https://app.test/billing?checkout=cancelled', $params['cancel_url']);
    }

    #[Test]
    #[TestWith([0])]
    #[TestWith([20 * 60])]
    #[TestWith([30 * 60 - 1])]
    public function aPageShouldExpireBetweenHalfAnHourAndAnHourFromNow(int $secondsIntoWindow): void
    {
        $windowStart = self::NOW - 10 * 60;
        $now = $windowStart + $secondsIntoWindow;

        $this->open($now);

        $expiresIn = (int) $this->stripe->sessionParams()['expires_at'] - $now;
        self::assertGreaterThanOrEqual(30 * 60, $expiresIn);
        self::assertLessThanOrEqual(60 * 60, $expiresIn);
    }

    #[Test]
    public function askingAgainInTheSameWindowShouldSendTheSameRequest(): void
    {
        $this->open();
        $this->open(self::NOW + 60);

        self::assertNotNull($this->stripe->idempotencyKey(1));
        self::assertSame($this->stripe->idempotencyKey(1), $this->stripe->idempotencyKey(3));
        self::assertSame($this->stripe->requests[1]['params'], $this->stripe->requests[3]['params']);
    }

    #[Test]
    public function askingInTheNextWindowShouldOpenANewPage(): void
    {
        $this->open();
        $this->open(self::NOW + 30 * 60);

        self::assertNotSame($this->stripe->idempotencyKey(1), $this->stripe->idempotencyKey(3));
    }

    #[Test]
    public function anotherBuyerShouldNotReuseThePage(): void
    {
        $this->open();
        $this->open(buyerEmail: 'co-owner@example.com');

        self::assertNotSame($this->stripe->idempotencyKey(1), $this->stripe->idempotencyKey(3));
    }

    #[Test]
    public function aPlanWithoutAPriceInStripeShouldNotBeSold(): void
    {
        $this->stripe->prices = [];

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Stripe has no active price with the lookup key "team".');

        $this->open();
    }

    #[Test]
    public function aStripeThatCannotBeReachedShouldBeReportedAsUnavailable(): void
    {
        $this->stripe->failure = new ApiConnectionException('Could not connect to Stripe.');

        $this->expectException(PaymentProviderUnavailableException::class);

        $this->open();
    }

    #[Test]
    #[TestWith([429])]
    #[TestWith([500])]
    #[TestWith([503])]
    public function aStripeThatIsBusyOrFailingShouldBeReportedAsUnavailable(int $status): void
    {
        $this->stripe->status = $status;

        $this->expectException(PaymentProviderUnavailableException::class);

        $this->open();
    }

    #[Test]
    public function aRequestStripeRefusesShouldStayOurError(): void
    {
        $this->stripe->status = 400;

        $this->expectException(InvalidRequestException::class);

        $this->open();
    }
}
