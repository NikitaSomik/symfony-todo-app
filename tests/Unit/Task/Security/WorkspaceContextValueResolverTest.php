<?php

declare(strict_types=1);

namespace App\Tests\Unit\Task\Security;

use App\Auth\Entity\User;
use App\Task\Security\WorkspaceContext;
use App\Task\Security\WorkspaceContextValueResolver;
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
 * A workspace of strangers and a workspace of the user are covered end to end in
 * TaskWorkspaceAccessTest; these are the branches a request cannot reach there, because the
 * firewall and the route requirement stop it first.
 */
final class WorkspaceContextValueResolverTest extends TestCase
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

        $resolver = new WorkspaceContextValueResolver($this->createStub(WorkspaceAccess::class), $security);
        $request = new Request(attributes: ['id' => $id]);

        [...$resolver->resolve($request, new ArgumentMetadata('context', WorkspaceContext::class, false, false, null))];
    }
}
