<?php

declare(strict_types=1);

namespace App\Tests\Application\Auth;

use App\Fixtures\Auth\UserFactory;
use App\Tests\ApiTestCase;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use PHPUnit\Framework\Attributes\Test;

final class JwtFailureTest extends ApiTestCase
{
    private function assertUnauthorizedInJsonApi(): void
    {
        self::assertResponseStatusCodeSame(401);
        self::assertSame(
            ['jsonapi' => ['version' => '1.1'], 'errors' => [['status' => '401', 'detail' => 'Unauthorized.']]],
            json_decode((string) static::getClient()->getResponse()->getContent(), true),
        );
    }

    #[Test]
    public function requestWithoutTokenShouldBeAnsweredInJsonApi(): void
    {
        $this->get($this->route('api_profile_me'));

        $this->assertUnauthorizedInJsonApi();
    }

    #[Test]
    public function requestWithAnInvalidTokenShouldBeAnsweredInJsonApi(): void
    {
        $this->setCookie('access_token', 'not-a-jwt');

        $this->get($this->route('api_profile_me'));

        $this->assertUnauthorizedInJsonApi();
    }

    #[Test]
    public function requestWithAnExpiredTokenShouldBeAnsweredInJsonApi(): void
    {
        $user = UserFactory::createOne();
        $expired = static::getContainer()->get(JWTEncoderInterface::class)->encode([
            'sub' => $user->getUserIdentifier(),
            'iat' => time() - 120,
            'exp' => time() - 60,
        ]);
        $this->setCookie('access_token', $expired);

        $this->get($this->route('api_profile_me'));

        $this->assertUnauthorizedInJsonApi();
    }
}
