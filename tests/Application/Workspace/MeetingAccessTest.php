<?php

declare(strict_types=1);

namespace App\Tests\Application\Workspace;

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
use Symfony\Component\Uid\Uuid;

final class MeetingAccessTest extends ApiTestCase
{
    private const string SERVICE_TOKEN = 'meet-service-token-for-tests';

    private User $owner;
    private User $member;
    private User $viewer;
    private Workspace $workspace;
    private string $limitModeBefore;

    protected function setUp(): void
    {
        parent::setUp();
        $this->limitModeBefore = $_SERVER['BILLING_LIMITS'];
        $this->owner = UserFactory::createOne();
        $this->member = UserFactory::createOne();
        $this->viewer = UserFactory::createOne();
        $this->workspace = WorkspaceFactory::new()
            ->withMembers([[$this->member, WorkspaceRole::MEMBER], [$this->viewer, WorkspaceRole::VIEWER]])
            ->create(['owner' => $this->owner]);
    }

    protected function tearDown(): void
    {
        $_SERVER['BILLING_LIMITS'] = $_ENV['BILLING_LIMITS'] = $this->limitModeBefore;
        parent::tearDown();
    }

    private function asMeet(): static
    {
        return $this->withHeader('Authorization', 'Bearer '.self::SERVICE_TOKEN);
    }

    private function meetingAccess(string $workspaceId, int|string $userId): Response
    {
        return $this->get(sprintf('/internal/v1/workspaces/%s/members/%s/meeting-access', $workspaceId, $userId));
    }

    private function meetingAccessOf(User $user): Response
    {
        return $this->meetingAccess($this->workspace->getId()->toRfc4122(), (int) $user->getId());
    }

    private function assertUnauthorizedInJsonApi(Response $response): void
    {
        self::assertResponseStatusCodeSame(401);
        self::assertSame(
            ['jsonapi' => ['version' => '1.1'], 'errors' => [['status' => '401', 'detail' => 'Unauthorized.']]],
            $this->json($response),
        );
    }

    #[Test]
    public function anOwnerShouldGetEveryPermissionAndTheLimitsOfFree(): void
    {
        $response = $this->asMeet()->meetingAccessOf($this->owner);

        self::assertResponseStatusCodeSame(200);
        self::assertSame([
            'permissions' => ['view', 'manage_workspace', 'work_on_tasks', 'delete_tasks', 'manage_billing', 'host_meetings'],
            'limits' => ['participants' => 3, 'minutes' => 40],
        ], $this->json($response));
    }

    #[Test]
    public function aMemberShouldBeAbleToHostMeetings(): void
    {
        $response = $this->asMeet()->meetingAccessOf($this->member);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['view', 'work_on_tasks', 'host_meetings'], $this->json($response)['permissions']);
    }

    #[Test]
    public function aViewerShouldOnlyView(): void
    {
        $response = $this->asMeet()->meetingAccessOf($this->viewer);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['view'], $this->json($response)['permissions']);
    }

    #[Test]
    #[TestWith([Plan::TEAM, 10, 120])]
    #[TestWith([Plan::BUSINESS, 50, 240])]
    public function limitsShouldFollowThePlanOfTheWorkspace(Plan $plan, int $participants, int $minutes): void
    {
        SubscriptionFactory::createOne(['workspaceId' => $this->workspace->getId(), 'plan' => $plan]);

        $response = $this->asMeet()->meetingAccessOf($this->member);

        self::assertSame(['participants' => $participants, 'minutes' => $minutes], $this->json($response)['limits']);
    }

    #[Test]
    public function someoneWhoIsNotAMemberShouldGetNoPermissionsAndNoLimits(): void
    {
        $response = $this->asMeet()->meetingAccessOf(UserFactory::createOne());

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['permissions' => [], 'limits' => null], $this->json($response));
    }

    #[Test]
    public function aWorkspaceThatDoesNotExistShouldAnswerLikeOneTheUserIsNotAMemberOf(): void
    {
        $response = $this->asMeet()->meetingAccess(Uuid::v7()->toRfc4122(), (int) $this->owner->getId());

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['permissions' => [], 'limits' => null], $this->json($response));
    }

    #[Test]
    public function limitsShouldBeAbsentWhileTheyAreSwitchedOff(): void
    {
        $_SERVER['BILLING_LIMITS'] = $_ENV['BILLING_LIMITS'] = 'off';

        $response = $this->asMeet()->meetingAccessOf($this->member);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['permissions' => ['view', 'work_on_tasks', 'host_meetings'], 'limits' => null], $this->json($response));
    }

    #[Test]
    public function limitsShouldBeGivenInShadowMode(): void
    {
        $_SERVER['BILLING_LIMITS'] = $_ENV['BILLING_LIMITS'] = 'shadow';

        $response = $this->asMeet()->meetingAccessOf($this->member);

        self::assertSame(['participants' => 3, 'minutes' => 40], $this->json($response)['limits']);
    }

    #[Test]
    public function aCallWithoutTheServiceTokenShouldBeRefused(): void
    {
        $response = $this->meetingAccessOf($this->owner);

        $this->assertUnauthorizedInJsonApi($response);
    }

    #[Test]
    public function aCallWithAWrongServiceTokenShouldBeRefused(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer not-the-service-token')->meetingAccessOf($this->owner);

        $this->assertUnauthorizedInJsonApi($response);
    }

    #[Test]
    public function aUserIdTooLargeForAnyUserShouldAnswerLikeSomeoneWhoIsNotAMember(): void
    {
        $response = $this->asMeet()->meetingAccess($this->workspace->getId()->toRfc4122(), '2147483648');

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['permissions' => [], 'limits' => null], $this->json($response));
    }

    #[Test]
    public function theCookieOfAUserShouldNotOpenTheInternalEndpoint(): void
    {
        $this->actingAs($this->owner)->meetingAccessOf($this->owner);

        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function theServiceTokenShouldNotOpenThePublicApi(): void
    {
        $this->asMeet()->get($this->route('api_profile_me'));

        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    #[TestWith(['not-a-uuid', '1'])]
    #[TestWith(['019a7c2e-0000-7000-8000-000000000000', 'not-a-number'])]
    public function anIdThatIsNotOneShouldBeNotFound(string $workspaceId, string $userId): void
    {
        $this->asMeet()->meetingAccess($workspaceId, $userId);

        self::assertResponseStatusCodeSame(404);
    }
}
