<?php

declare(strict_types=1);

namespace App\Tests\Application\Logging;

use App\Fixtures\Auth\UserFactory;
use App\Tests\ApiTestCase;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\TestHandler;
use PHPUnit\Framework\Attributes\Test;

/**
 * A plain password must never reach a log record, not even inside an exception or its context.
 * Records are rendered with the JSON formatter production uses, so nested objects are searched too.
 *
 * One request per test: the test client reboots the kernel before each request, and with it
 * the in-memory log handler, so only the last request's records can be checked.
 */
final class PasswordLoggingTest extends ApiTestCase
{
    private const string PASSWORD = 'Pl4in-Secret-Pa55word';

    #[Test]
    public function successfulRegistrationShouldNotLogThePassword(): void
    {
        $this->post($this->route('api_auth_register'), ['email' => 'new@example.com', 'password' => self::PASSWORD]);

        self::assertResponseStatusCodeSame(201);
        $this->assertPasswordNotLogged();
    }

    #[Test]
    public function successfulLoginShouldNotLogThePassword(): void
    {
        UserFactory::createOne(['email' => 'user@example.com', 'password' => password_hash(self::PASSWORD, \PASSWORD_BCRYPT, ['cost' => 4])]);

        $this->post($this->route('api_auth_login'), ['email' => 'user@example.com', 'password' => self::PASSWORD]);

        self::assertResponseStatusCodeSame(204);
        $this->assertPasswordNotLogged();
    }

    #[Test]
    public function failedLoginShouldNotLogThePassword(): void
    {
        UserFactory::createOne(['email' => 'user@example.com']);

        $this->post($this->route('api_auth_login'), ['email' => 'user@example.com', 'password' => self::PASSWORD]);

        self::assertResponseStatusCodeSame(401);
        $this->assertPasswordNotLogged();
    }

    #[Test]
    public function rejectedRegistrationShouldNotLogThePassword(): void
    {
        $this->post($this->route('api_auth_register'), ['email' => 'not-an-email', 'password' => self::PASSWORD]);

        self::assertResponseStatusCodeSame(422);
        $this->assertPasswordNotLogged();
    }

    #[Test]
    public function registrationWithATakenEmailShouldNotLogThePassword(): void
    {
        UserFactory::createOne(['email' => 'taken@example.com']);

        $this->post($this->route('api_auth_register'), ['email' => 'taken@example.com', 'password' => self::PASSWORD]);

        self::assertResponseStatusCodeSame(409);
        $this->assertPasswordNotLogged();
    }

    private function assertPasswordNotLogged(): void
    {
        /** @var TestHandler $logs */
        $logs = static::getContainer()->get('monolog.handler.test_records');
        $formatter = new JsonFormatter(includeStacktraces: true);

        self::assertNotEmpty($logs->getRecords(), 'The scenario is expected to produce log records.');

        foreach ($logs->getRecords() as $record) {
            self::assertStringNotContainsString(self::PASSWORD, $formatter->format($record), sprintf('Log record "%s" contains the password.', $record->message));
        }
    }
}
