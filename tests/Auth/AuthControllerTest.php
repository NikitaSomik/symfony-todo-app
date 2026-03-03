<?php

declare(strict_types=1);

namespace App\Tests\Auth;

use App\Auth\Factory\UserFactory;
use App\Tests\ApiTestCase;
use PHPUnit\Framework\Attributes\Test;

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
        UserFactory::createOne(['email' => 'user@example.com', 'password' => 'secret123']);

        $this->post($this->route('api_auth_login'), [
            'email' => 'user@example.com',
            'password' => 'secret123',
        ]);

        self::assertResponseIsSuccessful();
        self::assertBrowserHasCookie('jwt_token');
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
            'password' => 'secret123',
        ]);

        self::assertResponseStatusCodeSame(401);
    }
}
