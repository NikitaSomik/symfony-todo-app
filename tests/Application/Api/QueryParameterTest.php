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

    #[Test]
    #[TestWith(['foo=1', 'foo'])]
    #[TestWith(['include=user', 'include'])]
    #[TestWith(['fields[tasks]=title', 'fields'])]
    #[TestWith(['filter[foo]=1', 'filter[foo]'])]
    #[TestWith(['page[offset]=10', 'page[offset]'])]
    #[TestWith(['filter=abc', 'filter'])]
    #[TestWith(['page=5', 'page'])]
    #[TestWith(['search=milk', 'search'])]
    #[TestWith(['direction=asc', 'direction'])]
    public function unknownParameterShouldBeRejectedWith400(string $query, string $parameter): void
    {
        $response = $this->get('/api/v1/tasks?'.$query);

        self::assertResponseStatusCodeSame(400);
        self::assertSame(
            [[
                'status' => '400',
                'detail' => sprintf('The "%s" query parameter is not supported.', $parameter),
                'source' => ['parameter' => $parameter],
            ]],
            $this->json($response)['errors'],
        );
    }

    #[Test]
    public function everyUnknownParameterShouldBeReported(): void
    {
        $response = $this->get('/api/v1/tasks?filter[bar]=2&filter[status]=todo&foo=1&page[baz]=3');

        self::assertResponseStatusCodeSame(400);
        self::assertSame(
            ['filter[bar]', 'foo', 'page[baz]'],
            array_map(static fn (array $error): string => $error['source']['parameter'], $this->json($response)['errors']),
        );
    }

    #[Test]
    public function knownParameterWithAnInvalidValueShouldStillBeA422(): void
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
