<?php

declare(strict_types=1);

namespace App\Workspace\Security;

use App\Auth\Contract\AuthenticatedUser;
use App\Workspace\Entity\Workspace;
use App\Workspace\Repository\WorkspaceRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsTargetedValueResolver;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Uid\Uuid;

/**
 * Loads a workspace only among those the current user is a member of, so a workspace of
 * strangers answers like a missing one. What a member may do in it is the voter's question.
 */
#[AsTargetedValueResolver]
final readonly class MemberWorkspaceValueResolver implements ValueResolverInterface
{
    public function __construct(
        private WorkspaceRepository $workspaces,
        private Security $security,
    ) {
    }

    /**
     * @return iterable<Workspace>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $user = $this->security->getUser();

        if (!$user instanceof AuthenticatedUser) {
            throw new AccessDeniedException();
        }

        $id = (string) $request->attributes->get('id');
        $workspace = Uuid::isValid($id) ? $this->workspaces->findOneForMember(Uuid::fromString($id), $user->id()) : null;

        return [$workspace ?? throw new NotFoundHttpException(sprintf('Workspace "%s" not found.', $id))];
    }
}
