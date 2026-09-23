<?php

declare(strict_types=1);

namespace App\Tests\Application\Logging;

use App\Tests\ApiTestCase;
use Monolog\Handler\TestHandler;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Uid\Uuid;

final class RequestIdLoggingTest extends ApiTestCase
{
    #[Test]
    public function everyResponseShouldCarryARequestId(): void
    {
        $response = $this->get($this->route('api_profile_me'));

        self::assertResponseStatusCodeSame(401);
        self::assertTrue(Uuid::isValid((string) $response->headers->get('X-Request-Id')));
    }

    #[Test]
    public function eachRequestShouldGetItsOwnId(): void
    {
        $first = $this->get($this->route('api_profile_me'))->headers->get('X-Request-Id');
        $second = $this->get($this->route('api_profile_me'))->headers->get('X-Request-Id');

        self::assertNotSame($first, $second);
    }

    #[Test]
    public function idSentByAClientShouldNotBeTrusted(): void
    {
        $response = $this->withHeader('X-Request-Id', 'chosen-by-client')->get($this->route('api_profile_me'));

        self::assertNotSame('chosen-by-client', $response->headers->get('X-Request-Id'));
    }

    #[Test]
    public function logRecordsShouldCarryTheIdReturnedToTheClient(): void
    {
        $response = $this->get('/api/v1/does-not-exist');

        $records = $this->records();
        self::assertNotEmpty($records);

        foreach ($records as $record) {
            self::assertSame($response->headers->get('X-Request-Id'), $record->extra['request_id'] ?? null, $record->message);
        }
    }

    /**
     * @return list<LogRecord>
     */
    private function records(): array
    {
        /** @var TestHandler $logs */
        $logs = static::getContainer()->get('monolog.handler.test_records');

        return $logs->getRecords();
    }
}
