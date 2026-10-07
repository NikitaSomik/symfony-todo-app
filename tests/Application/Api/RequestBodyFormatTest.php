<?php

declare(strict_types=1);

namespace App\Tests\Application\Api;

use App\Fixtures\Auth\UserFactory;
use App\Tests\ApiTestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;

final class RequestBodyFormatTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(UserFactory::createOne());
    }

    /**
     * Without acceptFormat the payload mapping tried to read XML or form data, or found no format at all,
     * and answered a misleading 400.
     */
    #[Test]
    #[TestWith([null])]
    #[TestWith(['application/xml'])]
    #[TestWith(['text/plain'])]
    #[TestWith(['application/x-www-form-urlencoded'])]
    public function bodyThatIsNotJsonShouldBeRejectedWith415(?string $contentType): void
    {
        $response = $this->sendRaw('POST', $this->route('api_workspace_create'), $contentType, '{"name":"Mobile team"}');

        self::assertResponseStatusCodeSame(415);
        self::assertSame(
            [[
                'status' => '415',
                'detail' => 'The request body must be sent as application/json.',
                'source' => ['header' => 'Content-Type'],
            ]],
            $this->json($response)['errors'],
        );
    }

    #[Test]
    public function jsonBodyShouldBeAccepted(): void
    {
        $this->sendRaw('POST', $this->route('api_workspace_create'), 'application/json', '{"name":"Mobile team"}');

        self::assertResponseStatusCodeSame(201);
    }
}
