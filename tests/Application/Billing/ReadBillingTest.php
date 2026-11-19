<?php

declare(strict_types=1);

namespace App\Tests\Application\Billing;

use App\Auth\Entity\User;
use App\Billing\Enum\Plan;
use App\Billing\Enum\SubscriptionStatus;
use App\Fixtures\Auth\UserFactory;
use App\Fixtures\Billing\SubscriptionFactory;
use App\Fixtures\Workspace\WorkspaceFactory;
use App\Tests\ApiTestCase;
use App\Workspace\Entity\Workspace;
use App\Workspace\Enum\WorkspaceRole;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

final class ReadBillingTest extends ApiTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = UserFactory::createOne();
        $this->actingAs($this->user);
    }

    private function readBilling(Workspace $workspace): Response
    {
        return $this->get($this->route('api_billing_show', ['id' => $workspace->getId()->toRfc4122()]));
    }

    private function ownWorkspace(): Workspace
    {
        return WorkspaceFactory::createOne(['owner' => $this->user]);
    }

    #[Test]
    public function ownerShouldReadThePlanAndTheStatusOfTheSubscription(): void
    {
        $workspace = $this->ownWorkspace();
        SubscriptionFactory::createOne(['workspaceId' => $workspace->getId(), 'plan' => Plan::BUSINESS, 'status' => SubscriptionStatus::ACTIVE]);

        $response = $this->readBilling($workspace);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(
            ['type' => 'billing', 'id' => $workspace->getId()->toRfc4122(), 'attributes' => ['plan' => 'business', 'status' => 'active']],
            $this->jsonData($response),
        );
    }

    #[Test]
    #[TestWith([WorkspaceRole::MEMBER])]
    #[TestWith([WorkspaceRole::VIEWER])]
    public function aMemberWhoIsNotAnOwnerShouldReadItToo(WorkspaceRole $role): void
    {
        $workspace = WorkspaceFactory::new()->withMembers([[$this->user, $role]])->create();
        SubscriptionFactory::createOne(['workspaceId' => $workspace->getId(), 'plan' => Plan::TEAM, 'status' => SubscriptionStatus::ACTIVE]);

        $response = $this->readBilling($workspace);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['plan' => 'team', 'status' => 'active'], $this->jsonAttributes($response));
    }

    #[Test]
    public function aWorkspaceWithoutASubscriptionShouldBeOnFreeWithNoStatus(): void
    {
        $response = $this->readBilling($this->ownWorkspace());

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['plan' => 'free', 'status' => null], $this->jsonAttributes($response));
    }

    #[Test]
    #[TestWith([SubscriptionStatus::PENDING, 'free'])]
    #[TestWith([SubscriptionStatus::GRACE, 'business'])]
    #[TestWith([SubscriptionStatus::SUSPENDED, 'free'])]
    #[TestWith([SubscriptionStatus::CANCELED, 'free'])]
    public function thePlanShouldBeTheOneTheStatusGrants(SubscriptionStatus $status, string $plan): void
    {
        $workspace = $this->ownWorkspace();
        SubscriptionFactory::createOne(['workspaceId' => $workspace->getId(), 'plan' => Plan::BUSINESS, 'status' => $status]);

        $response = $this->readBilling($workspace);

        self::assertSame(['plan' => $plan, 'status' => $status->value], $this->jsonAttributes($response));
    }

    #[Test]
    public function aSubscriptionNotCancelledShouldBeReadBeforeACancelledOne(): void
    {
        $workspace = $this->ownWorkspace();
        SubscriptionFactory::createOne(['workspaceId' => $workspace->getId(), 'plan' => Plan::TEAM, 'status' => SubscriptionStatus::CANCELED]);
        SubscriptionFactory::createOne(['workspaceId' => $workspace->getId(), 'plan' => Plan::BUSINESS, 'status' => SubscriptionStatus::PENDING]);

        $response = $this->readBilling($workspace);

        self::assertSame(['plan' => 'free', 'status' => 'pending'], $this->jsonAttributes($response));
    }

    #[Test]
    public function aStrangerShouldGetTheAnswerForAMissingWorkspace(): void
    {
        $foreign = WorkspaceFactory::createOne();
        SubscriptionFactory::createOne(['workspaceId' => $foreign->getId()]);

        $foreignResponse = $this->readBilling($foreign);
        self::assertResponseStatusCodeSame(404);

        $missingResponse = $this->get($this->route('api_billing_show', ['id' => Uuid::v7()->toRfc4122()]));
        self::assertResponseStatusCodeSame(404);

        self::assertSame($this->json($missingResponse)['errors'], $this->json($foreignResponse)['errors']);
    }

    #[Test]
    public function aRequestWithoutTheAccessCookieShouldBeUnauthorized(): void
    {
        $workspace = $this->ownWorkspace();

        $this->clearCookies()->readBilling($workspace);

        self::assertResponseStatusCodeSame(401);
    }
}
