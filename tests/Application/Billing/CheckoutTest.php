<?php

declare(strict_types=1);

namespace App\Tests\Application\Billing;

use App\Auth\Entity\User;
use App\Billing\Enum\Plan;
use App\Billing\Exception\PaymentProviderUnavailableException;
use App\Billing\Repository\PayerRepository;
use App\Billing\Repository\SubscriptionRepository;
use App\Fixtures\Auth\UserFactory;
use App\Fixtures\Billing\SubscriptionFactory;
use App\Fixtures\Workspace\WorkspaceFactory;
use App\Tests\ApiTestCase;
use App\Tests\Support\PaymentProviderStub;
use App\Workspace\Entity\Workspace;
use App\Workspace\Enum\WorkspaceRole;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\InvalidRequestException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

final class CheckoutTest extends ApiTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = UserFactory::createOne();
        $this->actingAs($this->user);
    }

    /** @param array<string, mixed> $body */
    private function checkout(Workspace $workspace, array $body = ['plan' => 'team']): Response
    {
        return $this->post($this->route('api_billing_checkout', ['id' => $workspace->getId()->toRfc4122()]), $body);
    }

    private function workspaceWithCustomer(string $customerId): Workspace
    {
        $workspace = WorkspaceFactory::createOne(['owner' => $this->user]);
        static::getContainer()->get(PayerRepository::class)->saveOrGetExisting($workspace->getId(), $customerId);

        return $workspace;
    }

    private function stripe(): PaymentProviderStub
    {
        return static::getContainer()->get(PaymentProviderStub::class);
    }

    #[Test]
    #[TestWith(['team', Plan::TEAM])]
    #[TestWith(['business', Plan::BUSINESS])]
    public function ownerShouldGetAPaymentPageForAPlan(string $plan, Plan $expected): void
    {
        $workspace = WorkspaceFactory::createOne(['owner' => $this->user]);

        $response = $this->checkout($workspace, ['plan' => $plan]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('checkout_sessions', $this->jsonData($response)['type']);
        self::assertSame('https://checkout.stripe.test/c/pay/cs_test_stub', $this->jsonAttributes($response)['url']);
        self::assertEquals([['customerId' => 'cus_stub_1', 'workspaceId' => $workspace->getId(), 'plan' => $expected]], $this->stripe()->opened);
    }

    #[Test]
    public function theFirstPurchaseShouldCreateTheWorkspacesCustomerWithTheBuyersEmail(): void
    {
        $workspace = WorkspaceFactory::createOne(['owner' => $this->user]);

        $this->checkout($workspace);

        self::assertEquals([['workspaceId' => $workspace->getId(), 'email' => $this->user->getEmail()]], $this->stripe()->createdCustomers);
        self::assertSame('cus_stub_1', static::getContainer()->get(PayerRepository::class)->findCustomerId($workspace->getId()));
    }

    #[Test]
    public function aCustomerCreatedForThisPurchaseShouldNotBeCheckedForSubscriptions(): void
    {
        $this->checkout(WorkspaceFactory::createOne(['owner' => $this->user]));

        self::assertResponseStatusCodeSame(201);
        self::assertSame([], $this->stripe()->checkedCustomers);
    }

    #[Test]
    public function aWorkspaceThatHasACustomerShouldBuyAsThatCustomer(): void
    {
        $workspace = WorkspaceFactory::createOne(['owner' => $this->user]);
        static::getContainer()->get(PayerRepository::class)->saveOrGetExisting($workspace->getId(), 'cus_existing');

        $this->checkout($workspace);

        self::assertResponseStatusCodeSame(201);
        self::assertSame([], $this->stripe()->createdCustomers);
        self::assertSame(['cus_existing'], $this->stripe()->checkedCustomers);
        self::assertSame('cus_existing', $this->stripe()->opened[0]['customerId']);
    }

    #[Test]
    public function aWorkspaceWhoseCustomerAlreadySubscribedInStripeShouldNotBuyAgain(): void
    {
        $workspace = WorkspaceFactory::createOne(['owner' => $this->user]);
        static::getContainer()->get(PayerRepository::class)->saveOrGetExisting($workspace->getId(), 'cus_paid');
        $this->stripe()->subscribedCustomers = ['cus_paid'];

        $response = $this->checkout($workspace);

        self::assertResponseStatusCodeSame(409);
        self::assertSame('already_subscribed', $this->json($response)['errors'][0]['code']);
        self::assertSame([], $this->stripe()->opened);
    }

    #[Test]
    public function aWorkspaceThatHasACustomerShouldHaveItsOpenPagesClosedBeforeItsSubscriptionsAreChecked(): void
    {
        $workspace = $this->workspaceWithCustomer('cus_existing');

        $this->checkout($workspace);

        self::assertResponseStatusCodeSame(201);
        self::assertSame(['cus_existing'], $this->stripe()->closedPagesOf);
        self::assertSame(['closeOpenCheckoutPages', 'hasSubscription', 'openCheckout'], $this->stripe()->calls);
    }

    #[Test]
    public function aPagePaidWhileTheNewOneWasOpeningShouldStopTheNewOne(): void
    {
        $workspace = $this->workspaceWithCustomer('cus_paying');
        $this->stripe()->paidWhileClosing = ['cus_paying'];

        $response = $this->checkout($workspace);

        self::assertResponseStatusCodeSame(409);
        self::assertSame('already_subscribed', $this->json($response)['errors'][0]['code']);
        self::assertSame([], $this->stripe()->opened);
    }

    #[Test]
    public function aWorkspaceCreatingItsCustomerShouldHaveNoPagesClosed(): void
    {
        $this->checkout(WorkspaceFactory::createOne(['owner' => $this->user]));

        self::assertResponseStatusCodeSame(201);
        self::assertSame([], $this->stripe()->closedPagesOf);
    }

    #[Test]
    public function aPageStripeWillNotCloseShouldFailThePurchase(): void
    {
        $workspace = $this->workspaceWithCustomer('cus_stuck');
        $this->stripe()->closingFailure = new InvalidRequestException('This Checkout Session is not open.', 400);

        $this->checkout($workspace);

        self::assertResponseStatusCodeSame(500);
        self::assertSame([], $this->stripe()->checkedCustomers);
        self::assertSame([], $this->stripe()->opened);
    }

    #[Test]
    public function aPaymentProviderThatDoesNotAnswerWhileClosingPagesShouldBeAskedAgainLater(): void
    {
        $workspace = $this->workspaceWithCustomer('cus_existing');
        $this->stripe()->closingFailure = PaymentProviderUnavailableException::because(new ApiConnectionException('Could not connect to Stripe.'));

        $response = $this->checkout($workspace);

        self::assertResponseStatusCodeSame(503);
        self::assertSame('payment_provider_unavailable', $this->json($response)['errors'][0]['code']);
        self::assertSame([], $this->stripe()->opened);
    }

    #[Test]
    #[TestWith([WorkspaceRole::MEMBER])]
    #[TestWith([WorkspaceRole::VIEWER])]
    public function someoneWhoIsNotAnOwnerShouldNotBuy(WorkspaceRole $role): void
    {
        $workspace = WorkspaceFactory::new()->withMembers([[$this->user, $role]])->create();

        $this->checkout($workspace);

        self::assertResponseStatusCodeSame(403);
        self::assertSame([], $this->stripe()->calls);
    }

    #[Test]
    public function aStrangerShouldGetTheAnswerForAMissingWorkspace(): void
    {
        $foreignResponse = $this->checkout(WorkspaceFactory::createOne());
        self::assertResponseStatusCodeSame(404);

        $missingResponse = $this->post($this->route('api_billing_checkout', ['id' => Uuid::v7()->toRfc4122()]), ['plan' => 'team']);
        self::assertResponseStatusCodeSame(404);

        self::assertSame($this->json($missingResponse)['errors'], $this->json($foreignResponse)['errors']);
        self::assertSame([], $this->stripe()->calls);
    }

    #[Test]
    #[TestWith([WorkspaceRole::MEMBER])]
    #[TestWith([WorkspaceRole::VIEWER])]
    public function permissionShouldBeCheckedBeforeTheBody(WorkspaceRole $role): void
    {
        $workspace = WorkspaceFactory::new()->withMembers([[$this->user, $role]])->create();

        $this->checkout($workspace, ['plan' => 'enterprise']);

        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function aStrangerShouldNotFindTheWorkspaceEvenWithAnInvalidBody(): void
    {
        $this->checkout(WorkspaceFactory::createOne(), ['plan' => 'enterprise']);

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function aPersonalWorkspaceShouldBeBoughtLikeAnyOther(): void
    {
        $this->checkout(WorkspaceFactory::new()->personal()->create(['owner' => $this->user]));

        self::assertResponseStatusCodeSame(201);
    }

    #[Test]
    public function aWorkspaceWithAPaidPlanShouldChangeItInstead(): void
    {
        $workspace = WorkspaceFactory::createOne(['owner' => $this->user]);
        SubscriptionFactory::createOne(['workspaceId' => $workspace->getId(), 'plan' => Plan::TEAM]);

        $response = $this->checkout($workspace, ['plan' => 'business']);

        self::assertResponseStatusCodeSame(409);
        self::assertSame('already_subscribed', $this->json($response)['errors'][0]['code']);
        self::assertSame([], $this->stripe()->createdCustomers);
        self::assertSame([], $this->stripe()->opened);
    }

    #[Test]
    public function aPaymentProviderThatDoesNotAnswerShouldBeAskedAgainLater(): void
    {
        $this->stripe()->failure = PaymentProviderUnavailableException::because(new ApiConnectionException('Could not connect to Stripe.'));

        $response = $this->checkout(WorkspaceFactory::createOne(['owner' => $this->user]));

        self::assertResponseStatusCodeSame(503);
        self::assertResponseHeaderSame('Retry-After', '30');
        self::assertSame('payment_provider_unavailable', $this->json($response)['errors'][0]['code']);
    }

    #[Test]
    #[TestWith([['plan' => 'free']])]
    #[TestWith([['plan' => 'enterprise']])]
    #[TestWith([['plan' => '']])]
    #[TestWith([['interval' => 'month']])]
    public function onlyAPaidPlanShouldBeBought(array $body): void
    {
        $this->checkout(WorkspaceFactory::createOne(['owner' => $this->user]), $body);

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function openingAPageShouldNotChangeThePlan(): void
    {
        $workspace = WorkspaceFactory::createOne(['owner' => $this->user]);

        $this->checkout($workspace);

        self::assertSame(Plan::FREE, static::getContainer()->get(SubscriptionRepository::class)->planOf($workspace->getId()));
    }
}
