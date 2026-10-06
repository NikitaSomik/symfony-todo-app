<?php

declare(strict_types=1);

namespace App\Tests\Unit\Task\Security;

use App\Auth\Entity\User;
use App\Task\Entity\Task;
use App\Task\Repository\TaskRepository;
use App\Task\Security\MemberTaskValueResolver;
use App\Workspace\Contract\WorkspaceAccess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Uid\Uuid;

/**
 * Found tasks and tasks of strangers are covered end to end in TaskControllerTest; these are the
 * branches a request cannot reach there, because the firewall and the route requirement stop
 * it first.
 */
final class MemberTaskValueResolverTest extends TestCase
{
    #[Test]
    public function withoutAUserShouldRequireAuthentication(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->resolve(null, Uuid::v7()->toRfc4122());
    }

    #[Test]
    public function idThatIsNotAUuidShouldNotBeFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->resolve(new User(), 'not-a-uuid');
    }

    private function resolve(?User $user, string $id): void
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        $resolver = new MemberTaskValueResolver($this->createStub(TaskRepository::class), $this->createStub(WorkspaceAccess::class), $security);
        $request = new Request(attributes: ['id' => $id]);

        [...$resolver->resolve($request, new ArgumentMetadata('task', Task::class, false, false, null))];
    }
}
