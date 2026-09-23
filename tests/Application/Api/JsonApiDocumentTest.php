<?php

declare(strict_types=1);

namespace App\Tests\Application\Api;

use App\Fixtures\Auth\UserFactory;
use App\Tests\ApiTestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;

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

    #[Test]
    #[TestWith(['application/vnd.api+json'])]
    #[TestWith(['application/vnd.api+json; profile="https://example.com/profile"'])]
    #[TestWith(['application/vnd.api+json; charset=utf-8, application/vnd.api+json'])]
    #[TestWith(['application/json'])]
    #[TestWith(['*/*'])]
    public function acceptableAcceptHeaderShouldBeServed(string $accept): void
    {
        $this->actingAs(UserFactory::createOne());

        $this->accepting($accept)->get($this->route('api_profile_me'));

        self::assertResponseIsSuccessful();
    }

    #[Test]
    #[TestWith(['application/vnd.api+json; charset=utf-8'])]
    #[TestWith(['application/vnd.api+json; ext="https://example.com/ext"'])]
    #[TestWith(['application/vnd.api+json; charset=utf-8, application/vnd.api+json; ext="https://example.com/ext"'])]
    public function jsonApiMediaTypeOnlyWithUnsupportedParametersShouldBeRejectedWith406(string $accept): void
    {
        $this->actingAs(UserFactory::createOne());

        $response = $this->accepting($accept)->get($this->route('api_profile_me'));

        self::assertResponseStatusCodeSame(406);
        self::assertSame(['header' => 'Accept'], $this->json($response)['errors'][0]['source']);
    }
}
