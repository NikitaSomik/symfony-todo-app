<?php

declare(strict_types=1);

namespace App\Tests\Application\Error;

use App\Fixtures\Auth\UserFactory;
use App\Tests\ApiTestCase;
use PHPUnit\Framework\Attributes\Test;

final class ErrorResponseFormatTest extends ApiTestCase
{
    #[Test]
    public function loginWhenCredentialsAreInvalidShouldReturnApplicationErrorShape(): void
    {
        UserFactory::createOne(['email' => 'user@example.com']);

        $response = $this->post($this->route('api_auth_login'), [
            'email' => 'user@example.com',
            'password' => 'wrong_password',
        ]);
        $json = $this->json($response);

        self::assertResponseStatusCodeSame(401);
        self::assertSame(['errors'], array_keys($json));
        self::assertSame('401', $json['errors'][0]['status']);
        self::assertSame('Unauthorized.', $json['errors'][0]['detail']);
    }

    #[Test]
    public function protectedRouteWhenUnauthenticatedShouldReturnApplicationErrorShape(): void
    {
        $response = $this->get($this->route('api_profile_me'));
        $json = $this->json($response);

        self::assertResponseStatusCodeSame(401);
        self::assertSame(['errors'], array_keys($json));
        self::assertSame('401', $json['errors'][0]['status']);
        self::assertSame('Unauthorized.', $json['errors'][0]['detail']);
    }

    #[Test]
    public function refreshWhenTokenIsInvalidShouldReturnApplicationErrorShape(): void
    {
        $this->setCookie('refresh_token', 'invalid-token-value');

        $response = $this->post($this->route('api_auth_refresh'));
        $json = $this->json($response);

        self::assertResponseStatusCodeSame(401);
        self::assertSame(['errors'], array_keys($json));
        self::assertSame('401', $json['errors'][0]['status']);
        self::assertSame('Unauthorized.', $json['errors'][0]['detail']);
    }

    #[Test]
    public function validationFailureShouldReturnApplicationErrorShape(): void
    {
        $response = $this->post($this->route('api_auth_register'), [
            'email' => 'not-an-email',
            'password' => '123',
        ]);
        $json = $this->json($response);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['errors'], array_keys($json));
        self::assertCount(2, $json['errors']);
        self::assertSame('422', $json['errors'][0]['status']);
        self::assertSame('/email', $json['errors'][0]['source']['pointer']);
        self::assertSame('This value is not a valid email address.', $json['errors'][0]['detail']);
        self::assertSame('422', $json['errors'][1]['status']);
        self::assertSame('/password', $json['errors'][1]['source']['pointer']);
    }
}
