<?php

declare(strict_types=1);

namespace App\Tests\Application\Logging;

use App\Fixtures\Auth\UserFactory;
use App\Fixtures\Task\TaskFactory;
use App\Tests\ApiTestCase;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * A client error is the client's to fix, not an incident: it is logged at info, which the
 * prod handler keeps in its buffer instead of writing it out as an error.
 */
final class ClientErrorLoggingTest extends ApiTestCase
{
    /**
     * @return iterable<string, array{int, callable(self): void}>
     */
    public static function clientErrors(): iterable
    {
        yield 'invalid body (422)' => [422, static fn (self $test) => $test->post($test->route('api_auth_register'), ['email' => 'not-an-email', 'password' => 'secret123'])];
        yield 'malformed json (400)' => [400, static fn (self $test) => $test->sendRaw('POST', $test->route('api_auth_register'), 'application/json', '{"email":')];
        yield 'no content type (415)' => [415, static fn (self $test) => $test->sendRaw('POST', $test->route('api_auth_register'), null, 'email=a')];
        yield 'unknown route (404)' => [404, static fn (self $test) => $test->get('/api/v1/does-not-exist')];
        yield 'wrong method (405)' => [405, static fn (self $test) => $test->get($test->route('api_auth_register'))];
    }

    /**
     * @param callable(self): void $request
     */
    #[Test]
    #[DataProvider('clientErrors')]
    public function clientErrorShouldBeLoggedAtInfo(int $status, callable $request): void
    {
        $request($this);

        self::assertResponseStatusCodeSame($status);
        $this->assertLoggedAtInfo();
    }

    #[Test]
    public function tooManyRequestsShouldBeLoggedAtInfo(): void
    {
        for ($i = 1; $i <= 10; ++$i) {
            $this->post($this->route('api_auth_register'), ['email' => sprintf('user%d@example.com', $i), 'password' => 'secret123']);
        }

        $this->post($this->route('api_auth_register'), ['email' => 'one-too-many@example.com', 'password' => 'secret123']);

        self::assertResponseStatusCodeSame(429);
        $this->assertLoggedAtInfo();
    }

    #[Test]
    public function someoneElsesTaskShouldBeLoggedAtInfo(): void
    {
        $task = TaskFactory::createOne(['user' => UserFactory::createOne()]);
        $this->actingAs(UserFactory::createOne());

        $this->get($this->route('api_task_get', ['id' => $task->getId()->toRfc4122()]));

        self::assertResponseStatusCodeSame(404);
        $this->assertLoggedAtInfo();
    }

    private function assertLoggedAtInfo(): void
    {
        /** @var TestHandler $logs */
        $logs = static::getContainer()->get('monolog.handler.test_records');
        $exceptions = array_values(array_filter(
            $logs->getRecords(),
            static fn (LogRecord $record): bool => 'request' === $record->channel && isset($record->context['exception']),
        ));

        self::assertCount(1, $exceptions);
        self::assertSame(Level::Info, $exceptions[0]->level);
    }
}
