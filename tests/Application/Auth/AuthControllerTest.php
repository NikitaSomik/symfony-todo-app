<?php

declare(strict_types=1);

namespace App\Tests\Application\Auth;

use App\Auth\DataFixtures\RefreshTokenFactory;
use App\Auth\DataFixtures\UserFactory;
use App\Auth\Repository\RefreshTokenRepository;
use App\Tests\ApiTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Cookie;

final class AuthControllerTest extends ApiTestCase
{
    #[Test]
    public function registerWhenValidDataShouldReturn201(): void
    {
        $this->post($this->route('api_auth_register'), [
            'email' => 'user@example.com',
            'password' => 'secret123',
        ]);

        self::assertResponseStatusCodeSame(201);
    }

    #[Test]
    public function registerWhenValidDataShouldReturnUser(): void
    {
        $response = $this->post($this->route('api_auth_register'), [
            'email' => 'user@example.com',
            'password' => 'secret123',
        ]);

        $data = $this->json($response);

        self::assertArrayHasKey('id', $data);
        self::assertSame('user@example.com', $data['email']);
        self::assertArrayHasKey('created_at', $data);
        self::assertArrayNotHasKey('password', $data);
    }

    #[Test]
    public function registerWhenEmailIsInvalidShouldReturn422(): void
    {
        $this->post($this->route('api_auth_register'), [
            'email' => 'not-an-email',
            'password' => 'secret123',
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function registerWhenPasswordTooShortShouldReturn422(): void
    {
        $this->post($this->route('api_auth_register'), [
            'email' => 'user@example.com',
            'password' => '123',
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function registerWhenEmailAlreadyExistsShouldReturn409(): void
    {
        UserFactory::createOne(['email' => 'user@example.com']);

        $response = $this->post($this->route('api_auth_register'), [
            'email' => 'user@example.com',
            'password' => 'secret123',
        ]);

        self::assertResponseStatusCodeSame(409);
        self::assertArrayHasKey('message', $this->json($response));
    }

    #[Test]
    public function loginWhenValidCredentialsShouldReturn200WithCookie(): void
    {
        UserFactory::createOne(['email' => 'user@example.com']);

        $this->post($this->route('api_auth_login'), [
            'email' => 'user@example.com',
            'password' => 'password',
        ]);

        self::assertResponseIsSuccessful();
        self::assertBrowserHasCookie('access_token');
    }

    #[Test]
    public function loginWhenInvalidPasswordShouldReturn401(): void
    {
        UserFactory::createOne(['email' => 'user@example.com']);

        $this->post($this->route('api_auth_login'), [
            'email' => 'user@example.com',
            'password' => 'wrong_password',
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function loginWhenUserNotFoundShouldReturn401(): void
    {
        $this->post($this->route('api_auth_login'), [
            'email' => 'ghost@example.com',
            'password' => 'password',
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    // --- Login refresh cookie ---

    #[Test]
    public function loginShouldSetRefreshTokenCookie(): void
    {
        UserFactory::createOne(['email' => 'user@example.com']);

        $response = $this->post($this->route('api_auth_login'), [
            'email' => 'user@example.com',
            'password' => 'password',
        ]);

        $responseCookieNames = array_map(
            static fn (Cookie $c) => $c->getName(),
            $response->headers->getCookies(),
        );
        self::assertContains('refresh_token', $responseCookieNames);
    }

    // --- Refresh ---

    #[Test]
    public function refreshWhenValidTokenShouldReturn204(): void
    {
        $refreshToken = RefreshTokenFactory::createOne();

        $this->setCookie('refresh_token', $refreshToken->getToken());
        $this->post($this->route('api_auth_refresh'));

        self::assertResponseStatusCodeSame(204);
    }

    #[Test]
    public function refreshWhenValidTokenShouldRenewCookies(): void
    {
        $refreshToken = RefreshTokenFactory::createOne();

        $this->setCookie('refresh_token', $refreshToken->getToken());
        $response = $this->post($this->route('api_auth_refresh'));

        $responseCookieNames = array_map(
            static fn (Cookie $cookie) => $cookie->getName(),
            $response->headers->getCookies(),
        );
        self::assertContains('access_token', $responseCookieNames);
        self::assertContains('refresh_token', $responseCookieNames);
    }

    #[Test]
    public function refreshShouldRotateToken(): void
    {
        $refreshToken = RefreshTokenFactory::createOne();
        $oldToken = $refreshToken->getToken();

        $this->setCookie('refresh_token', $oldToken);
        $this->post($this->route('api_auth_refresh'));

        $repo = static::getContainer()->get(RefreshTokenRepository::class);
        self::assertNull($repo->findValidByToken($oldToken));
    }

    #[Test]
    public function refreshWhenNoCookieShouldReturn401(): void
    {
        $response = $this->post($this->route('api_auth_refresh'));

        self::assertResponseStatusCodeSame(401);
        $this->assertJsonContains(['message' => 'Unauthorized.'], $response);
    }

    #[Test]
    public function refreshWhenTokenExpiredShouldReturn401(): void
    {
        $refreshToken = RefreshTokenFactory::new()->expired()->create();

        $this->setCookie('refresh_token', $refreshToken->getToken());
        $response = $this->post($this->route('api_auth_refresh'));

        self::assertResponseStatusCodeSame(401);
        $this->assertJsonContains(['message' => 'Unauthorized.'], $response);
    }

    #[Test]
    public function refreshWhenInvalidTokenShouldReturn401(): void
    {
        $this->setCookie('refresh_token', 'invalid-token-value');
        $response = $this->post($this->route('api_auth_refresh'));

        self::assertResponseStatusCodeSame(401);
        $this->assertJsonContains(['message' => 'Unauthorized.'], $response);
    }

    #[Test]
    public function refreshWhenTokenTooShortShouldReturn401(): void
    {
        $this->setCookie('refresh_token', 'ab');
        $response = $this->post($this->route('api_auth_refresh'));

        self::assertResponseStatusCodeSame(401);
        $this->assertJsonContains(['message' => 'Unauthorized.'], $response);
    }

    #[Test]
    public function refreshWhenTokenTooLongShouldReturn401(): void
    {
        $this->setCookie('refresh_token', str_repeat('a', 256));
        $response = $this->post($this->route('api_auth_refresh'));

        self::assertResponseStatusCodeSame(401);
        $this->assertJsonContains(['message' => 'Unauthorized.'], $response);
    }

    // --- Logout ---

    #[Test]
    public function logoutShouldReturn204(): void
    {
        $user = UserFactory::createOne();
        $refreshToken = RefreshTokenFactory::createOne(['user' => $user]);

        $this->actingAs($user);
        $this->setCookie('refresh_token', $refreshToken->getToken());
        $this->post($this->route('api_auth_logout'));

        self::assertResponseStatusCodeSame(204);
    }

    #[Test]
    public function logoutShouldClearCookies(): void
    {
        $user = UserFactory::createOne();
        $refreshToken = RefreshTokenFactory::createOne(['user' => $user]);

        $this->actingAs($user);
        $this->setCookie('refresh_token', $refreshToken->getToken());
        $response = $this->post($this->route('api_auth_logout'));

        $clearedCookieNames = array_map(
            static fn (Cookie $c) => $c->getName(),
            $response->headers->getCookies(),
        );
        self::assertContains('access_token', $clearedCookieNames);
        self::assertContains('refresh_token', $clearedCookieNames);
    }

    #[Test]
    public function logoutShouldRevokeAllUserTokens(): void
    {
        $user = UserFactory::createOne();
        RefreshTokenFactory::createMany(3, ['user' => $user]);
        $activeToken = RefreshTokenFactory::createOne(['user' => $user]);

        $this->actingAs($user);
        $this->setCookie('refresh_token', $activeToken->getToken());
        $this->post($this->route('api_auth_logout'));

        $repo = static::getContainer()->get(RefreshTokenRepository::class);
        self::assertCount(0, $repo->findBy(['user' => $user]));
    }

    #[Test]
    public function logoutWithoutCookieShouldReturn204(): void
    {
        $user = UserFactory::createOne();

        $this->actingAs($user);
        $this->post($this->route('api_auth_logout'));

        self::assertResponseStatusCodeSame(204);
    }

    #[Test]
    public function logoutShouldSetClearSiteDataHeader(): void
    {
        $user = UserFactory::createOne();

        $this->actingAs($user);
        $this->post($this->route('api_auth_logout'));

        self::assertResponseHeaderSame('Clear-Site-Data', '"cookies"');
    }
}
