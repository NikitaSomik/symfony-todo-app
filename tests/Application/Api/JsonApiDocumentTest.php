<?php

declare(strict_types=1);

namespace App\Tests\Application\Api;

use App\Fixtures\Auth\UserFactory;
use App\Tests\ApiTestCase;
use PHPUnit\Framework\Attributes\Test;

final class JsonApiDocumentTest extends ApiTestCase
{
    #[Test]
    public function successDocumentShouldUseTheJsonApiMediaType(): void
    {
        $this->actingAs(UserFactory::createOne());

        $response = $this->get($this->route('api_profile_me'));

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/vnd.api+json');
        self::assertSame(['version' => '1.1'], $this->json($response)['jsonapi']);
    }

    #[Test]
    public function errorDocumentShouldUseTheJsonApiMediaType(): void
    {
        $response = $this->get($this->route('api_profile_me'));

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('Content-Type', 'application/vnd.api+json');
        self::assertSame(['version' => '1.1'], $this->json($response)['jsonapi']);
    }
}
