<?php

declare(strict_types=1);

namespace App\Tests\Application\Auth;

use App\Auth\RefreshToken\RandomRefreshTokenGenerator;
use App\Auth\RefreshToken\RefreshTokenHash;
use App\Auth\Repository\RefreshTokenRepository;
use App\Fixtures\Auth\RefreshTokenFactory;
use App\Fixtures\Auth\UserFactory;
use App\Tests\ApiTestCase;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
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

        $data = $this->jsonData($response);
        $attributes = $data['attributes'];

        self::assertArrayHasKey('id', $data);
        self::assertSame('user@example.com', $attributes['email']);
        self::assertArrayHasKey('created_at', $attributes);
        self::assertArrayNotHasKey('password', $attributes);
    }

    #[Test]
    public function registerShouldStoreEmailLowercased(): void
    {
        $response = $this->post($this->route('api_auth_register'), [
            'email' => 'User@Example.com',
            'password' => 'secret123',
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('user@example.com', $this->jsonData($response)['attributes']['email']);
    }

    #[Test]
    public function registerWhenEmailDiffersOnlyByCaseShouldReturn409(): void
    {
        UserFactory::createOne(['email' => 'user@example.com']);

        $this->post($this->route('api_auth_register'), [
            'email' => 'USER@EXAMPLE.COM',
            'password' => 'secret123',
        ]);

        self::assertResponseStatusCodeSame(409);
    }

    #[Test]
    public function loginShouldIgnoreEmailCase(): void
    {
        UserFactory::createOne(['email' => 'user@example.com']);

        $this->post($this->route('api_auth_login'), [
            'email' => 'USER@Example.com',
            'password' => 'password',
        ]);

        self::assertResponseIsSuccessful();
        self::assertBrowserHasCookie('access_token');
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
        self::assertSame('409', $this->json($response)['errors'][0]['status']);
        self::assertSame('Email is already taken.', $this->json($response)['errors'][0]['detail']);
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

    // --- Registration throttling ---

    #[Test]
    public function registerWhenTooManyAttemptsShouldReturn429(): void
    {
        for ($i = 1; $i <= 10; ++$i) {
            $this->post($this->route('api_auth_register'), [
                'email' => sprintf('user%d@example.com', $i),
                'password' => 'secret123',
            ]);
            self::assertResponseStatusCodeSame(201);
        }

        $response = $this->post($this->route('api_auth_register'), [
            'email' => 'one-too-many@example.com',
            'password' => 'secret123',
        ]);

        self::assertResponseStatusCodeSame(429);
        self::assertSame('429', $this->json($response)['errors'][0]['status']);
        self::assertSame('Too many requests. Please try again later.', $this->json($response)['errors'][0]['detail']);
    }

    #[Test]
    public function registerWhenThrottledFromAnotherIpShouldSucceed(): void
    {
        $this->fromIp('203.0.113.10');
        for ($i = 1; $i <= 10; ++$i) {
            $this->post($this->route('api_auth_register'), [
                'email' => sprintf('user%d@example.com', $i),
                'password' => 'secret123',
            ]);
        }

        $this->fromIp('203.0.113.20')->post($this->route('api_auth_register'), [
            'email' => 'other-ip@example.com',
            'password' => 'secret123',
        ]);

        self::assertResponseStatusCodeSame(201);
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

    #[Test]
    public function loginShouldStoreOnlyRefreshTokenHash(): void
    {
        UserFactory::createOne(['email' => 'user@example.com']);

        $response = $this->post($this->route('api_auth_login'), [
            'email' => 'user@example.com',
            'password' => 'password',
        ]);

        $refreshCookies = array_values(array_filter(
            $response->headers->getCookies(),
            static fn (Cookie $c) => 'refresh_token' === $c->getName(),
        ));
        self::assertCount(1, $refreshCookies);
        $plainToken = (string) $refreshCookies[0]->getValue();

        $storedHashes = static::getContainer()->get('doctrine.dbal.default_connection')
            ->fetchFirstColumn('SELECT token FROM refresh_tokens');

        self::assertSame([hash('sha256', $plainToken)], $storedHashes);
    }

    // --- Login throttling ---

    #[Test]
    public function loginWhenTooManyFailedAttemptsShouldReturn429(): void
    {
        UserFactory::createOne(['email' => 'user@example.com']);

        $this->failLogin('user@example.com', 5);
        $response = $this->post($this->route('api_auth_login'), [
            'email' => 'user@example.com',
            'password' => 'wrong_password',
        ]);

        self::assertResponseStatusCodeSame(429);
        self::assertSame('429', $this->json($response)['errors'][0]['status']);
        self::assertSame('Too many login attempts. Please try again in 1 minute.', $this->json($response)['errors'][0]['detail']);
    }

    #[Test]
    public function loginWhenThrottledShouldRejectCorrectPassword(): void
    {
        UserFactory::createOne(['email' => 'user@example.com']);

        $this->failLogin('user@example.com', 5);
        $this->post($this->route('api_auth_login'), [
            'email' => 'user@example.com',
            'password' => 'password',
        ]);

        self::assertResponseStatusCodeSame(429);
    }

    #[Test]
    public function loginWhenThrottledForUnknownEmailShouldReturn429(): void
    {
        $this->failLogin('ghost@example.com', 5);
        $response = $this->post($this->route('api_auth_login'), [
            'email' => 'ghost@example.com',
            'password' => 'password',
        ]);

        self::assertResponseStatusCodeSame(429);
        self::assertSame('Too many login attempts. Please try again in 1 minute.', $this->json($response)['errors'][0]['detail']);
    }

    #[Test]
    public function loginWhenAnotherEmailIsThrottledShouldSucceedFromSameIp(): void
    {
        UserFactory::createOne(['email' => 'user@example.com']);

        $this->failLogin('attacked@example.com', 5);
        $this->post($this->route('api_auth_login'), [
            'email' => 'user@example.com',
            'password' => 'password',
        ]);

        self::assertResponseIsSuccessful();
    }

    #[Test]
    public function loginWhenEmailIsThrottledFromAnotherIpShouldSucceed(): void
    {
        UserFactory::createOne(['email' => 'user@example.com']);

        $this->fromIp('203.0.113.10')->failLogin('user@example.com', 5);
        $this->fromIp('203.0.113.20')->post($this->route('api_auth_login'), [
            'email' => 'user@example.com',
            'password' => 'password',
        ]);

        self::assertResponseIsSuccessful();
    }

    #[Test]
    public function loginWhenForwardedForIsSpoofedShouldStillBeThrottled(): void
    {
        UserFactory::createOne(['email' => 'user@example.com']);

        for ($i = 1; $i <= 5; ++$i) {
            $this->withForwardedFor('198.51.100.'.$i)->failLogin('user@example.com', 1);
        }
        $this->withForwardedFor('198.51.100.99')->post($this->route('api_auth_login'), [
            'email' => 'user@example.com',
            'password' => 'password',
        ]);

        self::assertResponseStatusCodeSame(429);
    }

    #[Test]
    public function loginWhenSucceededShouldNotCountAsFailedAttempt(): void
    {
        UserFactory::createOne(['email' => 'user@example.com']);

        $this->failLogin('user@example.com', 4);
        for ($i = 0; $i < 3; ++$i) {
            $this->post($this->route('api_auth_login'), [
                'email' => 'user@example.com',
                'password' => 'password',
            ]);
            self::assertResponseIsSuccessful();
        }

        $this->post($this->route('api_auth_login'), [
            'email' => 'user@example.com',
            'password' => 'wrong_password',
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    // --- Refresh ---

    #[Test]
    public function refreshWhenValidTokenShouldReturn204(): void
    {
        $refreshToken = $this->createRefreshToken();

        $this->setCookie('refresh_token', $refreshToken);
        $this->post($this->route('api_auth_refresh'));

        self::assertResponseStatusCodeSame(204);
    }

    #[Test]
    public function refreshWhenValidTokenShouldRenewCookies(): void
    {
        $refreshToken = $this->createRefreshToken();

        $this->setCookie('refresh_token', $refreshToken);
        $response = $this->post($this->route('api_auth_refresh'));

        $responseCookieNames = array_map(
            static fn (Cookie $cookie) => $cookie->getName(),
            $response->headers->getCookies(),
        );
        self::assertContains('access_token', $responseCookieNames);
        self::assertContains('refresh_token', $responseCookieNames);
    }

    #[Test]
    public function refreshWhenValidTokenShouldReturnEmptyBody(): void
    {
        $refreshToken = $this->createRefreshToken();

        $this->setCookie('refresh_token', $refreshToken);
        $response = $this->post($this->route('api_auth_refresh'));

        self::assertResponseStatusCodeSame(204);
        self::assertSame('', $response->getContent());
    }

    #[Test]
    public function refreshShouldRotateToken(): void
    {
        $oldToken = $this->createRefreshToken();

        $this->setCookie('refresh_token', $oldToken);
        $this->post($this->route('api_auth_refresh'));

        $repo = static::getContainer()->get(RefreshTokenRepository::class);
        self::assertNull($repo->findValidByToken(RefreshTokenHash::fromPlain($oldToken)));
    }

    #[Test]
    public function refreshWhenNoCookieShouldReturn401(): void
    {
        $response = $this->post($this->route('api_auth_refresh'));

        self::assertResponseStatusCodeSame(401);
        self::assertSame('401', $this->json($response)['errors'][0]['status']);
        self::assertSame('Unauthorized.', $this->json($response)['errors'][0]['detail']);
    }

    #[Test]
    public function refreshWhenTokenExpiredShouldReturn401(): void
    {
        $refreshToken = $this->createRefreshToken(['expiresAt' => new \DateTimeImmutable('-1 day')]);

        $this->setCookie('refresh_token', $refreshToken);
        $response = $this->post($this->route('api_auth_refresh'));

        self::assertResponseStatusCodeSame(401);
        self::assertSame('401', $this->json($response)['errors'][0]['status']);
        self::assertSame('Unauthorized.', $this->json($response)['errors'][0]['detail']);
    }

    #[Test]
    public function refreshWhenInvalidTokenShouldReturn401(): void
    {
        $this->setCookie('refresh_token', 'invalid-token-value');
        $response = $this->post($this->route('api_auth_refresh'));

        self::assertResponseStatusCodeSame(401);
        self::assertSame('401', $this->json($response)['errors'][0]['status']);
        self::assertSame('Unauthorized.', $this->json($response)['errors'][0]['detail']);
    }

    #[Test]
    public function refreshWhenTokenTooShortShouldReturn401(): void
    {
        $this->setCookie('refresh_token', 'ab');
        $response = $this->post($this->route('api_auth_refresh'));

        self::assertResponseStatusCodeSame(401);
        self::assertSame('401', $this->json($response)['errors'][0]['status']);
        self::assertSame('Unauthorized.', $this->json($response)['errors'][0]['detail']);
    }

    #[Test]
    public function refreshWhenTokenTooLongShouldReturn401(): void
    {
        $this->setCookie('refresh_token', str_repeat('a', 256));
        $response = $this->post($this->route('api_auth_refresh'));

        self::assertResponseStatusCodeSame(401);
        self::assertSame('401', $this->json($response)['errors'][0]['status']);
        self::assertSame('Unauthorized.', $this->json($response)['errors'][0]['detail']);
    }

    // --- Logout ---

    #[Test]
    public function logoutShouldReturn204(): void
    {
        $user = UserFactory::createOne();
        $refreshToken = $this->createRefreshToken(['user' => $user]);

        $this->actingAs($user);
        $this->setCookie('refresh_token', $refreshToken);
        $this->post($this->route('api_auth_logout'));

        self::assertResponseStatusCodeSame(204);
    }

    #[Test]
    public function logoutShouldClearCookies(): void
    {
        $user = UserFactory::createOne();
        $refreshToken = $this->createRefreshToken(['user' => $user]);

        $this->actingAs($user);
        $this->setCookie('refresh_token', $refreshToken);
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
        $activeToken = $this->createRefreshToken(['user' => $user]);

        $this->actingAs($user);
        $this->setCookie('refresh_token', $activeToken);
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
    public function logoutWithoutAuthenticatedUserShouldRevokeRefreshTokenStoredOnAuthPath(): void
    {
        $refreshToken = $this->createRefreshToken();

        $this->setCookieWithPath('refresh_token', $refreshToken, '/api/v1/auth');
        $this->post($this->route('api_auth_logout'));

        self::assertResponseStatusCodeSame(204);

        $repo = static::getContainer()->get(RefreshTokenRepository::class);
        self::assertNull($repo->findValidByToken(RefreshTokenHash::fromPlain($refreshToken)));
    }

    #[Test]
    public function refreshAfterLogoutShouldFail(): void
    {
        $user = UserFactory::createOne();
        $refreshToken = $this->createRefreshToken(['user' => $user]);

        $this->setCookieWithPath('refresh_token', $refreshToken, '/api/v1/auth');
        $this->post($this->route('api_auth_logout'));

        self::assertResponseStatusCodeSame(204);

        $this->setCookieWithPath('refresh_token', $refreshToken, '/api/v1/auth');
        $this->post($this->route('api_auth_refresh'));

        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function logoutShouldSetClearSiteDataHeader(): void
    {
        $user = UserFactory::createOne();

        $this->actingAs($user);
        $this->post($this->route('api_auth_logout'));

        self::assertResponseHeaderSame('Clear-Site-Data', '"cookies"');
    }

    #[Test]
    public function logoutShouldReturnEmptyBody(): void
    {
        $user = UserFactory::createOne();

        $this->actingAs($user);
        $response = $this->post($this->route('api_auth_logout'));

        self::assertResponseStatusCodeSame(204);
        self::assertSame('', $response->getContent());
    }

    // --- Stale access token cookie ---

    #[Test]
    public function refreshWhenAccessTokenCookieIsInvalidShouldReturn204(): void
    {
        $refreshToken = $this->createRefreshToken();

        $this->setCookie('access_token', 'stale-access-token');
        $this->setCookie('refresh_token', $refreshToken);
        $this->post($this->route('api_auth_refresh'));

        self::assertResponseStatusCodeSame(204);
    }

    #[Test]
    public function loginWhenAccessTokenCookieIsInvalidShouldSucceed(): void
    {
        UserFactory::createOne(['email' => 'user@example.com']);

        $this->setCookie('access_token', 'stale-access-token');
        $this->post($this->route('api_auth_login'), [
            'email' => 'user@example.com',
            'password' => 'password',
        ]);

        self::assertResponseIsSuccessful();
    }

    #[Test]
    public function registerWhenAccessTokenCookieIsInvalidShouldReturn201(): void
    {
        $this->setCookie('access_token', 'stale-access-token');
        $this->post($this->route('api_auth_register'), [
            'email' => 'user@example.com',
            'password' => 'secret123',
        ]);

        self::assertResponseStatusCodeSame(201);
    }

    #[Test]
    public function logoutWhenAccessTokenCookieIsInvalidShouldRevokeSessionAndClearCookies(): void
    {
        $refreshToken = $this->createRefreshToken();

        $this->setCookie('access_token', 'stale-access-token');
        $this->setCookieWithPath('refresh_token', $refreshToken, '/api/v1/auth');
        $response = $this->post($this->route('api_auth_logout'));

        self::assertResponseStatusCodeSame(204);

        $clearedCookieNames = array_map(
            static fn (Cookie $c) => $c->getName(),
            $response->headers->getCookies(),
        );
        self::assertContains('access_token', $clearedCookieNames);
        self::assertContains('refresh_token', $clearedCookieNames);

        $repo = static::getContainer()->get(RefreshTokenRepository::class);
        self::assertNull($repo->findValidByToken(RefreshTokenHash::fromPlain($refreshToken)));
    }

    #[Test]
    public function logoutShouldBlockPresentedAccessToken(): void
    {
        $user = UserFactory::createOne();
        $accessToken = static::getContainer()->get(JWTTokenManagerInterface::class)->create($user);

        $this->setCookie('access_token', $accessToken);
        $this->post($this->route('api_auth_logout'));

        self::assertResponseStatusCodeSame(204);

        $this->setCookie('access_token', $accessToken);
        $this->get($this->route('api_profile_me'));

        self::assertResponseStatusCodeSame(401);
    }

    private function failLogin(string $email, int $times): void
    {
        for ($i = 0; $i < $times; ++$i) {
            $this->post($this->route('api_auth_login'), [
                'email' => $email,
                'password' => 'wrong_password',
            ]);
            self::assertResponseStatusCodeSame(401);
        }
    }

    /**
     * Only the hash is persisted, so the plain value is generated here to be sent as the cookie.
     *
     * @param array<string, mixed> $attributes
     */
    private function createRefreshToken(array $attributes = []): string
    {
        $plainToken = (new RandomRefreshTokenGenerator())->generate();
        RefreshTokenFactory::createOne(['token' => RefreshTokenHash::fromPlain($plainToken), ...$attributes]);

        return $plainToken;
    }
}
