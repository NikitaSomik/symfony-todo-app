<?php

declare(strict_types=1);

namespace App\Tests\Application\Api;

use App\Fixtures\Auth\UserFactory;
use App\Tests\ApiTestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;

final class QueryParameterTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(UserFactory::createOne());
    }

    /**
     * Unknown parameters are left to the standard #[MapQueryString] mapping, which ignores them.
     * JSON:API asks for a 400 here; the API deliberately does not.
     */
    #[Test]
    #[TestWith(['foo=1'])]
    #[TestWith(['include=user'])]
    #[TestWith(['filter[foo]=1'])]
    public function unknownParameterShouldBeIgnored(string $query): void
    {
        $this->get('/api/v1/tasks?'.$query);

        self::assertResponseIsSuccessful();
    }

    #[Test]
    public function knownParameterWithAnInvalidValueShouldBeA422(): void
    {
        $response = $this->get('/api/v1/tasks?filter[status]=wrong');

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['parameter' => 'filter[status]'], $this->json($response)['errors'][0]['source']);
    }

    #[Test]
    #[TestWith(['10001'])]
    #[TestWith(['9223372036854775807'])]
    #[TestWith(['9223372036854775808'])]
    public function pageNumberBeyondTheLimitShouldBeA422(string $number): void
    {
        $response = $this->get('/api/v1/tasks?page[number]='.$number);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['parameter' => 'page[number]'], $this->json($response)['errors'][0]['source']);
    }
}
