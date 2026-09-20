<?php

declare(strict_types=1);

namespace App\Tests\Application\Error;

use App\Fixtures\Auth\UserFactory;
use App\Tests\ApiTestCase;
use App\Tests\Support\AuditLogFailureToggle;
use Monolog\Handler\TestHandler;
use PHPUnit\Framework\Attributes\Test;

final class ServerErrorLoggingTest extends ApiTestCase
{
    #[Test]
    public function serverErrorShouldBeLoggedWithItsException(): void
    {
        $this->actingAs(UserFactory::createOne());
        static::getContainer()->get(AuditLogFailureToggle::class)->enable();

        $response = $this->post($this->route('api_task_create'), ['title' => 'Buy milk']);

        self::assertResponseStatusCodeSame(500);
        self::assertSame('500', $this->json($response)['errors'][0]['status']);

        /** @var TestHandler $logs */
        $logs = static::getContainer()->get('monolog.handler.test_records');
        self::assertTrue(
            $logs->hasCriticalThatContains('Simulated audit log event failure.'),
            'The exception behind a 500 response must reach the logs.',
        );
    }
}
