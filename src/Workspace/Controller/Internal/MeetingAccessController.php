<?php

declare(strict_types=1);

namespace App\Workspace\Controller\Internal;

use App\Workspace\Contract\WorkspacePermission;
use App\Workspace\Service\GetMeetingAccess;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

final readonly class MeetingAccessController
{
    public function __construct(
        private GetMeetingAccess $getMeetingAccess,
    ) {
    }

    #[Route(
        '/internal/v1/workspaces/{id}/members/{userId}/meeting-access',
        name: 'internal_workspace_meeting_access',
        requirements: ['id' => Requirement::UUID_V7, 'userId' => Requirement::DIGITS],
        methods: ['GET'],
        format: 'json',
    )]
    public function get(Uuid $id, int $userId): JsonResponse
    {
        $access = $this->getMeetingAccess->handle($id, $userId);

        $limits = null;
        if (null !== $access->limits) {
            $limits = ['participants' => $access->limits->participants, 'minutes' => $access->limits->minutes];
        }

        return new JsonResponse([
            'permissions' => array_map(static fn (WorkspacePermission $permission): string => $permission->value, $access->permissions),
            'limits' => $limits,
        ]);
    }
}
