<?php

declare(strict_types=1);

namespace App\Tests\Application\Api;

use App\Fixtures\Auth\UserFactory;
use App\Tests\ApiTestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;

/**
 * How a request document is checked before its attributes are read, shown on task creation.
 */
final class RequestDocumentTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(UserFactory::createOne());
    }

    #[Test]
    #[TestWith(['application/json'])]
    #[TestWith(['application/vnd.api+json; charset=utf-8'])]
    #[TestWith(['application/vnd.api+json; ext="https://example.com/ext"'])]
    #[TestWith(['application/xml'])]
    public function bodyWithoutTheJsonApiMediaTypeShouldBeRejectedWith415(string $contentType): void
    {
        $response = $this->sendRaw('POST', $this->route('api_task_create'), $contentType, '{"data":{"type":"tasks","attributes":{"title":"Buy milk"}}}');

        self::assertResponseStatusCodeSame(415);
        self::assertSame(['header' => 'Content-Type'], $this->json($response)['errors'][0]['source']);
    }

    #[Test]
    public function bodyThatIsNotJsonShouldBeRejectedWith400(): void
    {
        $response = $this->sendRaw('POST', $this->route('api_task_create'), 'application/vnd.api+json', '{"data": ');

        self::assertResponseStatusCodeSame(400);
        self::assertSame('The request body is not valid JSON.', $this->json($response)['errors'][0]['detail']);
    }

    #[Test]
    #[TestWith([['title' => 'Buy milk'], '/data'])]
    #[TestWith([['data' => [['type' => 'tasks']]], '/data'])]
    #[TestWith([['data' => ['attributes' => ['title' => 'Buy milk']]], '/data/type'])]
    #[TestWith([['data' => ['type' => 'tasks', 'attributes' => 'Buy milk']], '/data/attributes'])]
    public function malformedDocumentShouldBeRejectedWith400(array $document, string $pointer): void
    {
        $response = $this->sendDocument('POST', $this->route('api_task_create'), $document);

        self::assertResponseStatusCodeSame(400);
        self::assertSame(['pointer' => $pointer], $this->json($response)['errors'][0]['source']);
    }

    #[Test]
    public function resourceOfAnotherTypeShouldBeRejectedWith409(): void
    {
        $response = $this->postResource($this->route('api_task_create'), 'users', ['title' => 'Buy milk']);

        self::assertResponseStatusCodeSame(409);
        self::assertSame(['pointer' => '/data/type'], $this->json($response)['errors'][0]['source']);
    }

    #[Test]
    public function clientGeneratedIdShouldBeRejectedWith403(): void
    {
        $response = $this->sendDocument('POST', $this->route('api_task_create'), [
            'data' => ['type' => 'tasks', 'id' => '0195f2f7-1f0a-7db2-b6f6-5d1d48d6752b', 'attributes' => ['title' => 'Buy milk']],
        ]);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(['pointer' => '/data/id'], $this->json($response)['errors'][0]['source']);
    }

    #[Test]
    public function readOnlyAndUnknownAttributesShouldBeIgnored(): void
    {
        $response = $this->postResource($this->route('api_task_create'), 'tasks', [
            'title' => 'Buy milk',
            'created_at' => '2000-01-01T00:00:00+00:00',
            'colour' => 'red',
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertNotSame('2000-01-01T00:00:00+00:00', $this->jsonAttributes($response)['created_at']);
        self::assertArrayNotHasKey('colour', $this->jsonAttributes($response));
    }
}
