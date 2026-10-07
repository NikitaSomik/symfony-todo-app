<?php

declare(strict_types=1);

namespace App\Task\Security;

use App\Auth\Contract\AuthenticatedUser;
use App\Task\Entity\Task;
use App\Task\Repository\TaskRepository;
use App\Workspace\Contract\WorkspaceAccess;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsTargetedValueResolver;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Uid\Uuid;

/**
 * Loads a task only for a member of its workspace, so that a task of strangers answers
 * exactly like a missing one: a 404 that does not reveal whether the id exists.
 *
 * It runs while the arguments are resolved, before the request body is validated, so an
 * invalid body sent to such a task gets the same 404 and not a 422. What a member may do
 * with the task is the voter's question.
 */
#[AsTargetedValueResolver]
final readonly class MemberTaskValueResolver implements ValueResolverInterface
{
    public function __construct(
        private TaskRepository $taskRepository,
        private WorkspaceAccess $workspaces,
        private Security $security,
    ) {
    }

    /**
     * @return iterable<Task>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $user = $this->security->getUser();

        // Without a user there is no member to look for: authentication is required, and the
        // firewall answers it with a 401.
        if (!$user instanceof AuthenticatedUser) {
            throw new AccessDeniedException();
        }

        $id = (string) $request->attributes->get('id');
        $task = Uuid::isValid($id) ? $this->taskRepository->find(Uuid::fromString($id)) : null;

        if (null === $task || null === $this->workspaces->roleOf($task->getWorkspaceId(), $user->id())) {
            throw new NotFoundHttpException(sprintf('Task "%s" not found.', $id));
        }

        return [$task];
    }
}
