<?php

declare(strict_types=1);

namespace App\Tests\Application\Logging;

use App\Fixtures\Auth\UserFactory;
use App\Tests\ApiTestCase;
use Monolog\Handler\TestHandler;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\Test;

final class UserIdLoggingTest extends ApiTestCase
{
    #[Test]
    public function logRecordsOfAnAuthenticatedRequestShouldCarryTheUserId(): void
    {
        $user = UserFactory::createOne();
        $this->actingAs($user);

        $this->get('/api/v1/tasks/0195f2f7-1f0a-7db2-b6f6-5d1d48d6752b');

        self::assertResponseStatusCodeSame(404);
        $withUser = array_filter($this->records(), static fn (LogRecord $record): bool => isset($record->extra['user_id']));
        self::assertNotEmpty($withUser, 'Records written after authentication must carry the user id.');

        foreach ($withUser as $record) {
            self::assertSame($user->getId(), $record->extra['user_id']);
        }
    }

    #[Test]
    public function logRecordsShouldNotCarryTheEmail(): void
    {
        $this->actingAs(UserFactory::createOne(['email' => 'private@example.com']));

        $this->get('/api/v1/tasks/0195f2f7-1f0a-7db2-b6f6-5d1d48d6752b');

        foreach ($this->records() as $record) {
            self::assertStringNotContainsString('private@example.com', json_encode($record->extra, \JSON_THROW_ON_ERROR));
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
