<?php

declare(strict_types=1);

namespace App\Tests\Application\Error;

use App\Fixtures\Auth\UserFactory;
use App\Fixtures\Workspace\WorkspaceFactory;
use App\Tests\ApiTestCase;
use App\Workspace\Enum\WorkspaceRole;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class UnauthorizedChallengeTest extends ApiTestCase
{
    /**
     * @return iterable<string, array{callable(self): void}>
     */
    public static function unauthorized(): iterable
    {
        yield 'no token (entry point)' => [static fn (self $test) => $test->get($test->route('api_profile_me'))];
        yield 'invalid token (JWT listener)' => [static function (self $test): void {
            $test->setCookie('access_token', 'not-a-jwt');
            $test->get($test->route('api_profile_me'));
        }];
        yield 'wrong password (login failure handler)' => [static fn (self $test) => $test->post($test->route('api_auth_login'), ['email' => 'nobody@example.com', 'password' => 'wrong-password'])];
        yield 'invalid refresh token (error normalizer)' => [static function (self $test): void {
            $test->setCookie('refresh_token', 'invalid-token-value');
            $test->post($test->route('api_auth_refresh'));
        }];
    }

    /**
     * @param callable(self): void $request
     */
    #[Test]
    #[DataProvider('unauthorized')]
    public function unauthorizedShouldSayHowToAuthenticate(callable $request): void
    {
        $request($this);

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('WWW-Authenticate', 'Bearer');
    }

    #[Test]
    public function forbiddenShouldNotAskToAuthenticate(): void
    {
        $owner = UserFactory::createOne();
        $viewer = UserFactory::createOne();
        $workspace = WorkspaceFactory::new()->withMembers([[$viewer, WorkspaceRole::VIEWER]])->create(['owner' => $owner]);
        $this->actingAs($viewer);

        $this->put($this->route('api_workspace_rename', ['id' => $workspace->getId()->toRfc4122()]), ['name' => 'Renamed']);

        self::assertResponseStatusCodeSame(403);
        self::assertResponseNotHasHeader('WWW-Authenticate');
    }
}
