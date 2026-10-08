<?php

declare(strict_types=1);

namespace App\Task\Security;

use App\Auth\Contract\AuthenticatedUser;
use App\Workspace\Contract\WorkspaceAccess;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsTargetedValueResolver;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Uid\Uuid;

#[AsTargetedValueResolver]
final readonly class WorkspaceContextValueResolver implements ValueResolverInterface
{
    public function __construct(
        private WorkspaceAccess $workspaces,
        private Security $security,
    ) {
    }

    /**
     * @return iterable<WorkspaceContext>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $user = $this->security->getUser();

        if (!$user instanceof AuthenticatedUser) {
            throw new AccessDeniedException();
        }

        $id = (string) $request->attributes->get('id');
        $role = Uuid::isValid($id) ? $this->workspaces->roleOf(Uuid::fromString($id), $user->id()) : null;

        if (null === $role) {
            throw new NotFoundHttpException(sprintf('Workspace "%s" not found.', $id));
        }

        return [new WorkspaceContext(Uuid::fromString($id), $role)];
    }
}
