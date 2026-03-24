<?php

declare(strict_types=1);

namespace App\Tests\Application\Auth;

use App\Auth\DataFixtures\UserFactory;
use App\Tests\ApiTestCase;
use PHPUnit\Framework\Attributes\Test;

final class ProfileControllerTest extends ApiTestCase
{
    #[Test]
    public function meWhenUnauthenticatedShouldReturn401(): void
    {
        $this->get($this->route('api_profile_me'));

        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function meWhenAuthenticatedShouldReturnCurrentUser(): void
    {
        $user = UserFactory::createOne();
        $this->actingAs($user);

        $response = $this->get($this->route('api_profile_me'));

        self::assertResponseIsSuccessful();
        $this->assertJsonContains([
            'email' => $user->getEmail(),
        ], $response);

        $data = $this->json($response);
        self::assertArrayHasKey('id', $data);
        self::assertArrayHasKey('created_at', $data);
    }
}
