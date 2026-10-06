<?php

declare(strict_types=1);

namespace App\Tests\Application\Error;

use App\Fixtures\Auth\UserFactory;
use App\Fixtures\Workspace\WorkspaceFactory;
use App\Tests\ApiTestCase;
use App\Tests\Support\AuditLogFailureToggle;
use Monolog\Handler\TestHandler;
use PHPUnit\Framework\Attributes\Test;

final class ServerErrorLoggingTest extends ApiTestCase
{
    #[Test]
    public function serverErrorShouldBeLoggedWithItsException(): void
    {
        $user = UserFactory::createOne();
        $this->actingAs($user);
        $workspace = WorkspaceFactory::createOne(['owner' => $user]);
        static::getContainer()->get(AuditLogFailureToggle::class)->enable();

        $response = $this->post($this->route('api_workspace_task_create', ['id' => $workspace->getId()->toRfc4122()]), ['title' => 'Buy milk']);

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
