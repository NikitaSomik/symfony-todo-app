<?php

declare(strict_types=1);

namespace App\Task\Security;

use App\Auth\Entity\User;
use App\Task\Entity\Task;
use App\Task\Repository\TaskRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsTargetedValueResolver;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Uid\Uuid;

/**
 * Loads a task only among the current user's own, so that someone else's task answers
 * exactly like a missing one: a 404 that does not reveal whether the id exists.
 *
 * It runs while the arguments are resolved, before the request body is validated, so an
 * invalid body sent to someone else's task gets the same 404 and not a 422.
 */
#[AsTargetedValueResolver]
final readonly class OwnedTaskValueResolver implements ValueResolverInterface
{
    public function __construct(
        private TaskRepository $taskRepository,
        private Security $security,
    ) {
    }

    /**
     * @return iterable<Task>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $user = $this->security->getUser();

        // Without a user there is no owner to look for: authentication is required, and the
        // firewall answers it with a 401.
        if (!$user instanceof User) {
            throw new AccessDeniedException();
        }

        $id = (string) $request->attributes->get('id');

        if (!Uuid::isValid($id)) {
            throw new NotFoundHttpException(sprintf('Task "%s" not found.', $id));
        }

        $task = $this->taskRepository->findOneBy(['id' => Uuid::fromString($id), 'user' => $user]);

        if (null === $task) {
            throw new NotFoundHttpException(sprintf('Task "%s" not found.', $id));
        }

        return [$task];
    }
}
