<?php

declare(strict_types=1);

namespace App\Tests\Application\Billing;

use App\Auth\Entity\User;
use App\Billing\Enum\Plan;
use App\Fixtures\Auth\UserFactory;
use App\Fixtures\Billing\SubscriptionFactory;
use App\Fixtures\Workspace\WorkspaceFactory;
use App\Tests\ApiTestCase;
use App\Workspace\Entity\Workspace;
use App\Workspace\Enum\WorkspaceRole;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\HttpFoundation\Response;

final class PlanLimitTest extends ApiTestCase
{
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = UserFactory::createOne();
        $this->actingAs($this->owner);
    }

    /** Everyone but the owner is a viewer: viewers count toward the limit like anyone else. */
    private function workspaceWithMembers(int $members, ?Plan $plan = null): Workspace
    {
        $others = array_map(
            static fn (User $user): array => [$user, WorkspaceRole::VIEWER],
            UserFactory::createMany($members - 1),
        );
        $workspace = WorkspaceFactory::new()->withMembers($others)->create(['owner' => $this->owner]);

        if (null !== $plan) {
            SubscriptionFactory::createOne(['workspaceId' => $workspace->getId(), 'plan' => $plan]);
        }

        return $workspace;
    }

    private function addSomeone(Workspace $workspace): Response
    {
        return $this->post(
            $this->route('api_workspace_member_get_all', ['id' => $workspace->getId()->toRfc4122()]),
            ['email' => UserFactory::createOne()->getEmail()],
        );
    }

    private function assertRefusedByThePlan(Response $response): void
    {
        self::assertResponseStatusCodeSame(403);
        self::assertSame('plan_limit_exceeded', $this->json($response)['errors'][0]['code']);
    }

    #[Test]
    #[TestWith([2, null])]
    #[TestWith([9, Plan::TEAM])]
    #[TestWith([49, Plan::BUSINESS])]
    public function lastPlaceOfAPlanShouldBeTaken(int $members, ?Plan $plan): void
    {
        $this->addSomeone($this->workspaceWithMembers($members, $plan));

        self::assertResponseStatusCodeSame(201);
    }

    #[Test]
    #[TestWith([3, null])]
    #[TestWith([10, Plan::TEAM])]
    #[TestWith([50, Plan::BUSINESS])]
    public function aFullPlanShouldRefuseAnotherMember(int $members, ?Plan $plan): void
    {
        $this->assertRefusedByThePlan($this->addSomeone($this->workspaceWithMembers($members, $plan)));
    }

    #[Test]
    public function aWorkspaceOverItsLimitShouldKeepItsMembersAndRefuseMore(): void
    {
        $workspace = $this->workspaceWithMembers(5);

        $this->assertRefusedByThePlan($this->addSomeone($workspace));

        $members = $this->jsonData($this->get($this->route('api_workspace_member_get_all', ['id' => $workspace->getId()->toRfc4122()])));
        self::assertCount(5, $members);
    }

    #[Test]
    public function someoneAlreadyInAFullWorkspaceShouldStillBeAConflict(): void
    {
        $colleague = UserFactory::createOne();
        $workspace = WorkspaceFactory::new()
            ->withMembers([[$colleague, WorkspaceRole::MEMBER], [UserFactory::createOne(), WorkspaceRole::MEMBER]])
            ->create(['owner' => $this->owner]);

        $this->post($this->route('api_workspace_member_get_all', ['id' => $workspace->getId()->toRfc4122()]), ['email' => $colleague->getEmail()]);

        self::assertResponseStatusCodeSame(409);
    }

    #[Test]
    public function firstTeamWorkspaceShouldBeCreatedOnFree(): void
    {
        WorkspaceFactory::new()->personal()->create(['owner' => $this->owner]);

        $this->post($this->route('api_workspace_create'), ['name' => 'Mobile team']);

        self::assertResponseStatusCodeSame(201);
    }

    #[Test]
    public function secondTeamWorkspaceOnFreeShouldBeRefused(): void
    {
        WorkspaceFactory::createOne(['owner' => $this->owner]);

        $this->assertRefusedByThePlan($this->post($this->route('api_workspace_create'), ['name' => 'Mobile team']));
    }

    #[Test]
    public function aPaidWorkspaceShouldLeaveRoomForOneOnFree(): void
    {
        $this->workspaceWithMembers(1, Plan::TEAM);

        $this->post($this->route('api_workspace_create'), ['name' => 'Mobile team']);

        self::assertResponseStatusCodeSame(201);
    }

    #[Test]
    public function aWorkspaceSomeoneElseOwnsShouldNotCountAgainstTheUser(): void
    {
        WorkspaceFactory::new()->withMembers([[$this->owner, WorkspaceRole::MEMBER]])->create();

        $this->post($this->route('api_workspace_create'), ['name' => 'Mobile team']);

        self::assertResponseStatusCodeSame(201);
    }
}
